<?php

namespace App\DataTables;

use App\Models\Recipe;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class RecipeDataTable extends DataTable
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
            ->editColumn('category', function ($row) {
                return $row->categories->pluck('title')->implode(', ');
            })
            ->editColumn('tag', function ($row) {
                return $row->tags->pluck('title')->implode(', ');
            })
            ->editColumn('calories', function ($row) {
                return number_format((float) $row->calories, 2);
            })
            ->editColumn('protein', function ($row) {
                return number_format((float) $row->protein, 2);
            })
            ->editColumn('fats', function ($row) {
                return number_format((float) $row->fats, 2);
            })
            ->editColumn('carbs', function ($row) {
                return number_format((float) $row->carbs, 2);
            })          
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function($recipe) use($auth_user){
                $id = $recipe->id;
                return view('recipe.action',compact('recipe','id', 'auth_user'))->render();
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
   
    public function query(Recipe $model)
    {
        $query = $model->newQuery()->with('categories', 'tags');

        if (request()->filled('category_id')) {
            $categoryId = request('category_id');
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('recipe_categories.id', $categoryId);
            });
        }

        if (request()->filled('tag_id')) {
            $tagId = request('tag_id');
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('recipe_tags.id', $tagId);
            });
        }

        if (request()->filled('ingredient')) {
            $ingredient = request('ingredient');
            $query->whereHas('recipeIngredients.ingredient', function ($q) use ($ingredient) {
                $q->where('ingredients.title', 'LIKE', "%{$ingredient}%");
            });
        }

        return $query;
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
            ['data' => 'category', 'name' => 'categories.title', 'title' => __('message.category')],
            ['data' => 'tag', 'name' => 'tags.title', 'title' => __('message.tag')],
            ['data' => 'type', 'name' => 'type', 'title' => __('message.type')],
            ['data' => 'meal_type', 'name' => 'meal_type', 'title' => __('message.meal_type')],
            ['data' => 'calories', 'name' => 'calories', 'title' => __('message.calories')],
            ['data' => 'protein', 'name' => 'protein', 'title' => __('message.protein')],
            ['data' => 'fats', 'name' => 'fats', 'title' => __('message.fat')],
            ['data' => 'carbs', 'name' => 'carbs', 'title' => __('message.carbs')],
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