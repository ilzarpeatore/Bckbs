<?php

/**
 * Busca posibles afirmaciones medicas/de salud en el texto libre de
 * recetas y dietas, para el rechazo de Apple Guideline 1.4.1 (Safety -
 * Physical Harm: "medical recommendations without citations").
 *
 * Requiere acceso real a la BD (produccion o una copia). Ejecutar con:
 *
 *   php artisan tinker --execute="require 'database/scripts/find_medical_claims.php';"
 *
 * o pegando el contenido dentro de `php artisan tinker`.
 *
 * Columnas revisadas (confirmadas contra los modelos/migraciones reales):
 *   - recipes.description       (texto libre, sin HTML purifier -> texto plano)
 *   - recipe_steps.instruction  (paso a paso de la receta, texto libre)
 *   - diets.description         (texto libre)
 *   - diets.ingredients         (texto libre, NO normalizado a una tabla)
 *
 * Nota: `recipes` no tiene columna `ingredients` propia -- sus ingredientes
 * viven en `recipe_ingredients` (cantidades/macros) + `ingredients.title`
 * (solo nombre del ingrediente), ninguna con texto libre tipo "bueno para...".
 * Por eso aqui no se busca ahi.
 */

$keywords = [
    // Afirmaciones de "recomendacion" explicita
    'ayuda a', 'ayuda al', 'ayuda contra', 'recomendado para', 'recomendable para',
    'ideal para', 'bueno para el', 'bueno para la', 'indicado para',
    // Enfermedades/condiciones medicas concretas
    'colesterol', 'diabetes', 'diabetico', 'diabetica', 'presion arterial',
    'hipertension', 'hipertenso', 'cancer', 'tension alta',
    // Verbos de efecto medico/curativo
    'reduce el', 'reduce la', 'previene', 'prevencion', 'cura', 'curativo',
    'combate', 'elimina toxinas', 'desintoxica', 'detox',
    // Frases de "salud general" habituales en marketing de nutricion
    'antiinflamatorio', 'anti-inflamatorio', 'fortalece el sistema inmune',
    'sistema inmunologico', 'quema grasa', 'acelera el metabolismo',
    'protege el corazon', 'salud cardiovascular', 'baja de peso', 'adelgaza',
];

function likeClauses($query, array $keywords, string $column)
{
    $query->where(function ($q) use ($keywords, $column) {
        foreach ($keywords as $k) {
            $q->orWhere($column, 'like', "%{$k}%");
        }
    });
}

// --- recipes.description ---
$recipes = \DB::table('recipes')
    ->where(function ($q) use ($keywords) {
        likeClauses($q, $keywords, 'description');
    })
    ->get(['id', 'title', 'description']);

echo "\n=== recipes.description: {$recipes->count()} coincidencias ===\n";
foreach ($recipes as $r) {
    echo "- [recipes#{$r->id}] {$r->title}\n";
    echo "  " . \Illuminate\Support\Str::limit(strip_tags((string) $r->description), 200) . "\n\n";
}

// --- recipe_steps.instruction (con el titulo de la receta para contexto) ---
$steps = \DB::table('recipe_steps')
    ->join('recipes', 'recipes.id', '=', 'recipe_steps.recipe_id')
    ->where(function ($q) use ($keywords) {
        likeClauses($q, $keywords, 'recipe_steps.instruction');
    })
    ->orderBy('recipe_steps.recipe_id')
    ->orderBy('recipe_steps.sequence')
    ->get(['recipe_steps.id', 'recipe_steps.recipe_id', 'recipe_steps.sequence', 'recipes.title', 'recipe_steps.instruction']);

echo "\n=== recipe_steps.instruction: {$steps->count()} coincidencias ===\n";
foreach ($steps as $s) {
    echo "- [recipes#{$s->recipe_id} step#{$s->sequence}] {$s->title}\n";
    echo "  " . \Illuminate\Support\Str::limit(strip_tags((string) $s->instruction), 200) . "\n\n";
}

// --- diets.description y diets.ingredients ---
foreach (['description', 'ingredients'] as $column) {
    $rows = \DB::table('diets')
        ->where(function ($q) use ($keywords, $column) {
            likeClauses($q, $keywords, $column);
        })
        ->get(['id', 'title', $column]);

    echo "\n=== diets.{$column}: {$rows->count()} coincidencias ===\n";
    foreach ($rows as $r) {
        echo "- [diets#{$r->id}] {$r->title}\n";
        echo "  " . \Illuminate\Support\Str::limit(strip_tags((string) $r->{$column}), 200) . "\n\n";
    }
}
