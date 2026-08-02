<?php

namespace App\DataTables;

use App\Helpers\DateTimeHelper;
use App\Models\ClassSchedule;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;
use Carbon\Carbon;

class ClassScheduleDataTable extends DataTable
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

            ->editColumn('start_date', function ($query) {
                return DateTimeHelper::date($query->start_date, 'F j, Y');
            })
            ->editColumn('end_date', function ($query) {
                return DateTimeHelper::date($query->end_date, 'F j, Y');
            })
            ->editColumn('start_time', function ($query) {
                return DateTimeHelper::date($query->start_time,'g:i A');
            })
            ->editColumn('end_time', function ($query) {
                return DateTimeHelper::date($query->end_time,'g:i A');
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->editColumn('workout_id', function ($query) {
                $workouts = '-';
                if ( $query->workout_type == 'other' ) {
                    $workouts = $query->workout_title;
                }

                if ( $query->workout_type == 'workout' ) {
                    $workouts = optional($query->workout)->title;
                }

                return $workouts;
            })
            ->addColumn('action', function($class_schedule){
                $id = $class_schedule->id;
                $is_future_date = Carbon::parse($class_schedule->end_date)->isFuture();
                return view('class_schedule.action',compact('class_schedule','id','is_future_date'))->render();
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
            ->rawColumns(['action']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\ClassSchedule $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(ClassSchedule $model)
    {
        return $model->newQuery();
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
                ->orderable(false),
            ['data' => 'class_name', 'name' => 'class_name', 'title' => __('message.class_name')],
            ['data' => 'workout_id', 'name' => 'workout_id', 'title' => __('message.workout')],
            ['data' => 'start_date', 'name' => 'start_date', 'title' => __('message.start_date')],
            ['data' => 'end_date', 'name' => 'end_date', 'title' => __('message.end_date')],
            ['data' => 'start_time', 'name' => 'start_time', 'title' => __('message.start_time')],
            ['data' => 'end_time', 'name' => 'end_time', 'title' => __('message.end_time')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('message.created_at')],
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->title(__('message.action'))
                  ->width(60)
                  ->addClass('text-center hide-search'),
        ];
    }
}