<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Events\AfterSheet;

class UserReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $user_data;
    protected $request;
    protected $counter = 1;
    protected $columns;

    public function __construct($user_data, $request)
    {
        $this->user_data = $user_data;
        $this->request   = $request;
        $this->columns = $request->input('columns', [
            'no', 'id', 'name', 'phone_number', 'email', 'age', 'status', 'created_at'
        ]);
    }

    public function collection()
    {
        return $this->user_data;
    }

    public function map($user): array
    {
        $row = [];

        foreach ($this->columns as $col) {
            switch ($col) {
                case 'no':
                    $row[] = $this->counter++;
                    break;
                case 'id':
                    $row[] = $user->id ?? '-';
                    break;
                case 'name':
                    $row[] = ($user->display_name ?? '-');
                    break;
                case 'phone_number':
                    $row[] = $user->phone_number ?? '-';
                    break;
                case 'email':
                    $row[] = $user->email ?? '-';
                    break;
                case 'age':
                    $row[] = $user->userProfile?->age ?? '-';
                    break;
                case 'status':
                    $row[] = $user->status ?? '-';
                    break;
                case 'created_at':
                    $row[] = $user->created_at ? $user->created_at->format('F j, Y h:i A') : '-';
                    break;
            }
        }

        return $row;
    }


    public function headings($exportType = 'excel'): array
    {

        $labels = [];
        foreach ($this->columns as $col) {
            switch ($col) {
                case 'no': $labels[] = __('message.no'); break;
                case 'id': $labels[] = __('message.id'); break;
                case 'name': $labels[] = __('message.name'); break;
                case 'phone_number': $labels[] = __('message.phone_number'); break;
                case 'email': $labels[] = __('message.email'); break;
                case 'age': $labels[] = __('message.age'); break;
                case 'status': $labels[] = __('message.status'); break;
                case 'created_at': $labels[] = __('message.created_at'); break;
            }
        }

        return $labels;
    }

    public function styles(Worksheet $sheet)
    {
        $highestColumn = $sheet->getHighestColumn();

        // Style headings only (row 1)
        $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Auto-size all columns
                foreach (range('A', $event->sheet->getDelegate()->getHighestColumn()) as $col) {
                    $event->sheet->getDelegate()->getColumnDimension($col)->setAutoSize(true);
                }

                // Center align all cells
                $event->sheet->getDelegate()->getStyle('A:Z')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
