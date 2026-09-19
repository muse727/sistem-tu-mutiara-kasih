<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Student Online Payment — Provider Selector
    |--------------------------------------------------------------------------
    | Pilih provider pembayaran online untuk Portal Siswa. Nilai diambil dari
    | STUDENT_PAYMENT_PROVIDER (env). Selector ini bersifat reversible:
    | cukup ganti env lalu `config:clear`, tanpa mengubah kode.
    |
    |   midtrans : Midtrans Snap (dipakai untuk local development).
    |   flip     : Flip Accept Payment V2 (tetap tersedia, belum dipakai).
    |
    | CATATAN: Selector ini HANYA deklarasi konfigurasi. Belum ada controller/
    | UI yang membacanya — perilaku pembayaran saat ini TIDAK berubah.
    */
    'student_payment' => [
        'provider'  => env('STUDENT_PAYMENT_PROVIDER', 'midtrans'),
        'supported' => ['midtrans', 'flip'],
    ],

    'midtrans' => [
        'server_key'     => env('MIDTRANS_SERVER_KEY'),
        'client_key'     => env('MIDTRANS_CLIENT_KEY'),
        'is_production'  => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized'   => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds'         => env('MIDTRANS_IS_3DS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flip Accept Payment V2 Configuration (SANDBOX ONLY)
    |--------------------------------------------------------------------------
    | api_url        : sandbox base URL (https://bigflip.id/big_sandbox_api)
    | secret_key     : Flip API Secret Key — used as HTTP Basic username with
    |                  an EMPTY password. Environment only. Never logged/committed.
    | callback_token : Flip dashboard Accept-Payment validation token. Used to
    |                  authenticate inbound callbacks via constant-time compare.
    |                  Environment only. Never logged/committed/returned.
    */
    'flip' => [
        'api_url'        => env('FLIP_API_URL', 'https://bigflip.id/big_sandbox_api'),
        'secret_key'     => env('FLIP_SECRET_KEY'),
        'callback_token' => env('FLIP_CALLBACK_TOKEN'),
    ],

    'turnstile' => [
        'site_key'   => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
