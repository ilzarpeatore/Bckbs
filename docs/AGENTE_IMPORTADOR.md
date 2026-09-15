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
                                   (aparte del import; el import NO hace esto)
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

**No hay endpoint HTTP todavía.** Solo CLI (sección 7, punto 2 sigue pendiente). Pero el punto 1 de la sección 7 (salida JSON) **ya está resuelto**: `--json` imprime un único objeto JSON por stdout, construido por `App\Services\ProgramsImport\ImportJsonReport` a partir de las mismas estructuras que ya devolvía `ProgramsImporter::import()` — no se tocó el motor de import, solo la capa de presentación del comando.

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

Hay una red de seguridad: `php artisan programs:check-integrity [--fix]` detecta (y opcionalmente repara) referencias rotas a posteriori — pero no corre automáticamente tras cada import.

---

## 6. Estado actual del código (a fecha de este documento)

- **`main` ya tiene todo mergeado** (2026-09-15, commit `e918b4b`): `ExcelWorkoutAdapter`, `EXCEL_FORMAT.md`, `excel.example.xlsx`, el fix de `ExerciseMatcher` (bug de soft-delete) y `programs:check-integrity`. `programs:import excel <archivo>` funciona directamente en `main`, sin cambiar de rama. La rama `feature/excel-program-import` sigue existiendo en el remoto pero ya está fusionada — no hace falta usarla.
- Probado end-to-end con éxito una vez: `Mesociclo_1_TONI_Septiembre.xlsx` → `training_program #48`, 3 semanas, 48 ejercicios, asignado a `demo@bestronger.app`. Verificado con `--dry-run` y `check-integrity` de nuevo tras el merge, sin regresiones.
- No existe todavía ningún comando/endpoint para el paso de asignar un programa a un cliente — se hizo a mano con un script puntual.

---

## 7. Qué le falta al sistema para que un agente lo pueda operar solo

Orden de prioridad, de lo que más desbloquea a lo que menos (el merge a `main` ya no es un bloqueante, se completó):

1. ~~**Salida estructurada (JSON) del dry-run y del import real.**~~ **Resuelto (2026-09-15):** `--json` en `programs:import` (ver sección 3). Incluye `review_required`, la lista ya filtrada de ejercicios con match nivel C/D/E o auto-creados — el agente ya no necesita parsear texto de terminal ni reimplementar la lógica de "qué es ambiguo".
2. **Endpoint HTTP** (protegido, solo coach/admin) que envuelva el mismo `ProgramsImporter`, para que un agente no necesite SSH. Recibe el `.xlsx`, hace dry-run, devuelve JSON.
3. **Comando/endpoint de asignación a cliente** (`programs:assign-client <program_id> <email> --start-date=`) — hoy no existe.
4. **`check-integrity` automático** tras cada import real, no manual.
5. **Umbral de revisión humana configurable por nivel de confianza**, no solo un corte binario (`--threshold`). Idealmente: los matches A/B se auto-aprueban, los C/D/E o "CREAR NUEVO" se marcan para revisión antes de escribir en producción. Parcialmente cubierto por `review_required` en `--json` (ya separa A/B de C/D/E/creado); falta que el propio comando pueda auto-aprobar sin flag manual cuando todo es A/B.

---

## 8. Diseño propuesto para el agente

### Responsabilidades

El agente importador **no genera el Excel** (eso lo hace otro agente/humano) y **no decide programación de entrenamiento** — su trabajo es puramente operativo: validar, importar, asignar, verificar.

### Flujo paso a paso

```
1. Recibe un .xlsx (ya en el formato de EXCEL_FORMAT.md)
2. Ejecuta dry-run con --json (vía endpoint HTTP cuando exista, o CLI + SSH
   mientras no exista) → programs:import excel <archivo> --dry-run --json
3. Analiza el JSON (ya no hace falta parsear texto):
   a. ¿review_required no está vacío (match nivel C/D/E o "CREAR NUEVO")?
      → sí: lista esos casos concretos (ya vienen con week/day/nombre/nivel/
        confianza/candidato) y pide aprobación humana explícita antes de
        seguir (no continúa solo)
      → no (todo nivel A/B): puede proceder sin pausa
   b. ¿programs_detected y las semanas de results[].preview.weeks coinciden
      con lo esperado? ¿hay semanas vacías por error, no por diseño?
4. Import real (sin --dry-run, con --json), solo tras el paso 3
5. Si se pidió asignar a un cliente: ejecuta la asignación
   (requiere que exista la pieza #4 de la sección 7)
6. Corre check-integrity y reporta si algo quedó roto (este comando
   todavía no tiene --json — su salida es corta y su señal relevante es
   binaria: "Sin referencias rotas" o no, ver sección 5)
7. Devuelve al humano: qué se creó (ids, de results[].training_program_id),
   qué ejercicios se auto-crearon (report[], para que alguien revise el
   catálogo después), y el resultado de check-integrity
```

### Principio de diseño no negociable

**Nunca escribir en producción sin que un humano haya visto al menos los casos de match ambiguo (nivel C/D/E) o de auto-creación.** El coste de una prescripción de entrenamiento equivocada (ejercicio incorrecto, carga mal traducida) es alto y silencioso — no falla con un error, simplemente el cliente entrena mal. Este es el mismo criterio que hemos seguido manualmente en todo este proceso: dry-run siempre primero, confirmación explícita antes de cada escritura real.

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
| Comando de integridad | `app/Console/Commands/CheckProgramsIntegrityCommand.php` |
| Constructor de la salida `--json` (`review_required`, payload, error) | `app/Services/ProgramsImport/ImportJsonReport.php` |
| Pruebas de la salida `--json` (puras, sin BD) | `tests/Unit/ImportJsonReportTest.php` |
