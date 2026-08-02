<?php

namespace App\Services;

use Illuminate\Support\Str;

class IngredientMappingService
{
    private static array $dictionary = [];
    private static array $keywordIndex = [];
    private static bool $loaded = false;

    private const DEFAULT_FALLBACKS = [
        'Proteins' => ['cal' => 2.0, 'pro' => 0.25, 'fat' => 0.12, 'carb' => 0.0],
        'Vegetables' => ['cal' => 0.35, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.07],
        'Fruits' => ['cal' => 0.60, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.15],
        'Grains & Pasta' => ['cal' => 2.50, 'pro' => 0.08, 'fat' => 0.03, 'carb' => 0.50],
        'Dairy' => ['cal' => 2.50, 'pro' => 0.15, 'fat' => 0.20, 'carb' => 0.05],
        'Fats & Oils' => ['cal' => 8.84, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        'Nuts & Seeds' => ['cal' => 5.50, 'pro' => 0.20, 'fat' => 0.50, 'carb' => 0.20],
        'Legumes' => ['cal' => 1.40, 'pro' => 0.09, 'fat' => 0.01, 'carb' => 0.24],
        'Spices & Herbs' => ['cal' => 2.50, 'pro' => 0.10, 'fat' => 0.05, 'carb' => 0.50],
        'Condiments & Sauces' => ['cal' => 1.50, 'pro' => 0.02, 'fat' => 0.08, 'carb' => 0.20],
        'Beverages' => ['cal' => 0.50, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.10],
        'Baked Goods' => ['cal' => 3.00, 'pro' => 0.06, 'fat' => 0.12, 'carb' => 0.42],
        'Seafood' => ['cal' => 1.00, 'pro' => 0.20, 'fat' => 0.02, 'carb' => 0.00],
        'Processed Foods' => ['cal' => 2.50, 'pro' => 0.10, 'fat' => 0.15, 'carb' => 0.25],
        'Sweeteners' => ['cal' => 3.50, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.85],
    ];

    private const CORE_DICTIONARY = [
        // Proteins
        ['name' => 'Pollo', 'keywords' => ['pollo', 'pechuga', 'muslo', 'ala', 'contramuslo'], 'category' => 'Proteins', 'cal' => 1.65, 'pro' => 0.31, 'fat' => 0.04, 'carb' => 0.0],
        ['name' => 'Carne de res', 'keywords' => ['carne de res', 'carne de vaca', 'filete', 'entrecot', 'chuletón', 'cuadril', 'solomillo', 'ternera'], 'category' => 'Proteins', 'cal' => 2.50, 'pro' => 0.26, 'fat' => 0.17, 'carb' => 0.0],
        ['name' => 'Cerdo', 'keywords' => ['cerdo', 'lomo', 'costillas de cerdo', 'magro de cerdo'], 'category' => 'Proteins', 'cal' => 2.43, 'pro' => 0.27, 'fat' => 0.14, 'carb' => 0.0],
        ['name' => 'Jamón', 'keywords' => ['jamón', 'jamon', 'jamón cocido', 'jamón york', 'jamón serrano', 'jamón ibérico'], 'category' => 'Proteins', 'cal' => 1.45, 'pro' => 0.21, 'fat' => 0.06, 'carb' => 0.01],
        ['name' => 'Pavo', 'keywords' => ['pavo', 'pechuga de pavo'], 'category' => 'Proteins', 'cal' => 1.35, 'pro' => 0.29, 'fat' => 0.02, 'carb' => 0.0],
        ['name' => 'Atún', 'keywords' => ['atún', 'atun'], 'category' => 'Proteins', 'cal' => 1.32, 'pro' => 0.29, 'fat' => 0.01, 'carb' => 0.0],
        ['name' => 'Salmón', 'keywords' => ['salmón', 'salmon'], 'category' => 'Proteins', 'cal' => 2.08, 'pro' => 0.20, 'fat' => 0.13, 'carb' => 0.0],
        ['name' => 'Bacalao', 'keywords' => ['bacalao'], 'category' => 'Proteins', 'cal' => 1.05, 'pro' => 0.23, 'fat' => 0.01, 'carb' => 0.0],
        ['name' => 'Camarones', 'keywords' => ['camarón', 'camarones', 'gamba', 'gambas', 'langostino', 'langostinos'], 'category' => 'Proteins', 'cal' => 0.99, 'pro' => 0.24, 'fat' => 0.01, 'carb' => 0.0],
        ['name' => 'Boquerones', 'keywords' => ['boquerón', 'boquerones', 'anchoa', 'anchoas'], 'category' => 'Proteins', 'cal' => 1.20, 'pro' => 0.22, 'fat' => 0.04, 'carb' => 0.0],
        ['name' => 'Huevo', 'keywords' => ['huevo', 'huevos', 'yema', 'clara'], 'category' => 'Proteins', 'cal' => 1.55, 'pro' => 0.13, 'fat' => 0.11, 'carb' => 0.01],
        ['name' => 'Chorizo', 'keywords' => ['chorizo', 'chistorra', 'morcilla', 'morcón', 'butifarra', 'longaniza', 'sobrasada'], 'category' => 'Proteins', 'cal' => 4.55, 'pro' => 0.24, 'fat' => 0.38, 'carb' => 0.02],
        ['name' => 'Tocino', 'keywords' => ['tocino', 'panceta', 'tocineta', 'bacón'], 'category' => 'Proteins', 'cal' => 5.41, 'pro' => 0.37, 'fat' => 0.42, 'carb' => 0.01],
        ['name' => 'Salchicha', 'keywords' => ['salchicha', 'salchichas', 'frankfurt'], 'category' => 'Proteins', 'cal' => 3.01, 'pro' => 0.13, 'fat' => 0.26, 'carb' => 0.02],
        ['name' => 'Albóndigas', 'keywords' => ['albóndiga', 'albondiga', 'albóndigas', 'albondigas'], 'category' => 'Proteins', 'cal' => 2.02, 'pro' => 0.15, 'fat' => 0.12, 'carb' => 0.06],
        ['name' => 'Pescado', 'keywords' => ['pescado', 'merluza', 'dorada', 'lubina', 'sardina', 'caballa', 'lenguado', 'rape'], 'category' => 'Proteins', 'cal' => 1.20, 'pro' => 0.20, 'fat' => 0.04, 'carb' => 0.0],
        ['name' => 'Mejillones', 'keywords' => ['mejillón', 'mejillones', 'almeja', 'almejas', 'berberecho', 'berberechos'], 'category' => 'Proteins', 'cal' => 1.72, 'pro' => 0.24, 'fat' => 0.07, 'carb' => 0.07],
        ['name' => 'Pulpo', 'keywords' => ['pulpo', 'calamar', 'chipirones', 'sepia'], 'category' => 'Proteins', 'cal' => 0.82, 'pro' => 0.15, 'fat' => 0.01, 'carb' => 0.02],
        ['name' => 'Cordero', 'keywords' => ['cordero', 'costillas de cordero'], 'category' => 'Proteins', 'cal' => 2.94, 'pro' => 0.25, 'fat' => 0.21, 'carb' => 0.0],
        ['name' => 'Conejo', 'keywords' => ['conejo'], 'category' => 'Proteins', 'cal' => 1.36, 'pro' => 0.21, 'fat' => 0.06, 'carb' => 0.0],
        ['name' => 'Pato', 'keywords' => ['pato'], 'category' => 'Proteins', 'cal' => 3.37, 'pro' => 0.19, 'fat' => 0.28, 'carb' => 0.0],
        ['name' => 'Carne picada', 'keywords' => ['carne picada', 'carne molida'], 'category' => 'Proteins', 'cal' => 2.50, 'pro' => 0.26, 'fat' => 0.17, 'carb' => 0.0],
        ['name' => 'Hamburguesa', 'keywords' => ['hamburguesa'], 'category' => 'Proteins', 'cal' => 2.50, 'pro' => 0.15, 'fat' => 0.20, 'carb' => 0.03],
        ['name' => 'Milanesa', 'keywords' => ['milanesa'], 'category' => 'Proteins', 'cal' => 2.60, 'pro' => 0.18, 'fat' => 0.16, 'carb' => 0.08],
        ['name' => 'Croqueta', 'keywords' => ['croqueta', 'croquetas'], 'category' => 'Proteins', 'cal' => 2.60, 'pro' => 0.10, 'fat' => 0.16, 'carb' => 0.20],
        ['name' => 'Mortadela', 'keywords' => ['mortadela', 'pepperoni', 'salami', 'fuet'], 'category' => 'Proteins', 'cal' => 3.11, 'pro' => 0.16, 'fat' => 0.26, 'carb' => 0.03],

        // Dairy
        ['name' => 'Leche', 'keywords' => ['leche'], 'category' => 'Dairy', 'cal' => 0.61, 'pro' => 0.03, 'fat' => 0.03, 'carb' => 0.05],
        ['name' => 'Queso', 'keywords' => ['queso', 'queso rallado', 'queso mozzarella', 'queso crema', 'queso parmesano', 'queso cheddar', 'queso feta', 'queso blanco'], 'category' => 'Dairy', 'cal' => 4.02, 'pro' => 0.25, 'fat' => 0.33, 'carb' => 0.01],
        ['name' => 'Mantequilla', 'keywords' => ['mantequilla'], 'category' => 'Dairy', 'cal' => 7.17, 'pro' => 0.01, 'fat' => 0.81, 'carb' => 0.01],
        ['name' => 'Manteca', 'keywords' => ['manteca', 'manteca de cerdo'], 'category' => 'Dairy', 'cal' => 8.84, 'pro' => 0.0, 'fat' => 1.0, 'carb' => 0.0],
        ['name' => 'Crema de leche', 'keywords' => ['crema de leche', 'nata', 'crema agria', 'mascarpone'], 'category' => 'Dairy', 'cal' => 3.45, 'pro' => 0.02, 'fat' => 0.36, 'carb' => 0.03],
        ['name' => 'Yogur', 'keywords' => ['yogur', 'yogurt', 'yogur griego', 'yogur natural'], 'category' => 'Dairy', 'cal' => 0.59, 'pro' => 0.10, 'fat' => 0.02, 'carb' => 0.04],
        ['name' => 'Helado', 'keywords' => ['helado'], 'category' => 'Dairy', 'cal' => 2.07, 'pro' => 0.04, 'fat' => 0.11, 'carb' => 0.24],
        ['name' => 'Dulce de leche', 'keywords' => ['dulce de leche', 'leche condensada', 'leche evaporada'], 'category' => 'Dairy', 'cal' => 3.15, 'pro' => 0.06, 'fat' => 0.08, 'carb' => 0.55],
        ['name' => 'Margarina', 'keywords' => ['margarina'], 'category' => 'Dairy', 'cal' => 7.17, 'pro' => 0.01, 'fat' => 0.81, 'carb' => 0.01],
        ['name' => 'Leche de coco', 'keywords' => ['leche de coco'], 'category' => 'Dairy', 'cal' => 2.30, 'pro' => 0.02, 'fat' => 0.24, 'carb' => 0.03],

        // Grains & Pasta
        ['name' => 'Arroz', 'keywords' => ['arroz', 'arroz blanco'], 'category' => 'Grains & Pasta', 'cal' => 1.30, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.28],
        ['name' => 'Pasta', 'keywords' => ['pasta', 'espagueti', 'fideos', 'macarrones', 'lasaña'], 'category' => 'Grains & Pasta', 'cal' => 1.31, 'pro' => 0.05, 'fat' => 0.01, 'carb' => 0.25],
        ['name' => 'Harina', 'keywords' => ['harina', 'harina de trigo', 'harina de maíz'], 'category' => 'Grains & Pasta', 'cal' => 3.64, 'pro' => 0.10, 'fat' => 0.01, 'carb' => 0.76],
        ['name' => 'Pan', 'keywords' => ['pan', 'pan de molde', 'pan integral', 'pan rallado', 'baguette', 'tostada'], 'category' => 'Grains & Pasta', 'cal' => 2.65, 'pro' => 0.09, 'fat' => 0.03, 'carb' => 0.49],
        ['name' => 'Tortilla', 'keywords' => ['tortilla', 'tortilla de maíz', 'tortilla de harina'], 'category' => 'Grains & Pasta', 'cal' => 2.18, 'pro' => 0.06, 'fat' => 0.03, 'carb' => 0.45],
        ['name' => 'Arepa', 'keywords' => ['arepa'], 'category' => 'Grains & Pasta', 'cal' => 2.19, 'pro' => 0.05, 'fat' => 0.02, 'carb' => 0.46],
        ['name' => 'Avena', 'keywords' => ['avena', 'cereal'], 'category' => 'Grains & Pasta', 'cal' => 3.89, 'pro' => 0.17, 'fat' => 0.07, 'carb' => 0.66],
        ['name' => 'Quinoa', 'keywords' => ['quinoa'], 'category' => 'Grains & Pasta', 'cal' => 1.20, 'pro' => 0.04, 'fat' => 0.02, 'carb' => 0.21],
        ['name' => 'Maíz', 'keywords' => ['maíz', 'elote', 'choclo'], 'category' => 'Grains & Pasta', 'cal' => 0.86, 'pro' => 0.03, 'fat' => 0.01, 'carb' => 0.19],
        ['name' => 'Galleta', 'keywords' => ['galleta', 'galletas', 'cookies'], 'category' => 'Grains & Pasta', 'cal' => 4.02, 'pro' => 0.07, 'fat' => 0.10, 'carb' => 0.72],
        ['name' => 'Pizza', 'keywords' => ['pizza'], 'category' => 'Grains & Pasta', 'cal' => 2.66, 'pro' => 0.11, 'fat' => 0.10, 'carb' => 0.33],
        ['name' => 'Empanada', 'keywords' => ['empanada', 'empanadas'], 'category' => 'Grains & Pasta', 'cal' => 2.40, 'pro' => 0.07, 'fat' => 0.12, 'carb' => 0.27],
        ['name' => 'Patatas', 'keywords' => ['patata', 'patatas', 'papa', 'papas', 'pure de patatas'], 'category' => 'Grains & Pasta', 'cal' => 0.77, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.17],
        ['name' => 'Batata', 'keywords' => ['batata'], 'category' => 'Grains & Pasta', 'cal' => 0.86, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.20],
        ['name' => 'Maicena', 'keywords' => ['maicena', 'almidón'], 'category' => 'Grains & Pasta', 'cal' => 3.81, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.91],
        ['name' => 'Croissant', 'keywords' => ['croissant', 'magdalena', 'donut', 'muffin', 'cupcake'], 'category' => 'Grains & Pasta', 'cal' => 4.06, 'pro' => 0.08, 'fat' => 0.21, 'carb' => 0.46],
        ['name' => 'Bizcocho', 'keywords' => ['bizcocho', 'tarta', 'pastel', 'torta', 'brownie', 'flan'], 'category' => 'Grains & Pasta', 'cal' => 3.71, 'pro' => 0.06, 'fat' => 0.15, 'carb' => 0.54],
        ['name' => 'Pancake', 'keywords' => ['pancake', 'waffle', 'panqueque'], 'category' => 'Grains & Pasta', 'cal' => 2.27, 'pro' => 0.06, 'fat' => 0.10, 'carb' => 0.28],
        ['name' => 'Churros', 'keywords' => ['churros', 'buñuelos', 'pestiños'], 'category' => 'Grains & Pasta', 'cal' => 4.06, 'pro' => 0.04, 'fat' => 0.22, 'carb' => 0.48],
        ['name' => 'Turrón', 'keywords' => ['turrón', 'mazapán', 'marzipan', 'mantecado', 'polvorón'], 'category' => 'Grains & Pasta', 'cal' => 5.20, 'pro' => 0.08, 'fat' => 0.28, 'carb' => 0.55],
        ['name' => 'Sopa', 'keywords' => ['sopa', 'gazpacho', 'salmorejo', 'crema de'], 'category' => 'Grains & Pasta', 'cal' => 0.60, 'pro' => 0.02, 'fat' => 0.02, 'carb' => 0.09],
        ['name' => 'Migas', 'keywords' => ['migas', 'gachas'], 'category' => 'Grains & Pasta', 'cal' => 2.50, 'pro' => 0.07, 'fat' => 0.10, 'carb' => 0.35],
        ['name' => 'Pizza', 'keywords' => ['pizza'], 'category' => 'Grains & Pasta', 'cal' => 2.66, 'pro' => 0.11, 'fat' => 0.10, 'carb' => 0.33],

        // Vegetables
        ['name' => 'Tomate', 'keywords' => ['tomate', 'tomates', 'tomate natural', 'tomate frito', 'tomate cherry'], 'category' => 'Vegetables', 'cal' => 0.18, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.04],
        ['name' => 'Cebolla', 'keywords' => ['cebolla', 'cebollas', 'cebolla en polvo'], 'category' => 'Vegetables', 'cal' => 0.40, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.09],
        ['name' => 'Ajo', 'keywords' => ['ajo', 'ajos', 'ajo en polvo'], 'category' => 'Vegetables', 'cal' => 1.49, 'pro' => 0.06, 'fat' => 0.00, 'carb' => 0.33],
        ['name' => 'Zanahoria', 'keywords' => ['zanahoria', 'zanahorias'], 'category' => 'Vegetables', 'cal' => 0.41, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.10],
        ['name' => 'Papa', 'keywords' => ['papa', 'papas', 'patata', 'patatas'], 'category' => 'Vegetables', 'cal' => 0.77, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.17],
        ['name' => 'Lechuga', 'keywords' => ['lechuga'], 'category' => 'Vegetables', 'cal' => 0.15, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Pepino', 'keywords' => ['pepino', 'pepinillos'], 'category' => 'Vegetables', 'cal' => 0.15, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Pimiento', 'keywords' => ['pimiento', 'pimientos', 'pimiento verde', 'pimiento rojo'], 'category' => 'Vegetables', 'cal' => 0.20, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.05],
        ['name' => 'Brócoli', 'keywords' => ['brócoli', 'brocoli'], 'category' => 'Vegetables', 'cal' => 0.34, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.07],
        ['name' => 'Espinaca', 'keywords' => ['espinaca', 'espinacas', 'acelga', 'acelgas'], 'category' => 'Vegetables', 'cal' => 0.23, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.04],
        ['name' => 'Calabacín', 'keywords' => ['calabacín', 'calabacin', 'zapallo', 'calabaza'], 'category' => 'Vegetables', 'cal' => 0.17, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Berenjena', 'keywords' => ['berenjena'], 'category' => 'Vegetables', 'cal' => 0.25, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.06],
        ['name' => 'Coliflor', 'keywords' => ['coliflor', 'col', 'repollo'], 'category' => 'Vegetables', 'cal' => 0.25, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.05],
        ['name' => 'Apio', 'keywords' => ['apio'], 'category' => 'Vegetables', 'cal' => 0.14, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Champiñones', 'keywords' => ['champiñón', 'champiñones', 'seta', 'setas', 'hongo', 'hongos'], 'category' => 'Vegetables', 'cal' => 0.22, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Espárragos', 'keywords' => ['espárrago', 'esparrago', 'espárragos', 'esparragos'], 'category' => 'Vegetables', 'cal' => 0.20, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.04],
        ['name' => 'Guisantes', 'keywords' => ['guisante', 'guisantes', 'chícharo', 'chicharos', 'arveja'], 'category' => 'Vegetables', 'cal' => 0.81, 'pro' => 0.05, 'fat' => 0.00, 'carb' => 0.14],
        ['name' => 'Ejotes', 'keywords' => ['ejote', 'ejotes', 'judía verde', 'judias verdes', 'habichuela'], 'category' => 'Vegetables', 'cal' => 0.31, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.07],
        ['name' => 'Nopal', 'keywords' => ['nopal'], 'category' => 'Vegetables', 'cal' => 0.16, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Aguacate', 'keywords' => ['aguacate', 'palta'], 'category' => 'Vegetables', 'cal' => 1.60, 'pro' => 0.02, 'fat' => 0.15, 'carb' => 0.09],
        ['name' => 'Puerro', 'keywords' => ['puerro'], 'category' => 'Vegetables', 'cal' => 0.61, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.14],
        ['name' => 'Remolacha', 'keywords' => ['remolacha', 'betabel'], 'category' => 'Vegetables', 'cal' => 0.43, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.10],
        ['name' => 'Alcachofa', 'keywords' => ['alcachofa'], 'category' => 'Vegetables', 'cal' => 0.47, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.10],
        ['name' => 'Rábano', 'keywords' => ['rábano', 'rabano'], 'category' => 'Vegetables', 'cal' => 0.16, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Jengibre', 'keywords' => ['jengibre'], 'category' => 'Vegetables', 'cal' => 0.80, 'pro' => 0.02, 'fat' => 0.01, 'carb' => 0.18],
        ['name' => 'Cilantro', 'keywords' => ['cilantro'], 'category' => 'Vegetables', 'cal' => 0.23, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.04],
        ['name' => 'Perejil', 'keywords' => ['perejil'], 'category' => 'Vegetables', 'cal' => 0.36, 'pro' => 0.03, 'fat' => 0.01, 'carb' => 0.06],
        ['name' => 'Albahaca', 'keywords' => ['albahaca'], 'category' => 'Vegetables', 'cal' => 0.23, 'pro' => 0.03, 'fat' => 0.01, 'carb' => 0.02],
        ['name' => 'Romero', 'keywords' => ['romero'], 'category' => 'Vegetables', 'cal' => 0.33, 'pro' => 0.01, 'fat' => 0.01, 'carb' => 0.06],
        ['name' => 'Tomillo', 'keywords' => ['tomillo'], 'category' => 'Vegetables', 'cal' => 0.28, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.07],
        ['name' => 'Laurel', 'keywords' => ['laurel', 'hoja de laurel'], 'category' => 'Vegetables', 'cal' => 0.31, 'pro' => 0.01, 'fat' => 0.01, 'carb' => 0.07],
        ['name' => 'Orégano', 'keywords' => ['orégano', 'oregano'], 'category' => 'Vegetables', 'cal' => 0.31, 'pro' => 0.01, 'fat' => 0.01, 'carb' => 0.07],
        ['name' => 'Hinojo', 'keywords' => ['hinojo'], 'category' => 'Vegetables', 'cal' => 0.31, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.07],
        ['name' => 'Chayote', 'keywords' => ['chayote'], 'category' => 'Vegetables', 'cal' => 0.19, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.04],
        ['name' => 'Pepinillo', 'keywords' => ['pepinillo'], 'category' => 'Vegetables', 'cal' => 0.15, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Tomate seco', 'keywords' => ['tomate seco'], 'category' => 'Vegetables', 'cal' => 2.13, 'pro' => 0.05, 'fat' => 0.03, 'carb' => 0.46],

        // Fruits
        ['name' => 'Manzana', 'keywords' => ['manzana', 'manzanas'], 'category' => 'Fruits', 'cal' => 0.52, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.14],
        ['name' => 'Plátano', 'keywords' => ['plátano', 'platano', 'banana'], 'category' => 'Fruits', 'cal' => 0.89, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.23],
        ['name' => 'Naranja', 'keywords' => ['naranja', 'naranjas'], 'category' => 'Fruits', 'cal' => 0.47, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.12],
        ['name' => 'Limón', 'keywords' => ['limón', 'limon'], 'category' => 'Fruits', 'cal' => 0.29, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.09],
        ['name' => 'Fresa', 'keywords' => ['fresa', 'fresas', 'frutilla'], 'category' => 'Fruits', 'cal' => 0.32, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.08],
        ['name' => 'Mango', 'keywords' => ['mango'], 'category' => 'Fruits', 'cal' => 0.60, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.15],
        ['name' => 'Piña', 'keywords' => ['piña', 'pina', 'ananá'], 'category' => 'Fruits', 'cal' => 0.50, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.13],
        ['name' => 'Uva', 'keywords' => ['uva', 'uvas'], 'category' => 'Fruits', 'cal' => 0.69, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.18],
        ['name' => 'Pera', 'keywords' => ['pera', 'peras'], 'category' => 'Fruits', 'cal' => 0.57, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.15],
        ['name' => 'Melocotón', 'keywords' => ['melocotón', 'melocoton', 'durazno'], 'category' => 'Fruits', 'cal' => 0.39, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.10],
        ['name' => 'Cereza', 'keywords' => ['cereza', 'cerezas'], 'category' => 'Fruits', 'cal' => 0.50, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.12],
        ['name' => 'Sandía', 'keywords' => ['sandía', 'sandia'], 'category' => 'Fruits', 'cal' => 0.30, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.08],
        ['name' => 'Melón', 'keywords' => ['melón', 'melon'], 'category' => 'Fruits', 'cal' => 0.34, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.08],
        ['name' => 'Papaya', 'keywords' => ['papaya'], 'category' => 'Fruits', 'cal' => 0.43, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.11],
        ['name' => 'Coco', 'keywords' => ['coco', 'coco rallado'], 'category' => 'Fruits', 'cal' => 3.54, 'pro' => 0.03, 'fat' => 0.33, 'carb' => 0.15],
        ['name' => 'Guayaba', 'keywords' => ['guayaba'], 'category' => 'Fruits', 'cal' => 0.68, 'pro' => 0.03, 'fat' => 0.01, 'carb' => 0.14],
        ['name' => 'Maracuyá', 'keywords' => ['maracuyá', 'maracuya', 'parchita'], 'category' => 'Fruits', 'cal' => 0.97, 'pro' => 0.02, 'fat' => 0.01, 'carb' => 0.23],
        ['name' => 'Toronja', 'keywords' => ['toronja', 'pomelo'], 'category' => 'Fruits', 'cal' => 0.42, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.11],
        ['name' => 'Higo', 'keywords' => ['higo', 'higos'], 'category' => 'Fruits', 'cal' => 0.74, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.19],
        ['name' => 'Ciruela', 'keywords' => ['ciruela', 'ciruelas'], 'category' => 'Fruits', 'cal' => 0.46, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.11],
        ['name' => 'Mandarina', 'keywords' => ['mandarina', 'mandarinas'], 'category' => 'Fruits', 'cal' => 0.53, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.13],
        ['name' => 'Kiwi', 'keywords' => ['kiwi'], 'category' => 'Fruits', 'cal' => 0.61, 'pro' => 0.01, 'fat' => 0.01, 'carb' => 0.15],
        ['name' => 'Frambuesa', 'keywords' => ['frambuesa', 'frambuesas'], 'category' => 'Fruits', 'cal' => 0.53, 'pro' => 0.01, 'fat' => 0.01, 'carb' => 0.12],
        ['name' => 'Arándano', 'keywords' => ['arándano', 'arandano', 'mora', 'moras', 'blueberry'], 'category' => 'Fruits', 'cal' => 0.57, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.14],
        ['name' => 'Granada', 'keywords' => ['granada'], 'category' => 'Fruits', 'cal' => 0.83, 'pro' => 0.02, 'fat' => 0.01, 'carb' => 0.19],
        ['name' => 'Lúcuma', 'keywords' => ['lúcuma', 'lucuma'], 'category' => 'Fruits', 'cal' => 1.03, 'pro' => 0.04, 'fat' => 0.01, 'carb' => 0.25],
        ['name' => 'Aguaymanto', 'keywords' => ['aguaymanto', 'uchuva'], 'category' => 'Fruits', 'cal' => 0.53, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.11],
        ['name' => 'Tamarindo', 'keywords' => ['tamarindo'], 'category' => 'Fruits', 'cal' => 2.39, 'pro' => 0.03, 'fat' => 0.00, 'carb' => 0.63],
        ['name' => 'Chirimoya', 'keywords' => ['chirimoya'], 'category' => 'Fruits', 'cal' => 0.75, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.18],
        ['name' => 'Lima', 'keywords' => ['lima'], 'category' => 'Fruits', 'cal' => 0.30, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.10],

        // Fats & Oils
        ['name' => 'Aceite de oliva', 'keywords' => ['aceite de oliva', 'aceite virgen', 'aceite de oliva virgen'], 'category' => 'Fats & Oils', 'cal' => 8.84, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        ['name' => 'Aceite vegetal', 'keywords' => ['aceite vegetal', 'aceite de girasol', 'aceite de canola', 'aceite de maíz'], 'category' => 'Fats & Oils', 'cal' => 8.84, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        ['name' => 'Aceite de coco', 'keywords' => ['aceite de coco'], 'category' => 'Fats & Oils', 'cal' => 8.84, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        ['name' => 'Grasa', 'keywords' => ['grasa'], 'category' => 'Fats & Oils', 'cal' => 9.02, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        ['name' => 'Aceite', 'keywords' => ['aceite'], 'category' => 'Fats & Oils', 'cal' => 8.84, 'pro' => 0.00, 'fat' => 1.00, 'carb' => 0.00],
        ['name' => 'Aceitunas', 'keywords' => ['aceituna', 'aceitunas', 'oliva'], 'category' => 'Fats & Oils', 'cal' => 1.45, 'pro' => 0.01, 'fat' => 0.15, 'carb' => 0.04],
        ['name' => 'Vinagre', 'keywords' => ['vinagre', 'vinagre de vino', 'vinagre de manzana'], 'category' => 'Condiments & Sauces', 'cal' => 0.21, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],

        // Nuts & Seeds
        ['name' => 'Almendra', 'keywords' => ['almendra', 'almendras'], 'category' => 'Nuts & Seeds', 'cal' => 5.79, 'pro' => 0.21, 'fat' => 0.50, 'carb' => 0.22],
        ['name' => 'Nuez', 'keywords' => ['nuez', 'nueces'], 'category' => 'Nuts & Seeds', 'cal' => 6.54, 'pro' => 0.15, 'fat' => 0.65, 'carb' => 0.14],
        ['name' => 'Maní', 'keywords' => ['maní', 'mani', 'cacahuate', 'cacahuete'], 'category' => 'Nuts & Seeds', 'cal' => 5.67, 'pro' => 0.26, 'fat' => 0.49, 'carb' => 0.16],
        ['name' => 'Pistacho', 'keywords' => ['pistacho', 'pistachos'], 'category' => 'Nuts & Seeds', 'cal' => 5.60, 'pro' => 0.20, 'fat' => 0.45, 'carb' => 0.28],
        ['name' => 'Avellana', 'keywords' => ['avellana', 'avellanas'], 'category' => 'Nuts & Seeds', 'cal' => 6.28, 'pro' => 0.15, 'fat' => 0.61, 'carb' => 0.17],
        ['name' => 'Castaña', 'keywords' => ['castaña', 'castañas'], 'category' => 'Nuts & Seeds', 'cal' => 2.45, 'pro' => 0.03, 'fat' => 0.02, 'carb' => 0.53],
        ['name' => 'Ajonjolí', 'keywords' => ['ajonjolí', 'ajonjoli', 'sésamo', 'sesamo'], 'category' => 'Nuts & Seeds', 'cal' => 5.73, 'pro' => 0.17, 'fat' => 0.50, 'carb' => 0.23],
        ['name' => 'Linaza', 'keywords' => ['linaza'], 'category' => 'Nuts & Seeds', 'cal' => 5.34, 'pro' => 0.18, 'fat' => 0.42, 'carb' => 0.29],
        ['name' => 'Chía', 'keywords' => ['chía', 'chia'], 'category' => 'Nuts & Seeds', 'cal' => 4.86, 'pro' => 0.17, 'fat' => 0.31, 'carb' => 0.42],
        ['name' => 'Semillas de zapallo', 'keywords' => ['semilla de zapallo', 'semillas de zapallo', 'pepita', 'pepitas'], 'category' => 'Nuts & Seeds', 'cal' => 5.59, 'pro' => 0.30, 'fat' => 0.49, 'carb' => 0.11],
        ['name' => 'Piñón', 'keywords' => ['piñón', 'pinon', 'piñones'], 'category' => 'Nuts & Seeds', 'cal' => 6.73, 'pro' => 0.14, 'fat' => 0.68, 'carb' => 0.14],
        ['name' => 'Pasas', 'keywords' => ['pasa', 'pasas', 'dátil', 'datil', 'higo seco'], 'category' => 'Nuts & Seeds', 'cal' => 2.96, 'pro' => 0.03, 'fat' => 0.05, 'carb' => 0.79],
        ['name' => 'Cranberries', 'keywords' => ['cranberry', 'cranberries', 'arándano rojo'], 'category' => 'Nuts & Seeds', 'cal' => 3.08, 'pro' => 0.00, 'fat' => 0.01, 'carb' => 0.82],

        // Legumes
        ['name' => 'Frijoles', 'keywords' => ['frijol', 'frijoles', 'habichuela', 'habichuelas', 'alubia', 'alubias', 'poroto', 'porotos'], 'category' => 'Legumes', 'cal' => 1.39, 'pro' => 0.09, 'fat' => 0.01, 'carb' => 0.25],
        ['name' => 'Lentejas', 'keywords' => ['lenteja', 'lentejas'], 'category' => 'Legumes', 'cal' => 1.16, 'pro' => 0.09, 'fat' => 0.00, 'carb' => 0.20],
        ['name' => 'Garbanzos', 'keywords' => ['garbanzo', 'garbanzos'], 'category' => 'Legumes', 'cal' => 1.64, 'pro' => 0.09, 'fat' => 0.03, 'carb' => 0.27],
        ['name' => 'Soya', 'keywords' => ['soya', 'soja'], 'category' => 'Legumes', 'cal' => 1.72, 'pro' => 0.09, 'fat' => 0.09, 'carb' => 0.10],
        ['name' => 'Tofu', 'keywords' => ['tofu'], 'category' => 'Legumes', 'cal' => 0.76, 'pro' => 0.08, 'fat' => 0.05, 'carb' => 0.01],
        ['name' => 'Hummus', 'keywords' => ['hummus'], 'category' => 'Legumes', 'cal' => 1.66, 'pro' => 0.08, 'fat' => 0.10, 'carb' => 0.14],
        ['name' => 'Habas', 'keywords' => ['haba', 'habas'], 'category' => 'Legumes', 'cal' => 1.10, 'pro' => 0.08, 'fat' => 0.00, 'carb' => 0.19],
        ['name' => 'Chícharos', 'keywords' => ['chícharo', 'chicharos', 'chicharo', 'guisante'], 'category' => 'Legumes', 'cal' => 0.81, 'pro' => 0.05, 'fat' => 0.00, 'carb' => 0.14],

        // Spices & Herbs
        ['name' => 'Sal', 'keywords' => ['sal', 'sal marina'], 'category' => 'Spices & Herbs', 'cal' => 0.00, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],
        ['name' => 'Pimienta', 'keywords' => ['pimienta', 'pimienta negra', 'pimienta blanca'], 'category' => 'Spices & Herbs', 'cal' => 2.51, 'pro' => 0.10, 'fat' => 0.03, 'carb' => 0.64],
        ['name' => 'Comino', 'keywords' => ['comino'], 'category' => 'Spices & Herbs', 'cal' => 3.75, 'pro' => 0.18, 'fat' => 0.22, 'carb' => 0.44],
        ['name' => 'Orégano', 'keywords' => ['orégano', 'oregano'], 'category' => 'Spices & Herbs', 'cal' => 2.65, 'pro' => 0.09, 'fat' => 0.04, 'carb' => 0.69],
        ['name' => 'Canela', 'keywords' => ['canela', 'canela en polvo', 'canela molida'], 'category' => 'Spices & Herbs', 'cal' => 2.47, 'pro' => 0.04, 'fat' => 0.01, 'carb' => 0.81],
        ['name' => 'Pimentón', 'keywords' => ['pimentón', 'pimenton', 'paprika'], 'category' => 'Spices & Herbs', 'cal' => 2.82, 'pro' => 0.14, 'fat' => 0.13, 'carb' => 0.53],
        ['name' => 'Nuez moscada', 'keywords' => ['nuez moscada'], 'category' => 'Spices & Herbs', 'cal' => 5.25, 'pro' => 0.05, 'fat' => 0.36, 'carb' => 0.49],
        ['name' => 'Achiote', 'keywords' => ['achiote', 'annatto'], 'category' => 'Spices & Herbs', 'cal' => 3.00, 'pro' => 0.02, 'fat' => 0.02, 'carb' => 0.65],
        ['name' => 'Curry', 'keywords' => ['curry'], 'category' => 'Spices & Herbs', 'cal' => 3.25, 'pro' => 0.14, 'fat' => 0.14, 'carb' => 0.58],
        ['name' => 'Clavo', 'keywords' => ['clavo', 'clavo de olor'], 'category' => 'Spices & Herbs', 'cal' => 2.74, 'pro' => 0.06, 'fat' => 0.13, 'carb' => 0.66],
        ['name' => 'Azafrán', 'keywords' => ['azafrán', 'azafran'], 'category' => 'Spices & Herbs', 'cal' => 3.10, 'pro' => 0.11, 'fat' => 0.06, 'carb' => 0.65],
        ['name' => 'Cúrcuma', 'keywords' => ['cúrcuma', 'curcuma'], 'category' => 'Spices & Herbs', 'cal' => 3.12, 'pro' => 0.10, 'fat' => 0.03, 'carb' => 0.68],
        ['name' => 'Chile', 'keywords' => ['chile', 'chiles', 'ají', 'aji', 'guindilla', 'pimienta roja'], 'category' => 'Spices & Herbs', 'cal' => 0.40, 'pro' => 0.02, 'fat' => 0.00, 'carb' => 0.09],
        ['name' => 'Ajo en polvo', 'keywords' => ['ajo en polvo'], 'category' => 'Spices & Herbs', 'cal' => 3.32, 'pro' => 0.17, 'fat' => 0.01, 'carb' => 0.73],
        ['name' => 'Cebolla en polvo', 'keywords' => ['cebolla en polvo'], 'category' => 'Spices & Herbs', 'cal' => 3.41, 'pro' => 0.10, 'fat' => 0.00, 'carb' => 0.80],
        ['name' => 'Cardamomo', 'keywords' => ['cardamomo'], 'category' => 'Spices & Herbs', 'cal' => 3.11, 'pro' => 0.11, 'fat' => 0.07, 'carb' => 0.68],
        ['name' => 'Anís estrellado', 'keywords' => ['anís', 'anis', 'anís estrellado'], 'category' => 'Spices & Herbs', 'cal' => 3.37, 'pro' => 0.17, 'fat' => 0.16, 'carb' => 0.50],

        // Condiments & Sauces
        ['name' => 'Mostaza', 'keywords' => ['mostaza'], 'category' => 'Condiments & Sauces', 'cal' => 0.66, 'pro' => 0.04, 'fat' => 0.03, 'carb' => 0.06],
        ['name' => 'Mayonesa', 'keywords' => ['mayonesa', 'alioli', 'salsa alioli'], 'category' => 'Condiments & Sauces', 'cal' => 6.80, 'pro' => 0.01, 'fat' => 0.75, 'carb' => 0.02],
        ['name' => 'Ketchup', 'keywords' => ['ketchup', 'catsup'], 'category' => 'Condiments & Sauces', 'cal' => 1.12, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.27],
        ['name' => 'Salsa', 'keywords' => ['salsa', 'salsa de tomate', 'salsa de soya', 'salsa roja', 'salsa verde', 'salsa ranchera'], 'category' => 'Condiments & Sauces', 'cal' => 1.00, 'pro' => 0.02, 'fat' => 0.01, 'carb' => 0.20],
        ['name' => 'Tabasco', 'keywords' => ['tabasco', 'sriracha'], 'category' => 'Condiments & Sauces', 'cal' => 0.93, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.23],
        ['name' => 'Aderezo', 'keywords' => ['aderezo', 'salsa césar', 'salsa tartara'], 'category' => 'Condiments & Sauces', 'cal' => 1.50, 'pro' => 0.01, 'fat' => 0.10, 'carb' => 0.15],
        ['name' => 'Salsa bechamel', 'keywords' => ['salsa bechamel', 'salsa de queso', 'salsa de champiñones'], 'category' => 'Condiments & Sauces', 'cal' => 1.10, 'pro' => 0.03, 'fat' => 0.08, 'carb' => 0.08],
        ['name' => 'Salsa pesto', 'keywords' => ['salsa pesto', 'pesto'], 'category' => 'Condiments & Sauces', 'cal' => 4.18, 'pro' => 0.08, 'fat' => 0.42, 'carb' => 0.06],
        ['name' => 'Salsa boloñesa', 'keywords' => ['salsa boloñesa', 'salsa bolognesa'], 'category' => 'Condiments & Sauces', 'cal' => 1.50, 'pro' => 0.06, 'fat' => 0.10, 'carb' => 0.12],
        ['name' => 'Salsa de pescado', 'keywords' => ['salsa de pescado', 'salsa de ostras', 'worcestershire'], 'category' => 'Condiments & Sauces', 'cal' => 0.80, 'pro' => 0.03, 'fat' => 0.03, 'carb' => 0.10],
        ['name' => 'Salsa brava', 'keywords' => ['salsa brava'], 'category' => 'Condiments & Sauces', 'cal' => 1.20, 'pro' => 0.02, 'fat' => 0.08, 'carb' => 0.10],
        ['name' => 'Salsa de ajo', 'keywords' => ['salsa de ajo'], 'category' => 'Condiments & Sauces', 'cal' => 1.00, 'pro' => 0.02, 'fat' => 0.01, 'carb' => 0.20],
        ['name' => 'Salsa de cebolla', 'keywords' => ['salsa de cebolla'], 'category' => 'Condiments & Sauces', 'cal' => 0.80, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.19],
        ['name' => 'Salsa de piña', 'keywords' => ['salsa de piña', 'salsa de mango', 'salsa de maracuyá'], 'category' => 'Condiments & Sauces', 'cal' => 1.00, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.25],
        ['name' => 'Tomate frito', 'keywords' => ['tomate frito', 'tomate triturado', 'tomate en conserva'], 'category' => 'Condiments & Sauces', 'cal' => 0.80, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.18],
        ['name' => 'Salsa de chocolate', 'keywords' => ['salsa de chocolate', 'salsa de caramelo'], 'category' => 'Condiments & Sauces', 'cal' => 2.80, 'pro' => 0.02, 'fat' => 0.05, 'carb' => 0.60],
        ['name' => 'Salsa de frutilla', 'keywords' => ['salsa de frutilla', 'salsa de fresa', 'salsa de mora', 'salsa de frambuesa'], 'category' => 'Condiments & Sauces', 'cal' => 1.00, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.25],

        // Sweeteners
        ['name' => 'Miel', 'keywords' => ['miel', 'miel de maple', 'syrup', 'jarabe'], 'category' => 'Sweeteners', 'cal' => 3.04, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.82],
        ['name' => 'Azúcar', 'keywords' => ['azúcar', 'azucar', 'azúcar morena', 'azúcar rubia', 'azúcar flor', 'panela'], 'category' => 'Sweeteners', 'cal' => 3.87, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 1.00],
        ['name' => 'Stevia', 'keywords' => ['stevia'], 'category' => 'Sweeteners', 'cal' => 0.00, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],
        ['name' => 'Chocolate', 'keywords' => ['chocolate', 'cacao', 'cacao en polvo', 'chips de chocolate'], 'category' => 'Sweeteners', 'cal' => 5.46, 'pro' => 0.05, 'fat' => 0.31, 'carb' => 0.61],
        ['name' => 'Vainilla', 'keywords' => ['vainilla', 'esencia de vainilla'], 'category' => 'Sweeteners', 'cal' => 2.88, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.13],
        ['name' => 'Dulce', 'keywords' => ['dulce', 'dulce de leche', 'dulce de membrillo', 'dulce de guayaba'], 'category' => 'Sweeteners', 'cal' => 3.50, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.90],

        // Beverages
        ['name' => 'Café', 'keywords' => ['café', 'cafe'], 'category' => 'Beverages', 'cal' => 0.01, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],
        ['name' => 'Té', 'keywords' => ['té', 'te'], 'category' => 'Beverages', 'cal' => 0.01, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],
        ['name' => 'Vino', 'keywords' => ['vino', 'vino blanco', 'vino tinto'], 'category' => 'Beverages', 'cal' => 0.83, 'pro' => 0.01, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Cerveza', 'keywords' => ['cerveza'], 'category' => 'Beverages', 'cal' => 0.43, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.03],
        ['name' => 'Ron', 'keywords' => ['ron', 'aguardiente', 'brandy'], 'category' => 'Beverages', 'cal' => 2.31, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],
        ['name' => 'Agua', 'keywords' => ['agua'], 'category' => 'Beverages', 'cal' => 0.00, 'pro' => 0.00, 'fat' => 0.00, 'carb' => 0.00],

        // Baked Goods
        ['name' => 'Pan integral', 'keywords' => ['pan integral'], 'category' => 'Baked Goods', 'cal' => 2.47, 'pro' => 0.13, 'fat' => 0.03, 'carb' => 0.41],
        ['name' => 'Pan de molde', 'keywords' => ['pan de molde', 'pan baguette', 'pan de perrito', 'pan de hamburguesa'], 'category' => 'Baked Goods', 'cal' => 2.65, 'pro' => 0.09, 'fat' => 0.03, 'carb' => 0.49],
        ['name' => 'Croissant', 'keywords' => ['croissant', 'magdalena', 'donut', 'muffin', 'cupcake'], 'category' => 'Baked Goods', 'cal' => 4.06, 'pro' => 0.08, 'fat' => 0.21, 'carb' => 0.46],
        ['name' => 'Bizcocho', 'keywords' => ['bizcocho', 'tarta', 'pastel', 'torta', 'brownie', 'flan'], 'category' => 'Baked Goods', 'cal' => 3.71, 'pro' => 0.06, 'fat' => 0.15, 'carb' => 0.54],
        ['name' => 'Pancake', 'keywords' => ['pancake', 'waffle', 'panqueque'], 'category' => 'Baked Goods', 'cal' => 2.27, 'pro' => 0.06, 'fat' => 0.10, 'carb' => 0.28],
        ['name' => 'Tostada', 'keywords' => ['tostada'], 'category' => 'Baked Goods', 'cal' => 2.93, 'pro' => 0.09, 'fat' => 0.04, 'carb' => 0.55],
        ['name' => 'Churros', 'keywords' => ['churros', 'buñuelos', 'pestiños'], 'category' => 'Baked Goods', 'cal' => 4.06, 'pro' => 0.04, 'fat' => 0.22, 'carb' => 0.48],
        ['name' => 'Turrón', 'keywords' => ['turrón', 'mazapán', 'mantecado', 'polvorón', 'alfajor'], 'category' => 'Baked Goods', 'cal' => 5.20, 'pro' => 0.08, 'fat' => 0.28, 'carb' => 0.55],
        ['name' => 'Sopa', 'keywords' => ['sopa', 'gazpacho', 'salmorejo', 'crema de'], 'category' => 'Baked Goods', 'cal' => 0.60, 'pro' => 0.02, 'fat' => 0.02, 'carb' => 0.09],
        ['name' => 'Migas', 'keywords' => ['migas', 'gachas'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.07, 'fat' => 0.10, 'carb' => 0.35],
        ['name' => 'Puré de patatas', 'keywords' => ['puré de patatas', 'pure de patatas', 'puré de papa'], 'category' => 'Baked Goods', 'cal' => 1.13, 'pro' => 0.02, 'fat' => 0.04, 'carb' => 0.17],
        ['name' => 'Roscón', 'keywords' => ['roscón', 'rosca'], 'category' => 'Baked Goods', 'cal' => 3.50, 'pro' => 0.07, 'fat' => 0.15, 'carb' => 0.50],
        ['name' => 'Torrija', 'keywords' => ['torrija', 'torrijas'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.06, 'fat' => 0.10, 'carb' => 0.33],
        ['name' => 'Leche frita', 'keywords' => ['leche frita'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.05, 'fat' => 0.10, 'carb' => 0.35],
        ['name' => 'Pionono', 'keywords' => ['pionono', 'brazo de gitano'], 'category' => 'Baked Goods', 'cal' => 3.00, 'pro' => 0.05, 'fat' => 0.12, 'carb' => 0.42],
        ['name' => 'Tarta de queso', 'keywords' => ['tarta de queso', 'cheesecake'], 'category' => 'Baked Goods', 'cal' => 3.21, 'pro' => 0.06, 'fat' => 0.21, 'carb' => 0.29],
        ['name' => 'Tarta de Santiago', 'keywords' => ['tarta de santiago'], 'category' => 'Baked Goods', 'cal' => 4.50, 'pro' => 0.10, 'fat' => 0.28, 'carb' => 0.42],
        ['name' => 'Coca', 'keywords' => ['coca', 'coca de recapte', 'coca de san juan'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.06, 'fat' => 0.08, 'carb' => 0.40],
        ['name' => 'Torta de aceite', 'keywords' => ['torta de aceite'], 'category' => 'Baked Goods', 'cal' => 4.50, 'pro' => 0.05, 'fat' => 0.25, 'carb' => 0.50],
        ['name' => 'Empanada gallega', 'keywords' => ['empanada gallega', 'empanada de carne', 'empanada de atún'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.08, 'fat' => 0.12, 'carb' => 0.27],
        ['name' => 'Pastel de carne', 'keywords' => ['pastel de carne'], 'category' => 'Baked Goods', 'cal' => 2.50, 'pro' => 0.10, 'fat' => 0.15, 'carb' => 0.20],
        ['name' => 'Tarta de manzana', 'keywords' => ['tarta de manzana', 'tarta de chocolate'], 'category' => 'Baked Goods', 'cal' => 2.80, 'pro' => 0.03, 'fat' => 0.13, 'carb' => 0.38],
        ['name' => 'Arroz con leche', 'keywords' => ['arroz con leche'], 'category' => 'Baked Goods', 'cal' => 1.30, 'pro' => 0.03, 'fat' => 0.02, 'carb' => 0.26],
        ['name' => 'Crema catalana', 'keywords' => ['crema catalana'], 'category' => 'Baked Goods', 'cal' => 2.20, 'pro' => 0.04, 'fat' => 0.10, 'carb' => 0.30],
        ['name' => 'Salsa de chocolate', 'keywords' => ['salsa de chocolate'], 'category' => 'Condiments & Sauces', 'cal' => 2.80, 'pro' => 0.02, 'fat' => 0.05, 'carb' => 0.60],
    ];

    public static function loadDictionary(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$dictionary = [];
        self::$keywordIndex = [];

        foreach (self::CORE_DICTIONARY as $item) {
            $key = Str::slug($item['name']);
            self::$dictionary[$key] = $item;

            foreach ($item['keywords'] as $keyword) {
                self::$keywordIndex[mb_strtolower($keyword)] = $item;
            }
        }

        // Sort by keyword length descending for better matching
        uksort(self::$keywordIndex, fn($a, $b) => strlen($b) - strlen($a));

        self::$loaded = true;
    }

    public static function findBestMatch(string $name): ?array
    {
        self::loadDictionary();

        $nameLower = mb_strtolower(trim($name));
        if (empty($nameLower)) {
            return null;
        }

        // Direct keyword match
        foreach (self::$keywordIndex as $keyword => $item) {
            if (Str::contains($nameLower, $keyword)) {
                return $item;
            }
        }

        // Direct slug match
        $slug = Str::slug($nameLower);
        if (isset(self::$dictionary[$slug])) {
            return self::$dictionary[$slug];
        }

        // Fuzzy: check if any keyword is contained in the name
        foreach (self::$keywordIndex as $keyword => $item) {
            if (strpos($nameLower, $keyword) !== false) {
                return $item;
            }
        }

        return null;
    }

    public static function getCategoryFallback(string $category): array
    {
        return self::DEFAULT_FALLBACKS[$category] ?? ['cal' => 1.0, 'pro' => 0.05, 'fat' => 0.05, 'carb' => 0.10];
    }

    public static function getKnownIngredientNames(): array
    {
        self::loadDictionary();
        return array_keys(self::$keywordIndex);
    }

    public static function getDictionary(): array
    {
        self::loadDictionary();
        return self::$dictionary;
    }

    public static function getCategoryForName(string $name): string
    {
        $match = self::findBestMatch($name);
        if ($match) {
            return $match['category'];
        }

        return self::guessCategoryFromKeywords($name);
    }

    private static function guessCategoryFromKeywords(string $name): string
    {
        $name = mb_strtolower($name);
        $keywords = [
            'Proteins' => ['pollo', 'carne', 'cerdo', 'pavo', 'jamón', 'atún', 'salmón', 'bacalao', 'camarón', 'langostino', 'huevo', 'chorizo', 'salchicha', 'pescado', 'res', 'ternera', 'cordero', 'conejo', 'pato', 'hamburguesa', 'milanesa', 'croqueta', 'albóndiga'],
            'Vegetables' => ['tomate', 'cebolla', 'ajo', 'zanahoria', 'papa', 'lechuga', 'pepino', 'pimiento', 'brócoli', 'espinaca', 'calabacín', 'berenjena', 'col', 'champiñón', 'puerro', 'remolacha', 'alcachofa', 'nopal', 'aguacate', 'cilantro', 'perejil', 'albahaca', 'romero', 'tomillo', 'jengibre', 'acelga'],
            'Fruits' => ['manzana', 'plátano', 'naranja', 'limón', 'fresa', 'mango', 'piña', 'uva', 'pera', 'cereza', 'sandía', 'melón', 'papaya', 'coco', 'guayaba', 'maracuyá', 'toronja', 'higo', 'ciruela', 'mandarina', 'kiwi', 'frambuesa', 'arándano', 'mora', 'granada', 'lúcuma', 'aguaymanto', 'tamarindo'],
            'Grains & Pasta' => ['arroz', 'pasta', 'espagueti', 'fideos', 'harina', 'pan', 'tortilla', 'arepa', 'avena', 'maíz', 'galleta', 'pizza', 'empanada', 'patata', 'papa', 'batata', 'maicena', 'bizcocho', 'tarta', 'pastel', 'croissant', 'churro', 'pancake', 'waffle', 'sopa', 'migas', 'pionono'],
            'Dairy' => ['leche', 'queso', 'mantequilla', 'manteca', 'crema', 'yogur', 'helado', 'dulce de leche', 'margarina', 'nata', 'requesón', 'ricotta'],
            'Fats & Oils' => ['aceite', 'grasa', 'manteca', 'aceituna', 'oliva'],
            'Nuts & Seeds' => ['almendra', 'nuez', 'maní', 'cacahuate', 'pistacho', 'avellana', 'castaña', 'ajonjolí', 'sésamo', 'linaza', 'chía', 'pepita', 'piñón', 'pasas', 'coco'],
            'Legumes' => ['frijol', 'lenteja', 'garbanzo', 'habichuela', 'alubia', 'poroto', 'soya', 'tofu', 'hummus', 'haba', 'chícharo'],
            'Spices & Herbs' => ['sal', 'pimienta', 'comino', 'orégano', 'canela', 'pimentón', 'paprika', 'nuez moscada', 'achiote', 'curry', 'clavo', 'azafrán', 'cúrcuma', 'chile', 'ají', 'cardamomo', 'anís', 'jengibre'],
            'Condiments & Sauces' => ['mostaza', 'mayonesa', 'ketchup', 'salsa', 'tabasco', 'aderezo', 'alioli', 'vinagre', 'pesto', 'soya', 'worcestershire', 'sriracha', 'tomate frito', 'tomate triturado'],
            'Sweeteners' => ['azúcar', 'miel', 'stevia', 'jarabe', 'syrup', 'chocolate', 'cacao', 'vainilla', 'dulce', 'panela'],
            'Beverages' => ['café', 'té', 'vino', 'cerveza', 'ron', 'agua', 'jugo', 'zumo', 'refresco', 'gaseosa'],
            'Baked Goods' => ['pan', 'torta', 'tarta', 'pastel', 'bizcocho', 'galleta', 'croissant', 'magdalena', 'donut', 'pancake', 'waffle', 'churro', 'turrón', 'mazapán', 'alfajor', 'buñuelo', 'roscón', 'empanada', 'pionono'],
            'Seafood' => ['marisco', 'salmón', 'bacalao', 'atún', 'sardina', 'caballa', 'mejillón', 'almeja', 'pulpo', 'calamar', 'langosta', 'cangrejo', 'vieira', 'gamba', 'camarón', 'ostra', 'lenguado', 'merluza', 'rape'],
        ];

        foreach ($keywords as $category => $words) {
            foreach ($words as $word) {
                if (Str::contains($name, $word)) {
                    return $category;
                }
            }
        }

        return 'Processed Foods';
    }

    public static function getMacrosForName(string $name): array
    {
        $match = self::findBestMatch($name);
        if ($match) {
            return [
                'name' => $match['name'],
                'category' => $match['category'],
                'calories_per_gram' => $match['cal'],
                'protein_per_gram' => $match['pro'],
                'fat_per_gram' => $match['fat'],
                'carbs_per_gram' => $match['carb'],
                'matched' => true,
            ];
        }

        $category = self::guessCategoryFromKeywords($name);
        $fallback = self::getCategoryFallback($category);

        return [
            'name' => $name,
            'category' => $category,
            'calories_per_gram' => $fallback['cal'],
            'protein_per_gram' => $fallback['pro'],
            'fat_per_gram' => $fallback['fat'],
            'carbs_per_gram' => $fallback['carb'],
            'matched' => false,
        ];
    }
}
