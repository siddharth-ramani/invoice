<?php

return [
    // Dashboard date defaults only; existing invoice timestamps and application timezone are unchanged.
    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Kolkata'),
    'payments_enabled' => env('BIZPILOT_PAYMENTS_ENABLED', false),
    'business_modules_enabled' => env('BIZPILOT_BUSINESS_MODULES_ENABLED', false),
    'platform_enabled' => env('BIZPILOT_PLATFORM_ENABLED', false),
];
