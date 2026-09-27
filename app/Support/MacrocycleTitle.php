<?php

namespace App\Support;

/**
 * Macrociclos (pedido 2026-09-27, página /macrociclos del panel): no existe
 * una entidad "macrociclo" en la BD -- cada mesociclo es un TrainingProgram
 * independiente y la pertenencia a un macrociclo solo vive en el título
 * ("Macrociclo 2 - Mesociclo 1", "M1 (Be Stronger Macrociclo 2)",
 * "Carlos - Macrociclo 1 - Mesociclo 3"...). Este parser saca de un título
 * el nombre del macrociclo y el número de mesociclo para poder agruparlos.
 *
 * Devuelve null si el título no menciona ni un mesociclo ni un macrociclo
 * (programa suelto, no pertenece a ninguno).
 */
class MacrocycleTitle
{
    /**
     * @return array{macrocycle: string, key: string, mesocycle: int|null}|null
     */
    public static function parse(?string $title): ?array
    {
        $title = trim((string) $title);
        if ($title === '') {
            return null;
        }

        $mesocycle = null;
        $rest = $title;

        // "Mesociclo 1", "Meso 1", "Mesociclo #1"
        if (preg_match('/\bmeso(?:ciclo)?\s*#?\s*(\d+)\b/iu', $rest, $m, PREG_OFFSET_CAPTURE)) {
            $mesocycle = (int) $m[1][0];
            $rest = substr_replace($rest, ' ', $m[0][1], strlen($m[0][0]));
        // "M1" (mayúscula, pegado al número) -- formato de los imports "M1 (Be Stronger Macrociclo 2)"
        } elseif (preg_match('/\bM(\d+)\b/u', $rest, $m, PREG_OFFSET_CAPTURE)) {
            $mesocycle = (int) $m[1][0];
            $rest = substr_replace($rest, ' ', $m[0][1], strlen($m[0][0]));
        }

        $mentionsMacro = preg_match('/\bmacro(?:ciclo)?\b/iu', $rest) === 1;
        if ($mesocycle === null && !$mentionsMacro) {
            return null;
        }

        // Paréntesis vacíos o que envuelven todo lo que queda, y separadores sueltos en los extremos
        $name = preg_replace('/\(\s*\)|\[\s*\]/u', ' ', $rest);
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (preg_match('/^\((.*)\)$/u', $name, $m) || preg_match('/^\[(.*)\]$/u', $name, $m)) {
            $name = trim($m[1]);
        }
        $name = trim(preg_replace('/\s+[-–—·|:,]\s+/u', ' - ', $name));
        $name = trim($name, " \t-–—·|:,");
        $name = preg_replace('/\s+-\s*$/u', '', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            $name = 'Sin nombre';
        }

        return [
            'macrocycle' => $name,
            'key'        => mb_strtolower($name),
            'mesocycle'  => $mesocycle,
        ];
    }
}
