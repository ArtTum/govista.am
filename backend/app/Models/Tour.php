<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Tour extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = [
        'title', 'subtitle', 'description', 'location', 'duration', 'highlights',
        'itinerary', 'included', 'excluded',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'subtitle' => 'array',
            'description' => 'array',
            'location' => 'array',
            'duration' => 'array',
            'gallery' => 'array',
            'highlights' => 'array',
            'itinerary' => 'array',
            'included' => 'array',
            'excluded' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
            'price' => 'decimal:2',
            'old_price' => 'decimal:2',
            'rating' => 'decimal:2',
        ];
    }
}
