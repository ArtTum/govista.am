<?php

return [
    // Bound supplier traffic independently of per-IP public throttling.
    'daily_search_limit' => (int) env('TRAVEL_DAILY_SEARCH_LIMIT', 200),
    'frontend_url' => env('FRONTEND_URL', 'https://govista.am'),
];
