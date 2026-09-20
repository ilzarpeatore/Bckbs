// Construye una meal_plan_template real vía la API de Bckbs a partir de un
// archivo de datos (.github/scripts/nutrition-plan-data/<cliente>.json):
// busca recetas reales en FatSecret (admin/fatsecret/recipes/search), las
// cribra por exclusiones (halal -- hard_exclude_keywords -- y aversiones
// declaradas -- soft_avoid_keywords), verifica ingredientes en el detalle
// antes de fijar cada receta, crea la plantilla (POST meal-plan-templates)
// y añade un item por (día, tipo de comida) real.
//
// Deliberadamente NO llama a POST .../import-to-calendar -- ese paso queda
// para que el coach lo dispare a mano tras revisar la plantilla creada.
//
// Uso: node build-nutrition-plan.mjs <ruta-json-datos> [--dry-run]

const API_BASE = process.env.BCKBS_API_BASE_URL;
const TOKEN = process.env.BCKBS_API_TOKEN;

if (!API_BASE || !TOKEN) {
  console.error('Faltan BCKBS_API_BASE_URL o BCKBS_API_TOKEN en el entorno.');
  process.exit(1);
}

const dataPath = process.argv[2];
const dryRun = process.argv.includes('--dry-run');

if (!dataPath) {
  console.error('Uso: node build-nutrition-plan.mjs <ruta-json-datos> [--dry-run]');
  process.exit(1);
}

const fs = await import('node:fs/promises');
const plan = JSON.parse(await fs.readFile(dataPath, 'utf8'));

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// El grupo admin de Bckbs usa el limiter 'api' por defecto de Laravel:
// 60 req/min por usuario (RouteServiceProvider::configureRateLimiting()).
// Se espacian las llamadas y se reintenta con backoff ante un 429 real,
// en vez de disparar todo en ráfaga (lo que rompió el primer dry-run real).
const MIN_GAP_MS = 1100;
let lastCallAt = 0;

async function api(method, path, body, attempt = 1) {
  const wait = MIN_GAP_MS - (Date.now() - lastCallAt);
  if (wait > 0) await sleep(wait);
  lastCallAt = Date.now();

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 429 && attempt <= 5) {
    const retryAfter = Number(res.headers.get('retry-after')) || 10 * attempt;
    console.warn(`  (429 Too Many Attempts, esperando ${retryAfter}s antes de reintentar -- intento ${attempt}/5)`);
    await sleep(retryAfter * 1000);
    return api(method, path, body, attempt + 1);
  }

  const json = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error(`${method} ${path} -> HTTP ${res.status}: ${JSON.stringify(json)}`);
  }
  return json;
}

function containsAny(haystack, keywords) {
  const h = (haystack || '').toLowerCase();
  return keywords.some((k) => h.includes(k.toLowerCase()));
}

function recipeText(r) {
  return `${r.name || ''} ${r.description || ''}`;
}

async function searchCandidates(slot) {
  const pool = new Map(); // fatsecret_recipe_id -> candidate
  for (const q of slot.queries) {
    let results = await runSearch(q, slot, false);
    if (results.length === 0) {
      console.warn(`  (sin resultados con filtros estrictos para "${q}", relajando rango de kcal)`);
      results = await runSearch(q, slot, true);
    }
    for (const r of results) {
      if (!pool.has(r.fatsecret_recipe_id)) pool.set(r.fatsecret_recipe_id, r);
    }
  }
  return [...pool.values()];
}

async function runSearch(q, slot, relaxed) {
  const kcalMargin = relaxed ? Math.max(slot.kcal_margin * 1.5, 0.3) : slot.kcal_margin;
  const params = new URLSearchParams({
    q,
    calories_from: String(Math.round(slot.target_kcal * (1 - kcalMargin))),
    calories_to: String(Math.round(slot.target_kcal * (1 + kcalMargin))),
    // Laravel valida esto como 'boolean' (solo acepta true/false/1/0/'1'/'0',
    // NUNCA el string "true") antes de que FatSecretRecipeService lo traduzca
    // internamente al literal "true" que exige la API de FatSecret -- mismo
    // bug que dry_run/force en TrainingProgramsView.tsx.
    must_have_images: '1',
  });
  if (!relaxed) {
    params.set('protein_percentage_from', String(slot.protein_pct[0]));
    params.set('protein_percentage_to', String(slot.protein_pct[1]));
    params.set('fat_percentage_from', String(slot.fat_pct[0]));
    params.set('fat_percentage_to', String(slot.fat_pct[1]));
    params.set('carb_percentage_from', String(slot.carb_pct[0]));
    params.set('carb_percentage_to', String(slot.carb_pct[1]));
  }
  const res = await api('GET', `/admin/fatsecret/recipes/search?${params.toString()}`);
  return res.data?.results ?? [];
}

