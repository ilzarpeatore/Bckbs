<?php

namespace App\Console\Commands;

use App\Enums\AchievementEventType;
use App\Models\AchievementEvent;
use App\Models\ClientExerciseLog;
use App\Models\ExerciseSessionMetric;
use App\Models\PersonalRecord;
use App\Services\SessionInterpretationService;
use App\Support\LoggedSetMath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpia los récords inflados por técnicas de "total de repeticiones"
 * (rest-pause, drop sets, cluster, myo-reps...) registrados antes de
 * 2026-10-03, cuando la ficha pedía apuntar todas las repeticiones en una
 * sola serie con el peso inicial (ver LoggedSetMath).
 *
 * Por cada cliente+ejercicio con alguna serie así:
 *   - max_1rm: recalcula el mejor 1RM real (sin esas series) y borra las
 *     filas de personal_records por encima; si el mejor real no tiene fila,
 *     la crea con la fecha de esa sesión.
 *   - achievement_events mejora_e1rm por encima del valor real:
 *     se borran (eran logros falsos).
 *   - exercise_session_metrics de esas sesiones: recalcula carga_efectiva,
 *     carga_efectiva_reps, volumen_total y e1rm_estimado.
 *
 * Por defecto solo informa (dry run). Con --apply escribe, en una
 * transacción. max_weight y max_volume no se tocan: el peso es real.
 */
class RecalculateTechniqueRecordsCommand extends Command
{
    protected $signature = 'records:recalcular-tecnicas {--apply : Escribe los cambios (sin esto solo informa)} {--client= : Solo este cliente}';

    protected $description = 'Corrige 1RM, récords y logros inflados por series de técnicas apuntadas como total de repeticiones';

    public function handle(SessionInterpretationService $interpretation): int
    {
        $apply = (bool) $this->option('apply');
        $pairs = []; // "client:exercise" => [best1rm, bestDate, bestReps]
        $affectedLogs = [];

        $query = ClientExerciseLog::whereNotNull('workout_template_exercise_id')->orderBy('id');
        if ($this->option('client')) {
            $query->where('client_id', (int) $this->option('client'));
        }

        // 1) Qué parejas cliente+ejercicio tienen alguna serie inflada.
        $query->chunkById(500, function ($logs) use ($interpretation, &$pairs, &$affectedLogs) {
            foreach ($logs as $log) {
                $sets = LoggedSetMath::annotate((array) ($log->logged_sets ?? []), $interpretation->resolvePrescribed($log));
                foreach ($sets as $set) {
                    if (is_array($set) && LoggedSetMath::isInflated($set)) {
                        $pairs["{$log->client_id}:{$log->exercise_id}"] = null;
                        $affectedLogs[$log->id] = true;
                        break;
                    }
                }
            }
        });

        if (!$pairs) {
            $this->info('No hay series de técnica infladas. Nada que corregir.');

            return self::SUCCESS;
        }

        $summary = ['parejas' => count($pairs), 'records_borrados' => 0, 'records_creados' => 0, 'logros_borrados' => 0, 'metricas_corregidas' => 0];

        $run = function () use ($interpretation, $pairs, $apply, &$summary) {
            foreach (array_keys($pairs) as $pair) {
                [$clientId, $exerciseId] = array_map('intval', explode(':', $pair));

                // 2) Mejor 1RM y mejores reps por peso reales del histórico.
                $best = 0.0;
                $bestDate = null;
                $logs = ClientExerciseLog::where('client_id', $clientId)->where('exercise_id', $exerciseId)->orderBy('id')->get();
                foreach ($logs as $log) {
                    $sets = LoggedSetMath::annotate((array) ($log->logged_sets ?? []), $interpretation->resolvePrescribed($log));
                    foreach ($sets as $set) {
                        if (!is_array($set)) {
                            continue;
                        }
                        $w = LoggedSetMath::weight($set);
                        $r = LoggedSetMath::strengthReps($set);
                        if ($w === null || $r === null || $w <= 0 || $r <= 0) {
                            continue;
                        }
                        $e = PersonalRecord::calculateEpley1RM($w, $r);
                        if ($e > $best) {
                            $best = $e;
                            $bestDate = $log->performed_date ?? $log->created_at;
                        }
                    }
                }

                $inflatedRecords = PersonalRecord::where('user_id', $clientId)->where('exercise_id', $exerciseId)
                    ->where('record_type', 'max_1rm')->where('value', '>', round($best, 2) + 0.01);
                $n = $inflatedRecords->count();
                if ($n) {
                    $this->line("cliente {$clientId} · ejercicio {$exerciseId}: {$n} récord(s) de 1RM por encima del real (" . round($best, 1) . ' kg)');
                    $summary['records_borrados'] += $n;
                    if ($apply) {
                        $inflatedRecords->delete();
                        $exists = PersonalRecord::where('user_id', $clientId)->where('exercise_id', $exerciseId)
                            ->where('record_type', 'max_1rm')->where('value', '>=', round($best, 2) - 0.01)->exists();
                        if ($best > 0 && !$exists) {
                            PersonalRecord::create([
                                'user_id' => $clientId, 'exercise_id' => $exerciseId, 'record_type' => 'max_1rm',
                                'value' => round($best, 2), 'achieved_at' => $bestDate,
                            ]);
                            $summary['records_creados']++;
                        }
                    }
                }

                $fakeEvents = AchievementEvent::where('client_id', $clientId)->where('exercise_id', $exerciseId)
                    ->where('type', AchievementEventType::MEJORA_E1RM->value)->where('value', '>', round($best, 2) + 0.01);
                $m = $fakeEvents->count();
                $summary['logros_borrados'] += $m;
                if ($apply && $m) {
                    $fakeEvents->delete();
                }
            }

            // 3) Métricas por sesión (motor de progresión).
            $metrics = ExerciseSessionMetric::where(function ($q) use ($pairs) {
                foreach (array_keys($pairs) as $pair) {
                    [$c, $e] = array_map('intval', explode(':', $pair));
                    $q->orWhere(fn ($qq) => $qq->where('client_id', $c)->where('exercise_id', $e));
                }
            })->get();
            foreach ($metrics as $metric) {
                $new = $interpretation->recomputeStrengthFields($metric);
                if ($new === null) {
                    continue;
                }
                $changed = abs((float) $metric->e1rm_estimado - (float) $new['e1rm_estimado']) > 0.01
                    || abs((float) $metric->carga_efectiva - (float) $new['carga_efectiva']) > 0.01
                    || (int) $metric->carga_efectiva_reps !== (int) $new['carga_efectiva_reps'];
                if ($changed) {
                    $summary['metricas_corregidas']++;
                    if ($apply) {
                        $metric->forceFill($new)->save();
                    }
                }
            }
        };

        $apply ? DB::transaction($run) : $run();

        $this->table(array_keys($summary), [array_values($summary)]);
        $this->info($apply ? 'Cambios aplicados.' : 'Dry run: no se ha cambiado nada. Ejecuta con --apply para aplicar.');

        return self::SUCCESS;
    }
}
