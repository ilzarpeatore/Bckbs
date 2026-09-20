<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgram extends Model
{
    use HasFactory, SoftDeletes;

    // AÑADIDO: is_personal, personal_client_id (calendario personal por cliente)
    // AÑADIDO: is_free_accessible, billing_plan_id (acceso gratuito / plan)
    // AÑADIDO: source, source_id (procedencia de imports de programas)
    protected $fillable = [
        'title', 'is_personal', 'personal_client_id', 'workout_id', 'coach_id', 'client_id',
        'num_weeks', 'fecha_inicio', 'fecha_fin', 'activo',
        'is_free_accessible', 'billing_plan_id',
        'source', 'source_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'activo'       => 'boolean',
        'is_personal'  => 'boolean',
    ];

    public function workout()
    {
        return $this->belongsTo(Workout::class, 'workout_id', 'id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id', 'id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function clientAssignments()
    {
        return $this->hasMany(ProgramClientAssignment::class, 'training_program_id', 'id');
    }

    public function dayAssignments()
    {
        return $this->hasMany(ProgramDayAssignment::class, 'training_program_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }

    // AÑADIDO: solo plantillas normales, sin los calendarios personales (para listados/biblioteca)
    public function scopeLibrary($query)
    {
        return $query->where('is_personal', false);
    }

    /**
     * BUG REAL (reportado 2026-09-20): TrainingProgramController::assignClient()
     * y ClientProfileCalendarController::importProgram() apuntaban
     * ProgramClientAssignment directamente a este mismo training_program_id
     * -- program_day_assignments (con sus workout_template_id) es una tabla
     * COMPARTIDA por el programa entero, no por cliente. Borrar un
     * entrenamiento del calendario de UN cliente (ClientProfileCalendarController::
     * removeAssignment(), que hace ProgramDayAssignment::delete() sin
     * filtrar por cliente) borraba esa fila para TODOS los clientes que
     * compartieran el programa, incluida la propia plantilla de la
     * biblioteca. Mismo patrón de bug ya detectado y arreglado el
     * 2026-09-18 para asignación de un solo entrenamiento suelto -- ver
     * WorkoutTemplate::cloneStructure() y el comentario en
     * ClientProfileCalendarController::assignDirect().
     *
     * Extraído de TrainingProgramController::duplicate() (única lógica de
     * clonado real que ya existía, hasta ahora solo para "duplicar en la
     * biblioteca") para que ambos casos de uso -- duplicar en biblioteca y
     * clonar al asignar a un cliente -- compartan exactamente la misma
     * lógica en vez de arriesgarse a divergir. $overrides pisa los valores
     * por defecto (copiar tal cual del original); quien llama decide
     * title/coach_id/client_id/source/source_id según el caso.
     *
     * Un mismo workout_template puede repetirse en varios días del propio
     * programa (ej. "Torso A" en semana 1 y semana 3) -- se clona una sola
     * vez por template original y se reutiliza esa copia dentro del NUEVO
     * programa, en vez de crear una copia distinta por cada fila que lo
     * referenciaba (rompería la relación "es el mismo entrenamiento" dentro
     * de la copia).
     */
    public function cloneWithStructure(array $overrides = []): self
    {
        $copy = self::create(array_merge([
            'title'               => $this->title,
            'is_personal'         => false,
            'personal_client_id'  => null,
            'workout_id'          => $this->workout_id,
            'coach_id'            => $this->coach_id,
            'client_id'           => null,
            'num_weeks'           => $this->num_weeks,
            'fecha_inicio'        => $this->fecha_inicio,
            'fecha_fin'           => $this->fecha_fin,
            'activo'              => true,
            'is_free_accessible'  => $this->is_free_accessible,
            'billing_plan_id'     => $this->billing_plan_id,
            'source'              => null,
            'source_id'           => null,
        ], $overrides));

        $clonedTemplateIds = [];
        foreach ($this->dayAssignments as $dayAssignment) {
            $newTemplateId = null;
            if ($dayAssignment->workout_template_id !== null && $dayAssignment->workoutTemplate !== null) {
                $originalTemplateId = $dayAssignment->workout_template_id;
                if (!isset($clonedTemplateIds[$originalTemplateId])) {
                    $clonedTemplateIds[$originalTemplateId] = $dayAssignment->workoutTemplate->cloneStructure()->id;
                }
                $newTemplateId = $clonedTemplateIds[$originalTemplateId];
            }

            $copy->dayAssignments()->create([
                'week_number'         => $dayAssignment->week_number,
                'day_of_week'         => $dayAssignment->day_of_week,
                'workout_template_id' => $newTemplateId,
                'scheduled_date'      => null,
                'is_deload'           => $dayAssignment->is_deload,
            ]);
        }

        return $copy;
    }

    public function cloneForClient(int $clientId): self
    {
        return $this->cloneWithStructure([
            'client_id' => $clientId,
            'source'    => 'client_import',
            'source_id' => $this->id,
        ]);
    }
}
