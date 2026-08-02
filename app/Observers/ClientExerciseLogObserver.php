<?php

namespace App\Observers;

use App\Models\ClientExerciseLog;
use App\Models\PersonalRecord;

/**
 * Listener documentado desde el principio en la migracion de
 * personal_records ("se rellena mediante un listener disparado al
 * completar una sesion") pero nunca implementado - la tabla estaba
 * siempre vacia. Se dispara en cada guardado de series (logSets ya
 * envia el estado acumulado de series completadas de ese ejercicio,
 * no solo la ultima), y solo crea un registro nuevo si supera el
 * record anterior de ese mismo tipo.
 */
class ClientExerciseLogObserver
{
    public function created(ClientExerciseLog $log): void
    {
        $sets = $log->logged_sets ?? [];
        if (empty($sets)) {
            return;
        }

        $maxWeight = 0.0;
        $maxOneRm = 0.0;
        $totalVolume = 0.0;

        foreach ($sets as $set) {
            $weight = (float) ($set['carga'] ?? 0);
            $reps = (int) ($set['reps'] ?? 0);
            if ($weight <= 0 || $reps <= 0) {
                continue;
            }

            $totalVolume += $weight * $reps;
            $maxWeight = max($maxWeight, $weight);
            $maxOneRm = max($maxOneRm, PersonalRecord::calculateEpley1RM($weight, $reps));
        }

        if ($maxWeight <= 0 && $maxOneRm <= 0 && $totalVolume <= 0) {
            return;
        }

        $achievedAt = $log->performed_date ?? now();

        $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_weight', $maxWeight, $achievedAt);
        $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_1rm', $maxOneRm, $achievedAt);
        $this->storeIfRecord($log->client_id, $log->exercise_id, 'max_volume', $totalVolume, $achievedAt);
    }

    private function storeIfRecord(int $userId, int $exerciseId, string $recordType, float $value, $achievedAt): void
    {
        if ($value <= 0) {
            return;
        }

        $previousBest = PersonalRecord::where('user_id', $userId)
            ->where('exercise_id', $exerciseId)
            ->where('record_type', $recordType)
            ->max('value');

        if ($previousBest !== null && $value <= $previousBest) {
            return;
        }

        PersonalRecord::create([
            'user_id'      => $userId,
            'exercise_id'  => $exerciseId,
            'record_type'  => $recordType,
            'value'        => round($value, 2),
            'achieved_at'  => $achievedAt,
        ]);
    }
}
