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
    'azure_vision' => [
        'key' => env('AZURE_VISION_KEY'),
        'endpoint' => env('AZURE_VISION_ENDPOINT'),
    ],
    'local_llm' => [
        'base_url' => env('LOCAL_LLM_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'model' => env('LOCAL_LLM_MODEL', 'qwen2.5:3b'),
        'api_key' => env('LOCAL_LLM_API_KEY', 'ollama'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI') ?: '/auth/google/callback',
    ],

    'chargily' => [
        'mode' => env('CHARGILY_MODE', 'test'),
        'api_key' => env('CHARGILY_API_KEY'),
        'secret_key' => env('CHARGILY_SECRET_KEY'),
        // Public URL Chargily POSTs to (use ngrok HTTPS in dev). Not the HMAC secret.
        'webhook_url' => env('CHARGILY_WEBHOOK_URL'),
        // Optional signing key from dashboard; if empty, CHARGILY_SECRET_KEY is used. Must not be a URL.
        'webhook_secret' => env('CHARGILY_WEBHOOK_SECRET'),
        'locale' => env('CHARGILY_LOCALE', 'fr'),
        'base_url_test' => env('CHARGILY_BASE_URL_TEST', 'https://pay.chargily.net/test/api/v2'),
        'base_url_live' => env('CHARGILY_BASE_URL_LIVE', 'https://pay.chargily.net/api/v2'),
    ],

    'saas' => [
        'trial_days' => (int) env('SAAS_TRIAL_DAYS', 3),
        'grace_days' => (int) env('SAAS_GRACE_DAYS', 3),
        'manual_double_approval_threshold' => (int) env('SAAS_MANUAL_DOUBLE_APPROVAL_THRESHOLD', 300000),
        'admin_email' => env('SAAS_ADMIN_EMAIL'),
        'payee' => [
            'name' => env('SAAS_PAYEE_NAME', 'FinCompta DZ'),
            'rc' => env('SAAS_PAYEE_RC'),
            'nif' => env('SAAS_PAYEE_NIF'),
            'nis' => env('SAAS_PAYEE_NIS'),
            'bank_name' => env('SAAS_PAYEE_BANK_NAME'),
            'bank_rib' => env('SAAS_PAYEE_BANK_RIB'),
            'bank_swift' => env('SAAS_PAYEE_BANK_SWIFT'),
            'email' => env('SAAS_PAYEE_EMAIL'),
            'phone' => env('SAAS_PAYEE_PHONE'),
            'address' => env('SAAS_PAYEE_ADDRESS'),
        ],
    ],

];
