<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ProgramsImport\Adapters\ExcelWorkoutAdapter;
use App\Services\ProgramsImport\ImportJsonReport;
use App\Services\ProgramsImport\ProgramsImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Envuelve el mismo ProgramsImporter que usa `php artisan programs:import
 * excel` (ver app/Console/Commands/ImportProgramsCommand.php), para que el
 * agente importador (docs/AGENTE_IMPORTADOR.md) pueda operar sin SSH.
 *
 * Resuelve el punto 2 de la sección 7 de ese documento -- el único de los
 * cinco bloqueantes originales que seguía completamente abierto.
 *
 * No es un controlador nuevo con lógica propia: solo adapta la entrada
 * (archivo subido en vez de ruta en disco) y la salida (JSON HTTP en vez de
 * stdout) al mismo ExcelWorkoutAdapter + ProgramsImporter + ImportJsonReport
 * que ya existen y ya están probados. El coach/agente que llama a este
 * endpoint es siempre el dueño de lo que se crea (auth('sanctum')->id()) --
 * a diferencia del comando CLI, aquí no se acepta un coach_id por parámetro.
 *
 *   POST program-import  (multipart/form-data)
 *     file       : .xlsx, requerido
 *     dry_run    : bool, por defecto TRUE -- hace falta pedir explícitamente
 *                  dry_run=false para escribir en producción. Mismo
 *                  principio de "dry-run siempre primero" que en la CLI,
 *                  pero aquí además es el comportamiento por defecto: un
 *                  agente que se olvide de pasar el parámetro previsualiza,
 *                  nunca escribe por accidente.
 *     threshold, num_weeks, auto_create, force, free : igual que los flags
 *                  homónimos de programs:import (ver esa clase para el
 *                  significado exacto de cada uno)
 *     confidence_gate : bool, por defecto false -- igual que --confidence-gate
 *                  de la CLI: antes de un import real, corre una pasada
 *                  dry-run en memoria y responde 422 sin escribir nada si
 *                  algún ejercicio requiere revisión (nivel C/D/E o
 *                  auto-creado).
 *     check_integrity : bool, por defecto false -- igual que --check-integrity
 *                  de la CLI: tras un import real con éxito, ejecuta
 *                  programs:check-integrity (sin --fix) y devuelve su
 *                  salida en `check_integrity_output`.
 */
class ProgramImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|mimes:xlsx|max:5120',
            'dry_run'          => 'sometimes|boolean',
            'threshold'        => 'sometimes|numeric|min:0|max:1',
            'num_weeks'        => 'sometimes|integer|min:0',
            'auto_create'      => 'sometimes|boolean',
            'force'            => 'sometimes|boolean',
            'free'             => 'sometimes|boolean',
            'confidence_gate'  => 'sometimes|boolean',
            'check_integrity'  => 'sometimes|boolean',
        ]);

        $dryRun = $request->boolean('dry_run', true);
        $uploaded = $request->file('file');
        $originalName = $uploaded->getClientOriginalName();

        $storedRelativePath = $uploaded->store('program-imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedRelativePath);

        try {
            $canonical = (new ExcelWorkoutAdapter())->convert($absolutePath);
        } catch (\Throwable $e) {
            return response()->json(ImportJsonReport::buildError('Error parseando la fuente: ' . $e->getMessage()), 422);
        }

        $programsDetected = count($canonical['programs'] ?? []);

        // limpiar caché de firmas del matcher para reflejar ejercicios nuevos (igual que ImportProgramsCommand)
        Cache::forget('exercise_matcher_db_signatures_v1');

        $coachId = auth('sanctum')->id();
        $numWeeks = (int) $request->input('num_weeks', 0); // 0 = usar las semanas que traiga el archivo (excel siempre trae todas explícitas)
        $threshold = (float) $request->input('threshold', 0.72);
        $autoCreate = $request->boolean('auto_create', true);
        $force = $request->boolean('force', false);
        $free = $request->boolean('free', false);

        if ($request->boolean('confidence_gate', false) && !$dryRun) {
            $gateImporter = new ProgramsImporter(
                coachId: $coachId,
                numWeeks: $numWeeks,
                threshold: $threshold,
                autoCreate: $autoCreate,
                dryRun: true,
                force: $force,
                freeAccessible: $free,
            );

            try {
                $gateResult = $gateImporter->import($canonical);
            } catch (\Throwable $e) {
                return response()->json(ImportJsonReport::buildError('Error comprobando confidence-gate: ' . $e->getMessage()), 422);
            }

            $blocking = ImportJsonReport::buildReviewRequired((array) ($gateResult['results'] ?? []));
            if ($blocking !== []) {
                return response()->json(ImportJsonReport::buildConfidenceGateBlocked($blocking), 422);
            }
        }

        $importer = new ProgramsImporter(
            coachId: $coachId,
            numWeeks: $numWeeks,
            threshold: $threshold,
            autoCreate: $autoCreate,
            dryRun: $dryRun,
            force: $force,
            freeAccessible: $free,
        );

        try {
            $result = $importer->import($canonical);
        } catch (\Throwable $e) {
            return response()->json(ImportJsonReport::buildError('Error importando: ' . $e->getMessage()), 422);
        }

        $reportCsvPath = ImportJsonReport::persistReportCsv($importer->report(), 'excel');

        $payload = ImportJsonReport::buildPayload(
            'excel',
            $originalName,
            $dryRun,
            $programsDetected,
            $result,
            $importer->report(),
            $reportCsvPath,
        );

        if (!$dryRun && $request->boolean('check_integrity', false)) {
            $anyCreated = collect((array) ($result['results'] ?? []))->contains(fn ($r) => ($r['status'] ?? null) === 'created');
            if ($anyCreated) {
                // No se usa Artisan::call() -- ver la explicación en
                // ImportProgramsCommand::runCheckIntegrity() sobre por qué
                // pisa el output compartido de la Application. Aquí no hay
                // un Artisan::call() exterior que romper, pero se resuelve
                // igual por consistencia y para no reintroducir el bug si
                // este endpoint se ejercita alguna vez a través de uno.
                $command = app(\Illuminate\Contracts\Console\Kernel::class)->all()['programs:check-integrity'];
                $input = new ArrayInput(['--coach-id' => $coachId]);
                $buffer = new BufferedOutput();
                $command->run($input, $buffer);
                $payload['check_integrity_output'] = trim($buffer->fetch());
            }
        }

        return response()->json($payload);
    }
}
