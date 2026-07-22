<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Token
    |--------------------------------------------------------------------------
    |
    | This is the system user access token or temporary access token generated
    | from the Meta App Developer Console. Permanent tokens are recommended
    | for production environments.
    |
    */
    'token' => env('WHATSAPP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Phone Number ID
    |--------------------------------------------------------------------------
    |
    | The unique identifier of the phone number registered with WhatsApp
    | from which you want to send messages.
    |
    */
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Account ID
    |--------------------------------------------------------------------------
    |
    | The unique identifier of the WhatsApp Business Account associated with
    | the phone number.
    |
    */
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The version of the Meta Graph API to use. Defaults to v20.0.
    |
    */
    'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),

    /*
    |--------------------------------------------------------------------------
    | Base API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Meta Graph API requests.
    |
    */
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for HTTP requests to the WhatsApp Cloud API.
    |
    */
    'timeout' => env('WHATSAPP_TIMEOUT', 30),
];
