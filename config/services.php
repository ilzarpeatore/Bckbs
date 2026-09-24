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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY')
    ],

    // Integración FatSecret Platform API (2026-09-19, ver
    // docs/FATSECRET_INTEGRATION.md) -- OAuth2 client credentials, requiere
    // que las llamadas salgan de una IP registrada en su panel (la del VPS
    // de producción, nunca desde un runner de CI ni desde el navegador).
    'fatsecret' => [
        'client_id' => env('FATSECRET_CLIENT_ID'),
        'client_secret' => env('FATSECRET_CLIENT_SECRET'),
    ],

    // Traducción de recetas de FatSecret (2026-09-21, ver
    // docs/FATSECRET_INTEGRATION.md sección 10) -- una key DeepL Free
    // termina en ":fx" y usa el host api-free.deepl.com; una key Pro no
    // lleva ese sufijo y usa api.deepl.com.
    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
    ],

    // Equivalencia de nombres de ejercicios del importador con IA
    // (ExerciseEquivalenceResolver). Sin key el importador funciona igual,
    // solo con el matcher por reglas.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
    ],

    'usda' => [
        'api_key' => env('USDA_API_KEY', ''),
    ],

];
