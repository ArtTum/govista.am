<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['title', 'description', 'region'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'region' => 'array',
            'gallery' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