async function verifyIngredients(fatsecretRecipeId) {
  const res = await api('GET', `/admin/fatsecret/recipes/${fatsecretRecipeId}`);
  const detail = res.data;
  const ingredientsText = (detail.ingredients_en || detail.ingredients || [])
    .map((i) => i.description || '')
    .join(' | ');
  return { detail, ingredientsText };
}

async function pickForSlot(slot) {
  console.log(`\n=== Slot: ${slot.meal_type} (${slot.label}) ===`);
  const candidates = await searchCandidates(slot);
  console.log(`  ${candidates.length} candidatos encontrados (pre-filtro nombre/descripcion).`);

  const nameFiltered = candidates.filter((c) => !containsAny(recipeText(c), plan.hard_exclude_keywords));
  console.log(`  ${nameFiltered.length} tras excluir cerdo/derivados por nombre/descripcion.`);

  // Ordena priorizando cercania a target_kcal, ya filtrado por nombre.
  nameFiltered.sort((a, b) => Math.abs(a.calories - slot.target_kcal) - Math.abs(b.calories - slot.target_kcal));

  const verified = [];
  for (const c of nameFiltered) {
    if (verified.length >= 7) break; // suficiente para variedad de 7 dias
    try {
      const { ingredientsText } = await verifyIngredients(c.fatsecret_recipe_id);
      if (containsAny(ingredientsText, plan.hard_exclude_keywords)) {
        console.log(`  descartado (cerdo/derivados en ingredientes): ${c.name} [${c.fatsecret_recipe_id}]`);
        continue;
      }
      const hasSoftAvoid = containsAny(recipeText(c) + ' ' + ingredientsText, plan.soft_avoid_keywords);
      verified.push({ ...c, hasSoftAvoid, ingredientsText });
    } catch (e) {
      console.warn(`  no se pudo verificar detalle de ${c.fatsecret_recipe_id}: ${e.message}`);
    }
  }

  // Preferir candidatos sin aversiones declaradas (cebolla/judia verde), a igualdad de orden ya fijado por cercania a target_kcal.
  verified.sort((a, b) => (a.hasSoftAvoid === b.hasSoftAvoid ? 0 : a.hasSoftAvoid ? 1 : -1));

  console.log(`  ${verified.length} candidatos verificados (ingredientes revisados) disponibles para esta semana.`);
  return verified;
}

async function main() {
  const slotPicks = [];
  for (const slot of plan.slots) {
    const picks = await pickForSlot(slot);
    slotPicks.push({ slot, picks });
  }

  const summary = { cliente_id: plan.cliente_id, titulo: plan.titulo, dry_run: dryRun, dias: {} };
  for (const day of plan.weekdays) summary.dias[day] = [];

  for (const { slot, picks } of slotPicks) {
    if (picks.length === 0) {
      console.warn(`\nAVISO: 0 candidatos validos para el slot "${slot.meal_type} / ${slot.label}" -- se omite en todos los dias.`);
      continue;
    }
    plan.weekdays.forEach((day, i) => {
      const chosen = picks[i % picks.length];
      summary.dias[day].push({
        meal_type: slot.meal_type,
        label: slot.label,
        fatsecret_recipe_id: chosen.fatsecret_recipe_id,
        name: chosen.name,
        calories: chosen.calories,
        protein: chosen.protein,
        fat: chosen.fat,
        carbs: chosen.carbs,
        aviso_aversion_declarada: chosen.hasSoftAvoid || undefined,
      });
    });
  }

  console.log('\n\n========== RESUMEN (antes de escribir nada) ==========');
  console.log(JSON.stringify(summary, null, 2));

  if (dryRun) {
    console.log('\n--dry-run: no se creo ninguna plantilla ni item real. Revisa el resumen de arriba.');
    await fs.writeFile('nutrition-plan-summary.json', JSON.stringify(summary, null, 2));
    return;
  }

  console.log(`\nCreando plantilla real: "${plan.titulo}" (type: ${plan.type})...`);
  const created = await api('POST', '/admin/meal-plan-templates', { title: plan.titulo, type: plan.type });
  const templateId = created.data.id;
  console.log(`Plantilla creada: id=${templateId}`);

  let itemsCreated = 0;
  for (const [day, items] of Object.entries(summary.dias)) {
    for (const item of items) {
      await api('POST', `/admin/meal-plan-templates/${templateId}/items`, {
        day_key: day,
        meal_type: item.meal_type,
        fatsecret_recipe_id: item.fatsecret_recipe_id,
      });
      itemsCreated++;
    }
  }

  summary.meal_plan_template_id = templateId;
  summary.items_creados = itemsCreated;
  await fs.writeFile('nutrition-plan-summary.json', JSON.stringify(summary, null, 2));

  console.log(`\nListo. Plantilla id=${templateId}, ${itemsCreated} items creados.`);
  console.log('NO se ha asignado al calendario del cliente (no se llamo a import-to-calendar) -- pendiente de revision humana.');
}

main().catch((e) => {
  console.error('\nERROR:', e.message);
  process.exit(1);
});
