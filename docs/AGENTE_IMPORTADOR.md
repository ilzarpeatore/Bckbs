# Documento base — Agente Importador de Programas (Bckbs)

Este documento reúne todo lo necesario para diseñar/construir un agente que automatice la importación de programas de entrenamiento generados en Excel, sin tener que re-descubrir el sistema desde cero. Está escrito a partir de una implementación real, ya probada en producción (no es un diseño teórico).

**Repo:** `ilzarpeatore/Bckbs` (Laravel). **Servidor:** VPS `bestronger-vps`, path `/var/www/testapp`, sirve `testapp.bestronger.es`.

---

## 1. Objetivo del sistema completo

Un coach (o una IA en su nombre) describe un programa de entrenamiento de N semanas. Un agente genera un `.xlsx` en un formato fijo. Ese archivo se convierte en un programa real, importado a la base de datos, y opcionalmente se asigna a un cliente concreto para que le aparezca en su calendario de la app.

```
Objetivo del coach → agente genera .xlsx → import → training_program (plantilla)
                                                          │
                                                          ▼
                                          program_client_assignments (opcional)
                                                          │
                                                          ▼
                                          el cliente lo ve en su calendario
```

**El agente que prepara el Excel no es (todavía) el mismo agente que importa.** Hoy el import lo ejecuta un humano/Claude Code por SSH. Si se construye un "agente importador", su trabajo empieza donde termina el agente generador: recibe un `.xlsx` ya escrito y lo lleva hasta la base de datos.

---

## 2. Cadena de datos en la BD (ya existe, no se toca)

```
training_programs              ← el programa completo de N semanas (plantilla reutilizable)
  └─ program_day_assignments   ← 1 fila por (semana, día): apunta a una rutina o a null=descanso
        └─ workout_templates   ← 1 rutina de un solo día
              └─ workout_template_blocks     ← bloques (Parte principal, Accesorio...)
                    └─ workout_template_exercises
                          ├─ exercise_id → exercises (catálogo)
                          ├─ prescribed (JSON: series, reps, carga, rir, rpe, descanso, tempo, duracion)
                          └─ enabled_metrics (JSON array)

program_client_assignments     ← asigna un training_program a un client_id concreto, con start_date
                                   (aparte del import; el import NO hace esto -- ver
                                   php artisan programs:assign-client, sección 3)
```

`training_programs` es una plantilla sin cliente hasta que se crea una fila en `program_client_assignments`. Un mismo programa se puede asignar a varios clientes.

---

## 3. Pipeline de import (código ya existente y funcionando)

```
.xlsx
  │
  ▼
ExcelWorkoutAdapter::convert()         app/Services/ProgramsImport/Adapters/ExcelWorkoutAdapter.php
  │  lee con PhpSpreadsheet (ya en composer.json, sin dependencia nueva)
  │  hoja "Programa" → título/descripción/semanas
  │  hoja "Programación" → agrupa filas en weeks[].days[].blocks[].exercises[]
  ▼
esquema canónico                        ← compartido con los otros adaptadores (hevy/strong/jefit/openweight/wger)
  │  ver database/data/programs/canonical.example.json para el shape exacto
  ▼
ProgramsImporter::import()             app/Services/ProgramsImport/ProgramsImporter.php
  ├─ por cada ejercicio → ExerciseMatcher::match()   (ver sección 5, es la parte delicada)
  ├─ transacción DB: crea training_programs + workout_templates/blocks/exercises + program_day_assignments
  └─ idempotente por (source, source_id) — reimportar el mismo archivo no duplica
```

### Comando actual (única forma de disparar el import hoy)

```
php artisan programs:import excel <ruta.xlsx> --dry-run          # solo vista previa, no escribe nada
php artisan programs:import excel <ruta.xlsx>                     # escribe de verdad
php artisan programs:import excel <ruta.xlsx> --dry-run --json    # igual, pero salida JSON (ver más abajo)
```

Flags relevantes de `programs:import` (`app/Console/Commands/ImportProgramsCommand.php`):

