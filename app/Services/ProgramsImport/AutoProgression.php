<?php

namespace App\Services\ProgramsImport;

/**
 * Progresión semana a semana para programas importados desde fuentes planas
 * (Hevy/Strong/JEFIT: una sola plantilla). Genera para cada semana un delta
 * de carga/series/reps/RPE/RIR a partir de la semana base, con fases de
 * acumulación y semanas de deload.
 *
 * Patrón por defecto (mesociclo de N semanas):
 *  - carga:  +2.5% cada 2 semanas desde la base; semanas de deload = 85%.
 *  - series: +1 en las semanas 5 y 10 (máx +2 respecto a la base).
 *  - reps:   -1 cada 4 semanas (mínimo base-2).
 *  - RPE:    +0.5 cada 4 semanas (máx base+1.5); deload = base-1.
 *  - RIR:    espejo del RPE (baja al subir intensidad); deload = base+1.
 *
 * Es configurable vía el JSON database/data/programs/progression-auto.json.
 */
final class AutoProgression
{
    public const CONFIG_PATH = __DIR__ . '/../../database/data/programs/progression-auto.json';

    /**
     * @return array<int, array{week:int, load_multiplier:float, sets_delta:int,
     *                           reps_delta:int, rpe_delta:float, rir_delta:float, deload:bool}>
     */
    public static function table(int $numWeeks): array
    {
        $cfg = self::config();
        $tables = $cfg['tables'];
        $table = null;
        foreach ($tables as $t) {
            if ($numWeeks >= $t['min_weeks'] && ($t['max_weeks'] === null || $numWeeks <= $t['max_weeks'])) {
                $table = $t;
                break;
            }
        }
        if ($table === null) {
            $table = $tables[array_key_last($tables)];
        }

        $deloadWeeks = $table['deload_weeks']; // ej: [4, 9]
        $rows = [];
        for ($w = 1; $w <= $numWeeks; $w++) {
            $deload = in_array($w, $deloadWeeks, true);

            $load = 1.0;
            $sets = 0;
            $reps = 0;
            $rpe = 0.0;
            $rir = 0.0;

            if ($deload) {
                $load = (float) $table['deload_load_pct'] / 100;
                $sets = (int) $table['deload_sets_delta'];
                $reps = (int) $table['deload_reps_delta'];
                $rpe = (float) $table['deload_rpe_delta'];
                $rir = (float) $table['deload_rir_delta'];
            } else {
                $load += (float) ($table['load_pct_per_2weeks']) / 100 * floor(($w - 1) / 2);
                $sets = (int) (($w >= 5 ? 1 : 0) + ($w >= 10 ? 1 : 0));
                $reps = -floor(($w - 1) / 4);
                $rpe = 0.5 * floor(($w - 1) / 4);
                $rir = -0.5 * floor(($w - 1) / 4);
            }

            $rows[] = [
                'week'           => $w,
                'load_multiplier' => round($load, 4),
                'sets_delta'     => $sets,
                'reps_delta'     => $reps,
                'rpe_delta'      => $rpe,
                'rir_delta'      => $rir,
                'deload'         => $deload,
            ];
        }

        return $rows;
    }

    public static function config(): array
    {
        $defaults = [
            'tables' => [
                [
                    'min_weeks'          => 1,
                    'max_weeks'          => 3,
                    'deload_weeks'       => [],
                    'load_pct_per_2weeks' => 5.0,
                    'deload_load_pct'    => 90.0,
                    'deload_sets_delta'  => -1,
                    'deload_reps_delta'  => 0,
                    'deload_rpe_delta'   => -1.0,
                    'deload_rir_delta'   => 1.0,
                ],
                [
                    'min_weeks'          => 4,
                    'max_weeks'          => 7,
                    'deload_weeks'       => [4],
                    'load_pct_per_2weeks' => 5.0,
                    'deload_load_pct'    => 85.0,
                    'deload_sets_delta'  => -1,
                    'deload_reps_delta'  => 0,
                    'deload_rpe_delta'   => -1.0,
                    'deload_rir_delta'   => 1.0,
                ],
                [
                    'min_weeks'          => 8,
                    'max_weeks'          => null,
                    'deload_weeks'       => [4, 9],
                    'load_pct_per_2weeks' => 5.0,
                    'deload_load_pct'    => 85.0,
                    'deload_sets_delta'  => -1,
                    'deload_reps_delta'  => 0,
                    'deload_rpe_delta'   => -1.0,
                    'deload_rir_delta'   => 1.0,
                ],
            ],
        ];

        if (is_file(self::CONFIG_PATH)) {
            $json = json_decode((string) file_get_contents(self::CONFIG_PATH), true);
            if (is_array($json) && isset($json['tables'])) {
                $defaults = $json;
            }
        }

        return $defaults;
    }
}
