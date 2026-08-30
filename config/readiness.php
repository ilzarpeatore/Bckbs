<?php

// Motor de Auto-Regulación de Carga — Fase 4 (readiness score, documento
// §4.1, punto 6: "pesos configurables por coach"). No se pidió un endpoint
// de configuración por coach en el alcance de esta tarea, así que de
// momento el punto de configuración es este archivo (un futuro endpoint de
// ajustes por coach solo tendría que escribir aquí o en una tabla de
// settings, sin tocar ReadinessCalculationService). Los pesos deben sumar
// 1.0; si falta una fuente para un cliente concreto, el servicio
// redistribuye proporcionalmente entre las fuentes disponibles.
// AÑADIDO 2026-08-12: resting_hr (FC en reposo) como cuarta fuente objetiva
// -- marcador de recuperación estándar, mismo tratamiento que hrv/sueno
// (z-score contra la línea base propia del cliente). Pesos rebalanceados
// para seguir sumando 1.0, restando principalmente de hrv/sueno (misma
// categoría de señal) y dejando subjetivo/acwr casi intactos.
return [
    'weights' => [
        'hrv'         => 0.25,
        'sueno'       => 0.25,
        'resting_hr'  => 0.15,
        'subjetivo'   => 0.20,
        'acwr'        => 0.15,
    ],
];
