<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTag;
use App\Models\RecipeCategoryMapping;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class AutoCategorizeRecipes extends Command
{
    protected $signature = 'recipes:categorize';
    protected $description = 'Auto-tag and categorize all recipes based on keyword matching';

    // Categorías: tipo de comida
    private array $mealCategories = [
        '🍳 Desayuno' => ['breakfast', 'desayuno', 'tostada', 'cereal', 'huevo', 'tortilla', 'panqueque', 'crepe', 'porridge', 'avena', 'muffin', 'magdalena', 'bizcocho', 'churro', 'croissant'],
        '🍽️ Comida' => ['almuerzo', 'comida', 'pollo', 'carne', 'pescado', 'pasta', 'arroz', 'guiso', 'estofado', 'asado', 'lomo', 'filete', 'chuleta', 'hamburguesa', 'lasaña', 'paella', 'cazuela'],
        '🌙 Cena' => ['cena', 'ligero', 'sopa', 'crema', 'ensalada', 'verduras', 'salteado', 'puré', 'tortilla', 'revuelto', 'pescado', 'caldo', 'gazpacho', 'crema'],
        '🍎 Snack' => ['snack', 'aperitivo', 'tapa', 'pincho', 'canapé', 'montadito', 'bocadillo', 'sándwich', 'picoteo', 'fruta', 'yogur', 'batido', 'barrita', 'hummus', 'guacamole', 'paté'],
    ];

    // Tags: tipo de dieta / restricción
    private array $dietaryTags = [
        '💪 Alta en proteínas' => ['pollo', 'pavo', 'ternera', 'huevo', 'clara', 'atún', 'salmón', 'proteína', 'tofu', 'lentejas', 'garbanzos', 'alubias', 'queso', 'yogur', 'batido proteico', 'pechuga', 'lomo', 'filete', 'carne', 'pescado', 'marisco', 'gambas', 'pulpo', 'bacalao', 'merluza', 'bonito', 'sardina'],
        '🔥 Baja en calorías' => ['light', 'light', 'bajo en calorías', 'diet', 'dietético', 'integral', 'verduras', 'hortalizas', 'ensalada', 'pepino', 'calabacín', 'espinacas', 'acelgas', 'brócoli', 'coliflor', 'apio', 'pimiento', 'tomate', 'champiñón', 'setas'],
        '🍗 Ganancia muscular' => ['proteína', 'muscular', 'volumen', 'ganancia', 'fuerza', 'huevo', 'pollo', 'ternera', 'batido', 'avena', 'arroz', 'pasta', 'boniato', 'patata', 'quinoa', 'legumbres', 'frutos secos', 'mantequilla cacahuete', 'leche', 'requesón'],
        '⚖️ Pérdida de grasa' => ['dieta', 'definición', 'adelgazar', 'quemar grasa', 'light', 'bajo en grasa', 'integral', 'verdura', 'ensalada', 'pescado blanco', 'pechuga', 'clara', 'pavo', 'fruta', 'té verde', 'infusión'],
        '🥗 Vegana' => ['vegano', 'vegana', 'tofu', 'tempeh', 'seitán', 'leche vegetal', 'bebida vegetal', 'soja', 'levadura nutricional', 'aquafaba'],
        '🥬 Vegetariana' => ['vegetariano', 'vegetariana', 'huevo', 'queso', 'yogur', 'leche', 'mantequilla', 'nata', 'verduras', 'hortalizas', 'legumbres', 'setas', 'champiñones'],
        '☪️ Halal' => ['halal', 'cordero', 'ternera halal', 'pollo halal'],
        '🌾 Sin gluten' => ['sin gluten', 'celiaco', 'celíaco', 'maicena', 'harina de arroz', 'harina de almendra', 'harina de coco', 'pan sin gluten'],
        '🥛 Sin lactosa' => ['sin lactosa', 'lactosa', 'bebida vegetal', 'leche de almendras', 'leche de soja', 'leche de avena', 'leche de coco', 'queso vegano'],
    ];

    // Tags: método / tiempo
    private array $methodTags = [
        '⏱️ Menos de 15 min' => [], // handled specially
        '📦 Meal Prep' => ['meal prep', 'batch cooking', 'preparar con antelación', 'congelar', 'táper', 'tupper'],
        '💸 Económica' => ['económico', 'económica', 'barato', 'barata', 'pobre', 'humilde', 'aprovechamiento', 'sobras', 'receta de la abuela', 'cocina de aprovechamiento'],
        '🍟 Air Fryer' => ['air fryer', 'airfryer', 'freidora de aire', 'freidora sin aceite'],
    ];

    public function handle(): int
    {
        $this->ensureCategories();
        $this->ensureTags();

        $total = Recipe::count();
        $this->info("Processing {$total} recipes...");

        $batchSize = 500;
        $processed = 0;

        Recipe::chunk($batchSize, function ($recipes) use (&$processed, $total) {
            foreach ($recipes as $recipe) {
                $text = strtolower($recipe->title . ' ' . ($recipe->description ?? ''));
                $categoryIds = $this->matchCategories($text);
                $tagIds = $this->matchTags($text, $recipe);

                RecipeCategoryMapping::where('recipe_id', $recipe->id)->delete();
                foreach ($categoryIds as $catId) {
                    RecipeCategoryMapping::create([
                        'recipe_id' => $recipe->id,
                        'recipe_category_id' => $catId,
                    ]);
                }

                RecipeTagMapping::where('recipe_id', $recipe->id)->delete();
                foreach ($tagIds as $tagId) {
                    RecipeTagMapping::create([
                        'recipe_id' => $recipe->id,
                        'recipe_tag_id' => $tagId,
                    ]);
                }
            }
            $processed += count($recipes);
            $this->info("Progress: {$processed}/{$total}");
        });

        $this->info("Done. Categories: " . RecipeCategoryMapping::count() . ", Tags: " . RecipeTagMapping::count());
        return 0;
    }

    private function ensureCategories(): void
    {
        foreach ($this->mealCategories as $name => $keywords) {
            RecipeCategory::firstOrCreate(['title' => $name], ['status' => 1]);
        }
        $this->info("Categories ensured: " . RecipeCategory::count());
    }

    private function ensureTags(): void
    {
        $allTags = array_merge(
            array_keys($this->dietaryTags),
            array_keys($this->methodTags),
        );
        foreach ($allTags as $name) {
            RecipeTag::firstOrCreate(['title' => $name], ['status' => 1]);
        }
        $this->info("Tags ensured: " . RecipeTag::count());
    }

    private function matchCategories(string $text): array
    {
        $ids = [];
        foreach ($this->mealCategories as $name => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    $cat = RecipeCategory::where('title', $name)->first();
                    if ($cat) {
                        $ids[$cat->id] = true;
                        break;
                    }
                }
            }
        }
        return array_keys($ids);
    }

    private function matchTags(string $text, Recipe $recipe): array
    {
        $ids = [];

        // Dietary tags
        foreach ($this->dietaryTags as $name => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    $tag = RecipeTag::where('title', $name)->first();
                    if ($tag) {
                        $ids[$tag->id] = true;
                        break;
                    }
                }
            }
        }

        // Method tags
        foreach ($this->methodTags as $name => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    $tag = RecipeTag::where('title', $name)->first();
                    if ($tag) {
                        $ids[$tag->id] = true;
                        break;
                    }
                }
            }
        }

        // ⏱️ Menos de 15 min — basado en el campo preparation_time
        if ($recipe->preparation_time && $recipe->preparation_time <= 15) {
            $tag = RecipeTag::where('title', '⏱️ Menos de 15 min')->first();
            if ($tag) $ids[$tag->id] = true;
        }

        return array_keys($ids);
    }
}
