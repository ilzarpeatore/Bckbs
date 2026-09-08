# Handoff — Verificación de Rondas 1-6 con base de datos real

## ACTUALIZACIÓN — 2026-09-08, misma tercera sesión, Rondas 8-10 (tonelaje real)

Segunda tanda de esta sesión, después de la consolidación de `linearSlope`
y la Ronda 7 (ver sección siguiente). Implementa la cadena de tonelaje
completa:

- **Ronda 8 (ítem 26)** — `volumen_total` (tonelaje: `peso × reps` de sets
  completados, misma fórmula que `MuscleVolumeService`/`ClientExerciseLogObserver`)
  y su tendencia `tendencia_volumen` (misma pendiente lineal que
  `tendencia_rir`, vía el trait compartido) añadidos a
  `exercise_session_metrics` — migración
  `2026_09_08_090001_add_volumen_total_to_exercise_session_metrics_table.php`,
  calculados en `SessionInterpretationService::aggregateSetLogs()`/`updateTrendMetrics()`.
  `null` (no `0.0`) cuando no hubo ningún set completado, mismo criterio que `carga_efectiva`.
- **Ronda 9 (ítem 27)** — `ConditionVariable::TENDENCIA_VOLUMEN` nuevo, resuelto
  directamente desde la columna ya calculada (mismo patrón que `TENDENCIA_RIR`).
- **Ronda 10 (ítems 28-29)** — **cambio de métrica, no solo aditivo**:
  - `ReadinessCalculationService::acwr()` ahora suma `volumen_total` en vez de `carga_efectiva`.
  - `MesocycleClosureService::persistComparisons()` ahora calcula `value`/`previous_best`
    (y la regresión del ítem 20) sobre `volumen_total` en vez de `carga_efectiva`.

**Importante para la verificación**: estos dos últimos cambios alteran el
valor numérico de métricas que YA se calculaban y ya están en producción
(ACWR, achievement `mesociclo_cerrado`) — no es un fallo si el número
cambia, es intencional, pero hay que comunicarlo (banda de readiness de
clientes existentes puede moverse la próxima vez que corra `readiness:calculate`).

### Verificación pendiente (añadir a la lista de antes)

- Aplicar la migración `volumen_total`.
- Reprocesar/backfillear `exercise_session_metrics` de sesiones ya existentes si se quiere `volumen_total` histórico (hoy solo se calcula hacia adelante, en sesiones nuevas — no hay backfill automático en esta tanda).
- Comparar el ACWR de un cliente real ANTES/DESPUÉS de este cambio — confirmar que el número es distinto (se espera) y que la banda de readiness resultante sigue siendo razonable.
- Cerrar un mesociclo de prueba y confirmar que el `AchievementEvent` de `mesociclo_cerrado` ahora refleja tonelaje, no peso pico.
- Montar una regla con condición `tendencia_volumen` y confirmar que se resuelve igual que `tendencia_rir` (sin dato → falla la condición, con dato → compara bien).

## ESTADO ANTERIOR — 2026-09-08, tercera sesión (sandbox sin BD, de nuevo)

Rama `claude/motor-autorregulacion-46dke6` reiniciada desde `main` (la
anterior ya se fusionó, ver sección de abajo) — mismo criterio que la
primera vez: "si el PR ya se mergeó, la rama de trabajo se reinicia desde
el último `main`". En esta tanda:

1. **Consolidada la decisión abierta #3**: `MesocycleClosureService::linearRegression()`
   ya no tiene su propia copia del núcleo de la regresión — usa
   `ComputesLinearSlope::computeRawLinearSlope()` (el mismo trait que ya
   usan `SessionInterpretationService` y `SessionProgressionRuleEngine`) y
   solo calcula el intercepto localmente. Cero cambio de comportamiento.
2. **Ronda 7 implementada (ítems 23-25)** — nivel de experiencia real del cliente:
   - Migración `2026_09_08_090000_add_coach_overrides_to_training_questionnaire_answers_table.php`: añade `training_experience_months_coach`, `technique_level_coach`, `overridden_by_id`, `overridden_at` a `training_questionnaire_answers`.
   - `TrainingQuestionnaireAnswer`: `effectiveExperienceMonths()`/`effectiveTechniqueLevel()` (override del coach si existe, si no el autoevaluado).
   - `Admin\OnboardingController::updateTrainingExperience()` + ruta `POST admin-onboarding-training-experience-update` (mismo grupo `admin.api` que el resto del panel admin de onboarding) — **requiere que el cliente ya tenga fila** en `training_questionnaire_answers` (el resto de columnas de esa tabla son NOT NULL sin default; crear una fila nueva solo con el override la dejaría inválida) — devuelve 422 explícito si no.
   - `ConditionVariable::NIVEL_EXPERIENCIA` nuevo + `SessionProgressionRuleEngine::resolveNivelExperiencia()` (memoizado en `$evaluationCache`) — el coach ya puede montar reglas condicionadas a meses de experiencia real.

