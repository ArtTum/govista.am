<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SwiftCatalogImport
{
    public function run(array $manifest, bool $apply = false): array
    {
        Validator::make($manifest, [
            'supplier' => ['required', Rule::in(['swift'])],
            'captured_at' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1', 'max:1000'],
            'records.*' => ['array:slug,type,title,description,location,image,gallery,price_from,currency,unit,features,icon,rating,review_count,featured,active,sort_order,supplier'],
            'records.*.slug' => ['required', 'distinct', 'max:190', 'regex:/^swift-(car|transfer)-[a-z0-9-]+$/'],
            'records.*.type' => ['required', Rule::in(['transport', 'transfer'])],
            'records.*.price_from' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'records.*.currency' => ['required', Rule::in(['AMD'])],
            'records.*.title' => ['required', 'array:hy,ru,en'],
            'records.*.description' => ['required', 'array:hy,ru,en'],
            'records.*.location' => ['required', 'array:hy,ru,en'],
            'records.*.unit' => ['required', 'array:hy,ru,en'],
            'records.*.features' => ['required', 'array:hy,ru,en'],
            'records.*.gallery' => ['present', 'array', 'max:0'],
            'records.*.rating' => ['required', 'in:0'],
            'records.*.review_count' => ['required', 'in:0'],
            'records.*.active' => ['required', 'accepted'],
            'records.*.featured' => ['required', 'boolean'],
            'records.*.sort_order' => ['required', 'integer', 'min:0'],
            'records.*.supplier.code' => ['required', Rule::in(['swift'])],
            'records.*.supplier.reference' => ['required', 'distinct', 'regex:/^(car|transfer):[0-9]+$/'],
            'records.*.supplier.name' => ['required', 'in:Swift Rent'],
            'records.*.supplier.synced_at' => ['required', 'date'],
            'records.*.supplier.tariffs' => ['required', 'array', 'min:1'],
            'records.*.supplier.tariffs.*.price' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ])->validate();

        foreach ($manifest['records'] as $record) {
            foreach (['hy', 'ru', 'en'] as $locale) {
                foreach (['title', 'description', 'location', 'unit'] as $field) {
                    if (! is_string($record[$field][$locale] ?? null) || trim($record[$field][$locale]) === '' || ($field === 'title' && mb_strlen($record[$field][$locale]) > 255)) {
                        throw ValidationException::withMessages(['records' => ['Missing or invalid localized supplier content.']]);
                    }
                }
            }
            $isCar = $record['type'] === 'transport';
            if (! str_starts_with($record['supplier']['reference'], $isCar ? 'car:' : 'transfer:') || ! str_starts_with($record['slug'], $isCar ? 'swift-car-' : 'swift-transfer-')) {
                throw ValidationException::withMessages(['records' => ['Supplier reference/type mismatch.']]);
            }
            foreach ([$record['image'] ?? '', $record['supplier']['url'] ?? ''] as $url) {
                if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(parse_url($url, PHP_URL_HOST), ['swift.rent', 'transfer.swift.rent'], true) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)) {
                    throw ValidationException::withMessages(['records' => ['Invalid Swift public URL.']]);
                }
            }
            $tariffs = collect($record['supplier']['tariffs']);
            $expectedPrice = $isCar ? $tariffs->firstWhere('key', 'price7')['price'] ?? null : $tariffs->min('price');
            if ((float) $record['price_from'] !== (float) $expectedPrice || ($isCar && ($record['supplier']['minimum_days'] ?? null) !== 7)) {
                throw ValidationException::withMessages(['records' => ['Starting price does not match its published tariff basis.']]);
            }
        }

        $stats = ['cars' => collect($manifest['records'])->where('type', 'transport')->count(), 'transfers' => collect($manifest['records'])->where('type', 'transfer')->count(), 'applied' => $apply];
        if (! $apply) {
            return $stats;
        }

        DB::transaction(function () use ($manifest) {
            foreach ($manifest['records'] as $record) {
                $existing = Service::where('slug', $record['slug'])->first();
                if ($existing && (data_get($existing->supplier, 'code') !== 'swift' || data_get($existing->supplier, 'reference') !== $record['supplier']['reference'])) {
                    throw ValidationException::withMessages(['records' => ['A supplier slug conflicts with an existing GoVista service.']]);
                }
                Service::updateOrCreate(['slug' => $record['slug']], $record);
            }
            Service::where('supplier->code', 'swift')->whereNotIn('slug', array_column($manifest['records'], 'slug'))->update(['active' => false]);
        });

        return $stats;
    }
}
