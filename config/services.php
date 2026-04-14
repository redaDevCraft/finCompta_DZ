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
    'model' => env('LOCAL_LLM_MODEL', 'qwen2.5:7b-instruct'),
    'api_key' => env('LOCAL_LLM_API_KEY', 'ollama'),
    ],

];
