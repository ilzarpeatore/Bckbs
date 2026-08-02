<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DB::table('blog_categories')->pluck('id', 'slug');

        $posts = [
            // Entrenamiento
            [
                'title' => '5 Ejercicios Clave para Ganar Fuerza en Casa',
                'slug' => '5-ejercicios-clave-ganar-fuerza-casa',
                'blog_category_id' => $categories['entrenamiento'] ?? null,
                'description' => 'No necesitas un gimnasio equipado para construir fuerza. Estos 5 ejercicios con peso corporal te ayudarán a tonificar todo tu cuerpo desde la comodidad de tu hogar.',
                'content' => '<h2>Entrena fuerte desde casa</h2><p>Ganar fuerza no requiere máquinas costosas. Con la técnica correcta y constancia, los ejercicios con peso corporal son extremadamente efectivos.</p><h3>1. Sentadillas</h3><p>El ejercicio compuesto por excelencia. Trabajan cuádriceps, glúteos y core. Realiza 3 series de 15 repeticiones.</p><h3>2. Flexiones</h3><p>Trabajan pecho, hombros y tríceps. Modifica la inclinación para ajustar la dificultad.</p><h3>3. Peso muerto a una pierna</h3><p>Excelente para equilibrio y fuerza de la cadena posterior.</p><h3>4. Plancha</h3><p>Fortalece el core profundo. Mantén 30-60 segundos por serie.</p><h3>5. Burpees</h3><p>Ejercicio completo que combina fuerza y cardio.</p>',
                'bibliography' => 'https://www.acefitness.org/resources/everyone/blog/5073/5-bodyweight-exercises/',
                'status' => 'publish',
                'is_featured' => 1,
                'datetime' => now()->subDays(1),
            ],
            [
                'title' => 'Cómo Crear una Rutina de Entrenamiento Semanal Efectiva',
                'slug' => 'como-crear-rutina-entrenamiento-semanal',
                'blog_category_id' => $categories['entrenamiento'] ?? null,
                'description' => 'Una buena rutina semanal equilibra intensidad y descanso. Aprende a estructurar tus entrenamientos para maximizar resultados sin sobreentrenar.',
                'content' => '<h2>La clave: estructura y progresión</h2><p>Entrenar sin plan es como viajar sin mapa. Necesitas una estructura clara que combine diferentes estímulos a lo largo de la semana.</p><h3>Distribución recomendada</h3><p><strong>Lunes:</strong> Tren superior (empuje)<br><strong>Martes:</strong> Tren inferior<br><strong>Miércoles:</strong> Cardio o descanso activo<br><strong>Jueves:</strong> Tren superior (tirón)<br><strong>Viernes:</strong> Cuerpo completo<br><strong>Sábado/Domingo:</strong> Descanso</p><h3>Principio de sobrecarga progresiva</h3><p>Cada semana intenta mejorar: más repeticiones, más peso, o mejor técnica. El progreso viene de la consistencia, no de la intensidad extrema.</p>',
                'bibliography' => 'https://www.nsca.com/education/articles/kinetic-select/program-design/',
                'status' => 'publish',
                'is_featured' => 0,
                'datetime' => now()->subDays(3),
            ],
            // Nutrición
            [
                'title' => 'Guía de Macros: Proteínas, Carbohidratos y Grasas',
                'slug' => 'guia-macros-proteinas-carbohidratos-grasas',
                'blog_category_id' => $categories['nutricion'] ?? null,
                'description' => 'Entender tus macronutrientes es fundamental para alcanzar tus objetivos fitness. Te explicamos cuánto necesitas de cada uno según tu meta.',
                'content' => '<h2>¿Qué son los macros?</h2><p>Los macronutrientes son los tres pilares de tu alimentación: proteínas, carbohidratos y grasas. Cada uno cumple funciones esenciales.</p><h3>Proteínas (4 kcal/g)</h3><p>Esenciales para construir y reparar músculo. Recomendación: 1.6-2.2g por kg de peso corporal para personas activas.</p><h3>Carbohidratos (4 kcal/g)</h3><p>Tu principal fuente de energía para entrenamientos intensos. No los elimines: son tu combustible.</p><h3>Grasas (9 kcal/g)</h3><p>Necesarias para hormonas, absorción de vitaminas y salud cerebral. Mínimo 0.8g por kg.</p><h3>¿Cómo calcular tus macros?</h3><p>Primero calcula tu TDEE (calorías totales diarias). Luego ajusta según tu objetivo: superávit para ganar músculo, déficit para perder grasa.</p>',
                'bibliography' => 'https://examine.com/guides/protein-intake/',
                'status' => 'publish',
                'is_featured' => 1,
                'datetime' => now()->subDays(2),
            ],
            [
                'title' => 'Alimentos que Debes Combinar Después de Entrenar',
                'slug' => 'alimentos-combinar-despues-entrenar',
                'blog_category_id' => $categories['nutricion'] ?? null,
                'description' => 'La ventana anabólica es más amplia de lo que crees, pero elegir bien tu comida post-entrenamiento puede marcar la diferencia.',
                'content' => '<h2>Combina proteína + carbohidratos</h2><p>Después de entrenar, tu cuerpo necesita reparar el tejido muscular y reponer el glucógeno. La combinación ideal es proteína de absorción rápida con carbohidratos de índice glucémico moderado.</p><h3>Buenas combinaciones</h3><ul><li>Pollo con arroz</li><li>Atún con pan integral</li><li>Batido de whey con banana</li><li>Huevos con avena</li><li>Yogur griego con frutas</li></ul><h3>Timing</h3><p>Intenta comer dentro de las primeras 2 horas post-entrenamiento. No necesitas correr al refrigerador, pero no dejes pasar medio día.</p>',
                'bibliography' => 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3577439/',
                'status' => 'publish',
                'is_featured' => 0,
                'datetime' => now()->subDays(5),
            ],
            // Hábitos
            [
                'title' => '7 Hábitos Diarios para Mejorar tu Salud Integral',
                'slug' => '7-habitos-diarios-mejorar-salud',
                'blog_category_id' => $categories['habitos'] ?? null,
                'description' => 'El fitness va más allá del gimnasio. Estos 7 hábitos diarios transformarán tu salud física y mental de forma sostenible.',
                'content' => '<h2>Pequeños cambios, grandes resultados</h2><p>No necesitas revolucionar tu vida. Con 7 ajustes simples puedes mejorar dramáticamente tu bienestar.</p><h3>1. Duerme 7-8 horas</h3><p>El sueño es cuando tu cuerpo se repara. Priorízalo como lo harías con una sesión de entrenamiento.</p><h3>2. Toma agua al despertar</h3><p>300-500ml de agua con hidratación después de 8 horas de sueño.</p><h3>3. Camina 8,000 pasos</h3><p>El NEAT (termogénesis por actividad no deportiva) quema más calorías que el ejercicio formal.</p><h3>4. Come vegetales en cada comida</h3><p>Fibra, micronutrientes y volumen para sentirte satisfecho con menos calorías.</p><h3>5. Medita 5 minutos</h3><p>Reduce cortisol, mejora la concentración y el manejo del estrés.</p><h3>6. Limita pantallas antes de dormir</h3><p>La luz azul suprime la melatonina. Apaga pantallas 1 hora antes de acostarte.</p><h3>7. Planifica tu día anterior</h3><p>5 minutos de planificación ahorrán horas de improvisación.</p>',
                'bibliography' => 'https://www.health.harvard.edu/blog/the-power-of-habits-2019082617429',
                'status' => 'publish',
                'is_featured' => 1,
                'datetime' => now()->subDays(1),
            ],
            [
                'title' => 'Gestión del Estrés: Por Qué Afecta Tu Físico',
                'slug' => 'gestion-estres-afecta-fisico',
                'blog_category_id' => $categories['habitos'] ?? null,
                'description' => 'El estrés crónico eleva el cortisol, promueve la acumulación de grasa abdominal y sabotea tu progreso. Aprende a combatirlo.',
                'content' => '<h2>El cortisol: tu enemigo silencioso</h2><p>Cuando estás estresado, tu cuerpo libera cortisol. En exceso, este hormona promueve almacenamiento de grasa (especialmente abdominal), destruye músculo, altera el sueño y reduce la inmunidad.</p><h3>Señales de estrés crónico</h3><ul><li>Dificultad para dormir</li><li>Cambios en el apetito</li><li>Irritabilidad constante</li><li>Dolor de cabeza frecuente</li><li>Baja en el rendimiento deportivo</li></ul><h3>Estrategias efectivas</h3><p>Ejercicio moderado, respiración profunda, tiempo en naturaleza, conexiones sociales y límites digitales son herramientas poderosas contra el estrés.</p>',
                'bibliography' => 'https://www.apa.org/topics/stress/body',
                'status' => 'publish',
                'is_featured' => 0,
                'datetime' => now()->subDays(7),
            ],
            // More Entrenamiento for variety
            [
                'title' => 'Guía para Principiantes: Tu Primera Semana en el Gimnasio',
                'slug' => 'guia-principiantes-primera-semana-gimnasio',
                'blog_category_id' => $categories['entrenamiento'] ?? null,
                'description' => '¿Es tu primera vez en el gimnasio? No te preocupes. Esta guía paso a paso te dará la confianza que necesitas para empezar.',
                'content' => '<h2>Bienvenido al gimnasio</h2><p>Todos empezamos en algún momento. Lo más importante es ir, aparecer y ser constante.</p><h3>Día 1-2: Familiarización</h3><p>Camina por el gimnasio. Observa los equipos. Pide ayuda al personal. No intentes levantar peso máximo el primer día.</p><h3>Día 3-4: Ejercicios básicos</h3><p>Enfócate en movimientos compuestos con poco peso: sentadillas, press de banca, remo y press militar. Aprende la técnica antes de subir peso.</p><h3>Día 5-6: Añade variedad</h3><p>Incorpora ejercicios de aislamiento: curl de bíceps, extensiones de tríceps, elevaciones laterales.</p><h3>Consejo clave</h3><p>No te compares con los veteranos. Tu única competencia eres tú mismo de ayer.</p>',
                'bibliography' => 'https://www.muscleandstrength.com/articles/starting-the-gym',
                'status' => 'publish',
                'is_featured' => 0,
                'datetime' => now()->subDays(4),
            ],
        ];

        foreach ($posts as $post) {
            $existing = DB::table('posts')->where('slug', $post['slug'])->first();
            if (!$existing) {
                DB::table('posts')->insert(array_merge($post, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
