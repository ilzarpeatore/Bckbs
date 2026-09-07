<?php

namespace App\Enums;

// Panel de Excepciones del Coach (docs/Panel_Excepciones_Implementacion.md §2.2).
enum ExceptionCategory: string
{
    case DOLOR = 'dolor';
    case ESTANCAMIENTO = 'estancamiento';
    case SUGERENCIA_CARGA = 'sugerencia_carga';
    case READINESS_BAJO = 'readiness_bajo';
    case SEMANA_ADAPTATIVA_PENDIENTE = 'semana_adaptativa_pendiente';
    // DEPRECADO 2026-08-12 (ver docs/Score_Riesgo_Abandono_Implementacion.md):
    // sustituido por RIESGO_ABANDONO. Se conserva el caso (no se borra) para
    // que las filas históricas ya cerradas sigan deserializando sin error —
    // check:client-inactivity ya no genera ítems nuevos de esta categoría.
    case INACTIVIDAD = 'inactividad';
    case RIESGO_ABANDONO = 'riesgo_abandono';
    // Motor de Auto-Regulación de Carga — Plan de Optimización, Ronda 4
    // ítem 15 (docs/Motor_Autorregulacion_Analisis.md): AdaptiveWeekPlanner
    // detecta que el cliente recorta el mismo día de la semana varias
    // semanas adaptativas seguidas — sin source_type/source_id único (no
    // hay una fila que identifique "el patrón", como en INACTIVIDAD),
    // idempotencia vía CoachExceptionFeedService::hasPendingForClientCategory().
    case PATRON_RECORTE_RECURRENTE = 'patron_recorte_recurrente';
}
