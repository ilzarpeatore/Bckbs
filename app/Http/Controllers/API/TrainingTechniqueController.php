<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\TrainingTechniques;

/** Catálogo de técnicas especiales (App\Support\TrainingTechniques), para el panel y la app. */
class TrainingTechniqueController extends Controller
{
    public function getList()
    {
        return json_custom_response(['data' => TrainingTechniques::list()]);
    }
}
