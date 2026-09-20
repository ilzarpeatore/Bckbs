# Integración con FatSecret Platform API — plan técnico

## 0. Alcance decidido (leer antes de tocar nada)

Tras investigar los términos de uso de FatSecret a fondo (ver hilo de decisión, 2026-09-19), se descartó importar **recetas** completas (texto, pasos, imágenes) porque:
- Su Terms of Use solo permite guardar de forma indefinida los **IDs** (`food_id`, `recipe_id`...) — el resto del contenido (texto, imágenes, valores nutricionales) hay que volver a pedirlo cada 24h, no se puede "poseer" una copia permanente sin ese refresco.
- Se reservan el derecho de meter publicidad en las imágenes que sirven.
- Sin plan Premier (de pago, presupuesto a medida), el dataset de recetas/alimentos por defecto es solo EEUU (`region=US`).
- Traducir su contenido (recetas/pasos) sin permiso explícito viola la cláusula de "no modificar ni alterar" el contenido.

**Decisión final:** NO se importan recetas de FatSecret. Las recetas se siguen creando 100% a mano en el panel (título, pasos, fotos — sin cambios en ese flujo). Lo único que se integra es **la base de datos de alimentos genéricos** (`foods.search` / `food.get`) para autocompletar la nutrición por gramo de un `Ingredient` al crearlo — el dato numérico (calorías/proteína/grasa/carbos por gramo), no el nombre ni ninguna otra cosa de FatSecret. El nombre en español del ingrediente lo escribe el coach a mano (para eso se pidió permiso explícito de traducción, ya concedido).

Esto reduce drásticamente el riesgo legal (no se toca contenido de receta ni imágenes de FatSecret) y encaja con la arquitectura ya existente sin apenas fricción — ver sección 2.

**ACTUALIZADO 2026-09-20 (dos veces):**
1. El alcance de arriba quedó ampliado el mismo día -- sí se usan recetas reales de FatSecret (con foto), pero **siempre en vivo/cache-aside de corta duración, nunca importadas de forma permanente** -- ver sección 9, es la ampliación real, no una contradicción de lo de arriba (el punto de "no importar de forma permanente" se mantiene igual).
2. **El punto "traducir su contenido... viola la cláusula" (línea 9) ya NO aplica a recetas/instrucciones** -- el usuario obtuvo permiso explícito de FatSecret para traducir también el contenido de receta (nombre + instrucciones), no solo nombres de ingrediente suelto como decía originalmente este párrafo. Ver sección 10 para el diseño de la traducción (pendiente de credenciales de un servicio de traducción, sesión cerrada el 2026-09-20 sin implementarlo todavía).

## 1. Cuenta y credenciales de FatSecret

- Empezar con el plan **Basic** (gratis, autoregistro, 5.000 llamadas/día) — de sobra para este uso (búsquedas puntuales del coach + un refresco mensual en bloque). Premier solo haría falta si en el futuro se quiere matching de productos de marca españoles concretos.
- Auth: usar **OAuth 2.0 client credentials**, no OAuth 1.0 (ambos están soportados en `foods.search`/`food.get`, pero OAuth2 es mucho más simple — token Bearer normal, sin firmar cada request con HMAC).
  - Token endpoint: `https://oauth.fatsecret.com/connect/token`
  - Request: `POST`, header `Authorization: Basic base64(CLIENT_ID:CLIENT_SECRET)`, `Content-Type: application/x-www-form-urlencoded`, body `grant_type=client_credentials&scope=basic`
  - Respuesta: `{ access_token, token_type: "Bearer", expires_in: 86400 }` — dura 24h.
  - Llamadas reales: `POST https://platform.fatsecret.com/rest/server.api` con `Authorization: Bearer <access_token>` + parámetros del método (`method=foods.search&search_expression=...&format=json`).
- **Importante — requisito de IP fija:** FatSecret exige pedir los tokens OAuth2 desde una IP fija que se registra al crear las credenciales (rechaza peticiones desde IPs no listadas). Esto significa:
  - **Todas las llamadas a FatSecret tienen que salir desde el VPS de producción** (IP fija conocida), nunca desde GitHub Actions (IP efímera de cada runner) ni desde el navegador del admin directamente.
  - Antes de crear las credenciales en FatSecret, sacar la IP pública saliente real del VPS (`curl ifconfig.me` desde el VPS, no asumir la que aparece en otro sitio) y dársela a FatSecret al darse de alta.
- Guardar `FATSECRET_CLIENT_ID` / `FATSECRET_CLIENT_SECRET` en el `.env` del VPS (nunca en el repo) — mismo patrón que el resto de credenciales sensibles del proyecto.

## 2. Cambios en base de datos (Bckbs)

Migración nueva `add_fatsecret_fields_to_ingredients_table`, sobre la tabla `ingredients` ya existente (`database/migrations/..._create_ingredients_table.php`):

```php
Schema::table('ingredients', function (Blueprint $table) {
    $table->unsignedBigInteger('fatsecret_food_id')->nullable()->unique()->after('carbs_per_gram');
    $table->unsignedBigInteger('fatsecret_serving_id')->nullable()->after('fatsecret_food_id');
    // qué ración de FatSecret se usó para calcular el per-gram (ver 4.2) --
    // hace falta guardarla para poder recalcular igual en el refresco mensual,
    // FatSecret puede devolver varias raciones por alimento y no todas sirven.
    $table->timestamp('fatsecret_synced_at')->nullable()->after('fatsecret_serving_id');
});
```

