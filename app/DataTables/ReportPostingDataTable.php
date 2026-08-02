<?php

namespace App\DataTables;

use App\Models\ReportPosting;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class ReportPostingDataTable extends DataTable
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

            ->editColumn('reason', function($row) {
                return isset($row->reason) ? stringLong($row->reason, 'desc') : null;
            })

            ->editColumn('user_id', function($row) {
                return optional($row->user)->display_name ?? '-';
            })

            ->editColumn('posting_id', function($row) {
                return isset($row->posting) ? stringLong( optional($row->posting)->description, 'desc' ) : null;
            })
            
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->editColumn('updated_at', function ($query) {
                return dateAgoFormate($query->updated_at, true);
            })
            ->addColumn('action', function($report_posting){
                $id = $report_posting->id;
                return view('posting.reported_post',compact('report_posting','id'))->render();
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
     * @param \App\Models\ReportPosting $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(ReportPosting $model)
    {
        $model = ReportPosting::query()->with('user:id,display_name','posting:id,description');

        if( $this->posting_id != null ) {
            $model = $model->where('posting_id', $this->posting_id);
        }

        return $this->applyScopes($model);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {

        $columns = [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false),
        ];

        // Insert posting_id right after user_id, but only if not filtering by a specific posting
        if ($this->posting_id === null) {
            $columns[] = Column::computed('posting_id')
                ->name('posting.description')
                ->title(__('message.posting'));
        }

        // Continue with the rest
        $columns = array_merge($columns, [
            ['data' => 'reason', 'name' => 'reason', 'title' => __('message.reason')],
            ['data' => 'user_id', 'name' => 'user.display_name', 'title' => __('message.reported_by')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('message.created_at')],
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->title(__('message.action'))
                ->width(60)
                ->addClass('text-center hide-search'),
        ]);

        return $columns;
    }
}
