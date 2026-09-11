<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = Permission::orderBy('name')->get();

        return json_custom_response(['data' => $permissions]);
    }

    /**
     * Crear un permiso nuevo (item 3, auditoria de migracion 2026-09-11) --
     * mismo comportamiento que PermissionController::savePermission (Blade,
     * caso type=permission): se asigna automaticamente al rol admin, igual
     * que el Blade.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:191|unique:permissions,name',
        ]);

        $permission = Permission::create([
            'name'      => $request->name,
            'title'     => ucwords(str_replace('-', ' ', $request->name)),
            'guard_name' => 'web',
            'parent_id' => $request->parent_id,
        ]);

        // Los roles Spatie de este proyecto viven bajo guard 'web' (el admin
        // Blade), no 'sanctum' -- sin el guard explicito, findByName() lo
        // adivina del contexto (sanctum, esta API) y falla con
        // RoleDoesNotExist. Mismo tipo de bug ya corregido antes en
        // ReportController::coachingMetrics().
        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($permission);

        return json_custom_response(['message' => 'Permiso creado.', 'data' => $permission], 201);
    }

    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return json_message_response('Permiso no encontrado.', 404);
        }

        $permission->delete();

        return json_message_response('Permiso eliminado.');
    }
}
