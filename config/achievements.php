<?php

/**
 * Motor de Auto-Regulación de Carga — Fase 3 (documento §3.2: "GET
 * /api/coaches/{id}/achievement-settings — umbrales configurables"). Todos
 * los valores tienen su constante espejo en el código que los aplica de
 * verdad (comentado abajo) — este archivo es el punto único de lectura
 * para el endpoint de coach, sin duplicar el criterio en dos sitios que
 * puedan desincronizarse en el futuro si algún día se hace editable.
 *
 * Decisión de diseño propia: valores globales de solo lectura en config en
 * vez de una tabla nueva por coach (el documento deja el criterio
 * abierto). Si en el futuro se necesita personalización real por coach,
 * esto se mueve a una tabla mínima sin romper el contrato del endpoint.
 */
return [
    // ClientExerciseLogObserver::PR_CARGA_MIN_IMPROVEMENT_PCT
    'pr_carga_min_improvement_pct' => 0.025,

    // EvaluateSessionAchievements::STREAK_MILESTONES
    'streak_milestones' => [5, 10, 20, 50],

    // EvaluateSessionAchievements::COMPLIANCE_*
    'compliance_window_days'   => 28,
    'compliance_threshold'      => 0.80,
    'compliance_min_scheduled'  => 8,
];
