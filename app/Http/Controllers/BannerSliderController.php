<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\BannerSliderDataTable;
use App\Helpers\AuthHelper;
use App\Models\BannerSlider;
use App\Http\Requests\BannerSliderRequest;

class BannerSliderController extends Controller
{
    public function index(BannerSliderDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.bannerslider')] );
        $auth_user = AuthHelper::authSession();

        if (!$auth_user->can('bannerslider-list')) {
            return back()->withErrors(__('message.permission_denied_for_account'));
        }

        $assets = ['data-table'];

        $headerAction = $auth_user->can('bannerslider-add')
            ? '<a href="' . route('bannerslider.create') . '" class="btn btn-sm btn-primary">'
                . __('message.add_form_title', ['form' => __('message.bannerslider')]) . '</a>'
            : '';

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    public function create()
    {
        if (!auth()->user()->can('bannerslider-add')) {
            return back()->withErrors(__('message.permission_denied_for_account'));
        }

        $pageTitle = __('message.add_form_title', ['form' => __('message.bannerslider')]);
        return view('bannerslider.form', compact('pageTitle'));
    }

    public function store(BannerSliderRequest $request)
    {
        if (!auth()->user()->can('bannerslider-add')) {
            return back()->withErrors(__('message.permission_denied_for_account'));
        }

        $banner = BannerSlider::create([
            'title'      => $request->title,
            'type'       => $request->type,
            'workout_id' => $request->type === 'workout' ? $request->workout_id : null,
            'url'        => $request->type === 'url' ? $request->url : null,
            'status'     => $request->status,
        ]);

        // Save image
        if ($request->hasFile('bannerslider_image')) {
            storeMediaFile($banner, $request->bannerslider_image, 'bannerslider_image');
        }

        return redirect()
            ->route('bannerslider.index')
            ->withSuccess(__('message.save_form', ['form' => __('message.bannerslider')]));
    }

    public function edit($id)
    {
        if (!auth()->user()->can('bannerslider-edit')) {
            return back()->withErrors(__('message.permission_denied_for_account'));
        }

        $data = BannerSlider::findOrFail($id);
        $pageTitle = __('message.update_form_title', ['form' => __('message.bannerslider')]);

        return view('bannerslider.form', compact('data','id','pageTitle'));
    }

    public function update(BannerSliderRequest $request, $id)
    {
        if (!auth()->user()->can('bannerslider-edit')) {
            return back()->withErrors(__('message.permission_denied_for_account'));
        }

        $banner = BannerSlider::findOrFail($id);

        $banner->update([
            'title'      => $request->title,
            'type'       => $request->type,
            'workout_id' => $request->type === 'workout' ? $request->workout_id : null,
            'url'        => $request->type === 'url' ? $request->url : null,
            'status'     => $request->status,
        ]);

        // Update image
        if ($request->hasFile('bannerslider_image')) {
            $banner->clearMediaCollection('bannerslider_image');
            $banner->addMediaFromRequest('bannerslider_image')->toMediaCollection('bannerslider_image');
        }

        return redirect()
            ->route('bannerslider.index')
            ->withSuccess(__('message.update_form', ['form' => __('message.bannerslider')]));
    }

    public function destroy($id)
    {
        if (!auth()->user()->can('bannerslider-delete')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $banner = BannerSlider::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.bannerslider')]);

        if ($banner != '') {
            $banner->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.bannerslider')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with($status, $message);
    }

}
