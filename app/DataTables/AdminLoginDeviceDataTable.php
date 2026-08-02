<?php

namespace App\DataTables;

use App\Models\AdminLoginDevice;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;

class AdminLoginDeviceDataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build DataTable class.
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->editColumn('created_at', function ($row) {
                return dateAgoFormate($row->created_at, true);
            })
            ->editColumn('login_at', function ($row) {
                return dateAgoFormate($row->login_at, true);
            })
            ->editColumn('ip_address', function ($row) {
                $currentIp = request()->ip();
                $ip = is_array($row) ? ($row['ip_address'] ?? null) : $row->ip_address ?? null;

                if ($ip && $ip === $currentIp) {
                   return __('message.your_ip_address', ['ip' => e($ip)]);
                }
                return maskSensitiveInfo('ip_address', $ip);
            })
            ->editColumn('user_id', function($row) {
                return optional($row->user)->display_name ?? '-';
            })
        
            ->addColumn('city', function ($row) {
                return optional($row->latestLoginHistory)->city ?? '-';
            })
            ->addColumn('region', function ($row) {
                return optional($row->latestLoginHistory)->region ?? '-';
            })
            ->addColumn('country', function ($row) {
                return optional($row->latestLoginHistory)->country ?? '-';
            })
            ->addColumn('browser', function ($row) {
                return optional($row->latestLoginHistory)->browser ?? '-';
            })
            ->addColumn('action', function($row){
                $id = $row->id;
                $user_id = $row->user_id;
                return view('device.action', compact('id','user_id'))->render();
            })
            ->addIndexColumn()
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];

                    $column_name = 'created_at';
                    $direction = 'desc';
                    if( $column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }
    
                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns(['action', 'ip_address']);
    }

    /**
     * Get query source of dataTable.
     */
    public function query(AdminLoginDevice $model)
    {
        return $model->with(['user:id,display_name', 'latestLoginHistory'])->where('is_active', 1)->newQuery();
    }

    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false)
                ->width(60),
            ['data' => 'user_id', 'name' => 'user.display_name', 'title' => __('message.user')],
            ['data' => 'ip_address', 'name' => 'ip_address', 'title' => __('message.ip_address')],
            ['data' => 'city', 'name' => 'latestLoginHistory.city', 'title' => __('message.city'),'orderable' => false],
            ['data' => 'region', 'name' => 'latestLoginHistory.region', 'title' => __('message.region'),'orderable' => false],
            ['data' => 'country', 'name' => 'latestLoginHistory.country', 'title' => __('message.country'),'orderable' => false],
            ['data' => 'browser', 'name' => 'latestLoginHistory.browser', 'title' => __('message.browser'),'orderable' => false],
            ['data' => 'login_at', 'name' => 'login_at', 'title' => __('message.login_at')],
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->title(__('message.action'))
                ->width(60)
                ->addClass('text-center hide-search'),
        ];
    }
}