**NO** añadir una columna para guardar el payload crudo de FatSecret de forma permanente (nombre en inglés, descripción, etc.) — eso sería justo el tipo de contenido que su ToS dice que no se puede cachear más de 24h. Solo se persisten para siempre: el `food_id`/`serving_id` (permitido indefinidamente según su política) y los 4 números ya calculados (`calories_per_gram`, etc., que son datos derivados vuestros, no "Content" de FatSecret verbatim).

`app/Models/Ingredient.php` — añadir a `$fillable`:
```php
protected $fillable = [
    'title', 'slug', 'ingredient_category_id', 'calories_per_gram', 'protein_per_gram',
    'fat_per_gram', 'carbs_per_gram', 'density', 'status',
    'fatsecret_food_id', 'fatsecret_serving_id', 'fatsecret_synced_at', // nuevo
];
```
y a `$casts`: `'fatsecret_synced_at' => 'datetime'`.

No hace falta tabla nueva de log — con `fatsecret_synced_at` + los logs normales de Laravel (`Log::info` en el comando de refresco, sección 6) es suficiente para depurar sin sobre-ingenería.

## 3. `app/Http/Controllers/API/Admin/IngredientController.php` — cambios mínimos

Es un `BaseController` genérico (mismo patrón que `PushNotificationController`, `WorkoutTemplateController`, etc. — ver `getValidationRules()`/`store()`/`update()` heredados). El guardado de un ingrediente YA acepta `calories_per_gram` etc. directamente, así que **no hace falta un endpoint nuevo de "vincular"** — el flujo de importar desde FatSecret vive entero en el frontend (busca → previsualiza → calcula → el admin confirma/traduce el título → se envía todo junto al `POST/PUT /admin/ingredients` de siempre, con los 2 campos nuevos incluidos).

Solo hay que ampliar `getValidationRules()`:
```php
'fatsecret_food_id'    => 'nullable|integer',
'fatsecret_serving_id' => 'nullable|integer',
'fatsecret_synced_at'  => 'nullable|date',
```

## 4. Servicio nuevo: cliente de FatSecret + 2 endpoints proxy

### 4.1 `app/Services/FatSecret/FatSecretClient.php`

Responsabilidades:
- Gestionar el token OAuth2: pedirlo, cachearlo (`Cache::remember('fatsecret_oauth_token', 86340, ...)` — 60s de margen sobre las 86400 reales para no usarlo justo al expirar), renovarlo solo si caduca.
- `searchFoods(string $query, string $region = 'US'): array` → `method=foods.search`.
- `getFood(int $foodId, string $region = 'US'): array` → `method=food.get.v4` (v4 es la versión no-deprecada actual, incluye imágenes/alérgenos si se necesitan más adelante).
- Manejo de errores: si FatSecret no responde o da error, lanzar una excepción propia (`FatSecretUnavailableException`) que los endpoints de abajo capturan para devolver un 503 claro — nunca debe romper ni bloquear el guardado normal de un ingrediente manual (esto es una AYUDA opcional, el admin siempre puede seguir rellenando a mano si la API falla).

### 4.2 Cálculo del per-gram — la parte que más fácil es meter la pata

Cada alimento de FatSecret trae varias "raciones" (`servings`), cada una con su propio `calories`/`protein`/`fat`/`carbohydrate` y, cuando está disponible, `metric_serving_amount` + `metric_serving_unit` (gramos o mililitros equivalentes de ESA ración concreta).

**Regla obligatoria: NUNCA coger la primera ración de la lista sin comprobar su `metric_serving_unit`.** Muchos alimentos traen raciones tipo "1 taza", "1 unidad", "1 loncha" cuyo equivalente en gramos puede no venir, o venir en `oz` en vez de `g`.

Algoritmo:
1. De todas las `servings` devueltas, quedarse solo con las que tengan `metric_serving_unit == 'g'` (peso) directamente, o `'ml'` si el ingrediente es líquido (convertir con el `density` del `Ingredient`, campo que ya existe).
2. Si no hay ninguna ración en gramos/ml, **no autocompletar nada** — mostrar en el admin "Esta ración no se puede convertir automáticamente, introduce los valores a mano", nunca adivinar con una conversión ambigua tipo "1 loncha ≈ 20g" inventada por el frontend.
3. Con una ración válida: `calories_per_gram = serving.calories / serving.metric_serving_amount` (y análogo para protein/fat/carbohydrate → `carbs_per_gram`).
4. Guardar qué `serving_id` se usó (`fatsecret_serving_id`) — el refresco mensual (sección 6) tiene que recalcular con la MISMA ración, no con la que FatSecret decida que es "por defecto" ese día (ese flag, `is_default`, es además Premier-exclusivo, no fiarse de él en Basic).

### 4.3 Endpoints nuevos (`routes/api.php`, dentro del grupo `admin` con `auth:sanctum` + `admin.api`, igual que el resto)

```php
Route::get('fatsecret/foods/search', [API\Admin\FatSecretController::class, 'search']);
Route::get('fatsecret/foods/{food_id}', [API\Admin\FatSecretController::class, 'show']);
```

`FatSecretController::search()`: valida `q` (string, requerido), llama a `FatSecretClient::searchFoods()`, devuelve solo `food_id`/`food_name`/`food_type`/`brand_name` (lista simplificada, sin nutrición todavía — mantiene la respuesta ligera para el autocompletado).

`FatSecretController::show($food_id)`: llama a `FatSecretClient::getFood()`, aplica el algoritmo de la sección 4.2 en el backend (no en el frontend — así el mismo cálculo se puede reutilizar tal cual en el comando de refresco de la sección 6), y devuelve al frontend algo ya masticado:
```json
{
  "food_id": 12345,
  "food_name_en": "Chicken Breast, Raw",
  "serving_id": 6789,
  "can_autocalculate": true,
  "calories_per_gram": 1.65,
  "protein_per_gram": 0.31,
  "fat_per_gram": 0.036,
  "carbs_per_gram": 0.0
}
```
Si `can_autocalculate` es `false` (ninguna ración convertible, ver 4.2 punto 2), el frontend debe mostrar los valores en blanco y dejar que el admin los rellene a mano — igual que hoy, solo que ya sabe que existe ese `food_id` para vincularlo de todas formas.

