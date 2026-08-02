<?php

namespace App\Traits;

use Yajra\DataTables\Services\DataTable;

trait DataTableTrait {

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\Datatables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->ajax('')
            ->parameters($this->getBuilderParameters());
    }


    public function getBuilderParameters(): array
    {
        return [
            'lengthMenu'    => [[10, 50, 100, 500, -1], [10, 50, 100, 500, __('pagination.all')]],
            'sDom'          => '<"row align-items-center"<"col-md-2" l><"col-md-6" B><"col-md-4"f>><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" i><"col-md-6" p>><"clear">',
            'drawCallback'  => "function () {
                $('.dataTables_paginate > .pagination').addClass('justify-content-end mb-0');
            }",
            'language' => [
                'search' => '',
                'searchPlaceholder' => __('pagination.search'),
                'lengthMenu' =>  __('pagination.show'). ' _MENU_ ' .__('pagination.entries'),
                'zeroRecords' => __('pagination.no_records_found'),
                'info' => __('pagination.showing') .' _START_ '.__('pagination.to') .' _END_ ' . __('pagination.of').' _TOTAL_ ' . __('pagination.entries'), 
                'infoFiltered' => __('pagination.filtered_from_total') . ' _MAX_ ' . __('pagination.entries'),
                'infoEmpty' => __('pagination.showing_entries'),
                'paginate' => [
                    'previous' => __('pagination.previous'),
                    'next' => __('pagination.next'),
                ],
            ],
            'initComplete' => "function () {
                $('#dataTableBuilder_wrapper .dt-buttons button').removeClass('btn-secondary');
                this.api().columns().every(function () {

                });
            },
            createdRow: (row, data, dataIndex, cells) => {

            }"
            
        ];
    }
}
