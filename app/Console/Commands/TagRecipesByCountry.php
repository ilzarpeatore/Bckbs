<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\RecipeTagMapping;
use Illuminate\Console\Command;

class TagRecipesByCountry extends Command
{
    protected $signature = 'recipes:tag-countries';
    protected $description = 'Tag recipes by country based on dish names';

    public function handle(): int
    {
        $countryKeywords = [
            '🇪🇸 España' => ['tortilla española', 'tortilla de patata', 'paella', 'gazpacho', 'salmorejo', 'croqueta', 'pulpo a la gallega', 'fabada', 'cocido', 'patatas bravas', 'patatas a la riojana', 'bacalao al pil pil', 'marmitako', 'migas', 'pisto', 'fideuà', 'arroz negro', 'torrija', 'crema catalana', 'churro', 'porras', 'ensaimada', 'papas arrugadas', 'mojo picón', 'polvorón', 'turrón', 'buñuelo'],
            '🇨🇴 Colombia' => ['arepa', 'bandeja paisa', 'ajiaco', 'sancocho', 'lechona', 'patacón', 'patacon', 'arepa de huevo', 'pandebono', 'almojábana', 'arequipe', 'natilla colombiana', 'fritanga', 'sobrebarriga', 'sudado', 'changua', 'calentao'],
            '🇲🇽 México' => ['taco', 'enchilada', 'mole', 'pozole', 'chilaquile', 'guacamole', 'quesadilla', 'burrito', 'fajita', 'nachos', 'tinga', 'cochinita pibil', 'carnita', 'barbacoa', 'birria', 'menudo', 'chiles rellenos', 'tlayuda', 'sope', 'gordita', 'chalupa', 'flauta', 'chimichanga', 'frijoles charros', 'frijoles refritos', 'nopal', 'horchata', 'tamarindo', 'margarita', 'michelada'],
            '🇦🇷 Argentina' => ['asado argentino', 'chimichurri', 'milanesa', 'locro', 'humita', 'provoleta', 'choripán', 'morcilla', 'chinchulín', 'molleja', 'matambre', 'dulce de leche', 'alfajor', 'factura', 'medialuna', 'ñoqui', 'sorrentino', 'vitel toné', 'fugazza', 'fainá', 'yerba mate', 'tereré'],
            '🇨🇱 Chile' => ['completo', 'chacarero', 'chorillana', 'pastel de choclo', 'cazuela', 'charquicán', 'porotos', 'curanto', 'machas a la parmesana', 'paila marina', 'caldillo de congrio', 'pebre', 'sopaipilla', 'mote con huesillo', 'brazo de reina', 'terremoto chile', 'piscola', 'cola de mono'],
            '🇵🇪 Perú' => ['ceviche', 'lomo saltado', 'ají de gallina', 'causa limeña', 'arroz chaufa', 'papa a la huancaina', 'anticucho', 'picarone', 'suspiro limeño', 'mazamorra morada', 'tacu tacu', 'rocoto relleno', 'pollo a la brasa', 'tiradito', 'pisco sour', 'chicha morada', 'lúcuma', 'chirimoya'],
        ];

        foreach ($countryKeywords as $name => $kw) {
            RecipeTag::firstOrCreate(['title' => $name], ['status' => 1]);
        }

        $total = Recipe::count();
        $processed = 0;

        Recipe::chunk(1000, function ($recipes) use ($countryKeywords, &$processed, $total) {
            foreach ($recipes as $recipe) {
                $text = strtolower($recipe->title . ' ' . ($recipe->description ?? ''));

                foreach ($countryKeywords as $country => $keywords) {
                    foreach ($keywords as $kw) {
                        if (stripos($text, $kw) !== false) {
                            $tag = RecipeTag::where('title', $country)->first();
                            if ($tag) {
                                RecipeTagMapping::firstOrCreate([
                                    'recipe_id' => $recipe->id,
                                    'recipe_tag_id' => $tag->id,
                                ]);
                            }
                            break 2;
                        }
                    }
                }
            }
            $processed += count($recipes);
            if ($processed % 10000 === 0) {
                $this->info("Progress: {$processed}/{$total}");
            }
        });

        foreach ($countryKeywords as $country => $kw) {
            $tag = RecipeTag::where('title', $country)->first();
            $count = $tag ? RecipeTagMapping::where('recipe_tag_id', $tag->id)->count() : 0;
            $this->info("  {$country}: {$count}");
        }

        return 0;
    }
}
