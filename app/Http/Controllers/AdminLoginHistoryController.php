<?php

namespace App\Http\Controllers;

use App\DataTables\AdminLoginHistoryDataTable;
use App\Helpers\AuthHelper;

class AdminLoginHistoryController extends Controller
{
    public function index(AdminLoginHistoryDataTable $dataTable)
    {
        $auth_user = AuthHelper::authSession();
        if(!$auth_user->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $pageTitle = __('message.list_form_title',['form' => __('message.admin_login_history')] );
        $assets = ['datatable'];
       
        return $dataTable->render('global.datatable', compact('assets','pageTitle','auth_user'));
    }
}
