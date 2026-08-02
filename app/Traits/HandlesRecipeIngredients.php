<?php

namespace App\Traits;

use App\Models\Ingredient;
use App\Models\IngredientUnitConversion;
use App\Models\RecipeIngredient;
use App\Models\MeasurementUnit;

trait HandlesRecipeIngredients
{
    public function saveRecipeIngredient($recipe, $data)
    {        
        $totalCalories = $totalProtein = $totalFats = $totalCarbs = 0;
        $savedIds = [];

        if (!empty($data)) {

            foreach ($data as $row) {
                $computed = $this->computeIngredientValues($row);
                if (!$computed) {
                    continue;
                }
                $ingredient = $computed['ingredient'];
                $grams = $computed['quantity_grams'];
                $calories = $computed['calories'];
                $protein = $computed['protein'];
                $fats = $computed['fats'];
                $carbs = $computed['carbs'];

                // Save ingredient
                $saved = RecipeIngredient::updateOrCreate(
                    [
                        'id' => $row['recipe_ingredient_id'] ?? null,
                    ],
                    [
                        'recipe_id'           => $recipe->id,
                        'ingredient_id'       => $ingredient->id,
                        'measurement_unit_id' => $computed['measurement_unit_id'],
                        'quantity'            => $computed['quantity'],
                        'quantity_grams'      => $computed['quantity_grams'],
                        'amount'              => $computed['amount'],
                        'calories'            => $calories,
                        'protein'             => $protein,
                        'fats'                => $fats,
                        'carbs'               => $carbs,
                    ]
                );

                $savedIds[] = $saved->id;

                // Totals
                $totalCalories += $calories;
                $totalProtein  += $protein;
                $totalFats     += $fats;
                $totalCarbs    += $carbs;
            }
        }

        RecipeIngredient::where('recipe_id',$recipe->id)->whereNotIn('id',$savedIds)->delete();

        // Update recipe totals
        $recipe->update([
            'calories' => $totalCalories,
            'protein'  => $totalProtein,
            'fats'     => $totalFats,
            'carbs'    => $totalCarbs,
        ]);

    }
    
    protected function computeIngredientValues(array $row): ?array
    {
        if (empty($row['ingredient_id'])) {
            return null;
        }

        $ingredient = Ingredient::find($row['ingredient_id']);
        if (!$ingredient) {
            return null;
        }

        $unit = null;
        if (!empty($row['measurement_unit_id'])) {
            $unit = MeasurementUnit::find($row['measurement_unit_id']);
        }

        $gramEquivalent = null;
        $unitType = $unit ? $unit->unit_type : null;
        $gramEquivalentIsFromConversion = false;

        if ($unit) {
            $conversion = IngredientUnitConversion::where([
                'ingredient_id' => $ingredient->id,
                'measurement_unit_id' => $row['measurement_unit_id'],
            ])->first();

            if ($conversion) {
                $gramEquivalent = $conversion->gram_equivalent;
                $gramEquivalentIsFromConversion = true;
            } else {
                $gramEquivalent = $unit->base_conversion_factor;
                $gramEquivalentIsFromConversion = false;
            }
        }

        // Amount is readonly in all cases; use configured conversion/base value.
        $unitGrams = $gramEquivalent ?? ($row['amount'] ?? 0);
        $quantity = (float) ($row['quantity'] ?? 1);
        $inputGrams = (float) ($row['quantity_grams'] ?? 0);

        if ($gramEquivalentIsFromConversion) {
            // Ingredient-specific conversions are already in grams per unit.
            $grams = $quantity * $unitGrams;
        } else {
            // If no conversion, allow manual grams input (not readonly).
            if ($inputGrams > 0) {
                $grams = $inputGrams;
            } else {
                $grams = $quantity * $unitGrams;
                if ($unitType === 'volume') {
                    $grams *= ($ingredient->density ?? 1.0);
                }
            }
        }

        $calories = $grams * $ingredient->calories_per_gram;
        $protein  = $grams * $ingredient->protein_per_gram;
        $fats     = $grams * $ingredient->fat_per_gram;
        $carbs    = $grams * $ingredient->carbs_per_gram;

        return [
            'ingredient' => $ingredient,
            'measurement_unit_id' => $row['measurement_unit_id'] ?? null,
            'quantity' => $row['quantity'] ?? 1,
            'quantity_grams' => $grams,
            'amount' => $unitGrams,
            'calories' => $calories,
            'protein' => $protein,
            'fats' => $fats,
            'carbs' => $carbs,
        ];
    }
}
