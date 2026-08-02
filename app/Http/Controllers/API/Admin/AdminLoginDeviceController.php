<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AdminLoginDevice;

class AdminLoginDeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminLoginDevice::with(['user']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $device = AdminLoginDevice::find($id);

        if (!$device) {
            return json_message_response('Device not found.', 404);
        }

        $device->delete();

        return json_message_response('Device removed successfully.');
    }
}
