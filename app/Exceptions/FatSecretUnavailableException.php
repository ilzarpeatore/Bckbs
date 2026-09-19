<?php

namespace App\Exceptions;

use Exception;

/**
 * FatSecret no respondió o devolvió un error. Nunca debe romper el flujo
 * normal de crear/editar un ingrediente a mano -- ver
 * docs/FATSECRET_INTEGRATION.md sección 4.1 (es una ayuda opcional).
 */
class FatSecretUnavailableException extends Exception
{
}
