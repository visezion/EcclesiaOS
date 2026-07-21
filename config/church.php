<?php

return [
    'name' => env('CHURCH_NAME', 'Kingdom Life Global Church'),
    'subtitle' => env('APP_SUBTITLE', 'Enterprise Church Management System'),
    'logo' => env('CHURCH_LOGO', null),
    'sidebar_background' => env('CHURCH_SIDEBAR_BACKGROUND', 'images/sidebar-church.png'),
    'address' => env('CHURCH_ADDRESS', 'Lagos, Nigeria'),
    'timezone' => env('CHURCH_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'currency' => env('CHURCH_CURRENCY', 'USD'),
    'contact_email' => env('CHURCH_CONTACT_EMAIL', 'hello@example.org'),
    'contact_phone' => env('CHURCH_CONTACT_PHONE', '+1 555 0100'),
];
