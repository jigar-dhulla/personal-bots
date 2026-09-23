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

    /*
    | Swiggy MCP (Instamart bot). OAuth 2.1 + PKCE against `auth_url`; the
    | redirect URI must be http://localhost (dev; `instamart:login` paste flow)
    | or an HTTPS URI allowlisted by builders@swiggy.in, pointing at this app's
    | /instamart/callback (dashboard login). See
    | https://mcp.swiggy.com/builders/docs/start/authenticate
    */
    'swiggy' => [
        'instamart_url' => env('SWIGGY_INSTAMART_URL', 'https://mcp.swiggy.com/im'),
        'auth_url' => env('SWIGGY_AUTH_URL', 'https://mcp.swiggy.com'),
        'redirect_uri' => env('SWIGGY_REDIRECT_URI', 'http://localhost:8765/callback'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
