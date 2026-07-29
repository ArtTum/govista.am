<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['title', 'description', 'location', 'unit', 'features'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'location' => 'array',
            'gallery' => 'array',
            'unit' => 'array',
            'features' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
            'price_from' => 'decimal:2',
            'rating' => 'decimal:2',
            'review_count' => 'integer',
        ];
    }
}