**Otra vez sin BD en este sandbox** (mismo `Connection refused` que la
primera tanda) — nada de esto se ha podido probar contra datos reales.
`php -l` limpio en los 7 ficheros tocados.

### Verificación pendiente para consola con BD real (añadir al checklist de abajo)

- Aplicar la migración nueva y confirmar las 4 columnas.
- `POST admin-onboarding-training-experience-update` con un `user_id` que SÍ tenga fila en `training_questionnaire_answers` → confirmar que solo se escriben las columnas `_coach` + `overridden_by_id`/`overridden_at`, sin tocar los valores autoevaluados originales.
- Mismo endpoint con un `user_id` que NO haya completado el onboarding → confirmar el 422 ("todavía no completó el cuestionario").
- Montar una regla con condición `nivel_experiencia` (p. ej. `gte 60` = 5 años) contra un cliente con override de coach vs. uno solo con autoevaluación vs. uno sin ninguna fila (debe fallar la condición, no romper).
- Confirmar que `MesocycleClosureService` sigue generando los mismos `previous_best` que antes de la consolidación (mismo caso de prueba que ya se usó para el ítem 20, si se conservó).

## ESTADO ANTERIOR — 2026-09-07, fin de la segunda sesión (consola local, BD real)

**Cerrado y en producción.** Las Rondas 1-6 (22 ítems) están verificadas
contra la BD real de la VPS, con un bug real encontrado y corregido (ítem
4), las 4 decisiones abiertas confirmadas, y todo fusionado a `main` y
desplegado:

- Rama `claude/motor-autorregulacion-46dke6` fusionada a `main` (fast-forward,
  sin conflictos) y empujada a `github.com/ilzarpeatore/Bckbs`.
- VPS (`/var/www/testapp`, `testapp.bestronger.es`) actualizada: `git pull`
  a `main`, `composer dump-autoload -o` (9062 clases, para que
  `progression:check-recalibration`/`progression:apply-fallbacks` queden
  registrados), sin necesidad de `migrate` (la migración `no_recortable` ya
  estaba aplicada desde la verificación) ni de reiniciar PHP-FPM
  (`opcache.validate_timestamps=On`, recoge el código nuevo solo).
  Confirmado que el sitio responde tras el deploy (sin 500).
- Commits relevantes en `main`: `d39bbda` (código Rondas 1-6), `82e3cba`
  (análisis+plan), `49d8d6e` (fix ítem 4), `cb4081e` (handoff con
  resultados y decisiones cerradas).
- Detalle completo de la verificación (metodología, resultados por ítem,
  el bug de ítem 4, las 4 decisiones) en las secciones 4b y 5 más abajo —
  se conservan tal cual se escribieron durante la verificación, como
  registro del proceso.
