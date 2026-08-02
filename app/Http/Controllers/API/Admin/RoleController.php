<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleController extends BaseController
{
    protected function getModelClass(): string
    {
        return Role::class;
    }

    protected function getResourceClass(): string
    {
        return \App\Http\Resources\UserResource::class;
    }

    public function index(Request $request)
    {
        $roles = Role::with('permissions')->get();

        return json_custom_response(['data' => $roles]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->name]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return $role;
        });

        $response = [
            'message' => 'Role created successfully.',
            'data'    => $role->load('permissions'),
        ];

        return json_custom_response($response, 201);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return json_message_response('Role not found.', 404);
        }

        return json_custom_response(['data' => $role]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return json_message_response('Role not found.', 404);
        }

        $request->validate([
            'name'          => 'sometimes|required|string|max:255|unique:roles,name,' . $id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::transaction(function () use ($role, $request) {
            $role->update($request->only(['name']));

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }
        });

        $response = [
            'message' => 'Role updated successfully.',
            'data'    => $role->load('permissions'),
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return json_message_response('Role not found.', 404);
        }

        $role->delete();

        return json_message_response('Role deleted successfully.');
    }
}