## 5. Cambios en el admin (`bstronger-admin`)

`src/views/recipes/IngredientView.tsx` usa hoy `CrudView` genérico (igual que `PushNotificationView.tsx` antes del fix de esta sesión). Mismo patrón que se usó ahí: **no tocar `CrudView.tsx`** (lo usan muchas otras pantallas, evitar riesgo de regresión), envolver con una tarjeta propia encima.

Nuevo componente, ej. `FatSecretImportCard` dentro de `IngredientView.tsx` (o archivo aparte si crece):
1. Input de búsqueda → `GET /admin/fatsecret/foods/search?q=...` (debounce, igual que cualquier buscador ya existente en el panel).
2. Lista de resultados (nombre en inglés + tipo Genérico/Marca — **priorizar visualmente "Generic" sobre "Brand"**, un genérico es el caso de uso real aquí; un producto de marca americana no tiene sentido para una receta española).
3. Al elegir uno → `GET /admin/fatsecret/foods/{food_id}` → previsualización con los 4 valores calculados (o aviso de "rellena a mano" si `can_autocalculate: false`).
4. Campo de texto para que el admin escriba el **título en español** del ingrediente (obligatorio, no se autorrellena con el inglés).
5. Botón "Crear ingrediente" → arma el payload completo (`title` en español + los 4 `*_per_gram` + `fatsecret_food_id`/`fatsecret_serving_id`/`fatsecret_synced_at: now()`) y lo manda al `POST /admin/ingredients` de siempre (el mismo que ya usa `CrudView`, sin cambios ahí).
6. En la tabla de ingredientes ya existente, añadir una columna/badge "Vinculado a FatSecret" (si `fatsecret_food_id` no es null) para que el admin sepa cuáles se pueden resincronizar.

Meterlo detrás de un feature flag en `constants/featureFlags.ts` (mismo mecanismo que ya usa `ScreenExplorer`) mientras se prueba, para poder apagarlo sin desplegar si algo falla en producción.

## 6. Refresco periódico (cumplir la regla de las 24h de forma barata)

Nuevo comando Artisan `app/Console/Commands/RefreshFatSecretIngredients.php`:
```php
Ingredient::whereNotNull('fatsecret_food_id')->chunk(50, function ($ingredients) {
    foreach ($ingredients as $ingredient) {
        // mismo cálculo que FatSecretController::show(), reutilizar el mismo
        // método del servicio para no duplicar la lógica de la sección 4.2
        $result = $fatSecretClient->getFood($ingredient->fatsecret_food_id);
        // ... recalcular con fatsecret_serving_id guardado, no con la ración "default" ...
        if (abs($nuevo_calories_per_gram - $ingredient->calories_per_gram) / max($ingredient->calories_per_gram, 0.01) > 0.05) {
            Log::info("FatSecret refresh: {$ingredient->title} cambió más de un 5% en calorías/gramo, revisar.");
        }
        $ingredient->update([...]);
    }
});
```
Programarlo en `routes/console.php` (o `App\Console\Kernel` si el proyecto sigue esa convención) con `->monthly()` — un alimento genérico casi nunca cambia de verdad, mensual sobra de margen y son pocas llamadas (una por ingrediente vinculado, muy lejos del límite de 5.000/día de Basic).

## 7. Cosas SIN confirmar todavía — verificar con una llamada real antes de dar esto por cerrado

La documentación pública de FatSecret tiene alguna imprecisión/contradicción entre fuentes en estos puntos exactos. No dar nada de esto por hecho hasta probarlo con la cuenta Basic real (gratis, se puede crear ya):

1. **¿`foods.search` devuelve ya la nutrición completa embebida, o solo identifica el alimento y hace falta `food.get` aparte?** Encontré descripciones contradictorias de esto en la documentación. El plan de arriba asume que search es solo para identificar (food_id/nombre/tipo) y `food.get` trae la nutrición real — si resulta que search ya trae todo, se puede simplificar el paso 2 de la UI (saltarse la llamada a `show()` y calcular directo desde los resultados de búsqueda).
2. ~~¿Los valores nutricionales de un alimento "Generic" realmente apenas cambian entre `region=US` y `region=ES`?~~ **RESUELTO 2026-09-20, ver sección 12** — no cambia nada, `region`/`language` no tiene ningún efecto observable en esta cuenta.
3. **Confirmar por escrito con FatSecret** (aprovechando el email/contacto que ya dio el permiso de traducción) que guardar de forma indefinida los 4 valores numéricos por `food_id` para vuestros propios ingredientes también entra dentro de lo permitido — el permiso que ya tenéis cubre traducir, pero conviene tener explícito también esto, ya que es el pilar central de todo el diseño de la sección 2.
4. **Si en el futuro se activa `include_food_images` en `food.get.v4`** (fotos de alimentos genéricos, no de recetas) para mostrar un icono bonito del ingrediente — confirmar si la cláusula de "nos reservamos meter publicidad en las imágenes" aplica también a estas fotos de alimento genérico o es específica de fotos de receta. Mientras tanto, NO activar `include_food_images` — no hace falta para el objetivo actual (solo nutrición).

## 9. Ampliación de alcance (2026-09-19, tras diseño con el usuario): recetas vía proxy en vivo

