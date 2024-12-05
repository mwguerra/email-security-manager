<?php

return [
    'verification_expiry_days' => env('EMAIL_VERIFICATION_EXPIRY_DAYS', 30),
    'password_expiry_days' => env('PASSWORD_EXPIRY_DAYS', 30),

    // Default redirect route for verification notices
    'redirect_route' => 'verification.notice',

    // Routes to skip verification
    'skip_routes' => [
        'verification.notice',
        'verification.verify',
        'verification.send',
        'password.request',
        'password.reset',
        'password.update',
        'logout'
    ],

    // Configure authenticatable models
    'authenticatable_models' => [
        'default' => \App\Models\User::class,
        // Add more models as needed:
        // 'admin' => \App\Models\Admin::class,
        // 'customer' => \App\Models\Customer::class,
    ],

    // Default authenticatable model key to use
    'default_authenticatable' => 'default',
];