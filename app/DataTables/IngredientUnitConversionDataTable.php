<?php

namespace App\DataTables;

use App\Models\IngredientUnitConversion;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Traits\DataTableTrait;

class IngredientUnitConversionDataTable extends DataTable
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

            ->editColumn('ingredient_id', function ($query) {
                return optional($query->ingredient)->title;
            })
            ->editColumn('measurement_unit_id', function ($query) {
                return optional($query->measurementUnit)->title;
            })
            ->editColumn('gram_equivalent', function ($query) {
                return $query->gram_equivalent;
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function ($ingredientUnitConversion) use ($auth_user) {
                $id = $ingredientUnitConversion->id;
                return view('ingredient_unit_conversion.action', compact('ingredientUnitConversion', 'id', 'auth_user'))->render();
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
            ->rawColumns(['action']);
    }

    /**
     * Get query source of dataTable.
     */
    public function query(IngredientUnitConversion $model)
    {
        return $model->newQuery()->with(['ingredient', 'measurementUnit']);
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
            ['data' => 'ingredient_id', 'name' => 'ingredient.title', 'title' => __('message.ingredient')],
            ['data' => 'measurement_unit_id', 'name' => 'measurementUnit.title', 'title' => __('message.measurement_unit')],
            ['data' => 'gram_equivalent', 'name' => 'gram_equivalent', 'title' => __('message.gram_equivalent')],
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