- Pendiente para retomar: Rondas 7-16 del plan (23 ítems, ver última
  sección de este documento) y, cuando se aborde la Ronda 3, consolidar la
  tercera copia de `linearRegression()`/`linearSlope()` (decisión abierta
  #3, ver sección 5).

---

*A partir de aquí, el documento original de la primera sesión (sandbox sin
BD) y las adiciones de la segunda (verificación con BD real), sin editar
retroactivamente — para ver la evolución completa del proceso.*

Este documento es para continuar desde una consola de Claude Code con acceso
a una base de datos real (este sandbox no tenía ninguna: `migrate:status`
devuelve `SQLSTATE[HY000] [2002] Connection refused`, así que nada de lo de
abajo se ha podido ejecutar contra datos reales, solo validar por lectura de
código y `php -l`).

## Dónde estamos

**Rama:** `claude/motor-autorregulacion-46dke6` (no confundir con `main`).
**Nada está commiteado todavía** — todo lo de abajo son cambios en el
working tree, pendientes de tu revisión antes de commitear.

Contexto completo del análisis y del plan: `docs/Motor_Autorregulacion_Analisis.md`
(también sin commitear). Este handoff asume que ya lo has leído o lo lees
primero — ahí está el porqué de cada ítem.

Se implementaron **las Rondas 1-6 del Plan de Optimización (22 ítems)**
mediante 4 agentes en paralelo, cada uno sobre un conjunto de ficheros
disjunto. Todos pasan `php -l`. Ninguno ha tocado la base de datos ni
ejecutado tests (no había BD ni PHPUnit instalado en este sandbox).

## `git status` actual

```
 M app/Console/Commands/ApplyProgressionFallbacks.php
 M app/Console/Commands/CheckProgressionRecalibration.php
 M app/Enums/ExceptionCategory.php
 M app/Models/ClientExerciseOverride.php
 M app/Observers/ClientExerciseLogObserver.php
 M app/Services/AdaptiveWeekPlanner.php
 M app/Services/MesocycleClosureService.php
 M app/Services/ReadinessCalculationService.php
 M app/Services/SessionInterpretationService.php
 M app/Services/SessionProgressionRuleEngine.php
?? app/Services/Concerns/                      (ComputesLinearSlope.php, trait nuevo)
?? database/migrations/2026_09_07_090000_add_no_recortable_to_client_exercise_overrides_table.php
?? docs/Motor_Autorregulacion_Analisis.md
```

Además, `.claude/helpers/graft-hooks.cjs`, `.claude/helpers/graft-statusline.cjs`
y `.claude/settings.json` aparecen modificados — son artefactos de entorno
de `graft` específicos de ESTE contenedor (rutas `BAKED` distintas), no
trabajo real. Decide si los descartas (`git checkout -- .claude/`) antes de
seguir; no están relacionados con el motor.

`storage/logs/laravel.log` también aparece sin trackear — es el log
generado al intentar `migrate:status` en este sandbox, bórralo o ignóralo,
no es parte del trabajo.

## Qué se implementó, por ronda

| Ronda | Ítems | Ficheros | Estado |
|---|---|---|---|
| 1 — Rendimiento síncrono | 1-4 | `SessionProgressionRuleEngine.php`, `ClientExerciseLogObserver.php` | ✅ Completo |
| 2 — Rendimiento batch/cron | 5-8 | `ReadinessCalculationService.php`, `ApplyProgressionFallbacks.php`, `CheckProgressionRecalibration.php` | ✅ Completo |
| 3 — Limpieza | 9-10 | `SessionInterpretationService.php` + nuevo trait `Concerns/ComputesLinearSlope.php` | ✅ Completo (10 era solo documentación, sin acción de código) |
| 4 — `AdaptiveWeekPlanner` | 11-16 | `AdaptiveWeekPlanner.php` + migración `no_recortable` + `ExceptionCategory` (case nuevo, lo añadí yo tras el corte del agente) | ✅ Completo |
| 5 — Cron inteligente | 17-20 | `CheckProgressionRecalibration.php`, `MesocycleClosureService.php` | ✅ Completo |
| 6 — Seguridad y ruido | 21-22 | `SessionProgressionRuleEngine.php`, `ApplyProgressionFallbacks.php` | ✅ Completo |

Detalle de cada ítem (qué cambia y por qué) en `docs/Motor_Autorregulacion_Analisis.md`,
sección "Plan de Optimización", Rondas 1-6.

## Lo siguiente — checklist para consola con BD real

### 1. Aplicar y verificar la migración nueva

```bash
php artisan migrate --path=database/migrations/2026_09_07_090000_add_no_recortable_to_client_exercise_overrides_table.php
php artisan migrate:status | grep no_recortable
```
Confirma que `client_exercise_overrides` tiene la columna `no_recortable`
(boolean, default false) y que `down()` revierte limpio (`php artisan
migrate:rollback --step=1` sobre ella sola, en un entorno de prueba).

### 2. Ejecutar el resto de migraciones pendientes normalmente

```bash
php artisan migrate
```
(por si el entorno tenía otras migraciones sin aplicar, además de la nueva).

### 3. Tests automatizados

No hay tests que cubran hoy ninguno de los ficheros tocados (confirmado por
grep en `tests/` durante la implementación) — antes de commitear, valorar
si merece la pena añadir al menos tests de humo para:
- `SessionProgressionRuleEngine::evaluateForExercise()` con las cachés nuevas (ítems 1-2) — mismo resultado que antes con reglas/condiciones repetidas.
- `AdaptiveWeekPlanner::generateProposal()` con `mantener_ejercicios_principales` — verificar que YA prioriza sesiones con más ejercicios principales (antes del fix, dos estrategias distintas daban el mismo resultado; ahora deben diferir).
- La migración `no_recortable` + `accessoryExerciseIdsToTrim()` excluyendo el ejercicio marcado.

### 4. Verificaciones manuales por ítem (vía `php artisan tinker` o queries directas)

**Ítem 5-6 (Readiness, queries combinadas):** compara el resultado de
`ReadinessCalculationService::calculateForClient()` para un cliente real
ANTES (checkout del commit anterior a este trabajo) y DESPUÉS — mismo
`hrv_z_score`/`sueno_z_score`/ACWR/banda final. Es el ítem con más riesgo
silencioso (una regresión numérica no lanzaría ningún error, solo daría un
resultado distinto).

**Ítem 4 (`maybeRecordPrReps`, JSON_CONTAINS):** confirma que el motor real
en producción es MySQL 5.7.9+ (el propio código lo señala como supuesto sin
verificar). Prueba con un `ClientExerciseLog.logged_sets` real que tenga
`carga` guardado como string en vez de número (si existe algún caso así en
producción) — el agente documentó que `JSON_CONTAINS` compara por tipo y
ese caso NO matchearía aunque antes sí lo hacía vía `is_numeric()` en PHP.

**Ítem 21 (readiness_band sostenido en el motor de reglas):** monta un caso
con `readiness_scores` de 2 días seguidos en `bajo` vs. solo 1 día, y
confirma que una regla con condición `readiness_band` dispara solo en el
primer caso.

**Ítem 22 (fallback re-verifica contexto):** crea un `NextSessionTarget`
pendiente, añade un `PainReport` bloqueante posterior a su `generated_at`,
corre `progression:apply-fallbacks` y confirma que rechaza en vez de
aplicar (revisa el `OverrideLog` generado).

**Ítems 17-19 (recalibración):** monta 4 `OverrideLog` consecutivos con
desviación consistente (p. ej. +5% cada uno) para una regla+cliente, corre
`progression:check-recalibration`, y confirma que la notificación incluye
el % sugerido. Prueba también el caso errático (+3%,+15%,+4%,+20%) y
confirma que NO notifica (ítem 18).

**Ítem 20 (mesociclo, regresión completa):** cierra un mesociclo de prueba
con 4+ sesiones válidas de un ejercicio principal con progresión no lineal
(ej. sube-baja-sube) y compara el `previous_best` generado contra el que
daría el código anterior (primera sesión a secas) — deben diferir si hay
suficiente variación.

**Ítem 15 (memoria entre semanas, `AdaptiveWeekPlanner`):** genera 3
`AdaptiveWeekPlan` consecutivos para el mismo cliente recortando el mismo
`day_of_week`, y confirma que se crea el `CoachExceptionItem` de categoría
`patron_recorte_recurrente` en el tercero, no antes.

### 4b. Resultados de la verificación — 2026-09-07, consola local con BD real (VPS)

Metodología: git worktree en la propia VPS junto al despliegue real
(`/tmp/motor-verify`, ya eliminado), enlazando `.env`/`vendor` del
despliegue real sin copiarlos ni mostrar credenciales, con un autoloader
antepuesto para forzar que las clases `App\` se resuelvan desde el
worktree (el autoloader optimizado de Composer, al symlinkear `vendor/`,
resolvía `App\` a `/var/www/testapp/app` por defecto — cuidado si se repite
esta técnica). Todas las pruebas que escriben datos (OverrideLog, PainReport,
AdaptiveWeekPlan, SessionProgressionRule...) corrieron dentro de
`DB::beginTransaction()`/`rollBack()`; nada quedó persistido salvo la
migración en sí.

- **Migración `no_recortable`**: aplicada y confirmada (columna `tinyint`,
  default 0). `down()` revisado por código (dropColumn simple) — no se
  probó el rollback en vivo (bloqueado por el classifier de auto-mode,
  razonable tratándose de la única BD real).
- **Ítem 4 (JSON_CONTAINS) — BUG REAL ENCONTRADO Y CORREGIDO**: el riesgo
  documentado por el agente estaba invertido. En datos reales, `carga` se
  guarda mezclado (string/número). El bind original de `$weight` (float) ya
  cubría el caso string (por cómo PDO serializa floats), pero **nunca
  matcheaba cuando `carga` era un número JSON puro** (~20% de una muestra
  de 500 logs reales) — `maybeRecordPrReps()` perdía silenciosamente el
  historial de PRs para esos casos. Corregido en
  `ClientExerciseLogObserver.php` probando también
  `CAST(? AS DECIMAL(10,2))`. Verificado contra 3 logs reales. MySQL real
  confirmado: 8.0.46. Commit `49d8d6e`, ya empujado a esta rama.
- **Ítems 5-6 (readiness)**: ACWR idéntico entre versión vieja y nueva
  (0.4158028... vs 0.4158, redondeo de columna). El componente hrv/sueño no
  se pudo ejercitar más allá del caso trivial porque `health_data_points`
  está vacío en este entorno (sin integración de wearables activa aún).
- **Ítem 21 (readiness sostenido 2 días)**: correcto en los 3 casos
  (sostenido, aislado con suavizado a `scoreOnlyBand`, sin dato del día
  anterior).
- **Ítem 22 (fallback re-verifica contexto)**: correcto en los 4 casos
  (sin dolor, dolor bloqueante posterior, molestia leve no bloqueante,
  dolor anterior a `generated_at`).
- **Ítems 17-19 (recalibración)**: con 4 clientes reales y datos sintéticos
  en transacción, el comando dio exactamente "3 avisos individuales, 1 a
  nivel de regla" — excluyó correctamente el caso errático (item 18). Sin
  riesgo de push real: el único coach de este entorno no tiene `player_id`
  (OneSignal) registrado.
- **Ítem 20 (mesociclo)**: regresión lineal verificada con patrón
  sube-baja-sube (100,110,95,120 → previous_best 99.5 en vez de 100) y el
  fallback de pendiente plana (<0.1%) mantiene el comportamiento antiguo.
  No se montó un mesociclo real end-to-end (requiere bastante fixture
  relacional: `TrainingProgram`+`ProgramClientAssignment`+ejercicio
  principal) — se verificó la función de regresión y el umbral de
  decisión directamente por reflexión.
- **Ítem 15 (memoria 3 semanas AdaptiveWeekPlanner)**: correcto en los 3
  casos (3ª semana consecutiva crea el `CoachExceptionItem`, con solo 2
  semanas no lo crea, racha rota por `seleccion_manual_cliente` no lo crea).

### 5. Decisiones abiertas de los agentes — RESUELTAS 2026-09-07

Las 4 se confirman tal cual estaban documentadas/implementadas, sin cambios
de código. Verificado contra BD real primero (ver sección 4 más abajo).

1. **Ítem 22** — el chequeo de "readiness sostenido" se queda anclado al día
   más reciente disponible (no a `generated_at` del target). Confirmado,
   sin cambios.
2. **Ítem 19** — el aviso de recalibración a nivel de regla se mantiene
   ADEMÁS de los individuales por cliente (no los sustituye). Confirmado,
   sin cambios.
3. **`MesocycleClosureService`** se queda con su tercera copia local de la
   regresión lineal (`linearRegression()`, privado) por ahora. La
   consolidación con el trait `ComputesLinearSlope` (ampliarlo para exponer
   también el intercepto, o calcularlo en `MesocycleClosureService` a
   partir de `computeRawLinearSlope()`) se deja para cuando se aborde la
   Ronda 3 del plan, que ya señala esta duplicación entre
   `SessionInterpretationService`/`SessionProgressionRuleEngine` como
   pendiente de extraer — momento natural para consolidar también esta
   tercera copia.
4. **Umbrales elegidos por los agentes** — confirmados tal cual, sin
   cambios de negocio:
   - `CheckProgressionRecalibration`: rango de magnitud ≤50% de la media, ventana de 60 días entre ediciones, mínimo 3 clientes para aviso a nivel de regla.
   - `MesocycleClosureService`: mínimo 3 sesiones para regresión, pendiente relativa <0.1% se considera "plana" (cae al comportamiento antiguo).
   - `AdaptiveWeekPlanner`: `PROGRESS_STREAK_CAP=3` (tope del factor de progreso), `RECURRING_DROP_STREAK_WEEKS=3` (semanas para el aviso de patrón recurrente).

### 6. Commit — HECHO 2026-09-07

El trabajo de las Rondas 1-6 se commiteó (2 commits: análisis+plan, y
código) y se empujó a `origin/claude/motor-autorregulacion-46dke6` desde la
sesión cloud original. Tras la verificación contra BD real en esta
consola local, se añadió un tercer commit (`49d8d6e`) con el fix del bug
real del ítem 4 (JSON_CONTAINS), también empujado a la misma rama. La
rama sigue sin fusionar a `main` — esa decisión (cuándo y cómo mergear)
sigue pendiente.

## Lo que queda del plan completo (fuera de alcance de esta tanda)

Rondas 7-16 (23 ítems) siguen sin implementar: nivel de experiencia del
cliente (Ronda 7), tonelaje real (Rondas 8-10), sustitución de ejercicio
inteligente (Ronda 11), feed de logros más inteligente (Ronda 12),
refinamientos de ejecución de reglas (Ronda 13), síntesis de señales
(Ronda 14), fatiga acumulada de sesión (Ronda 15), y periodización/deload
(Ronda 16, la más invasiva, deliberadamente al final). Todo el detalle está
en `docs/Motor_Autorregulacion_Analisis.md`.
