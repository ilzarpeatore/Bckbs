<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SubAdminController extends BaseController
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }

    public function index(Request $request)
    {
        $query = User::where('user_type', 'sub_admin');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = UserResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:6',
        ]);

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);
        $data['user_type'] = 'sub_admin';
        $data['status'] = 'active';
        $data['display_name'] = $data['first_name'] . ' ' . $data['last_name'];

        $user = User::create($data);
        $user->assignRole('admin');

        $response = [
            'message' => 'Sub-admin created successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response, 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('id', $id)->where('user_type', 'sub_admin')->first();

        if (!$user) {
            return json_message_response('Sub-admin not found.', 404);
        }

        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name'  => 'sometimes|required|string|max:255',
            'email'      => 'sometimes|required|email|unique:users,email,' . $id,
            'password'   => 'nullable|string|min:6',
            'status'     => 'sometimes|in:active,banned',
        ]);

        $data = $request->only(['first_name', 'last_name', 'email', 'status']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $response = [
            'message' => 'Sub-admin updated successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $user = User::where('id', $id)->where('user_type', 'sub_admin')->first();

        if (!$user) {
            return json_message_response('Sub-admin not found.', 404);
        }

        $user->delete();

        return json_message_response('Sub-admin deleted successfully.');
    }
}
