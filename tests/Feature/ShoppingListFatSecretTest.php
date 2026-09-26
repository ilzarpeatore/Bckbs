<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanRecipe;
use App\Models\FatSecretRecipeCache;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Role;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ítem 26 del roadmap: la lista de la compra omitía en silencio las comidas
 * asignadas desde FatSecret (sus ingredientes son texto libre, sin
 * ingredient_id local). Ahora entran como líneas de texto.
 */
class ShoppingListFatSecretTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private DailyPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('user', 'web');
        $this->client = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => 'u_'.uniqid(),
            'email' => uniqid().'@example.test', 'password' => bcrypt('p'),
            'user_type' => 'user', 'status' => 'active', 'login_type' => 'manual',
        ]);
        $this->client->assignRole('user');
        $this->plan = DailyPlan::create(['user_id' => $this->client->id, 'date' => '2026-09-27']);
        Sanctum::actingAs($this->client);
    }

    private function fatsecretRecipe(int $id, array $ingredients, float $servings = 2): void
    {
        FatSecretRecipeCache::create([
            'fatsecret_recipe_id' => $id, 'name' => "Receta $id", 'number_of_servings' => $servings,
            'ingredients' => $ingredients, 'fetched_at' => now(),
        ]);
    }

    private function meal(int $fatsecretId, string $type = 'lunch', bool $complete = false): DailyPlanRecipe
    {
        return DailyPlanRecipe::create([
            'daily_plan_id' => $this->plan->id, 'fatsecret_recipe_id' => $fatsecretId,
            'meal_type' => $type, 'is_complete' => $complete ? 1 : 0,
        ]);
    }

    private function generate(array $extra = [])
    {
        return $this->postJson('/api/daily-plan-shopping-list-generate', array_merge([
            'daily_plan_id' => $this->plan->id, 'is_complete_only' => false, 'title' => 'Compra',
        ], $extra));
    }

    private function items(): \Illuminate\Support\Collection
    {
        return ShoppingListItem::orderBy('custom_item_name')->get();
    }

    public function test_una_comida_de_fatsecret_ya_no_se_omite_y_sus_ingredientes_entran_como_texto(): void
    {
        $this->fatsecretRecipe(57, [
            ['description' => '1 tbsp wok peanut oil', 'number_of_units' => 1, 'measurement_description' => 'tablespoon'],
            ['description' => '6 tazas de brócoli picado', 'number_of_units' => 6, 'measurement_description' => 'taza'],
            ['description' => '1 1/4 tsps garlic, minced', 'number_of_units' => 1.25, 'measurement_description' => 'tsp'],
            ['description' => '4 boneless chicken breasts', 'number_of_units' => 4, 'measurement_description' => 'breast'],
        ], 2);
        $this->meal(57);

        $this->generate()->assertOk();

        $items = $this->items()->keyBy('custom_item_name');
        // Cantidad de la receta (2 raciones) / 2 = una ración.
        $this->assertEquals(3.0, $items['Brócoli picado']->display_quantity);
        $this->assertSame('tazas', $items['Brócoli picado']->unit_label);
        $this->assertEquals(0.63, $items['Garlic']->display_quantity); // 1.25 / 2
        $this->assertSame('tsps', $items['Garlic']->unit_label);
        $this->assertEquals(2.0, $items['Boneless chicken breasts']->display_quantity);
        $this->assertNull($items['Boneless chicken breasts']->unit_label);
        $this->assertNull($items['Boneless chicken breasts']->ingredient_id);
        $this->assertFalse((bool) $items['Garlic']->manually_added);
    }

    public function test_la_misma_linea_de_varias_comidas_se_suma_y_las_raciones_de_la_lista_multiplican(): void
    {
        $line = ['description' => '2 cups chopped onions', 'number_of_units' => 2, 'measurement_description' => 'cup'];
        $this->fatsecretRecipe(1, [$line], 1);
        $this->fatsecretRecipe(2, [['description' => '1 cup chopped onions', 'number_of_units' => 1, 'measurement_description' => 'cup']], 1);
        $this->meal(1, 'lunch');
        $this->meal(2, 'dinner');

        $this->generate(['servings' => 2])->assertOk();

        $onion = $this->items();
        $this->assertCount(1, $onion);
        $this->assertEquals(6.0, $onion[0]->display_quantity); // (2 + 1) * 2 raciones
    }

    public function test_convive_con_las_recetas_locales_en_la_misma_lista(): void
    {
        $gram = MeasurementUnit::create(['title' => 'Gram', 'symbol' => 'g', 'unit_type' => 'weight', 'base_conversion_factor' => 1]);
        $rice = Ingredient::create(['title' => 'Arroz']);
        $recipe = Recipe::create(['title' => 'Arroz blanco', 'status' => 'active']);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'ingredient_id' => $rice->id, 'measurement_unit_id' => $gram->id, 'quantity' => 200, 'quantity_grams' => 200]);
        DailyPlanRecipe::create(['daily_plan_id' => $this->plan->id, 'recipe_id' => $recipe->id, 'meal_type' => 'lunch', 'is_complete' => 0]);
        $this->fatsecretRecipe(7, [['description' => '3 huevos', 'number_of_units' => 3, 'measurement_description' => 'huevo']], 1);
        $this->meal(7, 'breakfast');

        $this->generate()->assertOk();

        $items = $this->items();
        $this->assertCount(2, $items);
        $this->assertTrue($items->contains(fn ($i) => $i->ingredient_id === $rice->id && (float) $i->display_quantity === 200.0));
        $this->assertTrue($items->contains(fn ($i) => $i->custom_item_name === 'Huevos' && (float) $i->display_quantity === 3.0));
    }

    public function test_una_receta_sin_detalle_en_cache_se_omite_sin_romper_la_lista(): void
    {
        $this->fatsecretRecipe(10, [['description' => '2 cups rice', 'number_of_units' => 2, 'measurement_description' => 'cup']], 1);
        $this->meal(10, 'lunch');
        $this->meal(999, 'dinner'); // nunca cacheada

        $this->generate()->assertOk();

        $this->assertCount(1, $this->items());
    }

    public function test_al_regenerar_se_conserva_lo_marcado_como_comprado(): void
    {
        $this->fatsecretRecipe(3, [
            ['description' => '2 cups rice', 'number_of_units' => 2, 'measurement_description' => 'cup'],
            ['description' => '1 tbsp salt', 'number_of_units' => 1, 'measurement_description' => 'tbsp'],
        ], 1);
        $this->meal(3);
        $listId = $this->generate()->assertOk()->json('data.id');

        $rice = ShoppingListItem::where('custom_item_name', 'Rice')->firstOrFail();
        $this->postJson('/api/shopping-list-item-toggle', ['item_id' => $rice->id, 'is_checked' => true])->assertOk();

        $this->generate(['shopping_list_id' => $listId])->assertOk();

        $this->assertCount(2, $this->items());
        $this->assertTrue((bool) ShoppingListItem::where('custom_item_name', 'Rice')->first()->is_checked);
        $this->assertFalse((bool) ShoppingListItem::where('custom_item_name', 'Salt')->first()->is_checked);
    }

    public function test_el_detalle_muestra_la_unidad_de_texto_y_permite_renombrar_una_linea_de_fatsecret(): void
    {
        $this->fatsecretRecipe(4, [['description' => '2 cups rice', 'number_of_units' => 2, 'measurement_description' => 'cup']], 1);
        $this->meal(4);
        $listId = $this->generate()->assertOk()->json('data.id');
        $item = ShoppingListItem::firstOrFail();

        $detail = $this->getJson("/api/shopping-list-detail?id=$listId")->assertOk();
        $this->assertSame('cups', $detail->json('data.items.0.display_unit_symbol'));
        $this->assertSame('cups', $detail->json('data.items.0.unit_label'));

        $this->postJson('/api/shopping-list-item-update', ['item_id' => $item->id, 'custom_item_name' => 'Arroz basmati'])->assertOk();
        $this->assertSame('Arroz basmati', $item->fresh()->custom_item_name);
    }

    public function test_sin_comidas_sigue_dando_el_error_de_antes(): void
    {
        $this->generate()->assertStatus(422);
    }

    public function test_al_editar_las_fechas_de_una_lista_de_un_dia_el_rango_nuevo_manda(): void
    {
        $this->fatsecretRecipe(1, [['description' => '2 cups rice', 'number_of_units' => 2, 'measurement_description' => 'cup']], 1);
        $this->meal(1);
        $listId = $this->generate(['daily_plan_id' => null, 'start_date' => '2026-09-27', 'end_date' => '2026-09-27'])->assertOk()->json('data.id');
        $this->assertNotNull(\App\Models\ShoppingList::find($listId)->daily_plan_id); // se queda con el plan de ese día

        $otherPlan = DailyPlan::create(['user_id' => $this->client->id, 'date' => '2026-09-28']);
        $this->fatsecretRecipe(2, [['description' => '3 eggs', 'number_of_units' => 3, 'measurement_description' => 'egg']], 1);
        DailyPlanRecipe::create(['daily_plan_id' => $otherPlan->id, 'fatsecret_recipe_id' => 2, 'meal_type' => 'dinner', 'is_complete' => 0]);

        $this->generate([
            'shopping_list_id' => $listId, 'daily_plan_id' => null, 'start_date' => '2026-09-27', 'end_date' => '2026-09-28',
        ])->assertOk();

        $names = $this->items()->pluck('custom_item_name')->all();
        $this->assertContains('Rice', $names);
        $this->assertContains('Eggs', $names); // antes se ignoraba: mandaba el daily_plan_id viejo
    }
}