| Flag | Default | Qué hace |
|---|---|---|
| `--dry-run` | off | No escribe en BD, solo muestra preview (primeras 2 semanas en modo humano; todas en modo `--json`) |
| `--num-weeks` | auto para `excel` (usa las semanas que traiga el archivo); `12` para el resto de fuentes | Fuerza un nº de semanas distinto |
| `--threshold` | `0.72` | Confianza mínima del matcher (0-1) |
| `--coach-id` | `1` | Coach dueño de la plantilla creada |
| `--no-create` | off | No auto-crea ejercicios sin match — los deja solo reportados |
| `--force` | off | Reimporta aunque ya exista el mismo (source, source_id) |
| `--free` | off | Marca el programa como `is_free_accessible` |
| `--report=` | `database/data/programs/reports/` | Ruta del CSV de ejercicios creados/no-matcheados |
| `--json` | off | Salida JSON estructurada por stdout en vez de texto para humano — ver más abajo |

El punto 1 de la sección 7 (salida JSON) **ya está resuelto**: `--json` imprime un único objeto JSON por stdout, construido por `App\Services\ProgramsImport\ImportJsonReport` a partir de las mismas estructuras que ya devolvía `ProgramsImporter::import()` — no se tocó el motor de import, solo la capa de presentación del comando.

### Endpoint HTTP (para operar sin SSH)

**Resuelto (2026-09-15):** `POST program-import` (`app/Http/Controllers/API/ProgramImportController.php`, protegido por `auth:sanctum` como sus hermanos `training-program-*`). Envuelve exactamente el mismo `ExcelWorkoutAdapter` + `ProgramsImporter` + `ImportJsonReport` que el comando CLI — no hay dos implementaciones del import, solo dos formas de invocarlo.

```
POST /api/admin/program-import   (multipart/form-data, header Authorization: Bearer <token de coach>)
  file        : .xlsx, requerido, máx. 5MB
  dry_run     : bool, por defecto TRUE -- hace falta pedir explícitamente dry_run=false para escribir
  threshold, num_weeks, auto_create, force, free : igual que los flags homónimos de programs:import
```

Devuelve el mismo JSON que `--json` (sección de abajo), con `file` = nombre original del archivo subido. El `coach_id` sale siempre de `auth('sanctum')->id()` — nunca de un parámetro del request, para que un token no pueda escribir en la cuenta de otro coach. El archivo subido se guarda en `storage/app/program-imports/` (disco `local`) antes de procesarlo, igual que el CLI deja el reporte CSV en `database/data/programs/reports/`.

Forma del JSON:

```json
{
  "ok": true,
  "source": "excel",
  "file": "database/data/programs/excel.example.xlsx",
  "dry_run": true,
  "programs_detected": 1,
  "results": [ /* igual que ProgramsImporter::import()['results'], sin truncar semanas */ ],
  "stats": { /* igual que ProgramsImporter::import()['stats'] */ },
  "review_required": [
    {
      "program": "Mesociclo 1 TONI Septiembre",
      "week": 1,
      "day_of_week": 1,
      "source_exercise": "Curl concentrado raro",
      "level": "D",
      "confidence": 0.61,
      "matched_title": "Curl de bíceps con mancuernas",
      "matched_exercise_id": 899
    }
  ],
  "report": [ /* igual que ProgramsImporter::report() */ ],
  "report_csv_path": "database/data/programs/reports/excel-20260915-101500-report.csv"
}
```

`review_required` es la pieza nueva que no existía en ninguna estructura previa: aplana todas las semanas/días/ejercicios del preview y se queda solo con los que tienen nivel C/D/E o se crearían (`level: "created"`) — exactamente el criterio del paso 3 del flujo de la sección 8. Si `review_required` está vacío tras un `--dry-run`, todos los ejercicios matchearon en nivel A/B y el agente puede proceder sin pausa; si no, cada entrada es una decisión concreta que debe ver un humano antes del import real. Solo tiene contenido en modo `--dry-run` — un import real no captura el nivel de match por ejercicio, solo ids agregados, así que un `--json` sin `--dry-run` siempre devuelve `review_required: []` (se asume que la revisión ya ocurrió en el dry-run previo). En error (`--json` incluido), el JSON es `{"ok": false, "error": "..."}`. Código y pruebas: `app/Services/ProgramsImport/ImportJsonReport.php` + `tests/Unit/ImportJsonReportTest.php` (pruebas puras, sin BD).

### Asignar el programa a un cliente

