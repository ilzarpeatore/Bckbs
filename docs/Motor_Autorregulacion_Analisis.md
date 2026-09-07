# Motor de Auto-Regulación de Carga — Análisis para Plan de Optimización

## Objetivo de este documento

Registro vivo del análisis técnico del motor, fase por fase, antes de tocar
código. Sirve para:

1. Dejar constancia de cómo funciona HOY cada fase (con `file:line` exacto),
   para no optimizar a ciegas ni romper comportamiento documentado.
2. Listar brechas/candidatos de mejora identificados en cada fase, SIN
   decidir todavía si se implementan.
3. Una vez cubiertas todas las fases, cerrar con un **Plan de Optimización**
   priorizado (sección al final, pendiente hasta terminar el análisis).

No es el "documento" original citado en los comentarios del código
(`documento §2.1`, `§2.2`, etc.) — ese documento de diseño no vive en este
repo (no se encontró ningún `.md` con ese contenido); este análisis se ha
reconstruido leyendo directamente el código fuente y las migraciones.

## Estado del análisis

| Fase | Componente principal | Estado |
|---|---|---|
| Fase 1 | `SessionInterpretationService` | ✅ Analizada |
| Fase 2 | `SessionProgressionRuleEngine` | ✅ Analizada |
| Fase 3 | Sustitución de ejercicio + logros (`executeSustitucion`, `WorkoutSessionStatsService`, `ClientExerciseLogObserver`) | ✅ Analizada |
| Fase 4 | `ReadinessCalculationService` + `AdaptiveWeekPlanner` | ✅ Analizada |
| Cron / Fallbacks | `ApplyProgressionFallbacks`, `CheckProgressionRecalibration`, `MesocycleClosureService` | ✅ Analizada |
| Plan de Optimización | — | ✅ Primera versión (ver abajo) |

---

## Fase 1 — Interpretación de sesión

**Componente:** `app/Services/SessionInterpretationService.php:36-454`
**Disparo:** job asíncrono `ProcessSessionInterpretation`, encolado desde
`ClientCalendarController::finishSession()` (`app/Http/Controllers/API/ClientCalendarController.php:353-360`), solo para clientes `paid-tier`. Con
`QUEUE_CONNECTION=sync` corre en línea, dentro del mismo request de cerrar
sesión.

**Regla dura del diseño:** ningún componente de Fase 2 en adelante debe leer
`client_exercise_logs` directamente — todo pasa por la tabla de salida de
esta fase, `exercise_session_metrics`.

### Flujo (`processReview` → `processExercise`)

```
processReview(review)                         L84-97
 └─ por cada exercise_id tocado en la sesión (via ClientExerciseLog)
     └─ processExercise(review, exerciseId)    L120-186
         ├─ ClientExerciseCalibration::registerSessionCompleted   L137
         ├─ isBlockedByPain()                  L440-453  (prioridad absoluta, corta el resto si true)
         ├─ aggregateSetLogs(log)               L200-269
         ├─ updateOrCreate ExerciseSessionMetric  L145-168
         ├─ detectOutliers()                    L304-334
         ├─ checkDataSufficiency()              L340-356
         └─ updateTrendMetrics()                L362-408
             └─ linearSlope()                   L410-433
```

### Qué calcula y persiste (`exercise_session_metrics`)

| Campo | Cómo se calcula | Líneas |
|---|---|---|
| `carga_efectiva` | Peso de la serie MÁS PESADA que cumplió reps mínimas prescritas (no es tonelaje, es un solo set) | `aggregateSetLogs` L219-250 |
| `rir_delta_sesion` | Media de `(rir_reportado - rir_prescrito)` de los sets con RIR reportado | L211-253 |
| `completion_ratio` | `sets_completados / series_prescritas` | L254-256 |
| `peor_serie_index` / `peor_serie_rir` | Set con menor RIR reportado en la sesión | L239-242 |
| `is_outlier` | `carga_efectiva` se desvía >30% de la media de las últimas 5 sesiones válidas del mismo ejercicio | `detectOutliers` L304-334 |
| `sin_dato_suficiente` | >50% de los sets sin RIR reportado | `checkDataSufficiency` L340-356 |
| `tendencia_rir` | Pendiente lineal de `rir_delta_sesion` en las últimas 3 sesiones válidas | `updateTrendMetrics` L376-379 |
| `sesiones_consecutivas_sin_cambio` | Nº de sesiones seguidas con la MISMA `carga_efectiva` (mismo peso de la serie top) | L382-394 |
| `e1rm_estimado` | Epley 1RM sobre `carga_efectiva` + sus reps | L396-399 |
| `blocked_by_pain` | Hay un `pain_report` para ese ejercicio/sesión que bloquea progresión | `isBlockedByPain` L440-453 |
| `racha_misma_direccion` | Se deja en 0 aquí a propósito — lo puebla Fase 2 | L404-406 |

### Fuentes leídas

- `client_exercise_logs.logged_sets` (JSON, única fuente de sets reales — Fase 2+ nunca la toca directamente).
- `workout_template_exercises.prescribed` + `client_exercise_overrides.prescribed_override` para saber lo prescrito.
- `pain_reports` para el bloqueo por dolor.
- Histórico de `exercise_session_metrics` del propio cliente/ejercicio (para outliers y tendencias).

### Hallazgos / brechas identificadas

1. **No existe tonelaje real en `exercise_session_metrics`** (`peso × reps`
   sumado de todos los sets). `carga_efectiva` es el pico de un solo set, no
   el trabajo total de la sesión. Y no es que el tonelaje no se calcule en
   ningún sitio del proyecto — se calcula por lo menos **dos veces**, en dos
   servicios completamente aislados entre sí y del motor:
   - `MuscleVolumeService::computeVolume()` (`app/Services/MuscleVolumeService.php:210-321`, `$tonnage = $weight * $reps`, L237) — por grupo muscular y fecha, para reporting/dashboard. Único llamador (confirmado con graft): `ClientCalendarController::computeMuscleVolume`.
   - `ClientExerciseLogObserver::created()` (`app/Observers/ClientExerciseLogObserver.php:45-94`, `$totalVolume += $weight * $reps`, L64) — tonelaje **por sesión** (no por mesociclo), usado únicamente para el récord personal `max_volume` (tabla `personal_records`) y su notificación/feed (`achievement_events`), con lógica ya endurecida contra guardados parciales de la misma sesión (`storeVolumeRecord`, L152-196, fix 2026-08-12).

   Ninguno de los dos alimenta `exercise_session_metrics` ni el motor de
   reglas — son tres sistemas de "carga total trabajada" que no se hablan
   entre sí. Esto en realidad abarata la mejora: la fórmula y el criterio de
   agregación (`peso × reps` por set completado) ya están escritos y
   probados en producción dos veces; llevarlos a Fase 1 es reutilizar, no
   inventar.
2. **`sesiones_consecutivas_sin_cambio` es ciego a compensaciones.** Si el
   cliente baja peso pero sube series (mismo o más tonelaje real), el
   campo lo registra como "cambio" (distinto `carga_efectiva`) aunque el
   trabajo total haya subido; y al revés, un tonelaje estancado que se
   disimula variando series/reps semana a semana no se detecta.
3. **Sin conciencia de periodización/deload.** `detectOutliers` (±30%)
   trata una bajada de carga planificada (semana de descarga) igual que una
   caída anómala real — no hay forma de distinguir "bajada intencional" de
   "bajada real" en esta fase.
