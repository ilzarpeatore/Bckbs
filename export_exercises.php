<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BodyPart;
use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\Level;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$bpById = BodyPart::pluck('title', 'id')->toArray();
$eqById = Equipment::pluck('title', 'id')->toArray();
$levelById = Level::pluck('title', 'id')->toArray();

$exercises = Exercise::withTrashed()->with(['equipment', 'level'])->orderBy('id')->get();
$deletedCount = Exercise::onlyTrashed()->count();

$headers = [
    'ID', 'Título', 'Slug', 'Descripción / Instrucciones', 'Tips', 'Músculos (cuerpo)',
    'Equipamiento', 'Nivel', 'Tipo de ejercicio', 'Basado en', 'Duración', 'Series',
    'Segundos por rep', 'Tipo de video', 'URL del video', 'Premium', 'Estado', 'Imagen',
    'Creado', 'Eliminado',
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ejercicios');

foreach ($headers as $i => $h) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $h);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getStyle($col . '1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('1F4E78');
    $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
}
$sheet->getStyle('A1:T1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:T1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$row = 2;
foreach ($exercises as $ex) {
    $bpNames = array_map(
        fn ($id) => $bpById[$id] ?? "(id $id)",
        is_array($ex->bodypart_ids) ? $ex->bodypart_ids : []
    );

    $sets = '';
    if (is_array($ex->sets) && count($ex->sets)) {
        $parts = [];
        foreach ($ex->sets as $s) {
            if (is_array($s)) {
                $parts[] = implode(' ', array_filter([$s['sets'] ?? null, $s['reps'] ?? null], fn ($v) => $v !== null));
            }
        }
        $sets = implode(', ', array_filter($parts));
    }

    $imageUrl = $ex->getFirstMediaUrl('exercise_image') ?: '';

    $values = [
        $ex->id,
        $ex->title,
        $ex->slug,
        $ex->instruction,
        $ex->tips,
        implode(', ', $bpNames),
        $ex->equipment ? $ex->equipment->title : '',
        $ex->level ? $ex->level->title : '',
        $ex->type,
        $ex->based,
        $ex->duration,
        $sets,
        $ex->seconds_per_rep,
        $ex->video_type,
        $ex->video_url,
        $ex->is_premium ? 'Sí' : 'No',
        $ex->status,
        $imageUrl,
        $ex->created_at ? $ex->created_at->format('Y-m-d H:i') : '',
        $ex->deleted_at ? 'Sí' : 'No',
    ];

    foreach ($values as $i => $v) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        if ($col === 'A' || $col === 'Q' || $col === 'S' || $col === 'T') {
            $sheet->setCellValueExplicit($col . $row, (string) ($v ?? ''), DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($col . $row, $v ?? '');
        }
    }
    $row++;
}

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(38);
$sheet->getColumnDimension('C')->setWidth(38);
$sheet->getColumnDimension('D')->setWidth(80);
$sheet->getColumnDimension('E')->setWidth(50);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(14);
$sheet->getColumnDimension('I')->setWidth(14);
$sheet->getColumnDimension('J')->setWidth(10);
$sheet->getColumnDimension('K')->setWidth(10);
$sheet->getColumnDimension('L')->setWidth(18);
$sheet->getColumnDimension('M')->setWidth(10);
$sheet->getColumnDimension('N')->setWidth(12);
$sheet->getColumnDimension('O')->setWidth(34);
$sheet->getColumnDimension('P')->setWidth(9);
$sheet->getColumnDimension('Q')->setWidth(9);
$sheet->getColumnDimension('R')->setWidth(44);
$sheet->getColumnDimension('S')->setWidth(18);
$sheet->getColumnDimension('T')->setWidth(9);

$sheet->setAutoFilter($sheet->calculateWorksheetDimension());
$sheet->getStyle('A1:T1')->getAlignment()->setWrapText(true);
$sheet->getStyle('A1:T1')->getFont()->setSize(10);
$sheet->freezePane('A2');

$outDir = __DIR__ . '/exports';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$out = $outDir . '/ejercicios.xlsx';

$writer = new Xlsx($spreadsheet);
$writer->save($out);

echo "OK\n";
echo 'Filas: ' . ($exercises->count()) . ' (incluye ' . $deletedCount . " eliminados)\n";
echo "Archivo: $out\n";
