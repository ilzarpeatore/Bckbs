<?php

return [
        
    'ACTIVITY_LEVEL' => [
        'bmr'           => 1, 
        'sedentary'     => 1.2,
        'lightly_active'=> 1.375,
        'moderate'      => 1.55,
        'very_active'   => 1.725,
        'athlete'       => 1.9,
    ],
    
    'FITNESS_GOAL' => [
        'm'  => 0,
        'l'  => -0.10,
        'l1' => -0.20,
        'l2' => -0.40,
        'g'  => 0.10,
        'g1' => 0.20,
        'g2' => 0.40,
    ],

    /* ------------------------------
     | Macro Presets (percent)
     | carbs + protein + fat = 100
     ------------------------------ */
    'MACRO_RATIO' => [
        'balanced'      => [ 'carbs' => 40, 'protein' => 30, 'fat' => 30 ],
        'low_fat'       => [ 'carbs' => 40, 'protein' => 40, 'fat' => 20 ],
        'high_protein'  => [ 'carbs' => 20, 'protein' => 50, 'fat' => 30 ],
        'high_carb'     => [ 'carbs' => 55, 'protein' => 25, 'fat' => 20 ],
        'keto'          => [ 'carbs' =>  5, 'protein' => 25, 'fat' => 70 ],
    ],
    
    'MEAL_TYPE' => [ 'breakfast', 'lunch', 'dinner', 'snacks' ],

    /**
     * recipes.meal_type (columna JSON) esta vacia en todo el catalogo real -
     * la categorizacion real vive en recipe_category_mappings. Este mapeo
     * traduce cada meal_type logico a su recipe_categories.id real, para que
     * el buscador de "Añadir comida" (Recipe::scopeRecipeFilter) pueda
     * filtrar contra datos que sí existen. Ids segun el seed real (ver
     * recipe_categories: 8=Desayuno, 9=Comida, 10=Cena, 11=Snack).
     */
    'MEAL_TYPE_CATEGORY' => [
        'breakfast' => 8,
        'lunch'     => 9,
        'dinner'    => 10,
        'snacks'    => 11,
    ],
];