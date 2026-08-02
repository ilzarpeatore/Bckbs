<?php

namespace App\DataTables;

use App\Models\BannerSlider;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;

class BannerSliderDataTable extends DataTable
{
    use DataTableTrait;

    public function dataTable($query)
    {   
        $auth_user = $this->auth_user;
        return datatables()
            ->eloquent($query)

            ->editColumn('status', function($query) {
                $status = 'warning';
                switch ($query->status) {
                    case 'active':
                        $status = 'primary';
                        break;
                    case 'inactive':
                        $status = 'warning';
                        break;
                }
                return '<span class="text-capitalize badge bg-'.$status.'">'.$query->status.'</span>';
            })

            ->editColumn('type', function ($row) {
                return ucfirst($row->type);
            })

            ->addColumn('value', function ($row) {
                if ($row->type === 'workout') {
                    return $row->workout->title ?? '-';
                }
                return $row->url ?? '-';
            })

            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function ($bannerslider) use($auth_user) {
                $id = $bannerslider->id;
                $action_type = 'action';
                return view('bannerslider.action', compact('bannerslider', 'id','action_type', 'auth_user'))->render();
            })
            ->addIndexColumn()
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];
                    $column_name = 'id';
                    $direction = 'desc';
                    if( $column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }
                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns(['status', 'action']);
    }

    public function query(BannerSlider $model)
    {
        return BannerSlider::query()->with('workout');
    }

    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false),

            ['data' => 'title', 'name' => 'title', 'title' => __('message.title')],
            ['data' => 'type', 'name' => 'type', 'title' => __('message.type')],
            ['data' => 'value', 'name' => 'value', 'title' => __('message.workout_url')],
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
