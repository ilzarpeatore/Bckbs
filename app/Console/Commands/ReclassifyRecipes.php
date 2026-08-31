<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTag;
use App\Models\RecipeCategoryMapping;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class ReclassifyRecipes extends Command
{
    protected $signature = 'recipes:reclassify';
    protected $description = 'Precise recipe classification based on ingredient analysis and rules';

    // ─── PROHIBITED INGREDIENTS ───
    private const PORK = ['cerdo', 'jabalí', 'jamon', 'jamón', 'chorizo', 'panceta', 'bacon', 'tocino', 'lomo embuchado', 'salchicha', 'salchichon', 'salchichón', 'mortadela', 'fuet', 'sobrasada', 'gelatina'];
    private const MEAT = ['pollo', 'pavo', 'ternera', 'buey', 'bue', 'cordero', 'conejo', 'carne', 'filete', 'chuleta', 'lomo', 'solomillo', 'entrecot', 'entrecôte', 'hamburguesa', 'albondiga', 'albóndiga', 'costilla', 'pata', 'rabo', 'callos', 'sesos', 'hígado', 'higado', 'riñon', 'riñón', 'molleja', 'lengua', 'manitas'];
    private const SEAFOOD = ['pescado', 'marisco', 'atun', 'atún', 'salmón', 'salmon', 'merluza', 'bacalao', 'sardina', 'boqueron', 'boquerón', 'anchoa', 'gamba', 'langostino', 'camaron', 'camarón', 'pulpo', 'calamar', 'sepia', 'mejillon', 'mejillón', 'almeja', 'berberecho', 'navaja', 'percebe', 'bogavante', 'langosta', 'centollo', 'nécora', 'cangrejo', 'chipiron', 'chipirón', 'rape', 'rodaballo', 'lenguado', 'dorada', 'lubina', 'trucha', 'bonito', 'caballa', 'jurel', 'congrio', 'pez', 'ventresca', 'mojama', 'huevas', 'surimi'];
    private const EGGS = ['huevo', 'clara de huevo', 'yema', 'huevo duro', 'huevo cocido', 'tortilla'];
    private const DAIRY = ['leche', 'nata', 'queso', 'yogur', 'yogourt', 'mantequilla', 'mantequilla', 'requeson', 'requesón', 'ricotta', 'mascarpone', 'mozzarella', 'parmesano', 'cheddar', 'gouda', 'emmental', 'brie', 'camembert', 'feta', 'burrata', 'crema de leche', 'crema fresca', 'cuajada', 'kefir', 'kéfir'];
    private const HONEY = ['miel'];
    private const ALCOHOL = ['vino', 'cerveza', 'licor', 'whisky', 'whiskey', 'ron', 'vodka', 'ginebra', 'brandy', 'coñac', 'coñá', 'cava', 'champán', 'champagne', 'sidra', 'vermouth', 'vermut', 'anis', 'anís', 'aguardiente', 'pacharan', 'pacharán', 'orujo', 'jerez', 'oporto', 'marsala', 'amontillado', 'tequila', 'mezcal', 'sake'];
    private const GLUTEN = ['trigo', 'harina de trigo', 'harina', 'cebada', 'centeno', 'avena', 'pan', 'pasta', 'macarrones', 'espagueti', 'fideo', 'couscous', 'cuscús', 'bulgur', 'semola', 'sémola', 'espelta', 'kamut', 'triticale', 'galleta', 'bizcocho', 'magdalena', 'croissant', 'hojaldre', 'pizza', 'empanada', 'lasaña', 'canelón', 'ravioli', 'tortellini', 'seitan', 'seitán', 'cerveza'];

    // ─── POSITIVE INDICATORS ───
    private const PLANT_PROTEIN = ['tofu', 'tempeh', 'seitan', 'seitán', 'soja', 'edamame', 'lenteja', 'garbanzo', 'alubia', 'judia', 'judía', 'frijol', 'haba', 'guisante', 'altramuz', 'quinoa', 'amaranto', 'hemp', 'cañamo', 'cáñamo', 'levadura nutricional'];
    private const COMPLEX_CARBS = ['arroz', 'pasta', 'avena', 'patata', 'boniato', 'batata', 'legumbre', 'lenteja', 'garbanzo', 'alubia', 'quinoa', 'pan integral', 'pan', 'cereal'];
    private const VEGGIES = ['verdura', 'hortaliza', 'ensalada', 'espinaca', 'acelga', 'brocoli', 'brócoli', 'coliflor', 'calabacin', 'calabacín', 'berenjena', 'pimiento', 'tomate', 'pepino', 'apio', 'zanahoria', 'cebolla', 'puerro', 'champiñon', 'champiñón', 'seta', 'esparrago', 'espárrago', 'alcachofa', 'judía verde', 'remolacha', 'nabo', 'rábano', 'col', 'repollo', 'lombarda', 'cardo', 'borraja', 'endibia', 'escarola', 'canónigo', 'rúcula', 'berro'];

    // ─── COUNTRIES ───
    private const COUNTRIES = [
        '🇪🇸 España' => ['tortilla española', 'tortilla de patata', 'paella', 'gazpacho', 'salmorejo', 'croqueta', 'pulpo a la gallega', 'fabada', 'cocido', 'patatas bravas', 'patatas a la riojana', 'bacalao al pil pil', 'marmitako', 'migas', 'pisto', 'fideuà', 'arroz negro', 'torrija', 'crema catalana', 'churro', 'porras', 'ensaimada', 'papas arrugadas', 'mojo picón', 'polvorón', 'turrón', 'buñuelo'],
        '🇨🇴 Colombia' => ['arepa', 'bandeja paisa', 'ajiaco', 'sancocho', 'lechona', 'patacón', 'patacon', 'arepa de huevo', 'pandebono', 'almojábana', 'arequipe', 'natilla colombiana', 'fritanga', 'sobrebarriga', 'sudado', 'changua', 'calentao'],
        '🇲🇽 México' => ['taco', 'enchilada', 'mole', 'pozole', 'chilaquile', 'guacamole', 'quesadilla', 'burrito', 'fajita', 'nachos', 'tinga', 'cochinita pibil', 'carnita', 'barbacoa', 'birria', 'menudo', 'chiles rellenos', 'tlayuda', 'sope', 'gordita', 'chalupa', 'flauta', 'chimichanga', 'frijoles charros', 'frijoles refritos', 'nopal', 'horchata', 'tamarindo', 'margarita', 'michelada'],
        '🇦🇷 Argentina' => ['asado argentino', 'chimichurri', 'milanesa', 'locro', 'humita', 'provoleta', 'choripán', 'morcilla', 'chinchulín', 'molleja', 'matambre', 'dulce de leche', 'alfajor', 'factura', 'medialuna', 'ñoqui', 'sorrentino', 'vitel toné', 'fugazza', 'fainá', 'yerba mate', 'tereré'],
        '🇨🇱 Chile' => ['completo', 'chacarero', 'chorillana', 'pastel de choclo', 'cazuela', 'charquicán', 'porotos', 'curanto', 'machas a la parmesana', 'paila marina', 'caldillo de congrio', 'pebre', 'sopaipilla', 'mote con huesillo', 'brazo de reina', 'terremoto chile', 'piscola', 'cola de mono'],
        '🇵🇪 Perú' => ['ceviche', 'lomo saltado', 'ají de gallina', 'causa limeña', 'arroz chaufa', 'papa a la huancaina', 'anticucho', 'picarone', 'suspiro limeño', 'mazamorra morada', 'tacu tacu', 'rocoto relleno', 'pollo a la brasa', 'tiradito', 'pisco sour', 'chicha morada', 'lúcuma', 'chirimoya', 'seco de cordero', 'arroz con pato', 'carapulcra', 'pachamanca', 'juane', 'olluquito', 'quinua', 'quinoa', 'camote', 'choclo', 'cancha', 'chifle', 'manjar blanco', 'turrón de doña pepa', 'picarones', 'arroz tapado', 'parihuela', 'escabeche', 'adobo arequipeño', 'chupe de camarones', 'aguadito', 'arroz con mariscos', 'jalea', 'tallarines verdes', 'ocopa', 'solterito', 'inchicapi', 'patarashca', 'tacacho', 'humita peruana', 'alfajor de maicena', 'king kong', 'champus', 'emoliente', 'inca kola'],
    ];

    public function handle(): int
    {
        $this->ensureCategoriesAndTags();

        $total = Recipe::count();
        $processed = 0;

        Recipe::chunk(500, function ($recipes) use (&$processed, $total) {
            foreach ($recipes as $recipe) {
                $title = strtolower($recipe->title);
                $desc = strtolower($recipe->description ?? '');
                $text = $title . ' ' . $desc;
                $prepTime = (int) $recipe->preparation_time;
                $calories = (float) $recipe->calories;
                $protein = (float) $recipe->protein;
                $fats = (float) $recipe->fats;
                $carbs = (float) $recipe->carbs;
                $hasNutrition = $calories > 0 && $protein > 0;

                // Delete existing non-country mappings (preserve country tags from import)
                RecipeCategoryMapping::where('recipe_id', $recipe->id)->delete();
                $countryTagIds = RecipeTag::where('title', 'like', '🇪%')->orWhere('title', 'like', '🇨%')
                    ->orWhere('title', 'like', '🇲%')->orWhere('title', 'like', '🇦%')
                    ->orWhere('title', 'like', '🇵%')->orWhere('title', 'like', '🥙%')
                    ->orWhere('title', 'like', '🇧%')->orWhere('title', 'like', '🇩%')
                    ->orWhere('title', 'like', '🇫%')->orWhere('title', 'like', '🇬%')
                    ->orWhere('title', 'like', '🇭%')->orWhere('title', 'like', '🇮%')
                    ->orWhere('title', 'like', '🇯%')->orWhere('title', 'like', '🇳%')
                    ->orWhere('title', 'like', '🇷%')->orWhere('title', 'like', '🇸%')
                    ->orWhere('title', 'like', '🇹%')->orWhere('title', 'like', '🇺%')
                    ->pluck('id')->toArray();
                $existingCountryTags = RecipeTagMapping::where('recipe_id', $recipe->id)
                    ->whereIn('recipe_tag_id', $countryTagIds)
                    ->pluck('recipe_tag_id')->toArray();
                RecipeTagMapping::where('recipe_id', $recipe->id)
                    ->whereNotIn('recipe_tag_id', $countryTagIds)
                    ->delete();

                // ─── 1. DIETARY TAGS ───
                $tagIds = $existingCountryTags;

                if ($this->isHalal($text)) $tagIds = array_merge($tagIds, $this->getTagIds('☪️ Halal'));
                if ($this->isVegetarian($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🥬 Vegetariana'));
                if ($this->isVegan($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🥗 Vegana'));

                // ─── 2. NUTRITIONAL TAGS (only if data exists) ───
                if ($hasNutrition) {
                    $pctProtein = $calories > 0 ? ($protein * 4 / $calories) * 100 : 0;
                    if ($protein >= 20 || $pctProtein >= 20) $tagIds = array_merge($tagIds, $this->getTagIds('💪 Alta en proteínas'));
                    if ($calories <= 400) $tagIds = array_merge($tagIds, $this->getTagIds('🔥 Baja en calorías'));
                    if ($protein >= 20 && $this->hasComplexCarbs($text) && $calories >= 400 && $calories <= 900)
                        $tagIds = array_merge($tagIds, $this->getTagIds('🍗 Ganancia muscular'));
                    if ($protein >= 20 && $calories <= 400 && $this->hasVeggies($text))
                        $tagIds = array_merge($tagIds, $this->getTagIds('⚖️ Pérdida de grasa'));
                } else {
                    // Keyword-based fallback for nutritional tags
                    if ($this->isHighProtein($text)) $tagIds = array_merge($tagIds, $this->getTagIds('💪 Alta en proteínas'));
                    if ($this->hasComplexCarbs($text) && $this->isHighProtein($text))
                        $tagIds = array_merge($tagIds, $this->getTagIds('🍗 Ganancia muscular'));
                }

                // ─── 3. MEAL TYPE CATEGORY ───
                $catIds = $this->classifyMealType($title, $desc, $text);

                // ─── 4. TIME TAGS ───
                if ($prepTime > 0) {
                    if ($prepTime <= 15) $tagIds = array_merge($tagIds, $this->getTagIds('⏱️ Menos de 15 min'));
                    elseif ($prepTime <= 30) $tagIds = array_merge($tagIds, $this->getTagIds('⏱️ 15-30 min'));
                    else $tagIds = array_merge($tagIds, $this->getTagIds('⏱️ Más de 30 min'));
                }

                // ─── 5. RESTRICTIONS ───
                if ($this->isGlutenFree($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🌾 Sin gluten'));
                if ($this->isLactoseFree($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🥛 Sin lactosa'));

                // ─── 6. PREPARATION ───
                if ($this->isMealPrep($text)) $tagIds = array_merge($tagIds, $this->getTagIds('📦 Meal Prep'));
                if ($this->isAirFryer($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🍟 Air Fryer'));

                // ─── 7. EXTRAS ───
                if ($this->isEconomical($text)) $tagIds = array_merge($tagIds, $this->getTagIds('💸 Económica'));
                if ($this->isFiveIngredients($text)) $tagIds = array_merge($tagIds, $this->getTagIds('5️⃣ Solo 5 ingredientes'));
                if ($this->isNoCook($text)) $tagIds = array_merge($tagIds, $this->getTagIds('🧊 Sin cocinar'));

                // ─── 8. COUNTRY ───
                $countryTags = $this->classifyCountry($text);
                $tagIds = array_merge($tagIds, $countryTags);

                // Save
                foreach (array_unique($catIds) as $cid) {
                    RecipeCategoryMapping::create(['recipe_id' => $recipe->id, 'recipe_category_id' => $cid]);
                }
                foreach (array_unique($tagIds) as $tid) {
                    RecipeTagMapping::create(['recipe_id' => $recipe->id, 'recipe_tag_id' => $tid]);
                }
            }
            $processed += count($recipes);
            $this->info("Progress: {$processed}/{$total}");
        });

        $this->info("Done.");
        $this->showStats();
        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // CLASSIFICATION METHODS
    // ═══════════════════════════════════════════════════════════════

    private function isHalal(string $text): bool
    {
        foreach (self::PORK as $item) { if (stripos($text, $item) !== false) return false; }
        foreach (self::ALCOHOL as $item) { if (stripos($text, $item) !== false) return false; }
        return true;
    }

    private function isVegetarian(string $text): bool
    {
        if ($this->containsMeat($text)) return false;
        if ($this->containsSeafood($text)) return false;
        return true;
    }

    private function isVegan(string $text): bool
    {
        if ($this->containsMeat($text)) return false;
        if ($this->containsSeafood($text)) return false;
        if ($this->containsEggs($text)) return false;
        if ($this->containsDairy($text)) return false;
        if ($this->containsHoney($text)) return false;
        // Must have at least one plant-based indicator
        foreach (self::PLANT_PROTEIN as $item) { if (stripos($text, $item) !== false) return true; }
        foreach (self::VEGGIES as $item) { if (stripos($text, $item) !== false) return true; }
        return stripos($text, 'vegano') !== false || stripos($text, 'vegana') !== false;
    }

    private function isHighProtein(string $text): bool
    {
        foreach (self::MEAT as $item) { if (stripos($text, $item) !== false) return true; }
        foreach (self::SEAFOOD as $item) { if (stripos($text, $item) !== false) return true; }
        foreach (self::EGGS as $item) { if (stripos($text, $item) !== false) return true; }
        foreach (self::PLANT_PROTEIN as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function hasComplexCarbs(string $text): bool
    {
        foreach (self::COMPLEX_CARBS as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function hasVeggies(string $text): bool
    {
        foreach (self::VEGGIES as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function isGlutenFree(string $text): bool
    {
        // Check for explicit gluten-free indicators first
        $isExplicit = stripos($text, 'sin gluten') !== false 
            || stripos($text, 'celiaco') !== false 
            || stripos($text, 'celíaco') !== false;

        // Check for gluten-containing items
        foreach (self::GLUTEN as $item) { 
            if (stripos($text, $item) !== false) {
                // Exception: "sin gluten", "harina sin gluten", "pan sin gluten", "avena sin gluten"
                if ($isExplicit && (stripos($text, "sin gluten $item") !== false || stripos($text, "$item sin gluten") !== false)) {
                    continue;
                }
                // Exception: "harina de arroz", "harina de almendra" etc are gluten-free flours
                if ($item === 'harina' || $item === 'harina de trigo') {
                    if (stripos($text, 'harina de arroz') !== false) continue;
                    if (stripos($text, 'harina de almendra') !== false) continue;
                    if (stripos($text, 'harina de coco') !== false) continue;
                    if (stripos($text, 'harina de garbanzo') !== false) continue;
                    if (stripos($text, 'harina de maíz') !== false) continue;
                    if (stripos($text, 'maicena') !== false) continue;
                }
                return false;
            }
        }
        return $isExplicit;
    }

    private function isLactoseFree(string $text): bool
    {
        // Check for explicit lactose-free indicators
        $isExplicit = stripos($text, 'sin lactosa') !== false
            || stripos($text, 'bebida vegetal') !== false
            || stripos($text, 'leche de almendra') !== false
            || stripos($text, 'leche de soja') !== false
            || stripos($text, 'leche de avena') !== false
            || stripos($text, 'leche de coco') !== false;

        // Check for dairy items (unless marked sin lactosa)
        foreach (self::DAIRY as $item) { 
            if (stripos($text, $item) !== false) {
                if ($isExplicit && stripos($text, "sin lactosa") !== false) continue;
                return false;
            }
        }
        return $isExplicit;
    }

    private function isMealPrep(string $text): bool
    {
        return stripos($text, 'meal prep') !== false
            || stripos($text, 'congelar') !== false
            || stripos($text, 'conserva') !== false && stripos($text, 'días') !== false
            || stripos($text, 'táper') !== false
            || stripos($text, 'tupper') !== false
            || stripos($text, 'batch cooking') !== false;
    }

    private function isAirFryer(string $text): bool
    {
        return stripos($text, 'air fryer') !== false
            || stripos($text, 'airfryer') !== false
            || stripos($text, 'freidora de aire') !== false
            || stripos($text, 'freidora sin aceite') !== false;
    }

    private function isEconomical(string $text): bool
    {
        return stripos($text, 'económico') !== false
            || stripos($text, 'económica') !== false
            || stripos($text, 'barato') !== false
            || stripos($text, 'barata') !== false
            || stripos($text, 'aprovechamiento') !== false
            || stripos($text, 'sobras') !== false;
    }

    private function isFiveIngredients(string $text): bool
    {
        return stripos($text, '5 ingredientes') !== false
            || stripos($text, 'cinco ingredientes') !== false
            || stripos($text, 'pocos ingredientes') !== false
            || stripos($text, '3 ingredientes') !== false;
    }

    private function isNoCook(string $text): bool
    {
        return stripos($text, 'sin cocinar') !== false
            || stripos($text, 'sin cocción') !== false
            || stripos($text, 'sin cocer') !== false
            || stripos($text, 'en crudo') !== false
            || (stripos($text, 'no necesita cocción') !== false)
            || (stripos($text, 'ensalada') !== false && !$this->containsCookingMethods($text));
    }

    private function containsCookingMethods(string $text): bool
    {
        $methods = ['cocer', 'cocinar', 'hervir', 'freír', 'freir', 'asar', 'hornear', 'saltear', 'sofreír', 'sofreir', 'plancha', 'grill', 'vapor', 'guisar', 'estofar', 'rehogar', 'dorar', 'poch'];
        foreach ($methods as $m) { if (stripos($text, $m) !== false) return true; }
        return false;
    }

    private function classifyCountry(string $text): array
    {
        $ids = [];
        foreach (self::COUNTRIES as $country => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) {
                    $ids = array_merge($ids, $this->getTagIds($country));
                    break;
                }
            }
        }
        return $ids;
    }

    private function classifyMealType(string $title, string $desc, string $text): array
    {
        $ids = [];
        $isDessert = stripos($title, 'postre') !== false || stripos($title, 'tarta') !== false 
            || stripos($title, 'flan') !== false || stripos($title, 'helado') !== false
            || stripos($title, 'mousse') !== false || stripos($title, 'natilla') !== false
            || stripos($title, 'bizcocho') !== false || stripos($title, 'magdalena') !== false
            || stripos($title, 'galleta') !== false || stripos($title, 'dulce') !== false
            || stripos($title, 'brownie') !== false || stripos($title, 'crepe') !== false
            || stripos($title, 'torrija') !== false || stripos($title, 'arroz con leche') !== false
            || stripos($title, 'cuajada') !== false || stripos($title, 'yogur') !== false
            || stripos($title, 'batido') !== false;

        // Desserts → Snack
        if ($isDessert) {
            $ids = array_merge($ids, $this->getCategoryIds('🍎 Snack'));
            return $ids;
        }

        $breakfastKw = ['desayuno', 'tostada', 'cereal', 'porridge', 'avena', 'muffin', 'crepe', 'tortita', 'panqueque', 'magdalena', 'churro', 'croissant', 'bizcocho'];
        $lunchKw = ['almuerzo', 'comida', 'guiso', 'estofado', 'asado', 'lomo', 'filete', 'chuleta', 'hamburguesa', 'lasaña', 'paella', 'cazuela', 'pasta'];
        $dinnerKw = ['cena', 'ligero', 'sopa', 'crema', 'ensalada', 'salteado', 'puré', 'puré', 'revuelto', 'caldo', 'gazpacho'];
        $snackKw = ['snack', 'aperitivo', 'tapa', 'pincho', 'canapé', 'canape', 'montadito', 'bocadillo', 'sándwich', 'sandwich', 'picoteo', 'hummus', 'guacamole', 'paté', 'pate', 'croqueta', 'empanadilla', 'buñuelo'];

        $scores = ['🍳 Desayuno' => 0, '🍽️ Comida' => 0, '🌙 Cena' => 0, '🍎 Snack' => 0];

        foreach ($breakfastKw as $kw) if (stripos($text, $kw) !== false) $scores['🍳 Desayuno']++;
        foreach ($lunchKw as $kw) if (stripos($text, $kw) !== false) $scores['🍽️ Comida']++;
        foreach ($dinnerKw as $kw) if (stripos($text, $kw) !== false) $scores['🌙 Cena']++;
        foreach ($snackKw as $kw) if (stripos($text, $kw) !== false) $scores['🍎 Snack']++;

        $maxScore = max($scores);
        if ($maxScore > 0) {
            foreach ($scores as $cat => $score) {
                if ($score === $maxScore) {
                    $ids = array_merge($ids, $this->getCategoryIds($cat));
                    break;
                }
            }
        }

        return $ids;
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function containsMeat(string $text): bool
    {
        foreach (self::MEAT as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function containsSeafood(string $text): bool
    {
        foreach (self::SEAFOOD as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function containsEggs(string $text): bool
    {
        foreach (self::EGGS as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function containsDairy(string $text): bool
    {
        foreach (self::DAIRY as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function containsHoney(string $text): bool
    {
        foreach (self::HONEY as $item) { if (stripos($text, $item) !== false) return true; }
        return false;
    }

    private function getCategoryIds(string $name): array
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $cat = RecipeCategory::where('title', $name)->first();
            $cache[$name] = $cat ? [$cat->id] : [];
        }
        return $cache[$name];
    }

    private function getTagIds(string $name): array
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $tag = RecipeTag::where('title', $name)->first();
            $cache[$name] = $tag ? [$tag->id] : [];
        }
        return $cache[$name];
    }

    private function ensureCategoriesAndTags(): void
    {
        $categories = ['🍳 Desayuno', '🍽️ Comida', '🌙 Cena', '🍎 Snack'];
        foreach ($categories as $c) {
            RecipeCategory::firstOrCreate(['title' => $c], ['status' => 1]);
        }

        $tags = [
            '☪️ Halal', '🥬 Vegetariana', '🥗 Vegana',
            '💪 Alta en proteínas', '🔥 Baja en calorías', '🍗 Ganancia muscular', '⚖️ Pérdida de grasa',
            '⏱️ Menos de 15 min', '⏱️ 15-30 min', '⏱️ Más de 30 min',
            '🌾 Sin gluten', '🥛 Sin lactosa',
            '📦 Meal Prep', '🍟 Air Fryer',
            '💸 Económica', '5️⃣ Solo 5 ingredientes', '🧊 Sin cocinar',
            '🇪🇸 España', '🇨🇴 Colombia', '🇲🇽 México', '🇦🇷 Argentina', '🇨🇱 Chile', '🇵🇪 Perú',
        ];
        foreach ($tags as $t) {
            RecipeTag::firstOrCreate(['title' => $t], ['status' => 1]);
        }

        $this->info("Categories: " . RecipeCategory::count() . ", Tags: " . RecipeTag::count());
    }

    private function showStats(): void
    {
        $this->info(PHP_EOL . "--- Statistics ---");
        foreach (RecipeCategory::all() as $c) {
            $count = RecipeCategoryMapping::where('recipe_category_id', $c->id)->count();
            if ($count > 0) $this->info("  {$c->title}: {$count}");
        }
        $this->info("");
        foreach (RecipeTag::all() as $t) {
            $count = RecipeTagMapping::where('recipe_tag_id', $t->id)->count();
            if ($count > 0) $this->info("  {$t->title}: {$count}");
        }
    }
}
