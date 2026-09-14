<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelProvider;
use App\Services\Travel\PackageCatalog;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\TourvisorSearch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackageSearchController extends Controller
{
    private function provider(): ?TravelProvider
    {
        $provider = TravelProvider::where('code', 'tourvisor')->where('enabled', true)->where('environment', 'live')->first();

        return $provider && ProviderRegistry::ready($provider) && PackageCatalog::approved($provider) && data_get($provider->settings, 'evn_confirmed') ? $provider : null;
    }

    public function start(Request $request, TourvisorSearch $search)
    {
        $input = $request->validate([
            'locale' => ['required', 'in:hy,ru,en'], 'origin' => ['required', 'in:EVN'],
            'destination_code' => ['required', Rule::in(array_keys(PackageCatalog::DESTINATIONS))],
            'date_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'nights_min' => ['required', 'integer', 'between:1,28'], 'nights_max' => ['required', 'integer', 'between:1,28', 'gte:nights_min'],
            'adults' => ['required', 'integer', 'between:1,6'], 'children' => ['present', 'array', 'max:3'], 'children.*' => ['integer', 'between:0,17'],
            'direct_only' => ['required', 'boolean'], 'stars' => ['nullable', 'integer', 'between:1,5'],
        ]);
        abort_if(Carbon::parse($input['date_from'])->diffInDays($input['date_to']) > 20, 422, 'Մեկնման միջակայքը առավելագույնը 21 օր է։');
        abort_if($input['nights_max'] - $input['nights_min'] > 10, 422, 'Գիշերների միջակայքը առավելագույնը 10 է։');
        $provider = $this->provider();
        if (! $provider || ! data_get($provider->settings, 'destination_ids.'.$input['destination_code'].'.region_id') || ! data_get($provider->settings, 'destination_ids.'.$input['destination_code'].'.country_id')) {
            return ['available' => false, 'request_available' => true];
        }
        $limitKey = 'travel-provider:tourvisor:'.now()->toDateString();
        abort_if(RateLimiter::tooManyAttempts($limitKey, (int) config('travel.daily_search_limit')), 429, 'Այսօրվա որոնման սահմանը լրացել է։');
        RateLimiter::hit($limitKey, 86400);
        try {
            $id = $search->start($provider, $input);
            $token = Str::random(48);
            Cache::put('package-search:'.$token, ['id' => $id, 'input' => $input, 'fingerprint' => $search->fingerprint($provider), 'polls' => 0, 'expires_at' => now()->addMinutes(10)->timestamp], now()->addMinutes(10));

            return response()->json(['available' => true, 'token' => $token, 'expires_in' => 600])->header('Cache-Control', 'no-store');
        } catch (\Throwable) {
            $provider->update(['status' => 'error', 'last_checked_at' => now(), 'last_error' => 'Փաթեթների որոնումը չհաջողվեց։']);

            return response()->json(['message' => 'Որոնումը ժամանակավորապես անհասանելի է։ Կարող եք ուղարկել հայտ։'], 502);
        }
    }

    public function results(string $token, TourvisorSearch $search)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{48}$/', $token), 404);
        $lock = Cache::lock('package-search-lock:'.$token, 25);
        abort_unless($lock->get(), 429, 'Թարմացումն արդեն ընթացքի մեջ է։');
        try {
            $batch = Cache::get('package-search:'.$token);
            $provider = $this->provider();
            abort_unless($batch && $provider && hash_equals($batch['fingerprint'], $search->fingerprint($provider)), 410, 'Որոնումը ժամկետանց է։');
            if (isset($batch['result']) && ($batch['result']['complete'] || ($batch['last_poll'] ?? 0) > now()->subSeconds(3)->timestamp)) {
                return response()->json($batch['result'])->header('Cache-Control', 'no-store');
            }
            abort_if(($batch['last_poll'] ?? 0) > now()->subSeconds(3)->timestamp, 429, 'Սպասեք հաջորդ թարմացմանը։');
            abort_if($batch['polls'] >= 20, 429, 'Թարմացման սահմանը լրացել է։ Կրկին որոնեք կամ ուղարկեք հայտ։');
            // Count failed attempts too, and serialize polling to protect supplier quota.
            $batch['polls']++;
            $batch['last_poll'] = now()->timestamp;
            Cache::put('package-search:'.$token, $batch, max(1, $batch['expires_at'] - now()->timestamp));
            try {
                $result = $search->results($provider, $batch['id'], $batch['input']);
                $batch['result'] = $result;
                Cache::put('package-search:'.$token, $batch, max(1, $batch['expires_at'] - now()->timestamp));
                $provider->update(['status' => 'connected', 'last_checked_at' => now(), 'last_success_at' => now(), 'last_error' => null]);

                return response()->json($result)->header('Cache-Control', 'no-store');
            } catch (\Throwable) {
                return response()->json(['message' => 'Արդյունքների թարմացումը չհաջողվեց։'], 502);
            }
        } finally {
            $lock->release();
        }
    }
}
