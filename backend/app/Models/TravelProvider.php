<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelProvider extends Model
{
    protected $guarded = [];

    protected $hidden = ['credentials', 'integration_profiles'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'integration_profiles' => 'encrypted:array', 'settings' => 'array', 'enabled' => 'boolean', 'last_checked_at' => 'datetime', 'last_success_at' => 'datetime'];
    }
}
