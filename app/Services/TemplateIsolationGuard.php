<?php

namespace App\Services;

use App\Models\ProgramClientAssignment;
use App\Models\ProgramDayAssignment;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;

/**
 * Aislamiento entre clientes: cada cliente debe tener su programa y sus
 * plantillas de sesión TOTALMENTE independientes (TrainingProgram::cloneForClient,
 * assignDirect). Este guard es la red de seguridad del lado servidor por si
 * alguna ruta o dato futuro vuelve a dejar una fila compartida: en vez de
 * modificarla en silencio (y cambiar lo de otros clientes), se rechaza.
 *
 * "Propietario" de un programa:
 *   - `client:<id>` si es la copia de un cliente (client_id) o su programa
 *     personal (personal_client_id);
 *   - `library` en cualquier otro caso (programa de la biblioteca del coach).
 * Una plantilla está compartida entre propietarios cuando la usan días de
 * programas de más de un propietario distinto (p. ej. biblioteca + un cliente,
 * o el cliente A + el cliente B). Varios programas de la biblioteca entre sí
 * o varios programas del mismo cliente NO cuentan como compartir.
 */
class TemplateIsolationGuard
{
    public const LIBRARY = 'library';

    public static function ownerKey(TrainingProgram $program): string
    {
        $clientId = $program->client_id ?: $program->personal_client_id;

        return $clientId ? 'client:'.$clientId : self::LIBRARY;
    }

    /** @return array<int,string> claves de propietario distintas que usan la plantilla */
    public static function ownersOfTemplate(int $templateId): array
    {
        $programIds = ProgramDayAssignment::where('workout_template_id', $templateId)
            ->pluck('training_program_id')
            ->unique()
            ->all();

        $owners = $programIds === []
            ? []
            : TrainingProgram::whereIn('id', $programIds)
                ->get(['id', 'client_id', 'personal_client_id'])
                ->map(fn (TrainingProgram $p) => self::ownerKey($p))
                ->unique()
                ->values()
                ->all();

        // Las plantillas del catálogo (demo o públicas para todos los clientes) son de la BIBLIOTECA
        // aunque aún no tengan ningún día: si un día de cliente las usara directamente, editarlas
        // cambiaría el catálogo para todos.
        $flags = \App\Models\WorkoutTemplate::whereKey($templateId)->first(['id', 'is_demo', 'is_public']);
        if ($flags !== null && ($flags->is_demo || $flags->is_public) && !in_array(self::LIBRARY, $owners, true)) {
            $owners[] = self::LIBRARY;
        }

        return $owners;
    }

    public static function isSharedAcrossOwners(int $templateId): bool
    {
        return count(self::ownersOfTemplate($templateId)) > 1;
    }

    /** 409 si la plantilla la usan varios propietarios; null si se puede modificar. */
    public static function violation(?int $templateId): ?JsonResponse
    {
        if (!$templateId || !self::isSharedAcrossOwners($templateId)) {
            return null;
        }

        return json_message_response(
            'Esta plantilla está compartida con otro cliente o con la biblioteca, así que no se modifica para no cambiar lo de los demás. '
            .'Reasigna o reimporta el programa de este cliente para que tenga su propia copia, o usa el editor de sesiones del programa, que la desvincula automáticamente.',
            409
        );
    }

    /**
     * La sesión (program_day_assignment) pertenece a un programa de la biblioteca o de otro cliente
     * y se está usando desde el contexto de $clientId -> 409/403 según el caso.
     */
    public static function assignmentOutsideClient(ProgramDayAssignment $assignment, ?int $clientId): ?JsonResponse
    {
        $program = TrainingProgram::find($assignment->training_program_id);
        if ($program === null) {
            return null;
        }

        $owner = self::ownerKey($program);

        if ($owner === self::LIBRARY) {
            // Programa de la biblioteca asignado directamente (legacy, sin copia propia): solo se
            // permite si ese cliente es el ÚNICO que lo tiene -- si lo compartiera con otros, quitar
            // o duplicar un día lo cambiaría para todos.
            $clients = ProgramClientAssignment::where('training_program_id', $program->id)
                ->pluck('client_id')->unique()->values();

            $shared = $clientId !== null
                ? ($clients->contains(fn ($c) => (int) $c !== $clientId) || !$clients->contains($clientId))
                : $clients->count() > 1;

            if ($shared) {
                return json_message_response(
                    'Esta sesión pertenece a un programa de la biblioteca compartido con otros clientes; no se modifica desde el calendario de un cliente.',
                    409
                );
            }

            return null;
        }

        if ($clientId !== null && $owner !== 'client:'.$clientId) {
            return json_message_response('Esta sesión pertenece al programa de otro cliente.', 403);
        }

        return null;
    }

    /**
     * Clientes con este programa asignado DIRECTAMENTE (sin copia propia): id => nombre.
     * Vacío para las copias de cliente y para programas de biblioteca sin asignar.
     * Sirve para avisar al editar un programa de biblioteca que un cliente usa en vivo.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public static function directClientsOfProgram(TrainingProgram $program): array
    {
        if (self::ownerKey($program) !== self::LIBRARY) {
            return [];
        }

        $ids = ProgramClientAssignment::where('training_program_id', $program->id)
            ->pluck('client_id')->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return \App\Models\User::whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn ($u) => [
                'id'   => (int) $u->id,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: (string) $u->email,
            ])->all();
    }
}