**Resuelto (2026-09-15):** el punto 3 de la sección 7 ya no está pendiente. `programs:assign-client` reutiliza exactamente la misma lógica que ya usa el panel (`TrainingProgramController::assignClient()`, ruta HTTP `POST training-program-assign-client`) — misma semántica de renovación (si el cliente ya tenía este programa asignado, actualiza esa fila en vez de duplicarla), mismo cálculo de `fecha_fin`, misma notificación real al cliente — pero sin necesitar un token de coach vía Sanctum, que es como opera hoy el agente (SSH, no HTTP):

```
php artisan programs:assign-client <training_program_id> <email_cliente> [--start-date=YYYY-MM-DD] [--json]
```

Si no se pasa `--start-date`, usa hoy. Con `--json`, devuelve `{"ok": true, "renewed": bool, "assignment_id": ..., "start_date": ..., "fecha_fin": ...}` o `{"ok": false, "error": "..."}`. Código: `app/Console/Commands/AssignProgramClientCommand.php`. Sin pruebas dedicadas (igual que `ImportProgramsCommand`/`CheckProgramsIntegrityCommand`: es una capa fina sobre Eloquent + notificación, sin lógica pura que aislar de la BD; este repo no tiene BD de pruebas configurada en este entorno para un test de feature).

---

## 4. El formato del archivo Excel

Documentado completo en `database/data/programs/EXCEL_FORMAT.md` (pásaselo íntegro al agente que genera el archivo). Resumen:

- 2 hojas obligatorias: `Programa` (metadatos: título/descripción/semanas) y `Programación` (grid de ejercicios).
- Hoja `Programación`: 19 columnas, 1 fila = 1 ejercicio (no 1 fila por serie). Columnas clave: `semana`, `dia` (1-7), `nombre_dia`, `es_descanso`, `notas_dia`, `bloque`, `instrucciones_bloque`, `ejercicio`, `equipo`, `series`, `reps`, `rir`, `rpe`, `carga_kg`, `carga_pct`, `descanso_seg`, `tempo`, `duracion_seg`, `notas`.
- Los días sin ninguna fila en una semana se convierten en descanso automáticamente al importar — no hace falta escribirlos.
- Cada semana se escribe explícita (no hay progresión automática tipo AutoProgression como en los adaptadores CSV — el agente que genera el Excel decide la progresión real semana a semana).
- Ejemplo real funcionando: `database/data/programs/excel.example.xlsx`.

---

## 5. La parte delicada: `ExerciseMatcher` (aquí es donde puede fallar en silencio)

`app/Services/ExerciseMatcher/ExerciseMatcher.php`. Para cada nombre de ejercicio del Excel, busca el mejor candidato en el catálogo (`exercises`) y clasifica el match en 5 niveles:

| Nivel | Significa | Confianza base |
|---|---|---|
| A | Nombre normalizado idéntico | 1.0 |
| B | Firma igual (movimiento + músculo + equipo compatibles) | 0.82 + bonus |
| C | Contención de nombres + movimiento compatible | 0.75 + bonus |
| D | Solo movimiento igual (músculo/equipo ignorados) | 0.55 + bonus |
| E | Solo músculo igual (movimiento desconocido en la fuente) | 0.45 + bonus |

Umbral por defecto `0.72` (`--threshold`). Si ningún candidato supera el umbral, **se auto-crea un ejercicio nuevo sin pedir confirmación a nadie** (`ProgramsImporter::createExercise()`).

**Esto ya causó un bug real** (ver `exercisematcher_softdelete_bug` en memoria del proyecto, resuelto 2026-09-14): el matcher incluía ejercicios borrados (soft-delete) como candidatos, y un match "perfecto" (nivel A) contra un ejercicio borrado ganaba a cualquier alternativa activa — el import escribía una referencia rota sin ningún error visible. Ya está arreglado en el código, pero el patrón general sigue siendo el punto más frágil del sistema: **los niveles B-E son coincidencias aproximadas por texto, no semánticas** (ej.: puede confundir un remo unilateral con uno bilateral si el texto es parecido). Un agente automatizado que confíe ciegamente en cualquier match B-E puede introducir errores de prescripción silenciosos.

Hay una red de seguridad: `php artisan programs:check-integrity [--fix]` detecta (y opcionalmente repara) referencias rotas a posteriori. **Ya corre automáticamente** vía cron semanal (domingo 4:00 hora española, `app/Console/Kernel.php`, sin `--fix`, log en `storage/logs/programs-check-integrity.log`) — no depende de que alguien se acuerde de lanzarlo a mano, pero sigue siendo semanal, no inmediatamente tras cada import real (ver sección 7, punto 4).

