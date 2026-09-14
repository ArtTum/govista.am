<?php

namespace App\Services\Travel;

use App\Models\PackageOffer;
use App\Models\TravelProvider;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageCatalog
{
    public const PROVIDERS = ['anriva', 'maratuk', 'world_voyage', 'travelone', 'tez_tour', 'tourvisor', 'sletat', 'tbo'];

    public const DESTINATIONS = [
        'sharm' => ['country' => 'EG', 'names' => ['hy' => 'Շարմ էլ Շեյխ', 'ru' => 'Шарм-эль-Шейх', 'en' => 'Sharm El Sheikh']],
        'hurghada' => ['country' => 'EG', 'names' => ['hy' => 'Հուրգադա', 'ru' => 'Хургада', 'en' => 'Hurghada']],
        'dubai' => ['country' => 'AE', 'names' => ['hy' => 'Դուբայ', 'ru' => 'Дубай', 'en' => 'Dubai']],
        'abu_dhabi' => ['country' => 'AE', 'names' => ['hy' => 'Աբու Դաբի', 'ru' => 'Абу-Даби', 'en' => 'Abu Dhabi']],
        'antalya' => ['country' => 'TR', 'names' => ['hy' => 'Անթալիա', 'ru' => 'Анталья', 'en' => 'Antalya']],
        'maldives' => ['country' => 'MV', 'names' => ['hy' => 'Մալդիվներ', 'ru' => 'Мальдивы', 'en' => 'Maldives']],
        'phuket' => ['country' => 'TH', 'names' => ['hy' => 'Փհուկետ', 'ru' => 'Пхукет', 'en' => 'Phuket']],
        'bali' => ['country' => 'ID', 'names' => ['hy' => 'Բալի', 'ru' => 'Бали', 'en' => 'Bali']],
        'salalah' => ['country' => 'OM', 'names' => ['hy' => 'Սալալա', 'ru' => 'Салала', 'en' => 'Salalah']],
        'paris' => ['country' => 'FR', 'names' => ['hy' => 'Փարիզ', 'ru' => 'Париж', 'en' => 'Paris']],
        'rome' => ['country' => 'IT', 'names' => ['hy' => 'Հռոմ', 'ru' => 'Рим', 'en' => 'Rome']],
        'tbilisi' => ['country' => 'GE', 'names' => ['hy' => 'Թբիլիսի', 'ru' => 'Тбилиси', 'en' => 'Tbilisi']],
    ];

    public const MEALS = ['RO', 'BB', 'HB', 'FB', 'AI', 'UAI'];

    public const INCLUSIONS = ['flight', 'baggage', 'hotel', 'meals', 'transfer', 'insurance', 'taxes'];

    public static function approved(?TravelProvider $provider): bool
    {
        return $provider && data_get($provider->settings, 'partnership_status') === 'approved'
            && filled(data_get($provider->settings, 'contract_reference'))
            && data_get($provider->settings, 'content_rights_confirmed') === true;
    }

    public static function rules(): array
    {
        $rules = [
            'provider_code' => ['required', Rule::in(['local', ...self::PROVIDERS])],
            'supplier_reference' => ['required', 'string', 'max:120'],
            'destination_code' => ['required', Rule::in(array_keys(self::DESTINATIONS))],
            'origin' => ['required', 'regex:/^[A-Z]{3}$/'],
            'hotel_name' => ['nullable', 'string', 'max:190'], 'stars' => ['nullable', 'integer', 'between:1,5'],
            'room_type' => ['nullable', 'string', 'max:190'], 'meal_plan' => ['required', Rule::in(self::MEALS)],
            'departure_date' => ['nullable', 'date_format:Y-m-d'], 'nights' => ['required', 'integer', 'between:1,28'],
            'adults' => ['required', 'integer', 'between:1,9'], 'children' => ['present', 'array', 'max:6'],
            'children.*' => ['integer', 'between:0,17'], 'inclusions' => ['present', 'array', 'max:7'],
            'inclusions.*' => ['distinct', Rule::in(self::INCLUSIONS)],
            'price' => ['nullable', 'numeric', 'between:0.01,999999999.99'], 'cost' => ['nullable', 'numeric', 'between:0,999999999.99'],
            'currency' => ['required', 'in:AMD,USD,EUR,RUB,GBP,AED'], 'price_basis' => ['required', 'in:party_total,per_person'],
            'image' => ['nullable', 'string', 'max:500', 'regex:~^/(?:images|storage)/[a-zA-Z0-9_./-]+$~', 'not_regex:/\.\./'],
            'status' => ['required', 'in:draft,published,archived'], 'availability' => ['required', 'in:on_request,sold_out'],
            'valid_until' => ['nullable', 'date'], 'price_checked_at' => ['nullable', 'date', 'before_or_equal:now'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
        ];
        foreach (['title' => 190, 'description' => 3000, 'exclusions' => 2000, 'terms' => 4000] as $key => $length) {
            $rules[$key] = [$key === 'title' ? 'required' : 'present', 'array:hy,ru,en'];
            foreach (['hy', 'ru', 'en'] as $locale) {
                $rules[$key.'.'.$locale] = [$key === 'title' && $locale === 'hy' ? 'required' : 'nullable', 'string', 'max:'.$length];
            }
        }

        return $rules;
    }

    public static function validatePublication(array $data): void
    {
        if ($data['status'] !== 'published') {
            return;
        }
        $errors = [];
        if ($data['provider_code'] !== 'local' && ! self::approved(TravelProvider::where('code', $data['provider_code'])->first())) {
            $errors['provider_code'] = ['Սկզբում հաստատեք մատակարարի համագործակցությունն ու բովանդակության հրապարակման իրավունքը։'];
        }
        if (empty($data['departure_date']) || $data['departure_date'] < today()->toDateString()) {
            $errors['departure_date'] = ['Նշեք առաջիկա մեկնման ամսաթիվը։'];
        }
        if (empty($data['valid_until']) || Carbon::parse($data['valid_until'])->isPast()) {
            $errors['valid_until'] = ['Նշեք առաջարկի գործողության ապագա ժամկետը։'];
        }
        if (empty($data['terms']['hy'])) {
            $errors['terms.hy'] = ['Նշեք հաստատման և չեղարկման պայմանները։'];
        }
        if (isset($data['price']) && (empty($data['price_checked_at']) || Carbon::parse($data['price_checked_at'])->lt(now()->subDays(30)))) {
            $errors['price_checked_at'] = ['Գինը պետք է ստուգված լինի վերջին 30 օրվա ընթացքում։'];
        }
        if (isset($data['price']) && $data['price_basis'] === 'per_person' && count($data['children'])) {
            $errors['price_basis'] = ['Երեխաներով կազմի համար նշեք ամբողջ խմբի ընդհանուր գինը։'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function publicOffer(PackageOffer $offer, string $locale): array
    {
        $text = fn ($value) => filled($value[$locale] ?? null) ? $value[$locale] : ($value['hy'] ?? $value['en'] ?? '');

        return [
            'id' => $offer->id, 'provider' => $offer->provider_code,
            'source' => $offer->provider_code === 'local' ? 'GoVista' : ProviderRegistry::definition($offer->provider_code)['name'],
            'title' => $text($offer->title), 'description' => $text($offer->description),
            'destination_code' => $offer->destination_code, 'destination' => self::DESTINATIONS[$offer->destination_code]['names'][$locale],
            'origin' => $offer->origin, 'hotel_name' => $offer->hotel_name, 'stars' => $offer->stars, 'room_type' => $offer->room_type,
            'meal_plan' => $offer->meal_plan, 'departure_date' => $offer->departure_date?->toDateString(),
            'nights' => $offer->nights, 'adults' => $offer->adults, 'children' => $offer->children ?? [],
            'inclusions' => $offer->inclusions ?? [], 'exclusions' => $text($offer->exclusions), 'terms' => $text($offer->terms),
            'price' => $offer->price, 'currency' => $offer->currency, 'price_basis' => $offer->price_basis,
            'image' => $offer->image, 'valid_until' => $offer->valid_until?->toIso8601String(),
            'price_checked_at' => $offer->price_checked_at?->toIso8601String(), 'availability' => $offer->availability,
        ];
    }
}
