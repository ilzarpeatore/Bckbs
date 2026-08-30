<?php

namespace Database\Seeders;

use App\Models\{
    User, Equipment, WorkoutType, BodyPart, Level, Category, Tags,
    Exercise, Workout, WorkoutDay, WorkoutDayExercise,
    TrainingProgram,
    Diet, CategoryDiet,
    Recipe, RecipeCategory, RecipeTag, Ingredient, IngredientCategory,
    MeasurementUnit,
    Product, ProductCategory,
    Quotes, BannerSlider, Post, BlogCategory,
    PushNotification, ClassSchedule, Setting,
    Form, FormQuestion,
    Habit, Challenge, Resource,
    SectionTemplate, SectionTemplateExercise,
    WorkoutTemplate,
    MealPlanTemplate, MealPlanTemplateItem,
};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FitnessDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEquipment();
        $this->seedWorkoutTypes();
        $this->seedBodyParts();
        $this->seedLevels();
        $this->seedCategories();
        $this->seedTags();
        $this->seedExercises();
        $this->seedWorkouts();
        $this->seedCategoryDiets();
        $this->seedDiets();
        $this->seedMeasurementUnits();
        $this->seedIngredientCategories();
        $this->seedIngredients();
        $this->seedRecipeCategories();
        $this->seedRecipeTags();
        $this->seedRecipes();
        $this->seedProductCategories();
        $this->seedProducts();
        $this->seedTrainingPrograms();
        $this->seedBlogPosts();
        $this->seedQuotes();
        $this->seedSettings();
        $this->seedClassSchedules();
        $this->seedDemoClients();
        $this->seedMealPlanTemplates();
        $this->seedWorkoutTemplates();
        $this->seedSectionTemplates();
        $this->seedForms();
        $this->seedHabits();
        $this->seedChallenges();
        $this->seedResources();
    }

    private function seedEquipment(): void
    {
        foreach ([
            'Mancuernas', 'Barra olímpica', 'Kettlebell', 'Bandas de resistencia', 'TRX',
            'Máquina Smith', 'Polea', 'Banco', 'Máquina de remo', 'Cinta de correr',
            'Bicicleta estática', 'Elíptica', 'Peso corporal', 'Cajón pliométrico', 'Rueda abdominal', 'Fitball',
        ] as $title) {
            Equipment::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('Equipment: 16');
    }

    private function seedWorkoutTypes(): void
    {
        foreach (['Fuerza', 'Hipertrofia', 'Resistencia', 'Cardio', 'HIIT', 'Flexibilidad', 'Pliometría', 'Funcional'] as $title) {
            WorkoutType::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('WorkoutTypes: 8');
    }

    private function seedBodyParts(): void
    {
        foreach (['Pecho', 'Espalda', 'Hombros', 'Deltoides anterior', 'Deltoides lateral', 'Deltoides posterior', 'Bíceps', 'Tríceps', 'Antebrazos', 'Trapecios', 'Cuello', 'Cuerpo completo', 'Core', 'Abdominales', 'Oblicuos', 'Glúteos', 'Cuádriceps', 'Isquiotibiales', 'Aductores', 'Abductores', 'Gemelos', 'Tibial anterior'] as $title) {
            BodyPart::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('BodyParts: 22');
    }

    private function seedLevels(): void
    {
        foreach (['Principiante', 'Intermedio', 'Avanzado', 'Élite'] as $title) {
            Level::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('Levels: 4');
    }

    private function seedCategories(): void
    {
        foreach (['Empuje', 'Tracción', 'Pierna', 'Core', 'Movilidad', 'Compuesto', 'Aislamiento'] as $title) {
            Category::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('Categories: 7');
    }

    private function seedTags(): void
    {
        foreach (['Básico', 'Popular', 'Premium', 'Principiante', 'Avanzado', 'Peso libre', 'Máquina', 'Sin material'] as $title) {
            Tags::create(['title' => $title]);
        }
        $this->command?->info('Tags: 8');
    }

    private function seedExercises(): void
    {
        $list = [
            [1, 2, 2, 'Press banca con barra', 'Tumbado en banco plano, bajar la barra al pecho y empujar hacia arriba.'],
            [1, 1, 2, 'Press banca con mancuernas'],
            [1, 1, 2, 'Aperturas con mancuernas'],
            [1, 13, 2, 'Fondos en paralelas'],
            [1, 2, 2, 'Press inclinado con barra'],
            [1, 7, 1, 'Cruce de poleas'],
            [2, 13, 2, 'Dominadas'],
            [2, 2, 2, 'Remo con barra'],
            [2, 1, 1, 'Remo con mancuernas a una mano'],
            [2, 7, 1, 'Jalón al pecho'],
            [2, 7, 1, 'Remo en polea baja'],
            [2, 2, 3, 'Peso muerto'],
            [3, 2, 2, 'Press militar con barra'],
            [3, 1, 1, 'Elevaciones laterales'],
            [3, 1, 2, 'Press Arnold'],
            [3, 1, 2, 'Pájaros'],
            [4, 2, 1, 'Curl de bíceps con barra'],
            [4, 1, 1, 'Curl de bíceps alterno'],
            [4, 1, 1, 'Curl martillo'],
            [5, 7, 1, 'Extensión de tríceps en polea'],
            [5, 2, 2, 'Press francés'],
            [5, 1, 1, 'Patada de tríceps'],
            [6, 2, 2, 'Sentadilla con barra'],
            [6, 3, 2, 'Prensa de piernas'],
            [6, 3, 1, 'Extensiones de cuádriceps'],
            [6, 1, 1, 'Zancadas con mancuernas'],
            [7, 3, 1, 'Curl femoral tumbado'],
            [7, 2, 2, 'Peso muerto rumano'],
            [8, 2, 2, 'Hip thrust'],
            [9, 13, 1, 'Plancha abdominal', 'Mantener el cuerpo recto apoyado en antebrazos.'],
            [9, 13, 1, 'Crunches'],
            [9, 13, 2, 'Elevación de piernas colgado'],
            [9, 15, 3, 'Rueda abdominal'],
            [10, 13, 1, 'Elevación de gemelos de pie'],
            [11, 1, 1, 'Curl de antebrazos'],
            [12, 1, 1, 'Encogimientos de hombros'],
            [13, 13, 2, 'Burpees'],
            [13, 3, 2, 'Swing con kettlebell'],
            [13, 8, 2, 'Battle ropes'],
            [13, 14, 2, 'Box jumps'],
        ];

        foreach ($list as $ex) {
            Exercise::create([
                'bodypart_ids' => (string) $ex[0],
                'equipment_id' => $ex[1],
                'level_id' => $ex[2],
                'title' => $ex[3],
                'instruction' => $ex[4] ?? null,
                'status' => 'active',
            ]);
        }
        $this->command?->info('Exercises: ' . count($list));
    }

    private function seedWorkouts(): void
    {
        $data = [
            'Pecho y tríceps' => [1, [1,2,3,20,21,22]],
            'Espalda y bíceps' => [1, [7,8,10,17,18,19]],
            'Pierna completo' => [1, [23,24,25,26,27,28,29]],
            'Hombro y trapecio' => [1, [13,14,15,16,36]],
            'Full Body' => [1, [1,7,23,13,30,31,38]],
            'HIIT cardiovascular' => [5, [37,38,39,40,26]],
            'Core y abdominales' => [1, [30,31,32,33]],
        ];

        foreach ($data as $name => [$typeId, $exIds]) {
            $wo = Workout::create([
                'title' => $name,
                'workout_type_id' => $typeId,
                'status' => 'active',
            ]);
            $day = WorkoutDay::create([
                'workout_id' => $wo->id,
                'sequence' => 0,
            ]);
            foreach ($exIds as $exId) {
                WorkoutDayExercise::create([
                    'workout_id' => $wo->id,
                    'workout_day_id' => $day->id,
                    'exercise_id' => $exId,
                    'sequence' => 0,
                ]);
            }
        }
        $this->command?->info('Workouts: ' . count($data));
    }

    private function seedCategoryDiets(): void
    {
        foreach (['Definición', 'Volumen', 'Mantenimiento', 'Keto', 'Vegano', 'Alta proteína'] as $title) {
            CategoryDiet::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('CategoryDiets: 6');
    }

    private function seedDiets(): void
    {
        $list = [
            ['title' => 'Dieta definición 2000 kcal', 'categorydiet_id' => 1, 'calories' => 2000, 'protein' => 160, 'carbs' => 200, 'fat' => 55],
            ['title' => 'Dieta volumen 3000 kcal', 'categorydiet_id' => 2, 'calories' => 3000, 'protein' => 180, 'carbs' => 380, 'fat' => 80],
            ['title' => 'Dieta mantenimiento 2500 kcal', 'categorydiet_id' => 3, 'calories' => 2500, 'protein' => 170, 'carbs' => 300, 'fat' => 70],
            ['title' => 'Plan keto', 'categorydiet_id' => 4, 'calories' => 2200, 'protein' => 150, 'carbs' => 40, 'fat' => 160],
            ['title' => 'Dieta vegana alta proteína', 'categorydiet_id' => 5, 'calories' => 2400, 'protein' => 140, 'carbs' => 320, 'fat' => 60],
        ];
        foreach ($list as $d) {
            Diet::create(array_merge($d, ['status' => 'active']));
        }
        $this->command?->info('Diets: ' . count($list));
    }

    private function seedMeasurementUnits(): void
    {
        foreach ([
            ['title' => 'Gramos', 'unit_type' => 'weight'],
            ['title' => 'Mililitros', 'unit_type' => 'volume'],
            ['title' => 'Unidad', 'unit_type' => 'piece'],
            ['title' => 'Cucharada', 'unit_type' => 'volume'],
            ['title' => 'Cucharadita', 'unit_type' => 'volume'],
            ['title' => 'Taza', 'unit_type' => 'volume'],
        ] as $mu) {
            MeasurementUnit::create($mu);
        }
        $this->command?->info('MeasurementUnits: 6');
    }

    private function seedIngredientCategories(): void
    {
        foreach (['Proteínas', 'Verduras', 'Frutas', 'Cereales', 'Lácteos', 'Grasas saludables', 'Legumbres', 'Frutos secos'] as $title) {
            IngredientCategory::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('IngredientCategories: 8');
    }

    private function seedIngredients(): void
    {
        $list = [
            ['title' => 'Pechuga de pollo', 'ingredient_category_id' => 1, 'calories_per_gram' => 1.65, 'protein_per_gram' => 0.31, 'carbs_per_gram' => 0, 'fat_per_gram' => 0.036, 'status' => 'active'],
            ['title' => 'Salmón', 'ingredient_category_id' => 1, 'calories_per_gram' => 2.08, 'protein_per_gram' => 0.20, 'carbs_per_gram' => 0, 'fat_per_gram' => 0.13, 'status' => 'active'],
            ['title' => 'Huevos', 'ingredient_category_id' => 1, 'calories_per_gram' => 1.55, 'protein_per_gram' => 0.13, 'carbs_per_gram' => 0.011, 'fat_per_gram' => 0.11, 'status' => 'active'],
            ['title' => 'Arroz blanco', 'ingredient_category_id' => 4, 'calories_per_gram' => 1.30, 'protein_per_gram' => 0.027, 'carbs_per_gram' => 0.28, 'fat_per_gram' => 0.003, 'status' => 'active'],
            ['title' => 'Avena', 'ingredient_category_id' => 4, 'calories_per_gram' => 3.89, 'protein_per_gram' => 0.169, 'carbs_per_gram' => 0.66, 'fat_per_gram' => 0.069, 'status' => 'active'],
            ['title' => 'Brócoli', 'ingredient_category_id' => 2, 'calories_per_gram' => 0.34, 'protein_per_gram' => 0.028, 'carbs_per_gram' => 0.07, 'fat_per_gram' => 0.004, 'status' => 'active'],
            ['title' => 'Aguacate', 'ingredient_category_id' => 6, 'calories_per_gram' => 1.60, 'protein_per_gram' => 0.02, 'carbs_per_gram' => 0.09, 'fat_per_gram' => 0.15, 'status' => 'active'],
            ['title' => 'Patata', 'ingredient_category_id' => 2, 'calories_per_gram' => 0.77, 'protein_per_gram' => 0.02, 'carbs_per_gram' => 0.17, 'fat_per_gram' => 0.001, 'status' => 'active'],
            ['title' => 'Plátano', 'ingredient_category_id' => 3, 'calories_per_gram' => 0.89, 'protein_per_gram' => 0.011, 'carbs_per_gram' => 0.23, 'fat_per_gram' => 0.003, 'status' => 'active'],
            ['title' => 'Yogur griego', 'ingredient_category_id' => 5, 'calories_per_gram' => 0.97, 'protein_per_gram' => 0.10, 'carbs_per_gram' => 0.04, 'fat_per_gram' => 0.05, 'status' => 'active'],
            ['title' => 'Lentejas', 'ingredient_category_id' => 7, 'calories_per_gram' => 1.16, 'protein_per_gram' => 0.09, 'carbs_per_gram' => 0.20, 'fat_per_gram' => 0.004, 'status' => 'active'],
            ['title' => 'Almendras', 'ingredient_category_id' => 8, 'calories_per_gram' => 5.79, 'protein_per_gram' => 0.21, 'carbs_per_gram' => 0.22, 'fat_per_gram' => 0.50, 'status' => 'active'],
        ];
        foreach ($list as $ing) {
            Ingredient::create($ing);
        }
        $this->command?->info('Ingredients: ' . count($list));
    }

    private function seedRecipeCategories(): void
    {
        foreach (['Desayuno', 'Almuerzo', 'Cena', 'Snack', 'Pre-entreno', 'Post-entreno', 'Batidos'] as $title) {
            RecipeCategory::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('RecipeCategories: 7');
    }

    private function seedRecipeTags(): void
    {
        foreach (['Rápido', 'Alto en proteína', 'Bajo en calorías', 'Vegano', 'Sin gluten', 'Meal prep'] as $title) {
            RecipeTag::create(['title' => $title, 'status' => 'active']);
        }
        $this->command?->info('RecipeTags: 6');
    }

    private function seedRecipes(): void
    {
        $list = [
            ['title' => 'Tortilla de claras y avena', 'calories' => 350, 'protein' => 30, 'carbs' => 40, 'fats' => 10, 'preparation_time' => 10, 'meal_type' => 'breakfast', 'type' => 'standard'],
            ['title' => 'Pollo a la plancha con arroz y brócoli', 'calories' => 550, 'protein' => 45, 'carbs' => 55, 'fats' => 12, 'preparation_time' => 25, 'meal_type' => 'lunch', 'type' => 'standard'],
            ['title' => 'Batido de proteína post-entreno', 'calories' => 400, 'protein' => 35, 'carbs' => 50, 'fats' => 8, 'preparation_time' => 5, 'meal_type' => 'post_workout', 'type' => 'shake'],
            ['title' => 'Salmón al horno con patata', 'calories' => 620, 'protein' => 40, 'carbs' => 45, 'fats' => 25, 'preparation_time' => 35, 'meal_type' => 'dinner', 'type' => 'standard'],
            ['title' => 'Ensalada de garbanzos y aguacate', 'calories' => 480, 'protein' => 20, 'carbs' => 50, 'fats' => 22, 'preparation_time' => 15, 'meal_type' => 'lunch', 'type' => 'standard'],
            ['title' => 'Wrap de pollo y verduras', 'calories' => 420, 'protein' => 35, 'carbs' => 40, 'fats' => 12, 'preparation_time' => 15, 'meal_type' => 'lunch', 'type' => 'standard'],
            ['title' => 'Yogur griego con frutos rojos y almendras', 'calories' => 300, 'protein' => 18, 'carbs' => 30, 'fats' => 15, 'preparation_time' => 5, 'meal_type' => 'snack', 'type' => 'standard'],
            ['title' => 'Bowl de avena y plátano pre-entreno', 'calories' => 450, 'protein' => 20, 'carbs' => 75, 'fats' => 10, 'preparation_time' => 10, 'meal_type' => 'pre_workout', 'type' => 'standard'],
        ];
        foreach ($list as $r) {
            Recipe::create(array_merge($r, ['status' => 'active']));
        }
        $this->command?->info('Recipes: ' . count($list));
    }

    private function seedProductCategories(): void
    {
        foreach (['Suplementos', 'Ropa deportiva', 'Accesorios', 'Equipamiento', 'Nutrición'] as $title) {
            ProductCategory::create(['title' => $title]);
        }
        $this->command?->info('ProductCategories: 5');
    }

    private function seedProducts(): void
    {
        $list = [
            ['title' => 'Proteína Whey 1kg', 'productcategory_id' => 1, 'price' => 35.00, 'status' => 'active'],
            ['title' => 'Creatina Monohidrato 500g', 'productcategory_id' => 1, 'price' => 15.00, 'status' => 'active'],
            ['title' => 'BCAA 300g', 'productcategory_id' => 1, 'price' => 20.00, 'status' => 'active'],
            ['title' => 'Camiseta técnica', 'productcategory_id' => 2, 'price' => 25.00, 'status' => 'active'],
            ['title' => 'Bandas de resistencia set', 'productcategory_id' => 4, 'price' => 18.00, 'status' => 'active'],
            ['title' => 'Botella shaker', 'productcategory_id' => 3, 'price' => 10.00, 'status' => 'active'],
        ];
        foreach ($list as $p) {
            Product::create($p);
        }
        $this->command?->info('Products: ' . count($list));
    }

    private function seedTrainingPrograms(): void
    {
        $list = ['Hipertrofia 3 meses', 'Definición 3 meses', 'Fuerza 3 meses', 'Pérdida de Peso'];
        foreach ($list as $name) {
            TrainingProgram::create([
                'title' => $name,
                'workout_id' => 5,
                'coach_id' => 1,
                'num_weeks' => 12,
                'fecha_inicio' => now()->toDateString(),
                'activo' => true,
            ]);
        }
        $this->command?->info('TrainingPrograms: ' . count($list));
    }

    private function seedBlogPosts(): void
    {
        $list = [
            ['title' => 'Guía básica de entrenamiento', 'description' => 'Todo lo que necesitas para empezar', 'status' => 'publish'],
            ['title' => 'Nutrición deportiva 101', 'description' => 'Los fundamentos de la alimentación fitness', 'status' => 'publish'],
            ['title' => 'Cómo estructurar tu rutina semanal', 'description' => 'Frecuencia, volumen e intensidad', 'status' => 'publish'],
            ['title' => 'Recuperación muscular: claves', 'description' => 'Sueño, alimentación y descanso activo', 'status' => 'publish'],
        ];
        foreach ($list as $p) {
            Post::create($p);
        }
        $this->command?->info('Posts: ' . count($list));
    }

    private function seedQuotes(): void
    {
        $list = [
            'El dolor que sientes hoy será la fuerza que sientas mañana.',
            'No pares cuando estés cansado, para cuando hayas terminado.',
            'La disciplina es hacer lo que hay que hacer, aunque no tengas ganas.',
            'Tu único límite eres tú.',
            'Cada entrenamiento es un paso más hacia tu objetivo.',
        ];
        foreach ($list as $message) {
            Quotes::create(['title' => Str::limit($message, 50), 'message' => $message]);
        }
        $this->command?->info('Quotes: ' . count($list));
    }

    private function seedSettings(): void
    {
        Setting::create(['type' => 'site', 'key' => 'app_name', 'value' => 'MightyFitness']);
        Setting::create(['type' => 'site', 'key' => 'app_description', 'value' => 'Tu plataforma de entrenamiento personal']);
        $this->command?->info('Settings: 2');
    }

    private function seedClassSchedules(): void
    {
        $list = [
            ['class_name' => 'HIIT Mañanero', 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(3)->toDateString(), 'start_time' => '07:00', 'end_time' => '07:45', 'name' => 'HIIT', 'price' => 10],
            ['class_name' => 'Yoga', 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(3)->toDateString(), 'start_time' => '18:00', 'end_time' => '19:00', 'name' => 'Yoga', 'price' => 0],
            ['class_name' => 'Spinning', 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(3)->toDateString(), 'start_time' => '17:30', 'end_time' => '18:15', 'name' => 'Spinning', 'price' => 10],
        ];
        foreach ($list as $c) {
            ClassSchedule::create($c);
        }
        $this->command?->info('ClassSchedules: ' . count($list));
    }

    private function seedDemoClients(): void
    {
        $list = [
            ['first_name' => 'Juan', 'last_name' => 'Pérez', 'email' => 'juan@email.com', 'username' => 'juanp', 'password' => bcrypt('password'), 'user_type' => 'user', 'status' => 'active', 'display_name' => 'Juan Pérez', 'email_verified_at' => now()],
            ['first_name' => 'María', 'last_name' => 'García', 'email' => 'maria@email.com', 'username' => 'mariag', 'password' => bcrypt('password'), 'user_type' => 'user', 'status' => 'active', 'display_name' => 'María García', 'email_verified_at' => now()],
            ['first_name' => 'Carlos', 'last_name' => 'López', 'email' => 'carlos@email.com', 'username' => 'carlosl', 'password' => bcrypt('password'), 'user_type' => 'user', 'status' => 'active', 'display_name' => 'Carlos López', 'email_verified_at' => now()],
            ['first_name' => 'Ana', 'last_name' => 'Martínez', 'email' => 'ana@email.com', 'username' => 'anam', 'password' => bcrypt('password'), 'user_type' => 'user', 'status' => 'active', 'display_name' => 'Ana Martínez', 'email_verified_at' => now()],
            ['first_name' => 'Coach', 'last_name' => 'Principal', 'email' => 'coach@email.com', 'username' => 'coach', 'password' => bcrypt('password'), 'user_type' => 'coach', 'status' => 'active', 'display_name' => 'Coach Principal', 'email_verified_at' => now()],
        ];
        foreach ($list as $c) {
            $u = new User();
            foreach ($c as $k => $v) {
                $u->{$k} = $v;
            }
            $u->save();
            if ($c['user_type'] === 'user') {
                $u->assignRole('user');
            } elseif ($c['user_type'] === 'coach') {
                $u->assignRole('admin');
            }
        }
        $this->command?->info('Demo clients: ' . count($list));
    }

    private function seedMealPlanTemplates(): void
    {
        $t = MealPlanTemplate::create(['title' => 'Plan definición estándar', 'type' => 'weekday', 'coach_id' => 1]);
        foreach ([
            ['monday', 'breakfast', 1], ['monday', 'lunch', 2], ['monday', 'dinner', 4],
            ['tuesday', 'breakfast', 7], ['tuesday', 'lunch', 5], ['tuesday', 'dinner', 2],
            ['wednesday', 'breakfast', 1], ['wednesday', 'lunch', 6], ['wednesday', 'dinner', 4],
            ['thursday', 'breakfast', 8], ['thursday', 'lunch', 2], ['thursday', 'dinner', 5],
            ['friday', 'breakfast', 1], ['friday', 'lunch', 4], ['friday', 'dinner', 2],
        ] as [$day, $meal, $recipeId]) {
            MealPlanTemplateItem::create([
                'meal_plan_template_id' => $t->id,
                'day_key' => $day,
                'meal_type' => $meal,
                'recipe_id' => $recipeId,
            ]);
        }
        $this->command?->info('MealPlanTemplates: 1');
    }

    private function seedSectionTemplates(): void
    {
        $data = [
            'Push Day' => [1, 2, 13, 14, 20],
            'Pull Day' => [7, 8, 10, 17, 18],
            'Leg Day' => [23, 26, 27, 29, 34],
            'Core Circuit' => [30, 31, 32, 33],
        ];
        foreach ($data as $title => $exIds) {
            $st = SectionTemplate::create(['title' => $title, 'coach_id' => 1]);
            foreach ($exIds as $i => $exId) {
                SectionTemplateExercise::create([
                    'section_template_id' => $st->id,
                    'exercise_id' => $exId,
                    'sequence' => $i,
                ]);
            }
        }
        $this->command?->info('SectionTemplates: ' . count($data));
    }

    private function seedWorkoutTemplates(): void
    {
        WorkoutTemplate::create(['title' => 'Push-Pull-Legs', 'description' => 'Rutina clásica PPL', 'is_exclusive' => false, 'coach_id' => 1]);
        WorkoutTemplate::create(['title' => 'Upper-Lower 4 días', 'description' => 'Tren superior/inferior', 'is_exclusive' => true, 'coach_id' => 1]);
        $this->command?->info('WorkoutTemplates: 2');
    }

    private function seedForms(): void
    {
        $f = Form::create(['coach_id' => 1, 'title' => 'Check-in semanal', 'description' => 'Evaluación semanal del progreso', 'recurrence' => 'weekly']);
        FormQuestion::create(['form_id' => $f->id, 'question_text' => '¿Cómo te sientes esta semana?', 'type' => 'scale', 'order' => 0]);
        FormQuestion::create(['form_id' => $f->id, 'question_text' => '¿Has completado tus entrenamientos?', 'type' => 'boolean', 'order' => 1]);
        FormQuestion::create(['form_id' => $f->id, 'question_text' => '¿Cómo ha ido la alimentación?', 'type' => 'scale', 'order' => 2]);
        FormQuestion::create(['form_id' => $f->id, 'question_text' => 'Comentarios adicionales', 'type' => 'text', 'order' => 3]);
        $this->command?->info('Forms: 1');
    }

    private function seedHabits(): void
    {
        $list = [
            ['title' => 'Beber 2L de agua', 'target_value' => 2000, 'target_unit' => 'ml', 'frequency' => 'daily'],
            ['title' => '10k pasos diarios', 'target_value' => 10000, 'target_unit' => 'steps', 'frequency' => 'daily'],
            ['title' => 'Dormir 8h', 'target_value' => 8, 'target_unit' => 'hours', 'frequency' => 'daily'],
            ['title' => 'Estiramientos', 'target_value' => 15, 'target_unit' => 'min', 'frequency' => 'daily'],
        ];
        foreach ($list as $h) {
            Habit::create(array_merge($h, ['coach_id' => 1]));
        }
        $this->command?->info('Habits: ' . count($list));
    }

    private function seedChallenges(): void
    {
        Challenge::create(['title' => '30 días de plancha', 'description' => 'Añade 5 segundos cada día', 'coach_id' => 1, 'metric_type' => 'duration', 'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->addDays(25)->toDateString(), 'scope' => 'shared']);
        Challenge::create(['title' => 'Reto 100 flexiones', 'description' => 'Llega a 100 flexiones en un día', 'coach_id' => 1, 'metric_type' => 'count', 'start_date' => now()->toDateString(), 'end_date' => now()->addDays(30)->toDateString(), 'scope' => 'shared']);
        $this->command?->info('Challenges: 2');
    }

    private function seedResources(): void
    {
        $list = [
            ['title' => 'Guía de calentamiento', 'type' => 'pdf', 'category' => 'guides'],
            ['title' => 'Tabla de ejercicios básicos', 'type' => 'pdf', 'category' => 'guides'],
            ['title' => 'Vídeo técnica sentadilla', 'type' => 'video', 'category' => 'videos'],
            ['title' => 'Plantilla de progreso', 'type' => 'spreadsheet', 'category' => 'tools'],
        ];
        foreach ($list as $r) {
            Resource::create(array_merge($r, ['coach_id' => 1]));
        }
        $this->command?->info('Resources: ' . count($list));
    }
}
