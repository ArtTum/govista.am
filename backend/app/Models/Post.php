<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['title', 'excerpt', 'content'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'content' => 'array',
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
