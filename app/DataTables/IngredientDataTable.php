<?php

namespace App\DataTables;

use App\Models\Ingredient;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class IngredientDataTable extends DataTable
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
         $auth_user = $this->auth_user;
        return datatables()->eloquent($query)
            ->editColumn('calories', function ($ingredient) {
                return number_format($ingredient->calories, 2);
            })
            ->editColumn('protein', function ($ingredient) {
                return number_format($ingredient->protein, 2);
            })
            ->editColumn('fat', function ($ingredient) {
                return number_format($ingredient->fat, 2);
            })
            ->editColumn('carbs', function ($ingredient) {
                return number_format($ingredient->carbs, 2);
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
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function($ingredient) use($auth_user){
                $id = $ingredient->id;
                return view('ingredient.action',compact('id','auth_user'))->render();
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
            ->rawColumns(['action','status']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Ingredient $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Ingredient $model)
    {
        $model = Ingredient::query();
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
            ['data' => 'calories', 'name' => 'calories_per_gram', 'title' => __('message.calories_per_gram')],
            ['data' => 'protein', 'name' => 'protein_per_gram', 'title' => __('message.protein_per_gram')],
            ['data' => 'fat', 'name' => 'fat_per_gram', 'title' => __('message.fat_per_gram')],
            ['data' => 'carbs', 'name' => 'carbs_per_gram', 'title' => __('message.carbs_per_gram')],
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
