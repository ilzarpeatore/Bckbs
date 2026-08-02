<?php

namespace App\DataTables;

use App\Models\MeasurementUnit;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;

class MeasurementUnitDataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build DataTable class.
     */
    public function dataTable($query)
    {
        $auth_user = $this->auth_user;
        return datatables()
            ->eloquent($query)

            ->editColumn('status', function ($query) {
                $status = 'warning';
                switch ($query->status) {
                    case 'active':
                        $status = 'primary';
                        break;
                    case 'inactive':
                        $status = 'warning';
                        break;
                }
                return '<span class="text-capitalize badge bg-' . $status . '">' . $query->status . '</span>';
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function ($measurementunit)  use ($auth_user) {
                $id = $measurementunit->id;
                return view('measurement_unit.action', compact('measurementunit', 'id', 'auth_user'))->render();
            })
            ->addIndexColumn()
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];

                    $column_name = 'id';
                    $direction = 'desc';
                    if ($column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }

                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns(['action', 'status']);
    }

    /**
     * Get query source of dataTable.
     */
    public function query(MeasurementUnit $model)
    {
        return $model->newQuery();
    }

    /**
     * Get columns.
     */
    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false),

            ['data' => 'title', 'name' => 'title', 'title' => __('message.title')],
            ['data' => 'symbol', 'name' => 'symbol', 'title' => __('message.symbol')],
            ['data' => 'unit_type', 'name' => 'unit_type', 'title' => __('message.unit_type')],
            ['data' => 'status', 'name' => 'status', 'title' => __('message.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('message.created_at')],
            ['data' => 'updated_at', 'name' => 'updated_at', 'title' => __('message.updated_at')],

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->title(__('message.action'))
                ->width(60)
                ->addClass('text-center hide-search'),
        ];
    }
}