Además del punto 0 (nutrición de ingredientes), se decidió TAMBIÉN permitir que el coach (panel admin) y el propio cliente (app) busquen y asignen **recetas reales de FatSecret** (con su foto e ingredientes reales) -- pero nunca importadas/guardadas de forma permanente, siempre pedidas en vivo respetando su límite de 24h. Esto NO cambia la decisión del punto 0 sobre imágenes/contenido de FatSecret -- se acepta el riesgo de "pueden meter publicidad en las imágenes" y de mostrar contenido en inglés/EEUU en Basic, a cambio de fotos que sí coinciden con la receta real (el problema original con el banco de imágenes).

**Cómo funciona (confirmado contra la documentación real de `recipes.search`/`recipe.get`):**
- `recipes.search` (`FatSecretRecipeService::search()`) devuelve, en 1 sola llamada, hasta 50 resultados YA con `recipe_image`, nombre y `recipe_nutrition` (calorías/macros resumen) embebidos -- pintar la parrilla de resultados NO cuesta una llamada por receta.
- `recipe.get` (`FatSecretRecipeService::getOrRefresh()`) trae el detalle completo (pasos, ingredientes con cantidades) -- cacheado en `fatsecret_recipe_cache` con TTL de `FatSecretRecipeCache::TTL_HOURS` (6h, deliberadamente muy por debajo del límite real de 24h). Nunca se llama sin pasar por este cache-aside.
- `daily_plan_recipes.fatsecret_recipe_id` (nullable) es el único dato que se guarda de forma permanente para una comida asignada desde FatSecret -- igual que `recipe_id` para una receta propia, exactamente uno de los dos debe estar relleno. `calories/protein/fats/carbs` se snapshotean en la propia fila al momento de asignar (mismo patrón que ya usaba esta tabla).
- `DailyPlanRecipeResource::resolveRecipePreview()` normaliza AMBOS orígenes al mismo shape (`id`, `title`, `recipe_image`, macros) para que la app no necesite ninguna rama distinta -- para el caso FatSecret hace una lectura PASIVA de `fatsecret_recipe_cache` (nunca fuerza un refresco ahí, evita que listar un calendario semanal dispare 21 llamadas reales). El detalle completo sí pasa siempre por `getOrRefresh()`.

**Buscador desde la app del cliente** (pedido explícito: sustituir una comida asignada por otra de FatSecret): mismo servicio, endpoints separados (`API\FatSecretController` vs `API\Admin\FatSecretController`), con `throttle:100,1440` por cliente como red de seguridad -- el cupo real (5.000/día en Basic) es compartido entre TODOS los coaches y clientes, así que si el buscador se usa de verdad, monitorizar consumo real antes de decidir si hace falta Premier (no asumirlo de antemano).

**Estado real de la limpieza de datos (2026-09-19):** se decidió partir de cero en vez de vincular los datos existentes -- `ingredients`, `recipes`, `recipe_ingredients`, `recipe_steps`, `meal_plan_template_items`, `user_favourite_recipes` y las `daily_plan_recipes` que las referenciaban se vaciaron por completo (backup completo hecho antes, ver `/root/db_backups/` en el VPS y copia local). Solo la cuenta demo (`demo@bestronger.app`) tenía algo asignado, ningún cliente real se vio afectado.

## 8. Checklist de implementación (orden recomendado)