---

## 6. Estado actual del código (a fecha de este documento)

- **`main` ya tiene todo mergeado** (2026-09-15, commit `e918b4b`): `ExcelWorkoutAdapter`, `EXCEL_FORMAT.md`, `excel.example.xlsx`, el fix de `ExerciseMatcher` (bug de soft-delete) y `programs:check-integrity`. `programs:import excel <archivo>` funciona directamente en `main`, sin cambiar de rama. La rama `feature/excel-program-import` sigue existiendo en el remoto pero ya está fusionada — no hace falta usarla.
- Probado end-to-end con éxito una vez: `Mesociclo_1_TONI_Septiembre.xlsx` → `training_program #48`, 3 semanas, 48 ejercicios, asignado a `demo@bestronger.app`. Verificado con `--dry-run` y `check-integrity` de nuevo tras el merge, sin regresiones. (En ese momento la asignación se hizo a mano con un script puntual — hoy ya existe `programs:assign-client`, ver sección 3.)

### Tarea pendiente (2026-09-15)

Todo lo de esta sesión (`--json`, `programs:assign-client`, `POST program-import`, y el hotfix de `fail()`/`reportFailure()`) está en `main`, pero **verificado solo sin base de datos** — lint, `route:list` (1024 rutas de la app entera, sin fatales), `artisan list` y los 15 tests unitarios, que son puros y no tocan BD. Requiere acceso a datos del VPS (`bestronger-vps`) para verificarse de punta a punta. Detalle completo (qué probar exactamente y en qué orden) consolidado en `docs/TAREAS_PENDIENTES.md` del repo `ilzarpeatore/AgenticdesignBS`, sección 1.1 — no se repite aquí para no mantener la misma tarea descrita en dos sitios.

---

## 7. Qué le falta al sistema para que un agente lo pueda operar solo

Orden de prioridad, de lo que más desbloquea a lo que menos (el merge a `main` ya no es un bloqueante, se completó):

1. ~~**Salida estructurada (JSON) del dry-run y del import real.**~~ **Resuelto (2026-09-15):** `--json` en `programs:import` (ver sección 3). Incluye `review_required`, la lista ya filtrada de ejercicios con match nivel C/D/E o auto-creados — el agente ya no necesita parsear texto de terminal ni reimplementar la lógica de "qué es ambiguo".
2. ~~**Endpoint HTTP** que envuelva el mismo `ProgramsImporter`, para que un agente no necesite SSH.~~ **Resuelto (2026-09-15):** `POST program-import` (protegido por `auth:sanctum`, igual que sus hermanos `training-program-*`). Recibe el `.xlsx` como `multipart/form-data`, `dry_run=true` por defecto (hace falta pedir explícitamente `dry_run=false` para escribir), devuelve exactamente el mismo JSON que `programs:import --json` (mismo `ImportJsonReport`, sin reimplementar nada). Ver sección 3.
3. ~~**Comando/endpoint de asignación a cliente.**~~ **Resuelto (2026-09-15), dos veces de hecho:** ya existía un endpoint HTTP (`POST training-program-assign-client`, es lo que usa el panel — este documento decía erróneamente que no existía nada) y ahora también existe `programs:assign-client` para el agente por SSH (ver sección 3), que reutiliza la misma lógica.
4. **`check-integrity` automático** tras cada import real, no manual. **Parcialmente resuelto:** cron semanal (domingo 4am hora española, sin `--fix`) — ya no depende de que alguien lo lance a mano, pero sigue siendo semanal, no inmediatamente tras cada import.
5. **Umbral de revisión humana configurable por nivel de confianza**, no solo un corte binario (`--threshold`). Idealmente: los matches A/B se auto-aprueban, los C/D/E o "CREAR NUEVO" se marcan para revisión antes de escribir en producción. Parcialmente cubierto por `review_required` en `--json` (ya separa A/B de C/D/E/creado); la pausa la sigue imponiendo el propio agente (LLM) siguiendo su system-prompt, no la CLI — el diseño Human-in-the-Loop actual, no un hueco a cerrar necesariamente, pero queda anotado por si en algún momento se decide hacerlo un guardrail duro en código.

