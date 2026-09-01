<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // SEGURIDAD (auditoria 2026-09-01, INFO-1): origenes de desarrollo
    // local (localhost/127.0.0.1) y un duplicado de admin-testapp
    // quitados -- no deben vivir en la config de produccion. Riesgo real
    // bajo (lista explicita, no wildcard, ver SECURITY_AUDIT_BACKEND.md)
    // pero sin motivo para mantenerlos aqui.
    'allowed_origins' => [
        'https://admin-testapp.bestronger.es',
        'https://testapp.bestronger.es',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
