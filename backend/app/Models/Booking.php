<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class Booking extends Model
{
    protected $guarded = [];

    protected $hidden = ['submission_key', 'submission_hash'];

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function customerData(): array
    {
        $data = $this->only(['id', 'reference', 'type', 'item_title', 'start_date', 'end_date', 'participants', 'message', 'status', 'total_price', 'currency', 'quote_terms', 'quote_expires_at', 'confirmation_reference', 'request_details', 'version', 'created_at']);
        $data['components'] = collect($this->components ?? [])->map(fn ($item) => Arr::only($item, ['type', 'title', 'sell_price', 'currency', 'status']))->all();
        $data['request_details'] = Arr::except($this->request_details ?? [], ['supplier_reference', 'search_token']);
        $data['events'] = $this->events()->where('internal', false)->oldest('id')->get(['id', 'status', 'message', 'snapshot', 'created_at']);

        return $data;
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_price' => 'decimal:2',
            'request_details' => 'array',
            'components' => 'array',
            'quote_expires_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
