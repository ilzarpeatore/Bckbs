<?php

return [

    /*
     * Apple rechazo la app (Guideline 1.4.1 - Safety - Physical Harm) porque
     * el campo "description" de Recetas/Dietas hace afirmaciones de salud
     * sin citar fuente. Mientras se revisa y reescribe ese contenido (ver
     * database/scripts/find_medical_claims.php), esta bandera oculta
     * "description" en las respuestas publicas de la API (lo que ve la app
     * movil) sin tocar el panel de admin, que sigue viendo el texto real
     * para poder revisarlo y corregirlo.
     *
     * Poner HIDE_RECIPE_DIET_DESCRIPTIONS=false en .env (y limpiar la cache
     * de config si esta activa) en cuanto Apple apruebe la app.
     */
    'hide_recipe_diet_descriptions' => env('HIDE_RECIPE_DIET_DESCRIPTIONS', true),

];
