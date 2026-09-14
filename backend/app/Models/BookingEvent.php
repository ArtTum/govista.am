<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['internal' => 'boolean', 'snapshot' => 'array'];
    }
}
