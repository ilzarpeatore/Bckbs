# Integración con FatSecret Platform API — plan técnico

## 0. Alcance decidido (leer antes de tocar nada)

Tras investigar los términos de uso de FatSecret a fondo (ver hilo de decisión, 2026-09-19), se descartó importar **recetas** completas (texto, pasos, imágenes) porque:
- Su Terms of Use solo permite guardar de forma indefinida los **IDs** (`food_id`, `recipe_id`...) — el resto del contenido (texto, imágenes, valores nutricionales) hay que volver a pedirlo cada 24h, no se puede "poseer" una copia permanente sin ese refresco.
- Se reservan el derecho de meter publicidad en las imágenes que sirven.
- Sin plan Premier (de pago, presupuesto a medida), el dataset de recetas/alimentos por defecto es solo EEUU (`region=US`).
- Traducir su contenido (recetas/pasos) sin permiso explícito viola la cláusula de "no modificar ni alterar" el contenido.

**Decisión final:** NO se importan recetas de FatSecret. Las recetas se siguen creando 100% a mano en el panel (título, pasos, fotos — sin cambios en ese flujo). Lo único que se integra es **la base de datos de alimentos genéricos** (`foods.search` / `food.get`) para autocompletar la nutrición por gramo de un `Ingredient` al crearlo — el dato numérico (calorías/proteína/grasa/carbos por gramo), no el nombre ni ninguna otra cosa de FatSecret. El nombre en español del ingrediente lo escribe el coach a mano (para eso se pidió permiso explícito de traducción, ya concedido).

Esto reduce drásticamente el riesgo legal (no se toca contenido de receta ni imágenes de FatSecret) y encaja con la arquitectura ya existente sin apenas fricción — ver sección 2.

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
2. **¿Los valores nutricionales de un alimento "Generic" realmente apenas cambian entre `region=US` y `region=ES`?** Es la premisa que justifica no necesitar Premier para esto. Antes de dar por bueno el plan Basic, probar el mismo alimento (ej. "chicken breast", "olive oil", "white rice") con `region=US` vs pedirle a FatSecret (dado que ya hay contacto directo con permiso concedido) que confirme si con Basic se puede forzar `region=ES` en `food.get` aunque el parámetro region esté documentado como afectando sobre todo a `foods.search`.
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
- [x] `FatSecretClient` + `FatSecretFoodService` + `FatSecretRecipeService` -- **probado contra la API real** (`foods.search`, `food.get.v4`, `recipes.search.v3`, `recipe.get.v2` funcionan tal cual están codificados). Punto 7.1 resuelto en la práctica: `recipes.search` SÍ trae `recipe_image`/`recipe_nutrition` embebidos, tal como se documentó. Punto 7.2 (region US vs ES en genéricos) sigue sin probarse -- de momento todo el tráfico real ha sido con `region=US`.
- [x] `FatSecretController` (admin: foods+recipes; cliente: recipes) + rutas + `throttle:100,1440` en la ruta de cliente + `getValidationRules` de `IngredientController` ampliada. Verificado que la ruta de cliente cuelga del grupo `auth:sanctum` correcto (mismo nivel que `save-daily-plan-recipe`), no del prefijo `v1` ni pública.
- [x] `ClientMealPlanController::assignRecipe()` Y `DailyPlanController::saveDailyPlanRecipeData()` (el que usa el propio cliente) aceptan `recipe_id` O `fatsecret_recipe_id`.
- [x] `DailyPlanRecipeResource` normaliza ambos orígenes al mismo shape (lectura pasiva del cache para el preview, nunca fuerza refresco). Confirmado que el total diario (`recipeMealTypeResponse`, solo cuenta `is_complete=true`) suma correctamente las comidas de FatSecret exactamente igual que las propias -- no hay tratamiento especial ni bug de exclusión.
- [x] Comando `fatsecret:refresh-ingredients` -- **ejecutado en real** contra el ingrediente vinculado de prueba, actualiza `fatsecret_synced_at` correctamente sin romper nada.
- [x] **Bug real encontrado y arreglado en el proceso** (commit `9c6bed5`): `FatSecretRecipeCache` no declaraba `$table` explícito -- Eloquent adivinaba `fat_secret_recipe_caches` (snake_case+plural del nombre de la clase) en vez de `fatsecret_recipe_cache` (la tabla real de la migración). Cualquier llamada a `getOrRefresh()` daba 500 hasta el fix.
- [x] UI en `bstronger-admin`: tarjeta de importación de ingredientes en `IngredientView.tsx` + toggle "Mis recetas"/"FatSecret" en `ClientMealCalendarView.tsx` -- **probadas de extremo a extremo por API real** (crear ingrediente, buscar/asignar receta), pendiente solo una comprobación visual en navegador (la extensión de automatización no estaba disponible en la sesión que lo construyó).
- [x] UI en la app móvil (React App): toggle "Tus opciones"/"Buscar otra (FatSecret)" añadido a `assigned_meals_screen.tsx` (pantalla ya existente, pedido explícito no crear una nueva) -- código verificado con `tsc --noEmit` y probado el endpoint que consume (`save-daily-plan-recipe` con `fatsecret_recipe_id`) por API real: buscar salmón, añadirlo a la cena, marcarlo comido, confirmar que cuenta en el total del día. **Pendiente: build de la app y prueba en un dispositivo real** (build de prueba `1.0.1-fatsecret-test-...` lanzado el 2026-09-20).
- [x] `ingredients`/`recipes` ya no están en 0 -- hay al menos un ingrediente real de prueba (Aceite de oliva, id=429, vinculado a `food_id=34212`).
