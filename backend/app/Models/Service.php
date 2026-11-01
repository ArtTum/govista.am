<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasLocalizedContent {
        toLocalizedArray as private localizedContentArray;
    }

    protected $guarded = [];

    protected array $translatable = ['title', 'description', 'location', 'unit', 'features'];

    public function toLocalizedArray(string $locale = 'hy'): array
    {
        $data = $this->localizedContentArray($locale);
        unset($data['supplier']);
        if ($this->supplier) {
            $data['rental_terms'] = collect($this->supplier)->only([
                'tariffs', 'minimum_days', 'deposit', 'passengers', 'luggage', 'distance_km',
            ])->all();
        }

        return $data;
    }

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'location' => 'array',
            'gallery' => 'array',
            'unit' => 'array',
            'features' => 'array',
            'supplier' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
            'price_from' => 'decimal:2',
            'rating' => 'decimal:2',
            'review_count' => 'integer',
        ];
    }
}
