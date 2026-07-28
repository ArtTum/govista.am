<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['title', 'content', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'content' => 'array',
            'seo_title' => 'array',
            'seo_description' => 'array',
            'active' => 'boolean',
        ];
    }
}
