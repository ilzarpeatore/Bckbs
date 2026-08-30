<?php

namespace Database\Seeders;

use App\Models\MotivationalPhrase;
use Illuminate\Database\Seeder;

class MotivationalPhraseSeeder extends Seeder
{
    /**
     * Semilla inicial para la frase contextual de la nueva cabecera Home
     * (ver docs/Nueva_Cabecera_Home_Helix.md, seccion 4). Varias frases por
     * condicion para que haya variabilidad real (se elige una al azar entre
     * las que aplican), no solo una fija por caso.
     */
    public function run(): void
    {
        $phrases = [
            ['text' => 'Llevas {n} entrenamientos esta semana, tu objetivo cada vez está más cerca', 'condition_type' => 'workouts_this_week', 'min_value' => 2, 'max_value' => null],
            ['text' => 'Ya son {n} entrenamientos esta semana — vas muy bien encaminado', 'condition_type' => 'workouts_this_week', 'min_value' => 2, 'max_value' => null],
            ['text' => 'Has entrenado {n} veces esta semana. Sigue así, se nota el esfuerzo', 'condition_type' => 'workouts_this_week', 'min_value' => 2, 'max_value' => null],
            ['text' => 'Esta semana aún no has entrenado — hoy es un buen día para empezar', 'condition_type' => 'workouts_this_week', 'min_value' => 0, 'max_value' => 0],
            ['text' => 'Todavía no has entrenado esta semana. Un primer paso hoy ya cuenta', 'condition_type' => 'workouts_this_week', 'min_value' => 0, 'max_value' => 0],
            ['text' => 'Llevas {n} días seguidos cumpliendo tus hábitos, sigue así', 'condition_type' => 'habits_streak', 'min_value' => 5, 'max_value' => null],
            ['text' => 'Racha de {n} días con tus hábitos — la constancia es lo que marca la diferencia', 'condition_type' => 'habits_streak', 'min_value' => 5, 'max_value' => null],
            ['text' => 'Cada entrenamiento suma, aunque hoy no lo sientas así', 'condition_type' => 'general', 'min_value' => null, 'max_value' => null],
            ['text' => 'El progreso real se construye entrenamiento a entrenamiento', 'condition_type' => 'general', 'min_value' => null, 'max_value' => null],
            ['text' => 'Hoy es una buena oportunidad para acercarte a tu objetivo', 'condition_type' => 'general', 'min_value' => null, 'max_value' => null],
        ];

        foreach ($phrases as $p) {
            MotivationalPhrase::firstOrCreate(
                ['text' => $p['text']],
                ['condition_type' => $p['condition_type'], 'min_value' => $p['min_value'], 'max_value' => $p['max_value'], 'is_active' => true]
            );
        }
    }
}
