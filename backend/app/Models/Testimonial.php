<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['country', 'message'];

    protected function casts(): array
    {
        return [
            'country' => 'array',
            'message' => 'array',
            'active' => 'boolean',
        ];
    }
}
