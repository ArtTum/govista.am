<?php

namespace App\Models;

use App\Services\Travel\PackageCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PackageOffer extends Model
{
    protected $guarded = [];

    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'children' => 'array', 'inclusions' => 'array', 'exclusions' => 'array', 'terms' => 'array', 'price' => 'decimal:2', 'cost' => 'decimal:2', 'departure_date' => 'date', 'valid_until' => 'datetime', 'price_checked_at' => 'datetime', 'version' => 'integer'];
    }

    public function scopePublished(Builder $query): void
    {
        $approved = TravelProvider::whereIn('code', PackageCatalog::PROVIDERS)->get()->filter(fn ($provider) => PackageCatalog::approved($provider))->pluck('code')->all();
        $query->where('status', 'published')->where('availability', 'on_request')
            ->whereDate('departure_date', '>=', today())->where('valid_until', '>', now())
            ->whereIn('provider_code', ['local', ...$approved])
            ->where(fn ($q) => $q->whereNull('price')->orWhere('price_checked_at', '>=', now()->subDays(30)));
    }
}