Los cinco puntos originales de 2026-09-14 están ya resueltos, del todo o en parte (el 4 y el 5 quedan con matices anotados arriba, no bloqueantes).

---

## 8. Diseño propuesto para el agente

### Responsabilidades

El agente importador **no genera el Excel** (eso lo hace otro agente/humano) y **no decide programación de entrenamiento** — su trabajo es puramente operativo: validar, importar, asignar, verificar.

### Flujo paso a paso

**(Actualizado 2026-09-17 — ver "Principio de diseño no negociable" abajo, cambia dónde vive la pausa humana.)**

```
1. Recibe un .xlsx (ya en el formato de EXCEL_FORMAT.md)
2. Import real directo — POST program-import (dry_run=false) o
   programs:import excel <archivo> --json por SSH. No hace falta dry-run
   previo ni pausar por review_required: el import (creación de
   training_program + ejercicios de catálogo, incluidos auto-creados y
   matches ambiguos nivel C/D/E) se ejecuta siempre.
3. Corre check-integrity inmediatamente después (no esperar al cron
   semanal) y reporta si algo quedó roto — su salida es corta y su señal
   relevante es binaria: "Sin referencias rotas" o no, ver sección 5.
4. Devuelve al humano: training_program_id creado, la lista de
   review_required/report[] (qué ejercicios se auto-crearon o matchearon
   con nivel C/D/E, para que alguien lo revise en el panel admin), y el
   resultado de check-integrity.
5. El agente importador NUNCA ejecuta programs:assign-client ni
   POST training-program-assign-client por su cuenta, ni aunque
   review_required venga vacío. La asignación a un cliente real la hace
   siempre el humano a mano desde el panel admin, después de revisar el
   programa recién creado.
```

### Principio de diseño no negociable

**(Actualizado 2026-09-17, decisión explícita del usuario — sustituye la versión anterior de este principio.)** El import en sí (crear el `training_program` y los ejercicios de catálogo que haga falta, incluidos auto-creados y matches ambiguos C/D/E) se ejecuta automáticamente, sin pausa previa por humano: el coste de un ejercicio mal matcheado que solo existe en el catálogo, sin que ningún cliente lo vea todavía, es bajo y reversible. **La pausa humana no negociable se movió a la asignación: nunca asignar un programa a un cliente real de forma automática.** Eso lo hace el humano a mano en el panel admin, revisando ahí el programa ya creado (ejercicios auto-creados, matches C/D/E, progresión) antes de asignarlo. `--confidence-gate` (que aborta el import si hay `review_required`) sigue existiendo en el código pero ya no es el flujo por defecto del agente importador — es una opción disponible, no el criterio de escritura.

---

## 9. Archivos de referencia

| Qué | Dónde |
|---|---|
| Formato del Excel (para el agente generador) | `database/data/programs/EXCEL_FORMAT.md` |
| Ejemplo de Excel funcionando | `database/data/programs/excel.example.xlsx` |
| Esquema canónico interno | `database/data/programs/canonical.example.json` |
| Adaptador Excel | `app/Services/ProgramsImport/Adapters/ExcelWorkoutAdapter.php` (en `main`) |
| Importador (motor central) | `app/Services/ProgramsImport/ProgramsImporter.php` |
| Matcher de ejercicios | `app/Services/ExerciseMatcher/ExerciseMatcher.php` |
| Comando de import | `app/Console/Commands/ImportProgramsCommand.php` |
| Endpoint HTTP de import (sin SSH) | `app/Http/Controllers/API/ProgramImportController.php` (`POST program-import`) |
| Comando de asignación a cliente | `app/Console/Commands/AssignProgramClientCommand.php` |
| Endpoint HTTP de asignación (lo usa el panel) | `TrainingProgramController::assignClient()` (`POST training-program-assign-client`) |
| Comando de integridad (cron semanal, ver `app/Console/Kernel.php`) | `app/Console/Commands/CheckProgramsIntegrityCommand.php` |
| Constructor de la salida JSON (`review_required`, payload, error, CSV) | `app/Services/ProgramsImport/ImportJsonReport.php` — usado tanto por el comando CLI como por el endpoint HTTP |
| Pruebas de la salida `--json` (puras, sin BD) | `tests/Unit/ImportJsonReportTest.php` |
