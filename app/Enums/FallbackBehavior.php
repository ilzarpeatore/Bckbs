<?php

namespace App\Enums;

// Motor de Auto-Regulación de Carga — Fase 2 (documento §2.1, §2.3).
// Se aplica cuando no hay respuesta del coach antes de que la sesión
// correspondiente se vuelva "próxima" (ventana configurable, ver
// ApplyProgressionFallbacks).
enum FallbackBehavior: string
{
    case APLICAR_IGUAL = 'aplicar_igual';
    case MANTENER_SIN_CAMBIO = 'mantener_sin_cambio';
    case ESCALAR_A_NOTIFICACION_URGENTE = 'escalar_a_notificacion_urgente';
}
