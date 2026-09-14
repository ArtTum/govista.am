<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\PackageOffer;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TravelProvider;
use App\Services\Travel\PackageCatalog;
use App\Services\Travel\TourvisorSearch;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function booking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in([
                'tour', 'group', 'private', 'package',
                'accommodation', 'transport', 'events', 'custom', 'flight', 'transfer', 'activity', 'place',
            ])],
            'item_id' => ['nullable', 'integer'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'start_date' => ['nullable', 'required_with:end_date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', $request->input('type') === 'accommodation' ? 'after:start_date' : 'after_or_equal:start_date'],
            'participants' => ['nullable', 'integer', 'min:1', 'max:100'],
            'locale' => ['nullable', 'in:hy,ru,en'],
            'message' => ['nullable', 'string', 'max:3000'],
            'submission_key' => ['nullable', 'uuid'],
            'request_details' => ['nullable', 'array:origin,destination,adults,children,rooms,cabin,departure_time,provider,offer_id,service,destination_code,nights_min,nights_max,meal_plan,package_offer_id,search_token,date_to,stars,direct_only'],
            'request_details.origin' => ['nullable', 'string', 'max:190'],
            'request_details.destination' => ['nullable', 'string', 'max:190'],
            'request_details.adults' => ['nullable', 'integer', 'min:1', 'max:100'],
            'request_details.children' => ['nullable', 'array', 'max:10'],
            'request_details.children.*' => ['integer', 'min:0', 'max:17'],
            'request_details.rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'request_details.cabin' => ['nullable', 'in:economy,premium_economy,business,first'],
            'request_details.departure_time' => ['nullable', 'date_format:H:i'],
            'request_details.provider' => ['nullable', Rule::in(['local', 'viator', 'duffel', 'booking', 'hotelbeds', 'geoapify', ...PackageCatalog::PROVIDERS])],
            'request_details.offer_id' => ['nullable', 'string', 'max:255'],
            'request_details.service' => ['nullable', 'in:tours,hotels,flights,cars,transfers,activities,places,packages'],
            'request_details.destination_code' => ['nullable', Rule::in(array_keys(PackageCatalog::DESTINATIONS))],
            'request_details.nights_min' => ['nullable', 'integer', 'between:1,28'], 'request_details.nights_max' => ['nullable', 'integer', 'between:1,28', 'gte:request_details.nights_min'],
            'request_details.meal_plan' => ['nullable', Rule::in(PackageCatalog::MEALS)],
            'request_details.package_offer_id' => ['nullable', 'integer', 'min:1'],
            'request_details.search_token' => ['nullable', 'regex:/^[a-zA-Z0-9]{48}$/'],
            'request_details.date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'request_details.stars' => ['nullable', 'integer', 'between:1,5'], 'request_details.direct_only' => ['nullable', 'boolean'],
        ]);

        $packageId = data_get($data, 'request_details.package_offer_id');
        $searchToken = data_get($data, 'request_details.search_token');
        if ($packageId || $searchToken) {
            abort_unless(($data['type'] ?? '') === 'package' && empty($data['item_id']), 422, 'Ընտրեք փաթեթի հայտի տեսակը։');
            if ($packageId) {
                $offer = PackageOffer::published()->find($packageId);
                abort_unless($offer, 422, 'Առաջարկն այլևս հասանելի չէ։ Կրկին որոնեք կամ ուղարկեք անհատական հայտ։');
                $snapshot = PackageCatalog::publicOffer($offer, $data['locale'] ?? 'hy');
                $data['request_details']['supplier_reference'] = $offer->supplier_reference;
            } else {
                $batch = Cache::get('package-search:'.$searchToken);
                $provider = TravelProvider::where('code', 'tourvisor')->first();
                abort_unless($batch && $provider && $provider->enabled && $provider->environment === 'live' && PackageCatalog::approved($provider) && hash_equals($batch['fingerprint'], app(TourvisorSearch::class)->fingerprint($provider)), 422, 'Որոնումը ժամկետանց է։ Կրկին որոնեք։');
                $snapshot = collect($batch['result']['data'] ?? [])->firstWhere('id', data_get($data, 'request_details.offer_id'));
                abort_unless($snapshot, 422, 'Ընտրեք որոնման գործող առաջարկը։');
            }
            $data['item_title'] = $snapshot['title'];
            $data['start_date'] = $snapshot['departure_date'];
            // Hotel nights do not establish the return flight date for live packages.
            $data['end_date'] = ($snapshot['live'] ?? false) ? null : Carbon::parse($snapshot['departure_date'])->addDays($snapshot['nights'])->toDateString();
            $data['participants'] = $snapshot['adults'] + count($snapshot['children']);
            $data['request_details'] = [...$data['request_details'], 'provider' => $snapshot['provider'], 'offer_id' => (string) $snapshot['id'], 'origin' => $snapshot['origin'], 'destination' => $snapshot['destination'], 'destination_code' => $snapshot['destination_code'], 'adults' => $snapshot['adults'], 'children' => $snapshot['children'], 'nights_min' => $snapshot['nights'], 'nights_max' => $snapshot['nights'], 'package_snapshot' => $snapshot];
            unset($data['request_details']['search_token']);
            unset($data['request_details']['date_to'], $data['request_details']['meal_plan']);
            if (in_array($snapshot['meal_plan'], PackageCatalog::MEALS)) {
                $data['request_details']['meal_plan'] = $snapshot['meal_plan'];
            }
        }

        if (! empty($data['item_id'])) {
            $type = $data['type'] ?? 'custom';
            $isTour = in_array($type, ['tour', 'group', 'private', 'package'], true);
            $query = $isTour ? Tour::query() : Service::query();
            $item = $query->where('active', true)->find($data['item_id']);

            if (! $item || ($type !== 'tour' && $item->type !== $type)) {
                throw ValidationException::withMessages(['item_id' => ['This option is no longer available.']]);
            }

            $data['item_title'] = $item->toLocalizedArray($data['locale'] ?? 'hy')['title'];
        }

        $customer = $request->user('sanctum');
        abort_if($request->bearerToken() && ! $customer, 401);
        if (isset($data['request_details']['adults']) && (int) ($data['participants'] ?? 1) !== (int) $data['request_details']['adults'] + count($data['request_details']['children'] ?? [])) {
            throw ValidationException::withMessages(['participants' => ['Մասնակիցների թիվը չի համապատասխանում մեծահասակների և երեխաների քանակին։']]);
        }
        if ($customer?->role === 'customer') {
            $data['user_id'] = $customer->id;
            $data['email'] = $customer->email;
        }
        $hash = hash('sha256', json_encode($data));
        $create = function () use ($data, $hash) {
            if (! empty($data['submission_key'])) {
                $existing = Booking::where('submission_key', $data['submission_key'])->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->submission_hash ?? '', $hash), 409, 'Այս հայտի տվյալները փոփոխվել են։');

                    return $existing;
                }
            }
            $booking = Booking::create([...$data, 'submission_hash' => $hash, 'reference' => 'GV-'.now()->format('ymd').'-'.Str::upper(Str::random(8))]);
            $booking->events()->create(['actor_id' => $data['user_id'] ?? null, 'status' => 'new']);

            return $booking;
        };
        try {
            $booking = DB::transaction($create);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = empty($data['submission_key']) ? null : Booking::where('submission_key', $data['submission_key'])->first();
            if (! $existing) {
                throw $exception;
            }
            abort_unless(hash_equals($existing->submission_hash ?? '', $hash), 409);
            $booking = $existing;
        }

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
