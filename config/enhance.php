<?php

return [
    // Your Enhance orchestrator's base API URL, e.g. https://cp.mucahost.com/api
    'host' => env('ENHANCE_API_HOST'),

    // Top-level organization/reseller UUID the API calls are scoped to.
    'org_id' => env('ENHANCE_ORG_ID'),

    // Bearer token minted in the Enhance admin UI (Settings > API).
    'access_token' => env('ENHANCE_ACCESS_TOKEN'),
];
