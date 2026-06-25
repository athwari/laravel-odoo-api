<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | The Odoo database name and host URL, plus credentials. Either a
    | password OR an api_key may be supplied; if both are present, the
    | api_key takes precedence for authentication.
    |
    */

    'database' => env('ODOO_DATABASE'),

    'host' => env('ODOO_HOST'),

    'username' => env('ODOO_USERNAME'),

    'password' => env('ODOO_PASSWORD'),

    'api_key' => env('ODOO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Fixed User ID
    |--------------------------------------------------------------------------
    |
    | If set, the authenticate() RPC call is skipped entirely and this
    | user ID is used directly for all subsequent calls. Useful when the
    | UID for a dedicated integration user is already known and static
    | (e.g. in CI, or a long-lived service account), to save a round
    | trip on every connection.
    |
    */

    'fixed_user_id' => env('ODOO_FIXED_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => env('ODOO_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify the server's TLS/SSL certificate on every request.
    | Set to false only for local development with self-signed certificates.
    | Must always be true in production environments.
    |
    */

    'ssl_verify' => env('ODOO_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | Context
    |--------------------------------------------------------------------------
    |
    | Default request context sent with every RPC call: language,
    | timezone, and active company for multi-company Odoo instances.
    |
    */

    'context' => [
        'lang' => env('ODOO_LANG'),
        'timezone' => env('ODOO_TIMEZONE'),
        'company_id' => env('ODOO_COMPANY_ID'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish
    | to use as your default connection for all Odoo work.
    |
    */

    'default' => env('ODOO_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Odoo Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the Odoo connections setup for your application.
    |
    */

    'connections' => [
        'default' => [
            'database' => env('ODOO_DATABASE'),
            'host' => env('ODOO_HOST'),
            'username' => env('ODOO_USERNAME'),
            'password' => env('ODOO_PASSWORD'),
            'api_key' => env('ODOO_API_KEY'),
            'fixed_user_id' => env('ODOO_FIXED_USER_ID'),
            'timeout' => env('ODOO_TIMEOUT', 30),
            'ssl_verify' => env('ODOO_SSL_VERIFY', true),
            'context' => [
                'lang' => env('ODOO_LANG'),
                'timezone' => env('ODOO_TIMEZONE'),
                'company_id' => env('ODOO_COMPANY_ID'),
            ],
        ],
    ],

];
