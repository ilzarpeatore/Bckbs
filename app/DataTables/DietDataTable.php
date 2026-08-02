<?php

namespace App\DataTables;

use App\Models\Diet;
use App\Models\CategoryDiet;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class DietDataTable extends DataTable
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
            
            ->editColumn('categorydiet.title', function($query) {
                return $query->categorydiet->title ?? '-';
            })

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
            ->editColumn('visibility', function($query) {
                $visibility = 'warning';
                switch ($query->visibility) {
                    case 'public':
                        $visibility = 'primary';
                        break;
                    case 'private':
                        $visibility = 'warning';
                        break;
                }
                return '<span class="text-capitalize badge bg-'.$visibility.'">'.$query->visibility.'</span>';
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function($diet){
                $id = $diet->id;
                return view('diet.action',compact('diet','id'))->render();
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
            ->rawColumns(['action','status','visibility']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Diet $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Diet $model)
    {
        $model = Diet::query()->with('categorydiet');
        return $this->applyScopes($model);
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
            ['data' => 'title', 'name' => 'title', 'title' => __('message.title')],
            ['data' => 'categorydiet.title', 'name' => 'categorydiet.title', 'title' => __('message.categorydiet'), 'orderable' => false],
            ['data' => 'calories', 'name' => 'calories', 'title' => __('message.calories')],
            ['data' => 'carbs', 'name' => 'carbs', 'title' => __('message.carbs')],
            ['data' => 'protein', 'name' => 'protein', 'title' => __('message.protein')],
            ['data' => 'fat', 'name' => 'fat', 'title' => __('message.fat')],
            ['data' => 'servings', 'name' => 'servings', 'title' => __('message.servings')],
            ['data' => 'total_time', 'name' => 'total_time', 'title' => __('message.total_time')],
            ['data' => 'is_featured', 'name' => 'is_featured', 'title' => __('message.featured')],
            ['data' => 'status', 'name' => 'status', 'title' => __('message.status')],
            ['data' => 'visibility', 'name' => 'visibility', 'title' => __('message.visibility')],
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