4. **Duplicación de código:** `linearSlope()` está reimplementado
   idéntico en `SessionInterpretationService.php:410-433` y en
   `SessionProgressionRuleEngine.php:561-576` (comentario explícito en este
   último reconociendo la duplicación intencional, "pertenece al motor de
   reglas, no a la interpretación de sesión"). Candidato a extraer a un
   helper compartido si se toca cualquiera de las dos fases.
5. **`resolvePrescribed()` no desambigua slots duplicados** (mismo ejercicio
   en más de un bloque de la plantilla) — usa `findTemplateExercise()` sin
   criterio de desambiguación en el camino de histórico (ver también el
   hueco ya documentado en Fase 2 sobre `PRIMERA_SEMANA_MESOCICLO`).

### Candidatos de optimización/mejora (sin decidir, para el plan final)

- Añadir `volumen_total` (tonelaje real) a `exercise_session_metrics`,
  calculado en `aggregateSetLogs()`.
- Añadir `tendencia_volumen` en `updateTrendMetrics()` reutilizando
  `linearSlope()` (aditivo, no rompe nada existente).
- Extraer `linearSlope()` a un trait/helper compartido entre Fase 1 y Fase 2.
- Evaluar si `detectOutliers` debería recibir alguna señal de "semana de
  descarga" (requiere cruzar con datos de mesociclo — más invasivo, tocaría
  `TrainingProgramGeneratorService`).
- (Nota de rendimiento, ya cubierta en el índice previo): `linearSlope`,
  `detectOutliers` y `updateTrendMetrics` corren una vez por ejercicio en
  el loop síncrono de `finishSession()` — cualquier campo nuevo que se
  añada aquí debe mantenerse barato (sin queries N+1 adicionales).

---

## Fase 2 — Motor de reglas (`SessionProgressionRuleEngine`)

**Componente:** `app/Services/SessionProgressionRuleEngine.php:42-1054`
**Disparo:** job `EvaluateSessionProgressionRules` (`app/Jobs/EvaluateSessionProgressionRules.php:48-62`), encolado justo después de Fase 1 desde `finishSession()` (`ClientCalendarController.php:365`), mismo gate `paid-tier`, mismo `QUEUE_CONNECTION=sync` → también corre en línea dentro del request. El job hace un **loop por cada `exercise_id` de la sesión**, llamando `evaluateForExercise()` una vez por ejercicio — cualquier coste por-llamada se multiplica por el nº de ejercicios de la sesión.

### Flujo (`evaluateForExercise`)

```
evaluateForExercise(clientId, exerciseId, sessionId)          L63-132
 ├─ 1) bloqueo por dolor (metrics.blocked_by_pain)             L88-93   → corta aquí, prioridad absoluta
 ├─ 2) cold start: ClientExerciseCalibration incompleta         L96-105  → corta aquí, MANTENER
 ├─ 3) applicableRules(client, exerciseId, trainingProgramId)   L182-249
 │      query session_progression_rules del coach + jerarquía de scope
 ├─ 4) primera regla cuyas condiciones matchean gana             L112-121
 │      ruleExcludedByInsufficientData()  L255-268
 │      ruleMatches() → evaluateCondition() → resolveVariableValue()  L276-346
 └─ 5) executeAction(regla ganadora)                             L409-479
        ├─ sustituir_ejercicio → executeSustitucion()  (ver Fase 3)   L424-425
        ├─ acción neutral (mantener/bloquear/marcar_para_coach)       L428-436
        └─ ajustar carga/reps → resolveBaseReference() + rounding     L438-478
            └─ finalizeProposal() / finalizeNeutral()                 L803-861
```

### Jerarquía de reglas (`ScopeType`, `app/Enums/ScopeType.php`)

`cliente_específico (5) > programa_específico (4) > ejercicio_específico (3) > categoría_ejercicio (2) > global (1)`, desempate por `priority` desc y luego `created_at` desc; un empate real se loguea (`applicableRules` L236-246) pero no se resuelve de forma determinista más allá de eso.

### Variables de condición disponibles (`ConditionVariable`)