- [x] Cuenta Basic creada, IP del VPS (`212.227.82.45`) registrada, `FATSECRET_CLIENT_ID`/`FATSECRET_CLIENT_SECRET` en el `.env` del VPS + `config:cache` refrescado. **Confirmado en vivo el 2026-09-19/20** contra la API real (no solo en teoría).
- [ ] Responder a FatSecret confirmando también el punto 7.3 (guardado indefinido de los 4 valores numéricos por food_id) -- sigue pendiente, no bloqueante para lo ya construido.
- [x] Migraciones + cambios en `Ingredient`/`DailyPlanRecipe` models + tabla/modelo `FatSecretRecipeCache` (sección 2 y 9).
- [x] `FatSecretClient` + `FatSecretFoodService` + `FatSecretRecipeService` -- **probado contra la API real** (`foods.search`, `food.get.v4`, `recipes.search.v3`, `recipe.get.v2` funcionan tal cual están codificados). Punto 7.1 resuelto en la práctica: `recipes.search` SÍ trae `recipe_image`/`recipe_nutrition` embebidos, tal como se documentó. Punto 7.2 (region US vs ES) resuelto -- ver sección 12, `region=ES` no cambia nada, se mantiene `US` por defecto.
- [x] `FatSecretController` (admin: foods+recipes; cliente: recipes) + rutas + `throttle:100,1440` en la ruta de cliente + `getValidationRules` de `IngredientController` ampliada. Verificado que la ruta de cliente cuelga del grupo `auth:sanctum` correcto (mismo nivel que `save-daily-plan-recipe`), no del prefijo `v1` ni pública.
- [x] `ClientMealPlanController::assignRecipe()` Y `DailyPlanController::saveDailyPlanRecipeData()` (el que usa el propio cliente) aceptan `recipe_id` O `fatsecret_recipe_id`.
- [x] `DailyPlanRecipeResource` normaliza ambos orígenes al mismo shape (lectura pasiva del cache para el preview, nunca fuerza refresco). Confirmado que el total diario (`recipeMealTypeResponse`, solo cuenta `is_complete=true`) suma correctamente las comidas de FatSecret exactamente igual que las propias -- no hay tratamiento especial ni bug de exclusión.
- [x] Comando `fatsecret:refresh-ingredients` -- **ejecutado en real** contra el ingrediente vinculado de prueba, actualiza `fatsecret_synced_at` correctamente sin romper nada.
- [x] **Bug real encontrado y arreglado en el proceso** (commit `9c6bed5`): `FatSecretRecipeCache` no declaraba `$table` explícito -- Eloquent adivinaba `fat_secret_recipe_caches` (snake_case+plural del nombre de la clase) en vez de `fatsecret_recipe_cache` (la tabla real de la migración). Cualquier llamada a `getOrRefresh()` daba 500 hasta el fix.
- [x] UI en `bstronger-admin`: tarjeta de importación de ingredientes en `IngredientView.tsx` + toggle "Mis recetas"/"FatSecret" en `ClientMealCalendarView.tsx` -- **probadas de extremo a extremo por API real** (crear ingrediente, buscar/asignar receta), pendiente solo una comprobación visual en navegador (la extensión de automatización no estaba disponible en la sesión que lo construyó).
- [x] UI en la app móvil (React App): toggle "Tus opciones"/"Buscar otra (FatSecret)" añadido a `assigned_meals_screen.tsx` (pantalla ya existente, pedido explícito no crear una nueva) -- código verificado con `tsc --noEmit` y probado el endpoint que consume (`save-daily-plan-recipe` con `fatsecret_recipe_id`) por API real: buscar salmón, añadirlo a la cena, marcarlo comido, confirmar que cuenta en el total del día. **Build oficial `1.0.1-appstore-fatsecret-...` lanzado, subido a App Store Connect e instalado por el usuario en un dispositivo real el 2026-09-20.**
- [x] `ingredients`/`recipes` ya no están en 0 -- hay al menos un ingrediente real de prueba (Aceite de oliva, id=429, vinculado a `food_id=34212`).
- [x] **Plantillas de plan nutricional (`meal_plan_template_items`)** -- mismo patrón `fatsecret_recipe_id` nullable + `recipe_id` pasa a nullable (vía `DB::statement`, sin `doctrine/dbal`). `addItem()`/`exportFromCalendar()`/`importToCalendar()` lo aceptan y propagan. **Bug real encontrado y arreglado** (commit `5484617`): antes `recipe_id` era NOT NULL, así que "Guardar como plantilla" con una comida de FatSecret en el rango rompía con un error de BD. Probado de extremo a extremo en producción (crear item directo, exportar desde calendario, reimportar a otra fecha).
- [x] Extraído `App\Http\Resources\Concerns\NormalizesFatSecretRecipePreview` -- trait compartido entre `DailyPlanRecipeResource` y `MealPlanTemplateItemResource` para no duplicar la normalización del shape 'recipe'.
- [x] **Bug real encontrado y arreglado (grave, confirmado en vivo por el usuario, commit `7267262`):** en `plan_screen.tsx` (la pantalla principal del Plan, no el buscador de sustitución), tanto `toggleRecipeCompletion` (marcar/desmarcar como comido) como `openRecipeDetail` (ver detalle al tocar título/imagen) exigían `recipeId` local y nunca leían `fatsecret_recipe_id` -- una comida de FatSecret se veía bien en el Plan (nombre/foto sí) pero no se podía marcar como comida (nunca contaba en el total diario) ni ver su detalle. Arreglado: `api/recipes.ts` tiene `updateDailyPlanRecipeFromFatSecret`, `plan_screen.tsx` usa el id correcto según el origen, y `diet_detail_screen.tsx` tiene un modo FatSecret nuevo (reutiliza el render de ingredientes/pasos ya existente, adaptando la respuesta a los mismos shapes). De paso: la cita de fuente de datos nutricionales de esa pantalla decía "USDA FoodData Central" siempre -- para una receta de FatSecret eso es incorrecto Y un incumplimiento real de su obligación de atribución (ver sección 0), ahora cambia a "FatSecret" cuando corresponde. Favorito oculto en modo FatSecret (no aplica).
- [ ] **Encontrado, NO arreglado todavía:** `DailyPlanShoppingListService::consolidate()` hace `if (!$recipe) { continue; }` -- una comida de FatSecret se omite en silencio (sin error, sin aviso) al generar la lista de la compra. El usuario lo tiene identificado pero no ha pedido el fix todavía -- decidir prioridad la próxima sesión.
- [x] **Traducción de recetas (nombre + instrucciones + ingredientes) con DeepL** -- implementada y probada en real el 2026-09-21, ver sección 10 para el detalle completo. Key en `.env` del VPS.

## 10. Traducción de recetas/instrucciones -- IMPLEMENTADO Y VERIFICADO (2026-09-21)

**Contexto:** FatSecret dio permiso explícito para traducir también el contenido de receta (nombre + `directions` + descripciones de ingrediente), no solo nombres de ingrediente suelto. Esto no cambia nada de las secciones 0/9 sobre no importar de forma permanente -- la traducción vive DENTRO del mismo cache-aside ya existente (`fatsecret_recipe_cache`), no es un motivo para empezar a guardar el contenido para siempre.

**Servicio usado:** DeepL API Free (key termina en `:fx`, host `api-free.deepl.com`). Key en `DEEPL_API_KEY` del `.env` del VPS.

