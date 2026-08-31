<?php

namespace App\Services\ProgramsImport\Adapters;

/**
 * Adaptadores convierten un archivo de una fuente (CSV Hevy/Strong/JEFIT,
 * JSON openweight, JSON wger) al ESQUEMA CANÓNICO de importación:
 *
 *   [
 *     "programs" => [
 *        ["source", "source_id", "title", "description", "num_weeks", "weeks" => [...]],
 *        ...
 *     ]
 *   ]
 *
 * weeks[].days[].blocks[].exercises[] sigue canonical.example.json.
 */
interface AdapterInterface
{
    /** @return array{programs: array} */
    public function convert(string $filePath): array;
}
