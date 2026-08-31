<?php

namespace App\Enums;

/**
 * Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1).
 * Jerarquía fija de scope (documento §2.2, paso 4), AMPLIADA (2026-08-11)
 * con `programa_especifico`:
 * cliente_específico > programa_específico > ejercicio_específico > categoría_ejercicio > global.
 * "categoría_ejercicio" se resuelve contra BodyPart (no existe una tabla
 * "categoría" propia de ejercicios en el esquema real) — scope_id referencia
 * un BodyPart.id y se matchea si ese id está en Exercise.bodypart_ids.
 * "programa_especifico" — scope_id referencia un TrainingProgram.id;
 * aplica a CUALQUIER cliente que esté corriendo ese programa concreto (el
 * mismo programa puede estar asignado a varios clientes vía
 * ProgramClientAssignment) — útil para dar una lógica de progresión propia
 * a un bloque/mesociclo concreto (ej. fuerza vs. hipertrofia), distinta de
 * la regla general del cliente. Rankea por debajo de cliente_especifico
 * (un ajuste personal para ESA persona sigue ganando sobre lo que diga el
 * programa) pero por encima de ejercicio/categoría/global.
 */
enum ScopeType: string
{
    case GLOBAL = 'global';
    case CATEGORIA_EJERCICIO = 'categoria_ejercicio';
    case EJERCICIO_ESPECIFICO = 'ejercicio_especifico';
    case PROGRAMA_ESPECIFICO = 'programa_especifico';
    case CLIENTE_ESPECIFICO = 'cliente_especifico';

    /**
     * Rango de jerarquía para ordenar reglas — mayor gana, independiente de
     * la columna `priority` (que solo desempata dentro del mismo scope).
     */
    public function hierarchyRank(): int
    {
        return match ($this) {
            self::CLIENTE_ESPECIFICO => 5,
            self::PROGRAMA_ESPECIFICO => 4,
            self::EJERCICIO_ESPECIFICO => 3,
            self::CATEGORIA_EJERCICIO => 2,
            self::GLOBAL => 1,
        };
    }
}
