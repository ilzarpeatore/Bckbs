<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\SubscriptionDataTable;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Package;
use App\Helpers\AuthHelper;
use App\Traits\SubscriptionTrait;
use App\Http\Requests\SubscriptionRequest;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use SubscriptionTrait;
    public function index(SubscriptionDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.subscription')] );
        $auth_user = AuthHelper::authSession();
        if( !$auth_user->can('subscription-list') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];
        $users = User::where('user_type', 'user')->pluck('display_name', 'id');
        
        $user_id = request('user_id', []);

        $filter = [
            'status'    => request('status'),
            'user_id'   => $user_id,
        ];
        
        $selected_users = User::whereIn('id', (array)$user_id)->pluck('display_name', 'id');
        $filter['selected_users'] = $selected_users;

        $headerAction = $auth_user->can('subscription-add') ? '<a href="#" class="float-end btn btn-sm btn-primary" data-modal-form="form" data-size="small" data--href="'.route('subscription.create').'" data-app-title="'.__('message.add_button_form',['form' => '']).'" data-placement="top">'.__('message.add_button_form',['form' => '']).'</a>' : '';
        
        $include_filter_file = view('subscription.filter', compact('filter', 'users'))->render();
        return $dataTable->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction', 'include_filter_file'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if( !auth()->user()->can('subscription-add') ) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.add_form_title',[ 'form' => __('message.subscription')]);
        
        $users = User::where('status','active')->where('user_type','user')->where('is_subscribe',0)->pluck('display_name','id');
      
        $view = view('subscription.form', compact('pageTitle','users'))->render();
        return response()->json([ 'data' => $view, 'status' => true ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SubscriptionRequest $request)
    {
        if( !auth()->user()->can('subscription-add') ) {
            $message = __('message.permission_denied_for_account');
            return response()->json(['status' => false, 'event' =>'validation', 'message' => $message ]);
        }

        $data = $request->all();
        $user_id = request('user_id');
        foreach ( $user_id as $value ) {
            $user = User::where('id', $value)->first();
            $package_data = Package::where('id',request('package_id'))->first();
            
            $active_plan_left_days = 0;
            $data['user_id'] = $value;
            $data['status'] = config('constant.SUBSCRIPTION_STATUS.PENDING');
            $data['payment_status'] = 'paid';
            $data['subscription_start_date'] = date('Y-m-d H:i:s');
            $data['total_amount'] = $package_data->price;
            $data['transaction_detail'] = [
                'added_by' => auth()->id(),
                'name' => auth()->user()->display_name,
            ];
            $data['subscription_end_date'] = $this->get_plan_expiration_date( $data['subscription_start_date'], $package_data->duration_unit, $active_plan_left_days, $package_data->duration );
            $data['package_data'] = $package_data ?? null;
    
            $subscription = Subscription::create($data);

            if( $subscription->payment_status == 'paid' ) {
                $subscription->status = config('constant.SUBSCRIPTION_STATUS.ACTIVE');
                $subscription->payment_type = 'by cash';
                $subscription->save();
                $user->update([ 'is_subscribe' => 1 ]);
            }
        }
        $message = __('message.save_form', ['form' => __('message.subscription')]);
        return response()->json(['status' => true, 'event' =>'submited', 'message' => $message ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Subscription::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
       
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SubscriptionRequest $request, $id)
    {
        

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }
}
