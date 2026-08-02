<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\DataTables\RoleDataTable;
use App\Helpers\AuthHelper;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(RoleDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.role')] );
        $auth_user = AuthHelper::authSession();
        $assets = ['data-table'];
        $headerAction  = '';
        if($auth_user->can('role-add')){
            $headerAction ='<a href="#" class="float-end btn btn-sm btn-primary" data-modal-form="form" data-size="small" data--href="'.route('permission.add',['type'=>'role']).'" data-app-title="'.__('message.add_form_title',['form' => __('message.role')]).'" data-placement="top">'.__('message.add_form_title',['form' => __('message.role')]).'</a>';
        }
        return $dataTable->render('role.index', compact('assets', 'pageTitle', 'headerAction', 'auth_user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $data = $request->all();
        $view = view('role-permission.form-role')->render();
        return response()->json(['data' =>  $view, 'status'=> true]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
       //code here
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
       //code here
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //code here
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //code here
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
       //code here
    }
}
