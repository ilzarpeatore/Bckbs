<?php

namespace App\Helpers;

use App\Models\Permission;
use App\Models\Role;

class SeederHelper
{
    public static function seedPermissions(array $permissions, $parent_id = null)
    {
        foreach ($permissions as $permission) {
            
            $parent = Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['title' => ucwords($permission['name'])],
                ['parent_id' => null]
            );

            foreach ($permission['sub_permission'] as $sub) {
                Permission::firstOrCreate([
                    'name' => $sub,
                    'title' => ucwords(str_replace('-', ' ', $sub)),
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }

    public static function seedRoles(array $roles)
    {
        foreach ($roles as $value) {
            $permissionNames = $value['permissions'] ?? [];
            unset($value['permissions']);
    
            // Find existing role or skip if not found
            $role = Role::where('name', $value['name'])->first();
    
            // If role doesn't exist, create it
            if (!$role) {
                $role = Role::create([
                    'name'      => $value['name'],
                    'title'     => ucwords($value['name']),
                    'status'    => $value['status'] ?? 1,
                ]);
            }
    
            // Filter only existing permissions by name
            $existingPermissions = Permission::whereIn('name', $permissionNames)->pluck('name')->toArray();
    
            // Assign only existing permissions (overwrite previous ones)
            $role->givePermissionTo($existingPermissions);
        }
    }

}