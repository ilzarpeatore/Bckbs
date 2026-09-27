<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TrainingProgram;
use App\Services\MacrocyclePlanService;
use Illuminate\Http\Request;

/**
 * Dashboard de KPIs de un macrociclo (página /macrociclos del panel): datos
 * planificados (MacrocyclePlanService) y referencias MEV/MRV editables por
 * grupo muscular.
 */
class MacrocycleDashboardController extends Controller
{
    private const REFERENCES_TYPE = 'macrocycle_dashboard';
    private const REFERENCES_KEY = 'mev_mrv';

    /**
     * Valores iniciales, los de la hoja «Programación 6 meses» del coach
     * (sets directos por semana, orientativos). Claves = títulos de body_parts.
     */
    private const DEFAULT_REFERENCES = [
        'Dorsales'            => ['mev' => 10, 'mrv' => 20],
        'Pecho'               => ['mev' => 8, 'mrv' => 18],
        'Deltoides lateral'   => ['mev' => 6, 'mrv' => 16],
        'Deltoides frontal'   => ['mev' => 6, 'mrv' => 14],
        'Deltoides posterior' => ['mev' => 6, 'mrv' => 14],
        'Tríceps'             => ['mev' => 8, 'mrv' => 18],
        'Bíceps'              => ['mev' => 8, 'mrv' => 18],
        'Cuádriceps'          => ['mev' => 10, 'mrv' => 22],
        'Isquiotibiales'      => ['mev' => 8, 'mrv' => 16],
        'Gluteo'              => ['mev' => 8, 'mrv' => 18],
        'Gemelos'             => ['mev' => 6, 'mrv' => 14],
        'Abdominales'         => ['mev' => 6, 'mrv' => 12],
    ];

    /**
     * GET admin/macrocycle-plan?program_ids[]=1&program_ids[]=2
     * Solo programas del coach autenticado (mismo criterio que el editor de
     * sesiones); los demás se devuelven en skipped_ids.
     */
    public function plan(Request $request, MacrocyclePlanService $service)
    {
        $request->validate([
            'program_ids'   => 'required|array|min:1|max:24',
            'program_ids.*' => 'integer',
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->program_ids)));
        $programs = TrainingProgram::whereIn('id', $ids)
            ->where('coach_id', auth('sanctum')->id())
            ->where('is_personal', false)
            ->get();

        $numbers = [];
        foreach ($programs as $program) {
            $numbers[$program->id] = TrainingProgramController::mesocycleNumberOf($program);
        }

        // Orden del macrociclo: nº de mesociclo, luego fecha de creación
        $programs = $programs->sort(function ($a, $b) use ($numbers) {
            return ($numbers[$a->id] ?? PHP_INT_MAX) <=> ($numbers[$b->id] ?? PHP_INT_MAX)
                ?: $a->created_at <=> $b->created_at;
        })->values();

        $data = $service->build($programs, $numbers);
        $data['skipped_ids'] = array_values(array_diff($ids, $programs->pluck('id')->all()));

        return json_custom_response(['data' => $data]);
    }

    /** GET admin/macrocycle-references: MEV/MRV por grupo muscular. */
    public function references()
    {
        return json_custom_response(['data' => (object) $this->loadReferences()]);
    }

    /** POST admin/macrocycle-references-save { references: { "Pecho": {mev, mrv}, ... } } — sustituye todas. */
    public function saveReferences(Request $request)
    {
        $request->validate([
            'references'       => 'present|array|max:100',
            'references.*.mev' => 'nullable|numeric|min:0|max:200',
            'references.*.mrv' => 'nullable|numeric|min:0|max:200',
        ]);

        $clean = [];
        foreach ($request->input('references', []) as $muscle => $ref) {
            $muscle = trim((string) $muscle);
            if ($muscle === '' || mb_strlen($muscle) > 100) {
                continue;
            }
            $clean[$muscle] = [
                'mev' => isset($ref['mev']) && $ref['mev'] !== '' ? (float) $ref['mev'] : null,
                'mrv' => isset($ref['mrv']) && $ref['mrv'] !== '' ? (float) $ref['mrv'] : null,
            ];
        }

        Setting::updateOrCreate(
            ['type' => self::REFERENCES_TYPE, 'key' => self::REFERENCES_KEY],
            ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE)]
        );

        return json_custom_response(['data' => (object) $clean]);
    }

    private function loadReferences(): array
    {
        $row = Setting::where('type', self::REFERENCES_TYPE)->where('key', self::REFERENCES_KEY)->first();
        if ($row === null || $row->value === null) {
            return self::DEFAULT_REFERENCES;
        }
        $decoded = json_decode($row->value, true);

        return is_array($decoded) ? $decoded : self::DEFAULT_REFERENCES;
    }
}
