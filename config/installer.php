<?php

return [
    /* The browser installer is for first-boot setup and is off by default in production. */
    'enabled' => filter_var(
        env('INSTALLER_ENABLED', env('APP_ENV', 'production') !== 'production'),
        FILTER_VALIDATE_BOOLEAN,
    ),
];
