<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            [
                'name' => 'admin',
                'title' => 'Admin',
                'status' => 1,
                'permissions' => ['role','role-add', 'role-list', 'permission', 'permission-add', 'permission-list', 'user', 'user-list', 'user-add', 'user-edit', 'user-delete', 'user-show',  'equipment', 'equipment-list', 'equipment-add', 'equipment-edit', 'equipment-delete', 'categorydiet', 'categorydiet-list', 'categorydiet-add', 'categorydiet-edit', 'categorydiet-delete', 'workouttype', 'workouttype-list', 'workouttype-add', 'workouttype-edit', 'workouttype-delete', 'diet', 'diet-list', 'diet-add', 'diet-edit', 'diet-delete', 'level', 'level-list', 'level-add', 'level-edit', 'level-delete', 'bodyparts', 'bodyparts-list', 'bodyparts-add', 'bodyparts-edit', 'bodyparts-delete', 'exercise', 'exercise-list', 'exercise-add', 'exercise-edit', 'exercise-delete',
                'workout', 'workout-list', 'workout-add', 'workout-edit', 'workout-delete','category', 'category-list', 'category-add', 'category-edit', 'category-delete', 'tags', 'tags-list', 'tags-add', 'tags-edit', 'tags-delete','post', 'post-list', 'post-add', 'post-edit', 'post-delete', 'pages', 'terms-condition', 'privacy-policy', 'productcategory', 'productcategory-list', 'productcategory-add', 'productcategory-edit', 'productcategory-delete', 'product', 'product-list', 'product-add', 'product-edit', 'product-delete', 'package', 'package-list', 'package-add', 'package-edit', 'package-delete', 'pushnotification', 'pushnotification-list', 'pushnotification-add', 'pushnotification-delete', 'subscription', 'subscription-list', 'quotes', 'quotes-list', 'quotes-add', 'quotes-edit', 'quotes-delete', 'subscription-add',
                'screen', 'screen-list', 'defaultkeyword', 'defaultkeyword-list', 'defaultkeyword-add','defaultkeyword-edit', 'languagelist', 'languagelist-list', 'languagelist-add', 'languagelist-edit', 'languagelist-delete', 'languagewithkeyword', 'languagewithkeyword-list', 'languagewithkeyword-edit', 'bulkimport', 'bulkimport-add', 'classschedule', 'classschedule-list', 'classschedule-add', 'classschedule-edit', 'classschedule-delete', 'subadmin', 'subadmin-list', 'subadmin-add', 'subadmin-edit', 'subadmin-delete','page-list', 'page-add', 'page-edit', 'page-delete','website-section-list'],
            ],
            [
                'name' => 'user',
                'title' => 'User',
                'status' => 1,
                'permissions' => []
            ]
        ];

        foreach ($roles as $key => $value) {
            $permission = $value['permissions'];
            unset($value['permissions']);
            $role = Role::create($value);
            $role->givePermissionTo($permission);
        }
    }
}
