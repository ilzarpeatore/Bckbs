<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;
use App\Models\BodyPart;
use App\Models\Equipment;
use App\Models\Level;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExerciseDbImportSeeder extends Seeder
{
    /**
     * Set to true to download exercise GIFs from exercisedb.dev.
     * WARNING: 1,500 images will take several minutes and storage space.
     */
    private const DOWNLOAD_IMAGES = false;

    private const BODY_PART_MAP = [
        'chest' => 'Pecho',
        'back' => 'Espalda',
        'shoulders' => 'Hombros',
        'biceps' => 'Bíceps',
        'triceps' => 'Tríceps',
        'quadriceps' => 'Cuádriceps',
        'hamstrings' => 'Isquiotibiales',
        'glutes' => 'Glúteos',
        'abdominals' => 'Abdominales',
        'calves' => 'Gemelos',
        'forearms' => 'Antebrazos',
        'traps' => 'Trapecios',
        'full body' => 'Cuerpo completo',
        'neck' => 'Cuello',
        'lower arms' => 'Antebrazos',
        'cardio' => 'Cuerpo completo',
    ];

    private const EQUIPMENT_MAP = [
        'dumbbell' => 'Mancuernas',
        'barbell' => 'Barra olímpica',
        'olympic barbell' => 'Barra olímpica',
        'ez barbell' => 'Barra olímpica',
        'kettlebell' => 'Kettlebell',
        'resistance band' => 'Bandas de resistencia',
        'band' => 'Bandas de resistencia',
        'trx' => 'TRX',
        'assisted' => 'TRX',
        'smith machine' => 'Máquina Smith',
        'cable' => 'Polea',
        'bench' => 'Banco',
        'sled machine' => 'Máquina de remo',
        'skierg machine' => 'Máquina de remo',
        'stepmill machine' => 'Cinta de correr',
        'stationary bike' => 'Bicicleta estática',
        'elliptical machine' => 'Elíptica',
        'body weight' => 'Peso corporal',
        'weighted' => 'Peso corporal',
        'wheel roller' => 'Rueda abdominal',
        'stability ball' => 'Fitball',
        'medicine ball' => 'Fitball',
        'bosu ball' => 'Fitball',
        'tire' => 'Cajón pliométrico',
        'trap bar' => 'Barra olímpica',
        'hammer' => 'Mancuernas',
        'upper body ergometer' => 'Máquina de remo',
        'rope' => 'TRX',
        'roller' => 'Rueda abdominal',
    ];

    private const LEVEL_MAP = [
        'beginner' => 'Principiante',
        'intermediate' => 'Intermedio',
        'expert' => 'Avanzado',
    ];

    public function run(): void
    {
        $dataPath = database_path('data');

        $exercises = json_decode(file_get_contents("{$dataPath}/exercises.json"), true);
        $bodyParts = json_decode(file_get_contents("{$dataPath}/body-parts.json"), true);
        $equipmentList = json_decode(file_get_contents("{$dataPath}/equipment.json"), true);

        $this->command->info('Importing ExerciseDB data...');
        $this->command->info('Exercises: ' . count($exercises));
        $this->command->info('Body parts: ' . count($bodyParts));
        $this->command->info('Equipment items: ' . count($equipmentList));

        // Ensure reference data exists in DB
        $bodyPartIds = $this->syncBodyParts($bodyParts);
        $equipmentIds = $this->syncEquipment($equipmentList);
        $levelIds = $this->getLevelIds();

        $this->command->info('Mapped body parts: ' . count($bodyPartIds));
        $this->command->info('Mapped equipment: ' . count($equipmentIds));
        $this->command->info('Mapped levels: ' . count($levelIds));

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            foreach ($exercises as $index => $exercise) {
                $title = $this->capitalizeWords($exercise['name']);
                $slug = Str::slug($title);

                if (Exercise::withTrashed()->where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                $bodyPartNames = $exercise['bodyParts'] ?? [];
                $bodypartIds = [];
                foreach ($bodyPartNames as $bpName) {
                    $spanishName = self::BODY_PART_MAP[strtolower($bpName)] ?? $bpName;
                    if ($spanishName && isset($bodyPartIds[$spanishName])) {
                        $bodypartIds[] = $bodyPartIds[$spanishName];
                    }
                }
                $bodypartIds = array_unique($bodypartIds);

                $equipmentNames = $exercise['equipments'] ?? [];
                $equipmentId = null;
                foreach ($equipmentNames as $eqName) {
                    $spanishName = self::EQUIPMENT_MAP[strtolower($eqName)] ?? null;
                    if ($spanishName && isset($equipmentIds[$spanishName])) {
                        $equipmentId = $equipmentIds[$spanishName];
                        break;
                    }
                }

                // Default to body weight if no equipment mapped
                if (! $equipmentId && isset($equipmentIds['Peso corporal'])) {
                    $equipmentId = $equipmentIds['Peso corporal'];
                }

                $levelName = self::LEVEL_MAP[strtolower($exercise['level'] ?? 'intermediate')] ?? 'Intermedio';
                $levelId = $levelIds[$levelName] ?? null;

                $instructions = is_array($exercise['instructions'] ?? null)
                    ? $exercise['instructions']
                    : $this->parseInstructions($exercise['instructions'] ?? '');

                $instructionText = implode("\n\n", $instructions);

                $created = Exercise::create([
                    'title' => $title,
                    'instruction' => $instructionText,
                    'tips' => null,
                    'video_type' => null,
                    'video_url' => null,
                    'bodypart_ids' => array_values($bodypartIds),
                    'duration' => null,
                    'sets' => null,
                    'equipment_id' => $equipmentId,
                    'level_id' => $levelId,
                    'status' => 'active',
                    'is_premium' => 0,
                    'based' => 'reps',
                    'type' => 'sets',
                    'seconds_per_rep' => null,
                ]);

                if (self::DOWNLOAD_IMAGES && ! empty($exercise['gifUrl'])) {
                    try {
                        $created
                            ->addMediaFromUrl($exercise['gifUrl'])
                            ->toMediaCollection('exercise_image');
                    } catch (\Throwable $e) {
                        $this->command->warn("Could not download image for {$title}: {$e->getMessage()}");
                    }
                }

                $imported++;

                if ($imported % 100 === 0) {
                    $this->command->info("Imported {$imported} exercises...");
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Import failed: ' . $e->getMessage());
            throw $e;
        }

        $this->command->info("Import complete: {$imported} imported, {$skipped} skipped (duplicates), {$errors} errors.");
    }

    private function syncBodyParts(array $bodyParts): array
    {
        $existing = BodyPart::pluck('id', 'title')->toArray();
        $ids = [];

        foreach ($bodyParts as $bp) {
            $name = strtolower($bp['name']);
            $spanishName = self::BODY_PART_MAP[$name] ?? $bp['name'];

            if (isset($existing[$spanishName])) {
                $ids[$spanishName] = $existing[$spanishName];
                continue;
            }

            $created = BodyPart::create([
                'title' => $spanishName,
                'status' => 'active',
            ]);

            $ids[$spanishName] = $created->id;
            $existing[$spanishName] = $created->id;
            $this->command->info("Created body part: {$spanishName}");
        }

        return $ids;
    }

    private function syncEquipment(array $equipmentList): array
    {
        $existing = Equipment::pluck('id', 'title')->toArray();
        $ids = [];

        foreach ($equipmentList as $eq) {
            $name = strtolower($eq['name']);
            $spanishName = self::EQUIPMENT_MAP[$name] ?? $this->capitalizeWords($eq['name']);

            if (isset($existing[$spanishName])) {
                $ids[$spanishName] = $existing[$spanishName];
                continue;
            }

            $created = Equipment::create([
                'title' => $spanishName,
                'status' => 'active',
            ]);

            $ids[$spanishName] = $created->id;
            $existing[$spanishName] = $created->id;
            $this->command->info("Created equipment: {$spanishName}");
        }

        return $ids;
    }

    private function getLevelIds(): array
    {
        return Level::pluck('id', 'title')->toArray();
    }

    private function parseInstructions(string $instructions): array
    {
        if (empty($instructions)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/Step:\d+/i', $instructions)),
            fn ($s) => ! empty($s)
        ));
    }

    private function capitalizeWords(string $str): string
    {
        return collect(explode(' ', strtolower($str)))
            ->map(fn ($word) => ucfirst($word))
            ->implode(' ');
    }
}
