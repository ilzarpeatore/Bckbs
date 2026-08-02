<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Helpers\SeederHelper;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_plan_recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_plan_id')->nullable();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->string('meal_type')->nullable()->comment('breakfast, lunch, snack, dinner');
            $table->double('calories')->nullable()->default(0);
            $table->double('protein')->nullable()->default(0);
            $table->double('fats')->nullable()->default(0);
            $table->double('carbs')->nullable()->default(0);
            $table->boolean('is_complete')->default(0)->comment('0,1')->nullable();
            $table->foreign('daily_plan_id')->references('id')->on('daily_plans')->onDelete('cascade');
            $table->timestamps();
        });

        $permissions = [
            [
                'name' => 'ingredientcategory',
                'sub_permission' => [ 'ingredientcategory-list', 'ingredientcategory-add', 'ingredientcategory-edit', 'ingredientcategory-delete', ]
            ],
            [
                'name' => 'ingredient',
                'sub_permission' => [ 'ingredient-list', 'ingredient-add', 'ingredient-edit', 'ingredient-delete' ]
            ],
            [
                'name' => 'measurementunit',
                'sub_permission' => [ 'measurementunit-list', 'measurementunit-add', 'measurementunit-edit', 'measurementunit-delete' ]
            ],
            [
                'name' => 'ingredient-unit-conversion',
                'sub_permission' => [ 'ingredient-unit-conversion-list', 'ingredient-unit-conversion-add', 'ingredient-unit-conversion-edit', 'ingredient-unit-conversion-delete' ]
            ],
            [
                'name' => 'recipe-category',
                'sub_permission' => [ 'recipe-category-list', 'recipe-category-add', 'recipe-category-edit', 'recipe-category-delete' ]
            ],
            [
                'name' => 'recipe-tag',
                'sub_permission' => [ 'recipe-tag-list', 'recipe-tag-add', 'recipe-tag-edit', 'recipe-tag-delete' ]
            ],
            [
                'name' => 'recipe',
                'sub_permission' => [ 'recipe-list', 'recipe-add', 'recipe-edit', 'recipe-delete', 'recipe-show' ]
            ],
        ];

        SeederHelper::seedPermissions($permissions);
        $sub_permission = array_merge(
            array_column($permissions, 'name'),
            ...array_column($permissions, 'sub_permission')
        );

        $roles = [
            [
                'name' => 'admin',
                'permissions' => $sub_permission
            ]
        ];

        SeederHelper::seedRoles($roles);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plan_recipes');
    }
};
