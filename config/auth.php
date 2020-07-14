<?php
    return [
        'defaults' => [
            'guard' => 'api',
            'passwords' => 'users',
        ],

        'guards' => [
            'api' => [
                'driver' => 'passport',
                'provider' => 'users',
            ],
            'customer' => [
                'driver' => 'passport',
                'provider' => 'customer',
            ],
        ],

        'providers' => [
            'users' => [
                'driver' => 'eloquent',
                'model' => \App\Models\UserDetails\UserDetails::class
            ],
            'customer' => [
                'driver' => 'eloquent',
                'model' => \App\Models\CustomerDetails\CustomerDetails::class
            ]
        ]
    ];