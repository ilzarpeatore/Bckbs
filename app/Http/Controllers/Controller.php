<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // AÑADIDO 2026-07-30: varios controladores admin autocontenidos (ClientGoal,
    // ClientLimitation, ClientBodyMetric, Resource) llamaban a este método
    // asumiendo que existía (probablemente copiado de otra plantilla) — nunca
    // se había definido en ningún sitio del proyecto, por lo que esos endpoints
    // daban error 500 en cuanto se invocaban de verdad. Envuelve el mismo
    // json_custom_response() que ya usa el resto del proyecto.
    protected function sendResponse($data, string $message, int $statusCode = 200)
    {
        return json_custom_response([
            'data'    => $data,
            'message' => $message,
        ], $statusCode);
    }
}
