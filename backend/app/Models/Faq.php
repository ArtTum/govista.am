<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected array $translatable = ['question', 'answer'];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'answer' => 'array',
            'active' => 'boolean',
        ];
    }
}