**Cómo quedó implementado (distinto en un punto del diseño original, para mejor):**
1. `App\Services\Translation\DeepLTranslationService::translateMany(array $texts): array` -- traduce **nombre + todos los `directions` + todas las `ingredients[].description`** de una receta en **una sola llamada HTTP** (DeepL admite varios parámetros `text` en el mismo request, devueltos en el mismo orden) -- no una llamada por texto. Si falla por cualquier motivo (sin key, cuota agotada, red...) devuelve los textos originales en inglés tal cual, nunca lanza excepción ni rompe `getOrRefresh()`.
2. **Cambio respecto al diseño original:** en vez de columnas nuevas `name_es`/`directions_es` (que habría exigido tocar todo el código que ya lee `name`/`directions`), se hizo al revés -- `fatsecret_recipe_cache.name`/`directions`/`ingredients` pasan a contener el texto YA TRADUCIDO (o inglés si la traducción no está disponible), y el inglés original se conserva aparte en `name_en`/`directions_en`/`ingredients_en` + un flag `is_translated`. Resultado: **cero cambios** en `NormalizesFatSecretRecipePreview`, `FatSecretController::show()`, `DailyPlanRecipeResource`, `MealPlanTemplateItemResource`, ni en ninguna pantalla del móvil/admin -- todos ya leían `name`/`directions`/`ingredients`, y ahora esos campos vienen en español solos.
3. **Sí se traducen las descripciones de ingrediente** (`ingredients[].description`) -- se decidió que dejar el resto en español y las descripciones de ingrediente en inglés se vería inconsistente para el cliente final en la pestaña "Ingredientes" de `diet_detail_screen.tsx`.
4. **NO se traduce `recipes.search()`** (la búsqueda en vivo, hasta 50 resultados por llamada) -- solo el detalle que entra en `fatsecret_recipe_cache` vía `getOrRefresh()`, tal como se recomendó por coste/latencia. La lista de resultados de búsqueda (panel admin y buscador de sustitución de la app) sigue en inglés.
5. **La atribución a FatSecret se mantiene igual** aunque el texto esté traducido -- traducir no quita la obligación de citar la fuente (`diet_detail_screen.tsx` ya cita "FatSecret" en vez de "USDA" en modo FatSecret, sin cambios adicionales necesarios por la traducción).

**Probado en real (2026-09-21):** receta "Chicken Vegetable Noodle Soup" (`fatsecret_recipe_id=59732`) → `name: "Sopa de pollo con verduras y fideos"`, los 7 pasos traducidos con lenguaje natural (no literal-robótico), 6 ingredientes traducidos ("3 oz de caldo de pollo", "1 taza de zanahorias cortadas en tiras o rodajas"...), inglés real conservado en `*_en`, `is_translated: true`.

**Pendiente, no bloqueante:** actualizar `AgenticdesignBS::entrega-bckbs.md` para reflejar que la traducción ya es real, no solo un permiso obtenido (la sección 2-bis actual ya no dice "NO traduzcas", pero tampoco dice todavía "ya está implementado").

## 11. Importación permanente a la biblioteca propia -- REVIERTE la sección 0/9, riesgo legal asumido explícitamente (2026-09-20)

**Decisión del usuario, en sus palabras:** "es importante que al crear los planes nutricionales... se queden guardados en la biblioteca de recetas por si yo quisiera modificar algo". Se le advirtió explícitamente que esto contradice la razón de ser de las secciones 0 y 9 (su ToS solo permite guardar los `food_id`/`recipe_id` de forma indefinida, el resto de contenido -- nombre, pasos, imagen -- hay que "olvidarlo" y volver a pedirlo cada 24h; por eso `fatsecret_recipe_cache` tiene TTL de 6h). El usuario, con esa información, pidió explícitamente **"copiar el contenido tal cual, asumiendo el riesgo"** -- sin pasar por confirmación manual del coach (la alternativa más segura que se le ofreció y no eligió).

**Qué se implementó:**
- Nueva columna `recipes.fatsecret_recipe_id` (nullable, única) -- migración `2026_09_20_150000_add_fatsecret_recipe_id_to_recipes_table.php`. Es la clave de idempotencia: si una receta de FatSecret ya se importó antes, no se duplica.
- `FatSecretRecipeService::importToLibrary(int $fatsecretRecipeId, ?string $mealType)`: usa el contenido YA TRADUCIDO de `getOrRefresh()` (sección 10) -- nunca el `_en`. Crea `Recipe` (título, macros totales exactos, `description` con una nota del nombre original en inglés) + `RecipeStep` por cada paso + un `RecipeCategoryMapping` si se pasa `mealType` (para que sea filtrable por `recipe-filter-list` igual que cualquier receta propia).
- **Simplificación deliberada en `recipe_ingredients`:** cada línea de ingrediente de FatSecret es texto libre con la cantidad ya incluida en la descripción (ej. "2 tazas de queso cheddar rallado"), no un ingrediente genérico con unidad separada -- no hay forma fiable de convertir "2 tazas"/"1 loncha" a gramos exactos sin inventar una conversión (misma regla que ya aplicaba `FatSecretFoodService::detail()` para ingredientes sueltos, sección 4.2). Por eso cada línea se guarda como un `Ingredient` nuevo con esa descripción completa como `title`, **`fatsecret_food_id` deliberadamente a `null`** (si se pusiera, el mismo `food_id` repetido en varias recetas -- ej. "sal" -- chocaría con el índice único que ya usa el flujo manual de ingredientes de la sección 2) y macros de línea en `0` (no se llama a `food.get` por cada ingrediente -- hubiera sido ~150+ llamadas extra solo para la primera prueba de 28 recetas). **Los macros de la RECETA sí son exactos** (copiados del total ya calculado por FatSecret), que es lo que de verdad importa para que el plan cuadre.
- `MealPlanTemplateController::addItem()`: cuando el coach añade un item con `fatsecret_recipe_id`, ahora llama a `importToLibrary()` y guarda **tanto `recipe_id` (la copia nueva) como `fatsecret_recipe_id` (el origen)** en el mismo item -- `MealPlanTemplateItemResource`/`DailyPlanRecipeResource` ya priorizan `recipe_id` cuando existe, así que el coach ve y edita la receta de su propia biblioteca a partir de ahora, no la ficha en vivo de FatSecret. `importToCalendar()` propaga ambos campos sin cambios (ya copiaba lo que hubiera en el item).

