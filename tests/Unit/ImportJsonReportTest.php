<?php

namespace Tests\Unit;

use App\Services\ProgramsImport\ImportJsonReport;
use PHPUnit\Framework\TestCase;

class ImportJsonReportTest extends TestCase
{
    public function test_review_required_flags_non_ab_levels_and_created(): void
    {
        $results = [$this->dryRunResult([
            $this->exercise('Press banca con mancuernas', 'A', 0.97, 'Press banca con mancuernas', 2),
            $this->exercise('Remo con mancuernas a una mano', 'B', 0.88, 'Remo con mancuernas a una mano', 9),
            $this->exercise('Curl concentrado raro', 'D', 0.61, 'Curl de bíceps con mancuernas', 899),
            $this->exercise('Ejercicio totalmente inventado', 'created', null, null, null),
        ])];

        $review = ImportJsonReport::buildReviewRequired($results);

        $this->assertCount(2, $review);
        $this->assertSame('Curl concentrado raro', $review[0]['source_exercise']);
        $this->assertSame('D', $review[0]['level']);
        $this->assertSame(0.61, $review[0]['confidence']);
        $this->assertSame('Ejercicio totalmente inventado', $review[1]['source_exercise']);
        $this->assertSame('created', $review[1]['level']);
        $this->assertNull($review[1]['matched_exercise_id']);
    }

    public function test_review_required_is_empty_when_all_matches_are_a_or_b(): void
    {
        $results = [$this->dryRunResult([
            $this->exercise('Sentadilla en copa con mancuernas', 'A', 1.0, 'Sentadilla en copa con mancuernas', 1462),
            $this->exercise('Prensa de piernas', 'B', 0.85, 'Prensa de piernas', 24),
        ])];

        $this->assertSame([], ImportJsonReport::buildReviewRequired($results));
    }

    public function test_review_required_ignores_non_dry_run_results(): void
    {
        $results = [[
            'status'              => 'created',
            'training_program_id' => 48,
            'title'               => 'Mesociclo 1 TONI Septiembre',
            'weeks'               => 3,
            'assignments'         => 12,
        ]];

        $this->assertSame([], ImportJsonReport::buildReviewRequired($results));
    }

    public function test_build_payload_shape(): void
    {
        $result = [
            'results' => [$this->dryRunResult([
                $this->exercise('Hack Squat', 'C', 0.74, 'Prensa de piernas', 24),
            ])],
            'stats' => ['programs_created' => 0],
        ];

        $payload = ImportJsonReport::buildPayload(
            'excel',
            'Mesociclo_1_TONI_Septiembre.xlsx',
            true,
            1,
            $result,
            [['source_exercise' => 'Hack Squat', 'exercise_id' => 24, 'resolved_title' => 'Prensa de piernas', 'candidates' => null, 'action' => 'crearía (dry-run)']],
            null,
        );

        $this->assertTrue($payload['ok']);
        $this->assertTrue($payload['dry_run']);
        $this->assertSame(1, $payload['programs_detected']);
        $this->assertCount(1, $payload['review_required']);
        $this->assertSame('C', $payload['review_required'][0]['level']);
        $this->assertCount(1, $payload['report']);
        $this->assertArrayHasKey('stats', $payload);
    }

    public function test_build_error_shape(): void
    {
        $this->assertSame(
            ['ok' => false, 'error' => 'Archivo no encontrado: x.xlsx'],
            ImportJsonReport::buildError('Archivo no encontrado: x.xlsx'),
        );
    }

    public function test_persist_report_csv_writes_expected_rows(): void
    {
        $path = sys_get_temp_dir() . '/import-json-report-test-' . uniqid() . '.csv';

        $written = ImportJsonReport::persistReportCsv(
            [['source_exercise' => 'Hack Squat', 'exercise_id' => 24, 'resolved_title' => 'Prensa de piernas', 'candidates' => null, 'action' => 'crearía (dry-run)']],
            'excel',
            $path,
        );

        $this->assertSame($path, $written);
        $this->assertFileExists($path);
        $rows = array_map('str_getcsv', file($path));
        $this->assertSame(['source_exercise', 'exercise_id', 'resolved_title', 'candidates', 'action'], $rows[0]);
        $this->assertSame(['Hack Squat', '24', 'Prensa de piernas', '', 'crearía (dry-run)'], $rows[1]);

        unlink($path);
    }

    public function test_persist_report_csv_returns_null_for_empty_report(): void
    {
        $this->assertNull(ImportJsonReport::persistReportCsv([], 'excel', sys_get_temp_dir() . '/should-not-be-created.csv'));
        $this->assertFileDoesNotExist(sys_get_temp_dir() . '/should-not-be-created.csv');
    }

    public function test_persist_report_csv_returns_null_when_parent_dir_missing(): void
    {
        // fopen() no crea directorios intermedios -- a diferencia de un
        // directorio sin permisos, esto falla igual aunque el proceso
        // corra como root (como en este entorno de pruebas).
        $result = ImportJsonReport::persistReportCsv(
            [['source_exercise' => 'X', 'exercise_id' => null, 'resolved_title' => null, 'candidates' => null, 'action' => 'sin-match']],
            'excel',
            '/import-json-report-test-nonexistent-dir-' . uniqid() . '/report.csv',
        );

        $this->assertNull($result);
    }

    private function exercise(string $source, string $level, ?float $confidence, ?string $match, ?int $matchId): array
    {
        return [
            'source'     => $source,
            'match'      => $match,
            'match_id'   => $matchId,
            'level'      => $level,
            'confidence' => $confidence,
            'prescribed' => ['series' => '3', 'reps' => '8-10'],
        ];
    }

    private function dryRunResult(array $exercises): array
    {
        return [
            'status'  => 'dry-run',
            'title'   => 'Mesociclo 1 TONI Septiembre',
            'preview' => [
                'title' => 'Mesociclo 1 TONI Septiembre',
                'weeks' => [[
                    'week_number' => 1,
                    'days' => [[
                        'day_of_week' => 1,
                        'title'       => 'Torso A',
                        'is_rest'     => false,
                        'exercises'   => $exercises,
                    ]],
                ]],
            ],
        ];
    }
}
