<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportExerciseImages extends Command
{
    protected $signature = 'exercise:import-images {--limit=0 : Maximum number of images to download (0 = all)} {--retry : Retry only previously failed downloads}';
    protected $description = 'Download ExerciseDB GIFs for imported exercises';

    private const TEMP_DIR = 'exercise-import-images';

    public function handle(): int
    {
        $dataPath = database_path('data/exercises.json');

        if (! file_exists($dataPath)) {
            $this->error("Exercise data file not found: {$dataPath}");
            return self::FAILURE;
        }

        $exercises = json_decode(file_get_contents($dataPath), true);
        $limit = (int) $this->option('limit');
        $downloaded = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;
        $retried = 0;

        $tempDisk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/' . self::TEMP_DIR),
        ]);
        $tempDisk->makeDirectory('/');

        $isRetry = $this->option('retry');

        $this->info('Starting image download...');
        $this->info('Total exercises in JSON: ' . count($exercises));

        foreach ($exercises as $exercise) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $processed++;
            $title = $this->capitalizeWords($exercise['name']);
            $slug = Str::slug($title);
            $gifUrl = $exercise['gifUrl'] ?? null;

            if (empty($gifUrl)) {
                $skipped++;
                continue;
            }

            $model = Exercise::withTrashed()->where('slug', $slug)->first();

            if (! $model) {
                $this->warn("Exercise not found: {$title} ({$slug})");
                $skipped++;
                continue;
            }

            $hasMedia = $model->getMedia('image')->isNotEmpty();

            if ($hasMedia) {
                if ($isRetry) {
                    $skipped++;
                    continue;
                }
                // In normal mode, skip already processed images
                $skipped++;
                continue;
            }

            if ($isRetry && ! $hasMedia) {
                $retried++;
            }

            $tempPath = self::TEMP_DIR . '/' . $slug . '.gif';
            $localPath = storage_path('app/' . $tempPath);

            $success = false;
            $lastError = null;

            // Retry up to 3 times with a small delay
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $this->downloadFile($gifUrl, $localPath);

                    if (! file_exists($localPath) || filesize($localPath) === 0) {
                        throw new \RuntimeException('Downloaded file is empty');
                    }

                    $model
                        ->addMedia($localPath)
                        ->toMediaCollection('exercise_image');

                    $success = true;
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    if ($attempt < 3) {
                        usleep(500000); // 500ms between retries
                    }
                }
            }

            if ($success) {
                $downloaded++;
                $this->info("[{$processed}] Downloaded image for: {$title}");
            } else {
                $errors++;
                $this->warn("[{$processed}] Failed to download image for {$title}: {$lastError?->getMessage()}");
            }

            if (file_exists($localPath)) {
                @unlink($localPath);
            }

            // Be gentle with the server to avoid rate limiting
            usleep(150000); // 150ms between requests

            if ($processed % 50 === 0) {
                $this->info("Progress: {$processed} processed, {$downloaded} downloaded, {$skipped} skipped, {$errors} errors");
            }
        }

        $this->info("Done. Processed: {$processed}, Downloaded: {$downloaded}, Skipped: {$skipped}, Errors: {$errors}");

        return self::SUCCESS;
    }

    private function downloadFile(string $url, string $destination): void
    {
        // Try cURL first (works on most systems)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $data = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($data === false || $httpCode >= 400) {
                throw new \RuntimeException($error ?: "HTTP {$httpCode}");
            }

            file_put_contents($destination, $data);
            return;
        }

        // Fallback to file_get_contents with disabled SSL verification
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        $data = file_get_contents($url, false, $context);
        if ($data === false) {
            throw new \RuntimeException('file_get_contents failed');
        }

        file_put_contents($destination, $data);
    }

    private function capitalizeWords(string $str): string
    {
        return collect(explode(' ', strtolower($str)))
            ->map(fn ($word) => ucfirst($word))
            ->implode(' ');
    }
}
