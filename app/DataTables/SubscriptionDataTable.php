<?php

namespace App\DataTables;

use App\Models\Subscription;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class SubscriptionDataTable extends DataTable
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
            ->addColumn('total_amount', function($total_amount){             
                $amount = getPriceFormat($total_amount->total_amount);
                return $amount;
            })
            ->addColumn('action', function($subscription){
                $user_id = $subscription->user_id;
                return view('subscription.action',compact('user_id'))->render();
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
     * @param \App\Models\Subscription $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Subscription $model)
    {
        $model = Subscription::query()->with('user','package');

        if( $this->user_id != null ) {
            $model = $model->where('user_id', $this->user_id);
        }
        
        $model->when(request()->filled('user_id'), function ($q) {
            $q->whereIn('user_id', request('user_id'));

        });

        $model->when(request()->filled('status'), function ($q) {
            $status = request('status');
            if ($status === 'expire_soon') {
                $q->where('status', 'active')->orderBy('subscription_end_date', 'asc');
            } else {
                $q->where('status', $status);
            }
        });

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
            ['data' => 'user.display_name', 'name' => 'user.display_name', 'title' => __('message.user'), 'orderable' => false],
            ['data' => 'package.name', 'name' => 'package.name', 'title' => __('message.package'), 'orderable' => false],
            ['data' => 'total_amount', 'name' => 'total_amount', 'title' => __('message.total_amount')],
            ['data' => 'payment_type', 'name' => 'payment_type', 'title' => __('message.payment_type')],
            ['data' => 'subscription_start_date', 'name' => 'subscription_start_date', 'title' => __('message.subscription_start_date')],
            ['data' => 'subscription_end_date', 'name' => 'subscription_end_date', 'title' => __('message.subscription_end_date')],
            ['data' => 'status', 'name' => 'status', 'title' => __('message.status')],
        ];
        $actionColumn = Column::computed('action')
            ->exportable(false)
            ->printable(false)
            ->title(__('message.action'))
            ->width(60)
            ->addClass('text-center hide-search');
    
        if ($this->user_id != null) {
            return $columns;
        }
        $columns[] = $actionColumn;
        return $columns;
    }
}