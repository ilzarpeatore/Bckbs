# Handoff — Verificación de Rondas 1-6 con base de datos real

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

### 5. Decisiones abiertas de los agentes (revisar y decidir)

1. **Ítem 22** — el chequeo de "readiness sostenido" está anclado al día
   más reciente disponible, no a la fecha de `generated_at` del target.
   Cambiarlo es una línea si prefieres esto último.
2. **Ítem 19** — el aviso de recalibración a nivel de regla se añade
   ADEMÁS de los individuales por cliente, no los sustituye.
3. **`MesocycleClosureService`** tiene una tercera copia local de la
   regresión lineal (`linearRegression()`, privado) porque se implementó en
   paralelo a la extracción del trait `ComputesLinearSlope` — pendiente de
   consolidar: sustituir esa copia local por el trait compartido (usar
   `computeRawLinearSlope()` + calcular el intercepto ahí mismo, ya que el
   trait solo expone la pendiente, no el intercepto — habría que decidir si
   se amplía el trait o se deja el intercepto como cálculo propio de
   `MesocycleClosureService` sobre la pendiente ya compartida).
4. **Umbrales elegidos por los agentes** (documentados en el código, no
   vienen del plan original) — revisar si tienen sentido para el negocio
   real:
   - `CheckProgressionRecalibration`: rango de magnitud ≤50% de la media, ventana de 60 días entre ediciones, mínimo 3 clientes para aviso a nivel de regla.
   - `MesocycleClosureService`: mínimo 3 sesiones para regresión, pendiente relativa <0.1% se considera "plana" (cae al comportamiento antiguo).
   - `AdaptiveWeekPlanner`: `PROGRESS_STREAK_CAP=3` (tope del factor de progreso), `RECURRING_DROP_STREAK_WEEKS=3` (semanas para el aviso de patrón recurrente).

### 6. Una vez verificado — commit

Sigue pendiente tu confirmación explícita para commitear (no lo he hecho en
ningún momento de esta sesión). Cuando decidas hacerlo, probablemente tenga
sentido más de un commit (p. ej. uno por agente/ronda, o uno para
`docs/Motor_Autorregulacion_Analisis.md` separado del código) — decide el
grano que prefieras.

## Lo que queda del plan completo (fuera de alcance de esta tanda)

Rondas 7-16 (23 ítems) siguen sin implementar: nivel de experiencia del
cliente (Ronda 7), tonelaje real (Rondas 8-10), sustitución de ejercicio
inteligente (Ronda 11), feed de logros más inteligente (Ronda 12),
refinamientos de ejecución de reglas (Ronda 13), síntesis de señales
(Ronda 14), fatiga acumulada de sesión (Ronda 15), y periodización/deload
(Ronda 16, la más invasiva, deliberadamente al final). Todo el detalle está
en `docs/Motor_Autorregulacion_Analisis.md`.
