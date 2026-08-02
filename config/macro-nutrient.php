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
];