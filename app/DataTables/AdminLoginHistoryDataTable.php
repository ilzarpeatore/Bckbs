<?php

namespace App\DataTables;

use App\Models\AdminLoginHistory;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;


class AdminLoginHistoryDataTable extends DataTable
{
    use DataTableTrait;
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)

            ->editColumn('created_at', function ($row) {
                return dateAgoFormate($row->created_at, true);
            })
            ->editColumn('type', function ($row) {
                return str_replace('_', ' ', ucfirst($row->type));
            })

            ->addIndexColumn()

            ->rawColumns([]);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\AdminLoginHistory $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(AdminLoginHistory $model)
    {
        $model = $model->newQuery();
        if ( $this->user_id ) {
            $model->where('user_id', $this->user_id);
        }

        return $model->orderBy('created_at', 'desc');
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false)
                ->width(60),
            ['data' => 'ip_address', 'name' => 'ip_address', 'title' => __('message.ip_address')],
            ['data' => 'city', 'name' => 'city', 'title' => __('message.city')],
            ['data' => 'region', 'name' => 'region', 'title' => __('message.region')],
            ['data' => 'country', 'name' => 'country', 'title' => __('message.country')],
            ['data' => 'postal_code', 'name' => 'postal_code', 'title' => __('message.postal_code')],
            ['data' => 'browser', 'name' => 'browser', 'title' => __('message.browser')],
            ['data' => 'browser_version', 'name' => 'browser_version', 'title' => __('message.browser_version')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('message.created_at')],
        ];
    }
}