`rir_delta_sesion, completion_ratio, tendencia_rir, sesiones_consecutivas_sin_cambio, peor_serie, sin_dato_suficiente, e1rm_delta, readiness_band, hrv_z_score, sueno_z_score` — **10 variables, ninguna de volumen/tonelaje** (ver hallazgo #1 de Fase 1). Cualquier variable sin dato para esa fecha hace fallar la condición (nunca lanza excepción, `evaluateCondition` L314-317) — criterio consistente en todo el motor.

### Acciones posibles (`ActionType`)

`ajustar_carga_pct, ajustar_carga_absoluta, bajar_carga_pct, ajustar_reps` (numéricas, sobre una `base_reference`: último prescrito / último efectivo / e1RM estimado / primera semana del mesociclo) y `mantener, bloquear_progresion, marcar_para_coach, sustituir_ejercicio` (neutrales, no proponen valores). Modo `automatico` aplica sola a la próxima sesión programada (`applyToNextScheduledSession`, L920-953, vía `ClientExerciseOverride`); modo `sugerido_pendiente_aprobacion` queda `pendiente` y genera un ítem en el panel de excepciones del coach.

### Datos leídos (además de `exercise_session_metrics` de Fase 1)

`session_progression_rules` + `_conditions` + `_actions`, `client_exercise_calibration`, `readiness_scores` (Fase 4, vía `resolveReadinessValue` L363-383), `workout_template_exercises` / `client_exercise_overrides` / `program_day_assignments` (para resolver qué fue "lo prescrito", nunca `client_exercise_logs`), `exercise_substitutions` (Fase 3), `next_session_targets` (histórico propio, para `racha_misma_direccion`).

### Hallazgos / brechas identificadas

1. **Ninguna regla puede condicionarse a volumen/tonelaje** — consecuencia directa del hallazgo #1 de Fase 1: si no se persiste ahí, no hay nada que exponer aquí como `ConditionVariable`.
2. **Queries repetidas dentro de la misma evaluación:**
   - `applicableRules()` (L182) se ejecuta completa (query + sort en PHP) una vez por ejercicio evaluado — sin caché por `(coach_id, exercise_id, training_program_id)` dentro del mismo job.
   - `resolveReadinessValue()` (L363) y `resolveE1rmDelta()` (L385) se resuelven **una vez por condición evaluada**, no una vez por evaluación — si una regla tiene 2 condiciones que leen `readiness_band` y `hrv_z_score` del mismo cliente/fecha, son 2 queries a `readiness_scores` en vez de 1.
   - `updateRachaMismaDireccion()` (L996-1021) trae 20 `NextSessionTarget` con `rule.action` eager-cargado y hace un loop en PHP **en cada `finalize`**, incluso cuando la acción es neutral.
3. **`resolveFirstWeekPrescribed()` (L758-792) no desambigua slots duplicados** — mismo hueco que Fase 1, documentado ya en el propio código como limitación conocida y aceptada ("periférico, `PRIMERA_SEMANA_MESOCICLO` es el menos usado de los 4 `BaseReference`").
4. **`racha_misma_direccion` se basa en el signo de la acción configurada, no en el valor propuesto** (decisión de diseño explícita en el código, L982-995) — correcto para lo que mide, pero significa que no hay ninguna métrica de "cuánto" lleva subiendo/bajando en términos reales, solo la dirección.
5. **Sustitución de ejercicio siempre queda pendiente de aprobación** (L610-618, decisión de diseño explícita) — nunca se auto-aplica ni en modo `automatico`; correcto por diseño (no hay mecanismo de "aplicar cambio de ejercicio" al plan), pero es una asimetría a tener en cuenta si se compara "qué tan automático es el motor" entre tipos de acción.

### Candidatos de optimización/mejora (sin decidir, para el plan final)

- Cachear `applicableRules()` por `(coach_id, exercise_id, training_program_id)` dentro de la ejecución del job `EvaluateSessionProgressionRules` (que ya evalúa varios ejercicios de la misma sesión/coach).
- Resolver `readiness_score` y `e1rm_delta` **una vez por `evaluateForExercise()`** (no por condición) y pasar el valor ya resuelto a `evaluateCondition()`.
- Revisar si `updateRachaMismaDireccion()` necesita correr siempre o solo cuando la acción no es neutral.
- Nuevo `ConditionVariable` de volumen (`volumen_delta` / `tendencia_volumen`), dependiente de que Fase 1 primero persista tonelaje real (ver Fase 1).

### ¿Qué le falta para pensar como un entrenador?

Huecos funcionales identificados frente a cómo razonaría un coach humano, todos verificados en el código actual:

1. **Gana la primera regla que matchea, no hay síntesis de señales.** AND dentro de un `logic_group`, OR entre grupos, pero SOLO dentro de UNA regla (L276-299); la jerarquía de scope decide qué regla gana (L222-232). Dos reglas nunca "votan" ni se ponderan — un coach combina señales débiles de varias fuentes en una sola decisión matizada, el motor no puede.
2. **Sin doble progresión por rango de reps.** `ajustar_reps` (L465-470) y `ajustar_carga_pct` (L444-449) son acciones independientes disparadas por condiciones — no existe el esquema "agota el rango de reps en todas las series, luego sube peso y vuelve al mínimo de reps" que es el patrón más común en programación real.
3. **No distingue serie top de series de backoff.** `peor_serie_rir`/`rir_delta_sesion` tratan la sesión como un bloque único — los esquemas de autorregulación por RIR reales (RTS, Juggernaut) deciden la carga siguiente mirando específicamente el rendimiento de la serie TOP.
4. **Redondeo genérico, no por equipo del ejercicio.** `RoundingMode` (`app/Enums/RoundingMode.php`) es una elección fija de la regla (`nearest_1kg`/`nearest_2_5kg`/`none`), no deriva del incremento real del equipo (mancuernas ±2kg, máquina con saltos de 5kg, barra con discos de 1.25kg) — puede proponer un peso no cargable en la práctica.
5. **Sin progresión diferenciada por rol del ejercicio en la sesión** (principal vs. accesorio) — mismo tratamiento de regla si el scope es global/categoría.
6. **Sin nivel de experiencia/antigüedad como variable** — los 10 `ConditionVariable` no cubren esto; hoy solo se podría diferenciar creando una regla por cliente a mano.
7. **La sustitución de ejercicio no propone carga de arranque.** `finalizeSustitucion()` (L620-661) deja `proposed_weight`/`proposed_reps` en `null` siempre — el coach parte de cero para el ejercicio sustituto.
8. **Bloqueo por dolor es binario, sin gradiente de precaución** entre "bloqueo total" y "sin historial" — no hay una progresión más conservadora tras una molestia leve reciente que no llegó a bloquear.
9. **Sin fatiga acumulada de la sesión completa** — `evaluateForExercise()` corre aislado por ejercicio (loop del job, L59-61 de `EvaluateSessionProgressionRules`), sin saber si ya se disparó `bajar_carga` en otros ejercicios de la misma sesión.
10. **Sin conciencia de periodización/deload** (mismo hueco que Fase 1, ya anotado como Ronda 16).

De los diez, el **#1** (síntesis de señales) y el **#7** (carga de arranque en sustitución) son los de mayor impacto visible para un coach usando el producto; el resto son refinamientos de segundo orden, varios dependientes de tener antes tonelaje (Fase 1) o nivel de experiencia como dato de cliente.

## Fase 3 — Sustitución de ejercicio y logros

### 3a. Sustitución de ejercicio

Ya cubierta como parte de Fase 2, ver `executeSustitucion()` en
`SessionProgressionRuleEngine.php:495-661` — validación de estancamiento
(`completion_ratio` bajando → prioridad sobre readiness) antes de buscar
variante en `exercise_substitutions`; siempre queda `pendiente`.

### 3b. Feed de logros (`achievement_events` + `personal_records`)

**Dos disparadores independientes, momentos distintos del ciclo de vida de una sesión:**

```
ClientExerciseLog::created (evento Eloquent, en CADA guardado de series,
                             DURANTE la sesión, antes de finishSession())
 └─ ClientExerciseLogObserver::created()          L45-94
     ├─ storeIfRecord('max_weight')                L99-124   → personal_records + AchievementEventType::PR_CARGA (si mejora ≥2.5%, L238-239)
     ├─ storeIfRecord('max_1rm')                    L99-124   → personal_records + AchievementEventType::MEJORA_E1RM
     ├─ storeVolumeRecord('max_volume')              L152-196  → personal_records SOLO (no genera achievement_event de tipo propio)
     ├─ isBlockedByOutlier()                         L282-302  → si exercise_session_metrics.is_outlier=true para esa sesión, NO genera achievement_event (personal_records SÍ se crea igual)
     └─ maybeRecordPrReps()                          L346-387  → AchievementEventType::PR_REPS, comparado contra reps históricas a ESE MISMO peso exacto (ventana de 200 logs)

ClientCalendarController::finishSession()  (AL CERRAR la sesión completa)
 └─ WorkoutSessionStatsService::computeAchievements()  L47-126
     compara el mejor set de HOY contra el de la sesión anterior (no all-time)
     → persistido como AchievementEventType::PROGRESO_SESION (vía persistSessionProgressAchievements, fuera de este servicio)
```

`AchievementEventType` (`app/Enums/AchievementEventType.php`): `pr_carga`, `pr_reps`, `mejora_e1rm` (récords all-time, gateados a paid-tier), `progreso_sesion` (sesión-vs-sesión-anterior, señal más laxa y frecuente), `racha_sesiones` / `hito_compliance` (job `EvaluateSessionAchievements`), `mesociclo_cerrado` (`MesocycleClosureService`, cron diario).

### Hallazgos / brechas identificadas (3b)

1. **`max_volume` es tonelaje real pero por-sesión, no por-mesociclo ni con tendencia** — no hay pendiente/histórico de volumen, solo "¿es el máximo histórico?". No sirve tal cual como señal de progresión sostenida, solo como hito puntual.
2. **`isBlockedByOutlier()` depende de una carrera con Fase 1** (docblock L249-280): si `exercise_session_metrics` de esa sesión aún no existe cuando se guarda el set (caso normal, Fase 1 corre después en `finishSession()`), el bloqueo por outlier simplemente no aplica — comportamiento correcto y documentado ("dato no disponible = no bloqueante"), pero significa que un PR real registrado sobre una sesión que MÁS TARDE se marca outlier ya generó su `achievement_event` y no se revierte retroactivamente.
3. **Bug histórico ya corregido (documentado, no acción pendiente):** el fix 2026-08-12 en `storeVolumeRecord` y en `isBlockedByOutlier` — merece la pena leer los docblocks si se toca esta zona, para no reintroducir los mismos bugs (notificación de PR de volumen repetida en la misma sesión; bloqueo permanente del feed de logros por una métrica outlier de una posición de programa reutilizada).
4. **`maybeRecordPrReps` puede ser costoso**: carga hasta 200 `ClientExerciseLog.logged_sets` y hace un loop en PHP por cada set de cada log (L354-371) — corre en el hilo síncrono de guardado de series (no de `finishSession()`, sino de cada `logSets()` individual), más frecuente que cualquier otro cálculo del motor.

### 3b bis. ¿Qué le falta al feed de logros para pensar como un entrenador?

**¿Reconoce un logro genuino frente a ruido de medición?** No de forma consistente. `pr_carga` exige mejora ≥2.5% (`isSignificantImprovement`, `PR_CARGA_MIN_IMPROVEMENT_PCT`, L238) — pero **`max_volume` no tiene ningún umbral** (`storeVolumeRecord`, L173-181): cualquier incremento, aunque sea de 0.5kg por una repetición de más, se notifica como "nuevo récord". `mejora_e1rm` tampoco tiene umbral (L242-243) — al ser un valor derivado (fórmula de Epley sobre peso+reps), una fluctuación mínima en reps puede generar una "mejora" que es ruido de estimación, no progreso real.

**¿El umbral de "mejora significativa" tiene sentido para todos los clientes por igual?** No. El 2.5% es una constante global, sin relación con el nivel de experiencia del cliente (mismo hueco de la Ronda 7 del plan) — un avanzado rozando su techo genético y un novato reciben la misma vara de medir, cuando un 1% en el avanzado puede ser un logro real y un 2.5% en el novato puede ser ruido normal de aprendizaje motor.

**¿Distingue "récord de siempre" de "mejor momento reciente"?** No. `storeIfRecord`/`storeVolumeRecord` comparan siempre contra el máximo histórico ABSOLUTO (L105-108). Un cliente recuperándose de una lesión o de un parón largo no recibe ningún reconocimiento hasta superar su pico de hace meses/años — un coach real celebraría "mejor marca en los últimos 90 días" como hito intermedio válido.

**¿Los logros se relacionan con el objetivo del cliente?** No. `TrainingQuestionnaireAnswer.goal_type` (lose_fat/gain_muscle/recomposition/maintain) nunca se consulta desde `ClientExerciseLogObserver` ni `WorkoutSessionStatsService`. Un cliente en déficit calórico que MANTIENE su fuerza sin bajar está teniendo un resultado excelente dado su contexto — hoy el sistema solo celebra números al alza, así que ese cliente probablemente no reciba ningún logro durante toda su fase de pérdida de grasa.

**¿La comparación sesión-vs-sesión-anterior es robusta?** Es puramente punto-a-punto. `WorkoutSessionStatsService::computeAchievements` (L47-126) compara el mejor set de HOY contra el de la sesión INMEDIATAMENTE anterior — si esa sesión anterior fue un mal día no representativo, hoy "mejora" trivialmente y se celebra igual que una progresión real sostenida.

**¿Bloquea logros espurios sobre datos de mala calidad?** Solo a veces, por una condición de carrera ya documentada en el propio código (`isBlockedByOutlier`, L249-280): como este observer corre DURANTE la sesión (al guardar cada set) y Fase 1 calcula `is_outlier` DESPUÉS (en `finishSession()`), en el caso normal de producción el bloqueo por outlier prácticamente nunca se aplica en tiempo real, solo en reprocesados retroactivos/backfill.

**Qué debería hacerse:**

1. **Umbral mínimo para `max_volume`** (hoy no tiene ninguno) — aplicar el mismo criterio de "mejora significativa" que ya existe para `pr_carga`.
2. **Escalar el umbral de mejora según nivel de experiencia** (depende de la Ronda 7 del plan) — más exigente para novatos, más permisivo para avanzados.
3. **Concepto de "mejor marca reciente"** (p. ej. últimos 90 días) además del récord absoluto.
4. **Cruzar con `goal_type`** — un tipo de logro nuevo para "mantener fuerza en déficit" orientado a clientes en pérdida de grasa/recomposición.
5. **Suavizar `progreso_sesion`** — comparar contra la media de las últimas 2-3 sesiones válidas en vez de solo la inmediatamente anterior (mismo patrón de ventana que `TREND_WINDOW` de Fase 1).

**Prioridad recomendada:** el #1 es el más urgente y barato (un umbral que falta, mismo patrón que ya existe para `pr_carga`, cero riesgo). El resto depende de datos aún no conectados (nivel de experiencia, objetivo del cliente).

### 3c. ¿Qué le falta a la sustitución de ejercicio para pensar como un entrenador?

Hallazgo más contundente de lo esperado: la tabla `exercise_substitutions` (migración `2026_08_11_150000`) tiene una columna `category` (string, nullable) cuyo propio comentario dice explícitamente "Mapeo de variantes **por categoría**" — pero la query real en `executeSustitucion()` **no la usa en absoluto**:

```php
$substitution = ExerciseSubstitution::where('coach_id', $coachId)
    ->where('original_exercise_id', $exerciseId)
    ->first();   // sin filtrar por category, sin orderBy
```

Sin índice único en `(coach_id, original_exercise_id)` en el esquema, así que si un coach llegara a crear dos filas para el mismo ejercicio, ni siquiera es determinista cuál se coge.

1. **`category` existe en el esquema pero está muerta en el flujo automático.** El hallazgo más barato y concreto de cerrar de todo el análisis — el dato ya está, solo falta usarlo.
2. **La sustitución no sabe POR QUÉ se disparó.** Se llega aquí solo porque una regla con acción `sustituir_ejercicio` matcheó — el motivo real (meseta de fuerza, dolor recurrente no bloqueante, aburrimiento/preferencia) nunca se pasa a `executeSustitucion()`. Con `category` ya existente, sería natural mapear el motivo de la regla ganadora → categoría de sustitución buscada.
3. **Sin validación de coherencia biomecánica automática.** `Exercise.bodypart_ids` ya existe y se usa en `ScopeType::CATEGORIA_EJERCICIO` — pero no se comprueba que `substitute_exercise_id` trabaje el mismo grupo muscular que el original. Se confía 100% en que el coach configuró bien la tabla.
4. **Sin considerar equipamiento disponible del cliente.** El onboarding ya captura "Equipamiento disponible" (Form estándar, `StandardProfileFormSeeder`) — la sustitución no lo cruza; podría proponer una máquina que el cliente no tiene.
5. **Ventana de validación reciclada, no pensada para esto.** `STAGNATION_WINDOW=5` es literalmente `OUTLIER_WINDOW` de Fase 1 reutilizada "para no introducir un tercer número mágico" (comentario explícito) — 5 sesiones de un ejercicio 1×/semana son ~5 semanas de contexto; del mismo ejercicio a 3×/semana son <2 semanas. No se normaliza por frecuencia real.
6. **Las dos validaciones (completion_ratio bajando, readiness bajo) se evalúan independientes y en cascada, no combinadas** — mismo hueco #1 de la sección de Fase 2 (síntesis de señales), aplica aquí también.
7. **Sin cadena de sustitución.** Mapeo plano `original → substitute`. Si el sustituto TAMBIÉN se estanca más adelante, hace falta que el coach haya configurado una fila con el sustituto como `original_exercise_id` para tener una segunda opción — si no, degrada a `marcar_para_coach` aunque existiera una tercera alternativa razonable.
8. **Sin carga de arranque** (ya anotado en Fase 2 como punto #7 general) — se nota especialmente aquí: proponer un ejercicio nuevo sin ninguna referencia de con cuánto peso empezar es la mitad del trabajo de un coach real.
9. **Sin aprendizaje de resultado.** Si el coach aprueba la sustitución y el cliente responde bien o mal en el nuevo ejercicio, no hay ningún mecanismo que lo recuerde para la próxima vez que haga falta sustituir ese mismo ejercicio a ese mismo cliente.

**Prioridad recomendada:** el punto #1 (usar `category`) es el más barato de todo el catálogo — no requiere migración nueva, el dato ya existe, solo falta decidir un vocabulario de categorías y mapear el motivo de la regla ganadora antes de consultar `ExerciseSubstitution`. Después, el #8 (carga de arranque) es el siguiente salto de valor percibido.

## Fase 4 — Readiness score y plan de semana adaptativo

### 4a. `ReadinessCalculationService` (`app/Services/ReadinessCalculationService.php:38-389`)

**Disparo:** cron diario `readiness:calculate` a las 06:00, antes de que empiecen las sesiones del día — corre en batch, fuera del request, sobre TODOS los clientes `paid-tier` en chunks de 100 (`calculateForAllPaidClients`, L57-75).

```
calculateForClient(client, date)                    L84-126
 ├─ zScoreForMetric(hrv)                              L184-218  → media+desviación de 14 días, cold start si <7 días distintos
 ├─ zScoreForMetric(sleep_hours)                       L184-218  (misma función, otra métrica)
 ├─ zScoreForMetric(resting_hr)                        L184-218  (idem, con signo invertido en normalizeZ — FC alta = malo)
 ├─ subjetivoScore()                                   L230-251  media de energy/stress(invertido)/sleep_quality/soreness(reescalado)
 ├─ acwr()                                             L262-285  carga_efectiva aguda(7d)/crónica(28d/4)
 ├─ normalizeZ / normalizeSubjetivo / normalizeAcwr     L287-320  → todo a escala 0-100
 ├─ combine()                                           L327-350  media ponderada (config/readiness.php), redistribuye pesos si faltan fuentes
 └─ mapBand()                                           L360-388  'optimo'/'reducido'/'bajo'/'dato_insuficiente'
     regla de conflicto: si CUALQUIER fuente objetiva (hrv/sueño z<=-1, FC reposo z>=+1) o la subjetiva (<=2/5) está mal, banda final = 'bajo' aunque el promedio no lo refleje
 └─ syncReadinessExceptionItem()                        L138-173  solo alerta al coach si 2+ días CONSECUTIVOS en 'bajo' (evita ruido diario), auto-resuelve cuando deja de estarlo
```

Pesos actuales (`config/readiness.php`): `hrv 0.25, sueño 0.25, resting_hr 0.15, subjetivo 0.20, acwr 0.15` (deben sumar 1.0).

**ACWR ya es una forma de "carga total"** — pero usa `carga_efectiva` (pico de un set) sumado día a día, no tonelaje real; es decir, hereda la misma limitación de Fase 1 en vez de resolverla. Sirve para el gate de readiness (riesgo de lesión), no para decidir cuánto subir la próxima carga.

### Hallazgos / brechas identificadas (4a)

1. **3 queries separadas para hrv/sueño/resting_hr** (`zScoreForMetric` llamado 3 veces, cada una con su propio filtro `metric_type` sobre `health_data_points`) — mismo patrón de query, se podría traer las 3 métricas en una sola consulta agrupada.
2. **`acwr()` hace 2 queries con JOIN casi idénticas** (ventana de 7 días vs. 28 días sobre `carga_efectiva`) — combinable en una sola con `SUM(CASE WHEN ...)`.
3. **ACWR hereda la limitación de "pico de un set, no tonelaje"** — un indicador de carga aguda/crónica basado en tonelaje real sería más fiel a la literatura de ciencias del deporte (el ACWR "de libro" se calcula sobre carga total, no sobre el mejor set).
4. **Sin endpoint de configuración de pesos** — reconocido en el propio comentario del archivo de config: "de momento el punto de configuración es este archivo", no hay ajuste por coach todavía (fuera de alcance ya documentado, no es una bug).

### 4b. `AdaptiveWeekPlanner` (`app/Services/AdaptiveWeekPlanner.php:52-402`)

Motor **hermano**, no ajusta carga — reprograma la semana cuando el cliente tiene menos sesiones disponibles de las planificadas.

```
generateProposal(client, weekStart, sessionsAvailable, priorizacion)   L54-108
 └─ resolveSessionsForWeek()                    L241-281
 └─ selectSessionsToKeep(sessions, keepCount, priorizacion)  L295-342
     └─ dominantBodypartId()                     L349-370   heurística de priorización por grupo muscular dominante
     └─ accessoryExerciseIdsToTrim()              L390-401   qué recortar de las sesiones mantenidas
 └─ persist()                                     L192-231   → AdaptiveWeekPlan (propuesta, no aplicada)
applyPlan(plan)                                    L154-179   aplica la propuesta ya aprobada
generateFromClientSelection()                      L120-144   variante cuando el cliente elige manualmente qué mantener
```

"Ejercicio principal" = primer ejercicio por `sequence` de cada bloque (mismo proxy que `MesocycleClosureService`, decisión de diseño autodocumentada en la clase — no existe un campo `es_principal` en el esquema). "Grupo muscular prioritario" se infiere de la propia semana (bodypart más frecuente entre los ejercicios de esa semana), no de una configuración explícita por cliente — también autodocumentado como provisional.

### Hallazgos / brechas identificadas (4b)

1. **Dos estrategias de `priorizacion` que ejecutan el MISMO algoritmo.** `selectSessionsToKeep()` (L295-342): `mantener_distribucion_semanal_completa` y `mantener_ejercicios_principales` caen ambas en la misma rama de muestreo uniforme por índice (comentario explícito en el código, L316-317) — solo difieren en si se recorta accesorios DESPUÉS de seleccionar. Elegir "mantener ejercicios principales" **no hace que el algoritmo priorice conservar los días con más ejercicios compuestos** — simplemente espacia uniformemente igual que la otra opción y luego recorta accesorios de lo que ya salió seleccionado por puro espaciado de fechas. Es una discrepancia entre lo que el coach cree que está eligiendo y lo que el sistema realmente hace, ya en producción — no es una carencia, es un comportamiento engañoso.
2. **`resolveSessionsForWeek()` hace una query por día dentro de un loop** (L241-281, `while ($cursor->lte($weekEnd))` con un `ProgramDayAssignment::where(...)->first()` dentro) — hasta 7 queries por programa activo del cliente, en vez de una sola con `whereIn` sobre los días ya resueltos.
3. **Docblock de clase desactualizado** — el comentario de cabecera (L17-27) dice que la transición `aprobado -> aplicado` "está fuera del alcance de esta tarea", pero el propio método `applyPlan()` (L154-179) ya la implementa. Deuda de documentación, no de código.

### ¿Qué le falta a `AdaptiveWeekPlanner` para pensar como un entrenador?

1. **"Ejercicio principal" es un proxy sin verificación real** — si un coach ordena el bloque por otro motivo (p. ej. calentamiento primero por `sequence`), el algoritmo protege el ejercicio equivocado al recortar accesorios.
2. **`mantener_grupo_muscular_prioritario` infiere la prioridad de la semana recortada, no de un objetivo real configurado** — ya autodocumentado como provisional en el propio código, pidiendo explícitamente un campo de configuración futuro.
3. **Sin protección explícita de ejercicios críticos** — `accessoryExerciseIdsToTrim()` recorta TODO salvo el primero del bloque, sin poder marcar un accesorio como "no recortable" (p. ej. trabajo de rehabilitación prescrito por dolor).
4. **No cruza con progreso real** — decide qué sesión recortar solo por fecha/frecuencia de grupo muscular, nunca mirando `exercise_session_metrics` ni reglas de progresión activas. Un coach mantendría la sesión donde el cliente está a punto de un hito y recortaría antes la que lleva estancada.
5. **Sin memoria entre semanas adaptativas consecutivas** — si el cliente recorta siempre el mismo día varias semanas seguidas, un coach ajustaría el programa base; aquí cada propuesta es independiente, sin señal acumulada hacia el panel de excepciones.

**Prioridad recomendada:** el hallazgo #1 (dos estrategias idénticas) es el más urgente — es un bug de comportamiento respecto a lo que el coach espera, no una carencia funcional, y ya está en producción.

## Cron / Fallbacks

Cadencia completa (`Kernel::schedule`, `app/Console/Kernel.php:35-80`): `check:pain-patterns` diario, `progression:apply-fallbacks` cada hora, `progression:check-recalibration` semanal, `readiness:calculate` diario 06:00, `check:mesocycle-closures` diario 06:30 (después de readiness a propósito).

### `progression:apply-fallbacks` (`ApplyProgressionFallbacks.php:27-137`)

Resuelve `next_session_targets` en estado `pendiente` cuya próxima sesión programada cae dentro de 24h y el coach no respondió, según `fallback_behavior` de la regla: `aplicar_igual` (aplica y marca `aplicado`), `mantener_sin_cambio` (marca `rechazado`), `escalar_a_notificacion_urgente` (no resuelve, solo notifica, una vez por target vía caché).

- **Hallazgo:** `NextSessionTarget::where('status','pendiente')->with('rule')->get()` (L37-39) **sin límite ni paginación** — con muchos coaches/clientes activos esto crece sin cota, corriendo cada hora.

### `progression:check-recalibration` (`CheckProgressionRecalibration.php:27-115`)

Detecta cuando el coach lleva **4+ ediciones consecutivas** (sin ninguna aceptada/rechazada intercalada) de las sugerencias de una misma regla, todas en la misma dirección y con >2% de desviación consistente respecto al valor sugerido — y le notifica que quizá el parámetro base de la regla ya no encaja. Deduplicado por caché de 30 días sobre el mismo lote de 4 ediciones (L77-81).

- **Hallazgo:** query `OverrideLog::select('rule_id','client_id')->distinct()->get()` (L38-41) trae todos los pares distintos y luego hace una query adicional por cada par dentro del loop (L46-50) — patrón N+1 clásico, escala con el número de combinaciones regla×cliente con histórico.

### `check:mesocycle-closures` (`MesocycleClosureService.php:29-158`)

Cierra `program_client_assignments` cuya `fecha_fin` ya pasó, y persiste una comparación inicio-vs-fin (`persistComparisons`, L70-125) sobre los "ejercicios principales" del mesociclo (`principalExerciseIds`, L135-157, mismo criterio que `AdaptiveWeekPlanner`: primer ejercicio por secuencia de cada bloque) como `AchievementEventType::MESOCICLO_CERRADO` — `value`/`previous_best` son `carga_efectiva` final/inicial, **otra vez el pico de un set, no tonelaje del mesociclo completo**, que sería la métrica más honesta para "cuánto progresaste este bloque".

### ¿Qué le falta a Fase 4 (readiness + fallbacks + panel de excepciones) para pensar como un entrenador?

Verificado antes de escribir esto: el panel de excepciones SÍ ordena por severidad (`CoachExceptionItemController.php:195-200`, `FIELD(severity,'alta','media','baja')` + `created_at desc`) — no es un hueco, se descarta.

**`ReadinessCalculationService`**

1. **El conflicto "una sola fuente en zona baja → banda bajo" es estadísticamente ruidoso.** `mapBand()` (L360-388) fuerza `bajo` si CUALQUIER fuente objetiva tiene z≤-1.0 o la subjetiva ≤2/5 — con datos que siguen aproximadamente una normal, tener UNA métrica en z≤-1.0 un día cualquiera no es raro (~16% de los días, por azar puro). Esa banda del día concreto alimenta directamente `ConditionVariable::READINESS_BAND` en Fase 2 — puede disparar una bajada de carga por ruido de un solo mal dato. El panel de excepciones SÍ exige 2+ días consecutivos para avisar al coach (`syncReadinessExceptionItem`, L146-161), pero eso solo protege la notificación, no la decisión de carga del día mismo. **Es el hallazgo más urgente de los diez** — puede estar afectando decisiones de carga hoy mismo por ruido normal, no por un caso límite raro.
2. **Pesos globales fijos, sin adaptarse al cliente.** `config/readiness.php` es el único punto de ajuste (reconocido en su propio comentario) — un cliente sin wearable depende de la redistribución proporcional de `combine()` (L327-350), pero no hay forma de que el coach diga explícitamente "para este cliente el dato subjetivo pesa más".
3. **ACWR hereda el problema de tonelaje** (ya anotado en Ronda 8) — usa `carga_efectiva` sumada día a día en vez de trabajo real.
4. **Sin conciencia de causa conocida** — un mal sueño por viaje/enfermedad puntual documentado no se distingue de un mal sueño por sobreentrenamiento real; ambos generan el mismo `bajo`.
5. **Sin cruce con periodización** (mismo hueco transversal de Ronda 16) — una caída de readiness en semana de descarga planificada se lee igual que una caída inesperada en plena semana de carga.

**`ApplyProgressionFallbacks`**

6. **`aplicar_igual` ejecuta una propuesta "congelada" sin re-verificar el contexto actual.** Cuando el fallback dispara a las 24h (L67-75), aplica el `proposed_weight`/`proposed_reps` calculado cuando la regla se evaluó por primera vez — no vuelve a comprobar si el cliente ha reportado dolor o si su readiness ha cambiado entre medias. Segundo hallazgo más urgente — es un riesgo de seguridad del cliente, no solo de calidad de la sugerencia.
7. **Mismo fallback sea cual sea la magnitud del cambio** — subir un 2% y bajar un 15% se tratan igual si `fallback_behavior=aplicar_igual`.
8. **Escalación urgente de un solo nivel** — `escalar_a_notificacion_urgente` notifica una vez (deduplicado por caché, L90-95) y no vuelve a escalar si sigue sin respuesta pasado más tiempo.

**`CoachExceptionFeedService`**

9. **Severidad estática por categoría, no por magnitud real.** `ESTANCAMIENTO` siempre es `MEDIA` tanto si es el 3º intento fallido como el 15º en el ejercicio principal del cliente.
10. **Sin síntesis entre categorías del mismo cliente** — `READINESS_BAJO` y `ESTANCAMIENTO` abiertos a la vez se listan como ítems independientes, sin conectar que uno puede estar causando el otro.

**Prioridad recomendada:** #1 primero (exigir sostenimiento de 2 días también para la lectura que consume `resolveReadinessValue()` en Fase 2, no solo para la notificación al coach — cambio acotado, sin tocar esquema, alto impacto), luego #6 (re-verificar contexto antes de que el fallback aplique una propuesta congelada).

### ¿Qué le falta a `check-recalibration` y `mesocycle-closures` para pensar como un entrenador?

**`CheckProgressionRecalibration`**

1. **Distingue dirección pero no consistencia de magnitud.** Exige que las 4 últimas ediciones tengan la misma dirección (`up`/`down`) y cada una >2% de desviación (L60-74) — pero +3%, +15%, +4%, +20% (todas "up", todas >2%) cuenta igual que +3%, +3%, +3%, +3%. Un patrón errático en tamaño es una señal distinta (el contexto del cliente varía mucho, no que la regla esté mal calibrada) y hoy se trata igual.
2. **No calcula ni sugiere el nuevo valor.** El mensaje al coach es genérico — *"considera ajustarlo"* (`notifyCoach`, L111-112) — nunca propone un valor concreto, aunque los datos para calcular la media de las 4 desviaciones ya están ahí mismo.
3. **Sin ventana temporal.** "Las últimas 4 ediciones" sin límite de tiempo — 4 ediciones en 3 días es una señal mucho más fiable que 4 dispersas en 3 meses (donde el cliente pudo cambiar de fase de entrenamiento entre medias).
4. **Agrega solo por (regla, cliente), nunca a nivel de regla.** Si una regla global del coach está mal calibrada para 8 clientes distintos, genera 8 avisos independientes en vez de una sola señal "esta regla necesita revisión".

**`MesocycleClosureService`**

5. **Compara solo los dos extremos del bloque, no la tendencia completa.** `persistComparisons()` (L70-125) compara `carga_efectiva` de la PRIMERA vs. la ÚLTIMA sesión válida del mesociclo — un pico a mitad de bloque seguido de una bajada por fatiga, o una primera/última sesión atípica (test de calibración, mal día puntual), no se refleja. Es un hallazgo nuevo, no cubierto por el hueco de tonelaje ya anotado.
6. **Sin resumen cualitativo del bloque** — solo persiste deltas de carga por ejercicio principal, nada sobre adherencia (sesiones completadas vs. programadas), bloqueos por dolor, o readiness medio del periodo.
7. **Usa `carga_efectiva`, no tonelaje real del mesociclo** (mismo hueco ya cubierto en la Ronda 10) — se nota especialmente aquí porque es el único resumen que el coach recibe al cerrar un bloque completo.

**Prioridad recomendada:** para `check-recalibration`, el #2 (calcular y sugerir el valor, no solo avisar) es el más barato y de mayor percepción de utilidad. Para `mesocycle-closures`, el #5 (tendencia completa en vez de dos extremos) es el más urgente por ser un hallazgo nuevo que afecta la fiabilidad del resumen de cierre.

---

## Plan de Optimización

Criterio de orden: primero lo que afecta la **latencia real del cliente**
(camino síncrono de `finishSession()`), después batch/cron, después
limpieza de bajo riesgo, después lo que corrige **calidad/seguridad de una
decisión que el motor ya toma hoy en producción**, y solo al final las
mejoras **funcionales nuevas** (tonelaje, contexto del cliente, síntesis de
señales) — cada ronda es independiente, revisable y desplegable por
separado, sin depender de que se hagan todas.

**Nota de consolidación:** las Rondas 1-3 y 6-8 (más la 13) son las
originales de este documento. Las Rondas 4-5 y 9-12 se añadieron después,
al fusionar aquí los catálogos de "¿qué le falta para pensar como un
entrenador?" de Fase 2, Fase 3 y Fase 4 — que hasta ahora solo vivían
dentro de la sección de cada fase, sin consolidar en un único plan.

### Ronda 1 — Rendimiento en el camino síncrono (mayor impacto en latencia real)

Todo esto corre dentro del request HTTP de `finishSession()` (con `QUEUE_CONNECTION=sync`), una vez por ejercicio de la sesión.

1. **Cachear `applicableRules()`** (`SessionProgressionRuleEngine.php:182`) por `(coach_id, exercise_id, training_program_id)` durante la ejecución de `EvaluateSessionProgressionRules::handle()` — hoy se repite la query+sort completa por cada ejercicio del mismo job/coach.
2. **Resolver `readiness_score` y `e1rm_delta` una sola vez por `evaluateForExercise()`**, no una vez por condición evaluada (L363, L385) — memoizar dentro de la misma llamada.
3. **Evitar `updateRachaMismaDireccion()` cuando la acción es neutral** (L996) — hoy trae 20 `NextSessionTarget` con relaciones cargadas en cada `finalize`, incluso para `mantener`/bloqueos.
4. **Revisar `maybeRecordPrReps()`** (`ClientExerciseLogObserver.php:346-387`) — corre en CADA guardado de series (más frecuente que `finishSession()`), cargando hasta 200 logs y parseando JSON en PHP; candidato a acotar más la ventana o mover a un índice/query más dirigida por peso exacto.

*Riesgo: bajo — son cachés/memoización y reordenar checks, sin tocar el esquema ni el comportamiento observable.*

### Ronda 2 — Rendimiento en jobs batch/cron

5. **`zScoreForMetric()` en `ReadinessCalculationService`** (L184-218) — combinar las 3 queries por cliente (hrv/sueño/resting_hr) en una sola agrupada por `metric_type`.
6. **`acwr()`** (L262-285) — combinar las 2 queries con JOIN (ventana aguda/crónica) en una sola con suma condicional.
7. **`ApplyProgressionFallbacks::handle()`** (L37-39) — paginar/limitar la consulta de `next_session_targets` pendientes en vez de traerlos todos cada hora.
8. **`CheckProgressionRecalibration::handle()`** (L38-50) — resolver el patrón N+1 (una query por cada par regla×cliente distinto) con una única consulta agregada.

*Riesgo: bajo — no cambia el resultado, solo cómo se calcula; validar con datos reales antes/después por seguridad (mismos `readiness_scores`/notificaciones que hoy).*

### Ronda 3 — Limpieza de bajo riesgo

9. Extraer `linearSlope()` (duplicado idéntico en `SessionInterpretationService.php:410-433` y `SessionProgressionRuleEngine.php:561-576`) a un helper compartido.
10. Documentar (ya lo está en el código, dejarlo visible aquí como deuda conocida, no acción inmediata) el hueco de desambiguación de slots duplicados en `resolveFirstWeekPrescribed`/`resolvePrescribed`.

*Riesgo: mínimo — cosmético, sin cambio de comportamiento.*

### Ronda 4 — `AdaptiveWeekPlanner`: corregir estrategia engañosa y mejoras (NUEVA)

De Fase 4b. El ítem 11 es un bug de comportamiento ya en producción (el coach elige una estrategia y el sistema ejecuta otra), no una carencia — va primero por eso, antes incluso de rendimiento batch/cron.

11. **Corregir `mantener_ejercicios_principales`** para que realmente puntúe por nº de ejercicios principales de la sesión (mismo patrón de scoring que ya existe para `mantener_grupo_muscular_prioritario`, L297-314) en vez de caer en el muestreo uniforme genérico compartido con `mantener_distribucion_semanal_completa`.
12. **Batchear `resolveSessionsForWeek()`** (L241-281) — una query con `whereIn` sobre los días de la semana ya resueltos, en vez de una query por día dentro del loop.
13. **Permitir marcar un accesorio como "no recortable"** (p. ej. trabajo de rehabilitación prescrito) para que `accessoryExerciseIdsToTrim()` lo respete.
14. **Cruzar con progreso real** (`exercise_session_metrics`/reglas activas) al decidir qué sesión recortar, no solo fecha/frecuencia de grupo muscular.
15. **Memoria entre semanas adaptativas consecutivas** — detectar que el cliente recorta siempre el mismo día y avisar al coach vía panel de excepciones.
16. Actualizar el docblock de clase desactualizado (menciona como "fuera de alcance" algo que `applyPlan()` ya implementa) — cosmético.

*Riesgo: bajo (11, 12, 16) a medio (13-15, requieren nuevo campo/cruce de datos).*

### Ronda 5 — Cron más inteligente: recalibración y cierre de mesociclo (NUEVA)

De la sección "¿qué le falta a `check-recalibration` y `mesocycle-closures`?".

17. **`check-recalibration`: calcular y sugerir el nuevo valor del parámetro** (media de las 4 desviaciones ya disponibles) en vez de un aviso genérico ("considera ajustarlo").
18. **`check-recalibration`: exigir también consistencia de magnitud** (no solo dirección) y una **ventana temporal máxima** entre las 4 ediciones — 4 ediciones en 3 días es una señal distinta de 4 dispersas en 3 meses.
19. **`check-recalibration`: agregar a nivel de regla** (across todos los clientes del coach), no solo por (regla, cliente) — una regla mal calibrada para 8 clientes hoy genera 8 avisos en vez de una señal única.
20. **`mesocycle-closures`: comparar la tendencia completa del mesociclo** (todas las sesiones válidas, no solo primera vs. última) para no depender de dos puntos potencialmente atípicos.

*Riesgo: bajo-medio — todos son cambios de lógica sobre datos ya existentes, sin migración de esquema.*

### Ronda 6 — Seguridad y ruido en decisiones que el motor ya toma hoy (NUEVA)

De Fase 4, marcados en su momento como los dos hallazgos más urgentes de todo el análisis — van antes de cualquier mejora funcional nueva porque corrigen calidad/seguridad de decisiones que el motor ya ejecuta en producción, no añaden capacidad.

21. **Exigir sostenimiento de 2 días para `readiness_band` también en el motor de reglas**, no solo en la notificación al coach. Hoy `mapBand()` puede marcar `bajo` por una sola métrica en z≤-1.0 un día cualquiera (ruido estadístico normal, ~16% de probabilidad por azar) y esa banda alimenta directamente `ConditionVariable::READINESS_BAND` en `resolveReadinessValue()` — puede disparar una bajada de carga por ruido de un solo dato. `syncReadinessExceptionItem()` ya exige 2+ días consecutivos para el panel del coach; aplicar el mismo criterio a la lectura que consume Fase 2.
22. **`ApplyProgressionFallbacks` debe re-verificar contexto antes de `aplicar_igual`** — hoy aplica una propuesta calculada hasta 24h antes sin comprobar si el cliente ha reportado dolor o si su readiness ha cambiado entre medias.

*Riesgo: bajo — ambos son cambios acotados en lógica de lectura/validación, sin migración de esquema.*

### Ronda 7 — Contexto real del cliente: nivel de experiencia (NUEVA)

El dato correcto ya existe (`TrainingQuestionnaireAnswer.training_experience_months`, independiente del nº de sesiones en la app) pero es de solo lectura para el coach.

23. Endpoint de escritura en `Admin\OnboardingController` (o un controlador nuevo) para que el coach edite `training_experience_months`/`technique_level` de un cliente.
24. Campo de override que distinga autoevaluado-por-cliente vs. confirmado-por-coach (trazabilidad, el motor prefiere el del coach si existe).
25. Nuevo `ConditionVariable::NIVEL_EXPERIENCIA` en `SessionProgressionRuleEngine::resolveVariableValue()`.

*Riesgo: bajo — aditivo, no depende de ninguna otra ronda.*

### Ronda 8 — Tonelaje real en Fase 1 (base para el resto de mejoras funcionales)

26. Añadir `volumen_total` (tonelaje: `peso × reps` de sets completados) a `exercise_session_metrics`, calculado en `SessionInterpretationService::aggregateSetLogs()`, y su tendencia `tendencia_volumen` en `updateTrendMetrics()` (reutilizando `linearSlope()`). Migración aditiva (columna nullable), reutiliza la misma fórmula ya probada en `MuscleVolumeService::computeVolume()` y `ClientExerciseLogObserver::created()` — no hay que inventar el cálculo, solo llevarlo a este punto central.

*Riesgo: bajo-medio — migración + columna nueva, no toca lectura existente de `exercise_session_metrics`.*

### Ronda 9 — Exponer tonelaje al motor de reglas

27. Nuevo `ConditionVariable` (p. ej. `volumen_delta` / `tendencia_volumen`) en `app/Enums/ConditionVariable.php`, resuelto en `SessionProgressionRuleEngine::resolveVariableValue()`, para que el coach pueda condicionar reglas a volumen igual que ya hace con RIR/completion_ratio/e1RM.

*Riesgo: medio — toca el motor de reglas; requiere la Ronda 8 ya desplegada y con datos históricos suficientes antes de que tenga sentido ofrecerlo al coach.*

### Ronda 10 — Propagar tonelaje real a Fase 4 y cierres

28. `ReadinessCalculationService::acwr()` — evaluar sustituir `carga_efectiva` por `volumen_total` (de la Ronda 8) para acercar el ACWR al cálculo "de libro" de ciencias del deporte.
29. `MesocycleClosureService::persistComparisons()` — usar tonelaje total del mesociclo (inicio vs. fin) en vez del pico de un set como `value`/`previous_best` del evento `mesociclo_cerrado`.

*Riesgo: medio — cambia el significado de una métrica ya visible al coach/cliente (ACWR, comparación de cierre de mesociclo); comunicar el cambio, no solo desplegarlo en silencio.*

### Ronda 11 — Sustitución de ejercicio inteligente (NUEVA)

De Fase 3c — el hallazgo más barato de todo el catálogo (una columna que ya existe en el esquema y no se usa).

30. **Usar la columna `category`** (ya existe en `exercise_substitutions`, hoy ignorada por `executeSustitucion()`) para elegir variante según el motivo, en vez de coger la primera fila sin criterio.
31. Pasar el motivo de la regla ganadora (estancamiento, dolor no bloqueante, etc.) a `executeSustitucion()` para que pueda filtrar por `category`.
32. Carga de arranque en la sustitución propuesta — `finalizeSustitucion()` deja `proposed_weight`/`proposed_reps` en `null` siempre; calcular una equivalencia vía e1RM/`carga_efectiva` (o un `carga_ratio` configurable en `exercise_substitutions`).

*Riesgo: bajo-medio — aditivo sobre datos y tablas que ya existen.*

### Ronda 12 — Feed de logros más inteligente (NUEVA)

De Fase 3b bis.

33. **Umbral mínimo para `max_volume`** (hoy no tiene ninguno, a diferencia de `pr_carga`) — mismo criterio de "mejora significativa" ya existente.
34. **Escalar el umbral de mejora según nivel de experiencia** (depende de la Ronda 7) — más exigente para novatos, más permisivo para avanzados.
35. **Concepto de "mejor marca reciente"** (p. ej. últimos 90 días) además del récord absoluto, para reconocer progreso real durante una recuperación.
36. **Cruzar con `goal_type`** (`TrainingQuestionnaireAnswer`, ya existe) — un logro nuevo tipo "mantener fuerza en déficit" para clientes en pérdida de grasa/recomposición.
37. **Suavizar `progreso_sesion`** — comparar contra la media de las últimas 2-3 sesiones válidas en vez de solo la inmediatamente anterior (mismo patrón que `TREND_WINDOW` de Fase 1).

*Riesgo: bajo — el #33 es autocontenido y de riesgo mínimo; el #34 y #36 dependen de la Ronda 7 y de tener `goal_type` ya consultado desde aquí (nunca lo está hoy).*

### Ronda 13 — Refinamientos de ejecución de reglas (NUEVA)

De Fase 2, puntos #2-#5 y #8 del catálogo "¿qué le falta para pensar como un entrenador?" — cada uno independiente, se pueden hacer en cualquier orden entre sí.

38. **Doble progresión por rango de reps** — extender `prescribed` con `reps_min`/`reps_max` + `ConditionVariable::REPS_EN_TOPE_RANGO`; el propio coach monta el esquema clásico con dos reglas ordenadas por `priority` (el mecanismo de jerarquía ya existente resuelve el resto).
39. **Serie top vs. backoff** — capturar en `aggregateSetLogs()` el RIR/carga de la primera serie completada por separado (`rir_delta_serie_top`), nuevo `ConditionVariable` + `BaseReference::E1RM_SERIE_TOP` opcional.
40. **Redondeo por equipo del ejercicio** — `increment_kg` (nullable) en `exercises`, usado como fallback antes que el `RoundingMode` genérico de la regla.
41. **Progresión diferenciada por rol del ejercicio** (principal/accesorio) — reutilizar el criterio ya existente en `AdaptiveWeekPlanner`/`MesocycleClosureService::principalExerciseIds()`, exponer como `ConditionVariable::ROL_EJERCICIO`.
42. **Gradiente de precaución tras dolor no bloqueante** — `ConditionVariable::DOLOR_RECIENTE_NO_BLOQUEANTE` a partir de `PainReport` ya existente.

*Riesgo: medio — varios requieren un campo nuevo en esquema (`exercises.increment_kg`, `prescribed.reps_min/max`), pero cada uno es aislado y no depende de los otros cuatro.*

### Ronda 14 — Síntesis de señales (NUEVA, el cambio que más cambia "cómo piensa" el motor)

43. Operador "N de M condiciones" en `logic_group` (hoy AND estricto) — `ruleMatches()`/`evaluateCondition()` (L276-331), con un campo opcional `min_condiciones_requeridas` en el grupo. Es el punto #1 del catálogo de Fase 2 y el de mayor impacto conceptual de los diez, pero también el que más tiempo se beneficia de llegar DESPUÉS de las Rondas 7-13 (nivel de experiencia, volumen, rol del ejercicio) — cuantas más señales de calidad haya disponibles, más sentido tiene poder combinarlas.

*Riesgo: medio-alto — toca el núcleo de evaluación de `evaluateForExercise()`.*

### Ronda 15 — Fatiga acumulada de la sesión completa (NUEVA)

44. Pasar un contador mutable (nº de `bajar_carga`/`marcar_para_coach` ya disparados en la sesión) a través del loop de `EvaluateSessionProgressionRules::handle()`, expuesto como `ConditionVariable::ACCIONES_BAJADA_EN_SESION`. No requiere tabla nueva, es estado de una sola ejecución del job.

*Riesgo: medio — cambia cómo se orquesta la evaluación entre ejercicios de la misma sesión, no solo qué datos lee cada evaluación.*

### Ronda 16 — Conciencia de periodización/deload (mayor esfuerzo, al final)

45. Dar a `detectOutliers()` (Fase 1) alguna señal de "semana de descarga planificada" para no confundir una bajada intencional con una caída anómala — requiere cruzar con datos de mesociclo/`TrainingProgramGeneratorService`. Es el cambio más "de entrenador" pero también el más invasivo (toca la generación del programa, no solo la interpretación de sesión) — se deja deliberadamente para el final, una vez las rondas anteriores estén validadas en producción.

*Riesgo: alto — cruza fases y servicios, necesita su propio diseño antes de tocar código.*
