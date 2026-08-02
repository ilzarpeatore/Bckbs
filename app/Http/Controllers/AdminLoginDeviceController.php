<?php

namespace App\Http\Controllers;

use App\DataTables\AdminLoginDeviceDataTable;
use App\DataTables\AdminLoginHistoryDataTable;
use App\Helpers\AuthHelper;
use App\Models\AdminLoginDevice;
use Illuminate\Support\Facades\Auth;


class AdminLoginDeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AdminLoginDeviceDataTable $dataTable)
    {
        $auth_user = AuthHelper::authSession();

        if(!$auth_user->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $pageTitle = __('message.list_form_title',['form' => __('message.admin_device_login')] );
       
        $assets = ['datatable'];
        return $dataTable->render('global.datatable', compact('assets','pageTitle','auth_user',));
    }

    public function logoutDevice($id)
    {
        if(!auth()->user()->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $device = AdminLoginDevice::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$device) {
            return redirect()->back()->withErrors( __('message.device_authrized'));
        }
        $device->update([
            'is_active' => false,
            'logout_at' => now(),
        ]);
        if ($device->session_id === session()->getId()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        return redirect()->back()->with('success', __('message.device_logged_out'));
    }
    public function show(AdminLoginHistoryDataTable $dataTable, $user_id)
    {
        $auth_user = AuthHelper::authSession();
        if(!$auth_user->hasRole('admin')) {
            abort(403, __('message.action_is_unauthorized'));
        }
        $pageTitle = __('message.list_form_title', ['form' => __('message.you_logged')]);
        $assets = ['datatable'];
        return $dataTable->with('user_id', $user_id)->render('global.datatable', compact('assets', 'pageTitle', 'auth_user',));
    }

}
