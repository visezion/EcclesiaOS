<?php

return [

    'cloudflare_insights' => [
        'enabled' => env('CLOUDFLARE_INSIGHTS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paystack' => [
        'key' => env('PAYSTACK_KEY'),
        'secret' => env('PAYSTACK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'central_support' => [
        'url' => env('CENTRAL_SUPPORT_URL', env('APP_ENV') === 'local' ? 'http://127.0.0.1:8090' : 'https://ecclesiaos.vicezion.com'),
        'enrollment_key' => env('CENTRAL_SUPPORT_ENROLLMENT_KEY', env('APP_ENV') === 'local' ? 'ecclesiaos-local-enrollment' : ''),
    ],

];
