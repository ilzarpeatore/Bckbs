<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'name' => 'role',
                'title' => 'Role',
            ],
            [
                'name' => 'role-add',
                'title' => 'Role Add',
                'parent_id' => 1,
            ],
            [
                'name' => 'role-list',
                'title' => 'Role List',
                'parent_id' => 1,
            ],
            [
                'name' => 'permission',
                'title' => 'Permission',
            ],
            [
                'name' => 'permission-add',
                'title' => 'Permission Add',
                'parent_id' => 4,
            ],
            [
                'name' => 'permission-list',
                'title' => 'Permission List',
                'parent_id' => 4,
            ],
            [
                'name' => 'user',
                'title' => 'User',
            ],
            [
                'name' => 'user-list',
                'title' => 'User List',
                'parent_id' => 7,
            ],
            [
                'name' => 'user-add',
                'title' => 'User Add',
                'parent_id' => 7,
            ],
            [
                'name' => 'user-edit',
                'title' => 'User Edit',
                'parent_id' => 7,
            ],
            [
                'name' => 'user-delete',
                'title' => 'User Delete',
                'parent_id' => 7,
            ],
            [
                'name' => 'user-show',
                'title' => 'User Show',
                'parent_id' => 7,
            ],
            [
                'name' => 'equipment',
                'title' => 'Equipment',
            ],
            [
                'name' => 'equipment-list',
                'title' => 'Equipment List',
                'parent_id' => 13,
            ],
            [
                'name' => 'equipment-add',
                'title' => 'Equipment Add',
                'parent_id' => 13,
            ],
            [
                'name' => 'equipment-edit',
                'title' => 'Equipment Edit',
                'parent_id' => 13,
            ],
            [
                'name' => 'equipment-delete',
                'title' => 'Equipment Delete',
                'parent_id' => 13,
            ],
            [
                'name' => 'categorydiet',
                'title' => 'Categorydiet',
            ],
            [
                'name' => 'categorydiet-list',
                'title' => 'Categorydiet List',
                'parent_id' => 18,
            ],
            [
                'name' => 'categorydiet-add',
                'title' => 'Categorydiet Add',
                'parent_id' => 18,
            ],
            [
                'name' => 'categorydiet-edit',
                'title' => 'Categorydiet Edit',
                'parent_id' => 18,
            ],
            [
                'name' => 'categorydiet-delete',
                'title' => 'Categorydiet Delete',
                'parent_id' => 18,
            ],
            [
                'name' => 'workouttype',
                'title' => 'Workouttype',
            ],
            [
                'name' => 'workouttype-list',
                'title' => 'Workouttype List',
                'parent_id' => 23,
            ],
            [
                'name' => 'workouttype-add',
                'title' => 'Workouttype Add',
                'parent_id' => 23,
            ],
            [
                'name' => 'workouttype-edit',
                'title' => 'Workouttype Edit',
                'parent_id' => 23,
            ],
            [
                'name' => 'workouttype-delete',
                'title' => 'Workouttype Delete',
                'parent_id' => 23,
            ],
            [
                'name' => 'diet',
                'title' => 'Diet',
            ],
            [
                'name' => 'diet-list',
                'title' => 'Diet List',
                'parent_id' => 28,
            ],
            [
                'name' => 'diet-add',
                'title' => 'Diet Add',
                'parent_id' => 28,
            ],
            [
                'name' => 'diet-edit',
                'title' => 'Diet Edit',
                'parent_id' => 28,
            ],
            [
                'name' => 'diet-delete',
                'title' => 'Diet Delete',
                'parent_id' => 28,
            ],
            [
                'name' => 'level',
                'title' => 'Level',
            ],
            [
                'name' => 'level-list',
                'title' => 'Level List',
                'parent_id' => 33,
            ],
            [
                'name' => 'level-add',
                'title' => 'Level Add',
                'parent_id' => 33,
            ],
            [
                'name' => 'level-edit',
                'title' => 'Level Edit',
                'parent_id' => 33,
            ],
            [
                'name' => 'level-delete',
                'title' => 'Level Delete',
                'parent_id' => 33,
            ],
            [
                'name' => 'bodyparts',
                'title' => 'Bodyparts',
            ],
            [
                'name' => 'bodyparts-list',
                'title' => 'Bodyparts List',
                'parent_id' => 38,
            ],
            [
                'name' => 'bodyparts-add',
                'title' => 'Bodyparts Add',
                'parent_id' => 38,
            ],
            [
                'name' => 'bodyparts-edit',
                'title' => 'Bodyparts Edit',
                'parent_id' => 38,
            ],
            [
                'name' => 'bodyparts-delete',
                'title' => 'Bodyparts Delete',
                'parent_id' => 38,
            ],
            [
                'name' => 'category',
                'title' => 'Category',
            ],
            [
                'name' => 'category-list',
                'title' => 'Category List',
                'parent_id' => 43,
            ],
            [
                'name' => 'category-add',
                'title' => 'Category Add',
                'parent_id' => 43,
            ],
            [
                'name' => 'category-edit',
                'title' => 'Category Edit',
                'parent_id' => 43,
            ],
            [
                'name' => 'category-delete',
                'title' => 'Category Delete',
                'parent_id' => 43,
            ],
            [
                'name' => 'tags',
                'title' => 'Tags',
            ],
            [
                'name' => 'tags-list',
                'title' => 'Tags List',
                'parent_id' => 48,
            ],
            [
                'name' => 'tags-add',
                'title' => 'Tags Add',
                'parent_id' => 48,
            ],
            [
                'name' => 'tags-edit',
                'title' => 'Tags Edit',
                'parent_id' => 48,
            ],
            [
                'name' => 'tags-delete',
                'title' => 'Tags Delete',
                'parent_id' => 48,
            ],
            [
                'name' => 'exercise',
                'title' => 'Exercise',
            ],
            [
                'name' => 'exercise-list',
                'title' => 'Exercise List',
                'parent_id' => 53,
            ],
            [
                'name' => 'exercise-add',
                'title' => 'Exercise Add',
                'parent_id' => 53,
            ],
            [
                'name' => 'exercise-edit',
                'title' => 'Exercise Edit',
                'parent_id' => 53,
            ],
            [
                'name' => 'exercise-delete',
                'title' => 'Exercise Delete',
                'parent_id' => 53,
            ],
            [
                'name' => 'workout',
                'title' => 'Workout',
            ],
            [
                'name' => 'workout-list',
                'title' => 'Workout List',
                'parent_id' => 58,
            ],
            [
                'name' => 'workout-add',
                'title' => 'Workout Add',
                'parent_id' => 58,
            ],
            [
                'name' => 'workout-edit',
                'title' => 'Workout Edit',
                'parent_id' => 58,
            ],
            [
                'name' => 'workout-delete',
                'title' => 'Workout Delete',
                'parent_id' => 58,
            ],
            [
                'name' => 'pages',
                'title' => 'Pages',
            ],
            [
                'name' => 'terms-condition',
                'title' => 'Terms Condition',
                'parent_id' => 63,
            ],
            [
                'name' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'parent_id' => 63,
            ],
            [
                'name' => 'post',
                'title' => 'Post',
            ],
            [
                'name' => 'post-list',
                'title' => 'Post List',
                'parent_id' => 66,
            ],
            [
                'name' => 'post-add',
                'title' => 'Post Add',
                'parent_id' => 66,
            ],
            [
                'name' => 'post-edit',
                'title' => 'Post Edit',
                'parent_id' => 66,
            ],
            [
                'name' => 'post-delete',
                'title' => 'Post Delete',
                'parent_id' => 66,
            ],
            [
                'name' => 'productcategory',
                'title' => 'Productcategory',
            ],
            [
                'name' => 'productcategory-list',
                'title' => 'Productcategory List',
                'parent_id' => 71,
            ],
            [
                'name' => 'productcategory-add',
                'title' => 'Productcategory Add',
                'parent_id' => 71,
            ],
            [
                'name' => 'productcategory-edit',
                'title' => 'Productcategory Edit',
                'parent_id' => 71,
            ],
            [
                'name' => 'productcategory-delete',
                'title' => 'Productcategory Delete',
                'parent_id' => 71,
            ],
            [
                'name' => 'product',
                'title' => 'Product',
            ],
            [
                'name' => 'product-list',
                'title' => 'Product List',
                'parent_id' => 76,
            ],
            [
                'name' => 'product-add',
                'title' => 'Product Add',
                'parent_id' => 76,
            ],
            [
                'name' => 'product-edit',
                'title' => 'Product Edit',
                'parent_id' => 76,
            ],
            [
                'name' => 'product-delete',
                'title' => 'Product Delete',
                'parent_id' => 76,
            ],
            [
                'name' => 'package',
                'title' => 'Package',
            ],
            [
                'name' => 'package-list',
                'title' => 'Package List',
                'parent_id' => 81,
            ],
            [
                'name' => 'package-add',
                'title' => 'Package Add',
                'parent_id' => 81,
            ],
            [
                'name' => 'package-edit',
                'title' => 'Package Edit',
                'parent_id' => 81,
            ],
            [
                'name' => 'package-delete',
                'title' => 'Package Delete',
                'parent_id' => 81,
            ],
            [
                'name' => 'pushnotification',
                'title' => 'Pushnotification',
            ],
            [
                'name' => 'pushnotification-list',
                'title' => 'Pushnotification List',
                'parent_id' => 86,
            ],
            [
                'name' => 'pushnotification-add',
                'title' => 'Pushnotification Add',
                'parent_id' => 86,
            ],
            [
                'name' => 'pushnotification-delete',
                'title' => 'Pushnotification Delete',
                'parent_id' => 86,
            ],
            [
                'name' => 'subscription',
                'title' => 'Subscription',
            ],
            [
                'name' => 'subscription-list',
                'title' => 'Subscription List',
                'parent_id' => 90,
            ],
            [
                'name' => 'quotes',
                'title' => 'Quotes',
            ],
            [
                'name' => 'quotes-list',
                'title' => 'Quotes List',
                'parent_id' => 92,
            ],
            [
                'name' => 'quotes-add',
                'title' => 'Quotes Add',
                'parent_id' => 92,
            ],
            [
                'name' => 'quotes-edit',
                'title' => 'Quotes Edit',
                'parent_id' => 92,
            ],
            [
                'name' => 'quotes-delete',
                'title' => 'Quotes Delete',
                'parent_id' => 92,
            ],
            [
                'name' => 'subscription-add',
                'title' => 'Subscription Add',
                'parent_id' => 90,
            ],
            [
                'name' => 'screen',
                'title' => 'screen',
                'parent_id' => NULL,
            ],
            [
                'name' => 'screen-list',
                'title' => 'Screen List',
                'parent_id' => 98,
            ],
            [
                'name' => 'defaultkeyword',
                'title' => 'Defaultkeyword',
                'parent_id' => NULL,
            ],
            [
                'name' => 'defaultkeyword-list',
                'title' => 'Defaultkeyword List',
                'parent_id' => 100,
            ],
            [
                'name' => 'defaultkeyword-add',
                'title' => 'Defaultkeyword Add',
                'parent_id' => 100,
            ],
            [
                'name' => 'defaultkeyword-edit',
                'title' => 'Defaultkeyword Edit',
                'parent_id' => 100,
            ],
            [
                'name' => 'languagelist',
                'title' => 'Languagelist',
                'parent_id' => NUll,
            ],
            [
                'name' => 'languagelist-list',
                'title' => 'Languagelist List',
                'parent_id' => 104,
            ],
            [
                'name' => 'languagelist-add',
                'title' => 'Languagelist Add',
                'parent_id' => 104,
            ],
            [
                'name' => 'languagelist-edit',
                'title' => 'Languagelist Edit',
                'parent_id' => 104,
            ],
            [
                'name' => 'languagelist-delete',
                'title' => 'Languagelist Delete',
                'parent_id' => 104,
            ],
            [
                'name' => 'languagewithkeyword',
                'title' => 'Language With Keyword',
                'parent_id' => NUll,
            ],
            [
                'name' => 'languagewithkeyword-list',
                'title' => 'Language With Keyword List',
                'parent_id' => 109,
            ],
            [
                'name' => 'languagewithkeyword-edit',
                'title' => 'Language With Keyword Edit',
                'parent_id' => 109,
            ],
            [
                'name' => 'bulkimport',
                'title' => 'Bulkimport',
                'parent_id' => NUll,
            ],
            [
                'name' => 'bulkimport-add',
                'title' => 'Bulkimport Add',
                'parent_id' => 112,
            ],
            [
                'name' => 'classschedule',
                'title' => 'Class Schedule',
                'parent_id' => NUll,
            ],
            [
                'name' => 'classschedule-list',
                'title' => 'Class Schedule List',
                'parent_id' => 114,
            ],
            [
                'name' => 'classschedule-add',
                'title' => 'Class Schedule Add',
                'parent_id' => 114,
            ],
            [
                'name' => 'classschedule-edit',
                'title' => 'Class Schedule Edit',
                'parent_id' => 114,
            ],
            [
                'name' => 'classschedule-delete',
                'title' => 'Class Schedule Delete',
                'parent_id' => 114,
            ],
            [
                'name' => 'subadmin',
                'title' => 'Sub Admin',
                'parent_id' => NUll,
            ],
            [
                'name' => 'subadmin-list',
                'title' => 'Sub Admin List',
                'parent_id' => 119,
            ],
            [
                'name' => 'subadmin-add',
                'title' => 'Sub Admin Add',
                'parent_id' => 119,
            ],
            [
                'name' => 'subadmin-edit',
                'title' => 'Sub Admin Edit',
                'parent_id' => 119,
            ],
            [
                'name' => 'subadmin-delete',
                'title' => 'Sub Admin Delete',
                'parent_id' => 119,
            ],
            [
                'name' => 'page-list',
                'title' => 'Page List',
                'parent_id' => 63,
            ],
            [
                'name' => 'page-add',
                'title' => 'Page Add',
                'parent_id' => 63,
            ],
            [
                'name' => 'page-edit',
                'title' => 'Page Edit',
                'parent_id' => 63,
            ],
            [
                'name' => 'page-delete',
                'title' => 'Page Delete',
                'parent_id' => 63,
            ],
            [
                'name' => 'websitesection',
                'title' => 'Website Section',
                'parent_id' => NUll,
            ],
            [
                'name' => 'website-section-list',
                'title' => 'Website Section List',
                'parent_id' => 128,
            ],
        ];

        foreach ($permissions as $value) {
            Permission::create($value);
        }
    }
}
