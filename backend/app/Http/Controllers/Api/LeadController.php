<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function booking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in([
                'tour', 'group', 'private', 'package',
                'accommodation', 'transport', 'events', 'custom',
            ])],
            'item_id' => ['nullable', 'integer'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'participants' => ['nullable', 'integer', 'min:1', 'max:100'],
            'locale' => ['nullable', 'in:hy,ru,en'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['reference'] = 'GV-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        $booking = Booking::create($data);

        return response()->json([
            'message' => 'Շնորհակալություն։ Ձեր հայտը ստացվել է։',
            'reference' => $booking->reference,
        ], 201);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:3000'],
            'locale' => ['nullable', 'in:hy,ru,en'],
        ]);

        ContactMessage::create($data);

        return response()->json(['message' => 'Շնորհակալություն։ Մենք շուտով կկապվենք ձեզ հետ։'], 201);
    }
}