**Limitaciones conocidas, no resueltas en esta pasada:**
- `Recipe.type` (veg/non-veg/vegan) se queda en su valor por defecto (`veg`) para toda receta importada -- FatSecret no da esta clasificación de forma fiable en la respuesta, así que una receta con salchicha de cerdo puede aparecer marcada como "veg" en la biblioteca. Revisar a mano si se usa ese filtro.
- Solo se cubrió `MealPlanTemplateController::addItem()` (plantillas). **`ClientMealPlanController::assignRecipe()` y `DailyPlanController::saveDailyPlanRecipeData()`** (asignación directa al calendario de un cliente sin pasar por una plantilla, y la sustitución de comida desde la app del cliente) **siguen sin importar a la biblioteca** -- una comida de FatSecret asignada por esos dos caminos sigue siendo solo `fatsecret_recipe_id` efímero, tal como en la sección 9. Pendiente decidir si se extiende igual.
- No se ha respondido nada nuevo a FatSecret por escrito sobre esto (la pregunta pendiente de la sección 7.3 seguía sin resolver incluso para el caso mucho más pequeño de 4 números por ingrediente) -- este cambio es una decisión de producto unilateral del usuario, no algo confirmado con FatSecret.

**Probado en real (2026-09-20):** primer plan de prueba completo (1 semana, 4 comidas/día, 28 recetas buscadas y asignadas a `prueba@prueba.com`) -- las 28 se importaron a `recipes` sin errores, contenido verificado en español (ej. `fatsecret_recipe_id=23948` → `Recipe#6189 "Cazuela de desayuno"`, 5 pasos y 6 ingredientes en español), `meal_plan_template_items` (plantilla #4) y las 28 `daily_plan_recipes` ya asignadas se actualizaron retroactivamente con el `recipe_id` nuevo.

## 12. `region=ES`/"Localization" premium (2026-09-20) -- probado en real, SIN efecto para este caso de uso

FatSecret concedió a esta cuenta el permiso premium "Localization" para `region=ES` (Spain/Spanish) -- su documentación dice literalmente "the response will be in the nominated region, using the region's default language". Antes de tocar código se probó contra la API real de producción (no asumir nada, mismo criterio que el resto de esta integración):

**Pruebas hechas** (mismo `recipe_id`/`food_id`, comparando `region=US` vs `region=ES&language=es`):
1. `recipes.search.v3` con query en español ("pollo") + `region=ES&language=es` → **0 resultados**. El `search_expression` solo empareja contra el texto (en inglés) tal cual está almacenado -- `region` no traduce ni reinterpreta la query.
2. `recipes.search.v3` con query en inglés ("chicken") + `region=ES&language=es` → resultados normales (764 total), pero nombres/descripciones siguen en inglés -- ninguna traducción ni catálogo distinto.
3. `recipe.get.v2` para la misma receta (`recipe_id=20218`, "Baked Chicken Parmesan") con `region=US` vs `region=ES&language=es` → **`recipe_name` y los `directions` byte-idénticos**, en inglés en ambos casos.
4. `food.get.v4` para el mismo alimento genérico (`food_id=5110`, "Puerto Rican Style Rice with Chicken") con `region=US` vs `region=ES&language=es` → **`food_name`, `calories` y `serving_description` byte-idénticos**.
5. `foods.search` con `region=ES` y query "pollo" SÍ devolvió resultados ("Pollo Asado", "Pollo Casero") -- pero son productos de marca (`food_type: "Brand"`) cuyo `food_name` YA está literalmente en español en la base de datos de FatSecret, coincide por texto plano, no por efecto del parámetro `region` (el mismo alimento genérico de la prueba 4 no cambió nada).

**Conclusión:** para esta cuenta, `region`/`language` **no tiene ningún efecto observable** sobre el contenido de recetas ni de alimentos genéricos -- ni traduce, ni sirve un catálogo distinto para España. Probablemente el permiso "Localization" concedido afecta a otro tipo de dato (p.ej. disponibilidad de productos de marca regionales, unidades de medida por defecto en mercados con sistema imperial) que no es relevante para nuestro uso (recetas/ingredientes genéricos). **No se cambia el default `region='US'` en ningún método de `FatSecretFoodService`/`FatSecretRecipeService`** -- no hay ninguna ganancia y sí el riesgo de romper algo sin motivo.

La traducción real a español que sí funciona y ya está en producción es la de DeepL (sección 10) -- ese es el único mecanismo que debe seguir usándose. Token temporal de prueba (`temp-region-es-test`, id 168) revocado tras esta verificación.

## 13. Revisión completa de `platform.fatsecret.com/docs/` (2026-09-20) -- qué nos faltaba

Se leyó toda la documentación pública de la Platform API (guía REST, todos los métodos, Terms and Conditions, Attribution Policy) para comprobar si faltaba algo. Implementado y desplegado lo accionable; el resto queda documentado como decisión consciente de no hacerlo (por ahora) o como bloqueado por un permiso de cuenta que hay que pedir a FatSecret.

**Implementado y verificado en real:**
1. **Filtros server-side de `recipes.search.v3`** (todos en plan Basic, no requieren Premier): `calories.from/to`, `protein_percentage.from/to`, `carb_percentage.from/to`, `fat_percentage.from/to`, `prep_time.from/to`, `recipe_types` (comma-separated), `recipe_types_matchall`, `must_have_images`, `sort_by`. Antes `FatSecretRecipeService::search()` solo mandaba `search_expression`+`region`+paginación -- cualquier filtrado por macros quedaba en manos de quien llamaba (ver corrección de `entrega-bckbs.md` más abajo). Ahora acepta un `$filters` array opcional; expuesto como query params opcionales en `/admin/fatsecret/recipes/search` y `/fatsecret/recipes/search` (lógica de validación/mapeo compartida en `App\Http\Controllers\Concerns\BuildsFatSecretRecipeSearchFilters`, usado por ambos controllers). Commit `7353d00`.
   - **Bug real encontrado probando en vivo:** `must_have_images`/`recipe_types_matchall` exigen el string literal `"true"`/`"false"` -- `"1"`/`"0"` (lo que produce cualquier cast `boolean` normal de Laravel/PHP) lo ignora **en silencio, sin error** y no filtra nada. Confirmado comparando la llamada cruda: con `"1"`, 24 de 50 resultados sin imagen pese al filtro; con `"true"`, 0 de 50. Corregido en `FatSecretRecipeService::buildSearchFilterParams()` (cast explícito con `filter_var(..., FILTER_VALIDATE_BOOLEAN)` antes de mandar el string). Commit `3c57651`.
2. **`food.get.v4` → `food.get.v5`** en `FatSecretFoodService::detail()`/`recalculateWithServing()`. Confirmado compatible hacia atrás (mismos nombres de campo por ración, probado con un alimento genérico donde v4/v5 son idénticos) -- v5 solo AÑADE una ración estandarizada "100 g"/"100 ml" (`serving_id=0`, marcada como "derivada", no usable en `food_entry.create` -- método que nunca usamos) para alimentos de marca que en v4 podían no tener ninguna ración nativa en gramos, lo que mejora la tasa de `can_autocalculate=true`. Probado con un alimento de marca real (`food_id=582850`, "Pollo Asado") que en v5 gana la ración `serving_id=0`. Verificado end-to-end contra `/admin/fatsecret/foods/{id}` ya desplegado. Commit `48d6bc1`.
3. Docblock de `FatSecretRecipeService` corregido -- `recipe.get.v2` y `recipes.search.v3` **son las versiones vigentes/recomendadas**, no están deprecadas (la duda documentada en la sección 7 original era infundada).

**Evaluado, NO implementado -- requiere autorización de cuenta que no tenemos:**
4. **Natural Language Processing** (`natural-language-processing/v1`, texto libre → alimentos+nutrición estructurada): la documentación pública dice que está "disponible en plan Basic" (solo `region`/`language` serían Premier). **Probado en real contra `oauth.fatsecret.com/connect/token` pidiendo `scope=basic nlp`** → `400 invalid_scope`. Conclusión: el scope `nlp` no viene activado por defecto en una cuenta Basic normal, hace falta pedirlo a FatSecret explícitamente para esta cuenta -- mismo patrón que el permiso de `region=ES` (sección 12) o el de traducción (sección 10), ambos concedidos tras contacto directo. **No bloqueante, pendiente de decisión de producto:** si se quiere ofrecer "describe lo que has comido" en la app, primero pedir el scope `nlp` a FatSecret; el resto (parseo del texto → estructura, sin `region`/`language` que si son Premier) ya sería viable en Basic una vez concedido.

**Evaluado, descartado por coste (Premier Exclusive, no Basic):**
5. **Image Recognition** (`image-recognition/v2`, foto → alimentos+nutrición): confirmado en la documentación que requiere **Premier Exclusive** (plan de pago con presupuesto a medida), no está en Basic bajo ningún scope. Descartado por ahora -- no se ha probado contra la API real porque ni siquiera se puede sin ese plan.

**Confirmado que NO aplica a nuestra arquitectura, no es un olvido:**
6. Las APIs de "profile" de FatSecret (`food.create` personalizado del usuario, diario de comidas/ejercicio/peso, favoritos, alimentos más comidos/recientes) requieren que cada usuario final tenga su propia cuenta de FatSecret con OAuth delegado (3-legged) -- incompatible con tener nuestro propio sistema de usuarios/diario/nutrición ya construido. Correctamente fuera de alcance.
7. `foods.autocomplete` (sugerencias de autocompletado mientras se escribe) y `food.find_id_for_barcode` (código de barras) -- de bajo valor/ya pospuesto explícitamente por decisión de producto (el barcode "más adelante", según decisión original de la sección 0). Sin cambios.
8. `food_brands.get`/`food_categories.get`/`food_sub_categories.get`/`recipe_types.get` -- listados de referencia (marcas, categorías, tipos de receta), sin uso identificado todavía más allá de poblar un futuro selector de `recipe_types` en el buscador -- no implementado, no es prioritario.

**Corrección aplicada en `AgenticdesignBS::entrega-bckbs.md`:** el documento afirmaba que `recipes.search` no tenía filtro de rango de macros server-side y que el agente debía filtrar en su propio razonamiento -- **eso ya no es correcto** desde el punto 1 de arriba, corregido para que el agente use los filtros nuevos directamente en la llamada en vez de sobre-pedir y descartar resultados.

**Pendiente, decisión y ejecución del usuario (no tocado en esta pasada):** la Attribution Policy real (`platform.fatsecret.com/attribution`) exige la atribución en **3 sitios**, no solo dentro de la app: (a) dentro de la app -- ya lo tenemos (`diet_detail_screen.tsx`); (b) en la ficha de la App Store/Play Store, texto exacto `"Powered by fatsecret nutrition API" (www.fatsecret.com)` -- no lo tenemos; (c) en la web pública de la app -- probablemente tampoco. También el link actual apunta a `fatsecret.com` en vez de `https://platform.fatsecret.com` como piden, y ellos proveen badges oficiales (PNG/SVG) en vez de solo texto. El usuario se encarga de este punto directamente.
