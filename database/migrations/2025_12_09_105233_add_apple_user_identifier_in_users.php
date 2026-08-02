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
        Schema::table('users', function (Blueprint $table) {
            $table->string('apple_user_identifier')->nullable();
        });

        $permissions = [
            [
                'name' => 'bannerslider',
                'sub_permission' => [ 'bannerslider-list', 'bannerslider-add', 'bannerslider-edit', 'bannerslider-delete' ],
            ],
        ];
    
        SeederHelper::seedPermissions($permissions);
    
        $sub_permission = array_merge(
            array_column($permissions, 'name'),
            ...array_column($permissions, 'sub_permission')
        );
    
        $new_permission = [];
    
        $roles = [
            [
                'name' => 'admin',
                'permissions' => array_merge(  $sub_permission, $new_permission  )
            ]
        ];
    
        SeederHelper::seedRoles($roles);


        $screen_data = [
            [
                'screenID' => '8',
                'ScreenName' => 'HomeScreen',
                'keyword_data' => [
                    [
                        'screenId' => '8',
                        'keyword_id' => 370,
                        'keyword_name' => 'lblDailyTracking',
                        'keyword_value' => 'Daily Tracking Progress',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 371,
                        'keyword_name' => 'lblStpCnt',
                        'keyword_value' => 'Steps Count',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 372,
                        'keyword_name' => 'lblWtrInt',
                        'keyword_value' => 'Water Intake',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 373,
                        'keyword_name' => 'lblGlass',
                        'keyword_value' => 'Glass',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 374,
                        'keyword_name' => 'lblStpTrack',
                        'keyword_value' => 'Step Tracker',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 375,
                        'keyword_name' => 'lblStpC1',
                        'keyword_value' => 'Steps are left to reach your goal!!!',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 376,
                        'keyword_name' => 'lblOnly',
                        'keyword_value' => 'Only',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 377,
                        'keyword_name' => 'lblStpC2',
                        'keyword_value' => 'Great! You reached your daily goal',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 378,
                        'keyword_name' => 'lblStpC3',
                        'keyword_value' => 'Please Set Your Goal First',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 379,
                        'keyword_name' => 'lblStpC4',
                        'keyword_value' => 'Amazing! Exceeded your goal by',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 380,
                        'keyword_name' => 'lblDG',
                        'keyword_value' => 'Daily Goal',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 381,
                        'keyword_name' => 'lblWtrTrack',
                        'keyword_value' => 'Water Tracker',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 382,
                        'keyword_name' => 'lblGoalC1',
                        'keyword_value' => 'glasses are left to reach your goal!!!',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 383,
                        'keyword_name' => 'lblGlasses',
                        'keyword_value' => 'Glasses',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 384,
                        'keyword_name' => 'lblLogNw',
                        'keyword_value' => 'Log Now',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 385,
                        'keyword_name' => 'lblEnrGls',
                        'keyword_value' => 'Enter glasses',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 386,
                        'keyword_name' => 'lblWtrConsDaily',
                        'keyword_value' => 'Water Consumption (Daily)',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 387,
                        'keyword_name' => 'assignedWorkouts',
                        'keyword_value' => 'Assigned Workouts',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 388,
                        'keyword_name' => 'assignedDiet',
                        'keyword_value' => 'Assigned Diet',
                    ],
                ],
            ],
            [
                'screenID' => '13',
                'ScreenName' => 'ExerciseDetailScreen',
                'keyword_data' => [
                    [
                        'screenId' => '13',
                        'keyword_id' => 389,
                        'keyword_name' => 'resetExercise',
                        'keyword_value' => 'Reset Exercise',
                    ],
                    [
                        'screenId' => '13',
                        'keyword_id' => 390,
                        'keyword_name' => 'lblComplete',
                        'keyword_value' => 'Complete',
                    ],
                    [
                        'screenId' => '13',
                        'keyword_id' => 391,
                        'keyword_name' => 'lblUpNext',
                        'keyword_value' => 'Up Next',
                    ],
                    [
                        'screenId' => '13',
                        'keyword_id' => 392,
                        'keyword_name' => 'confirmCompleteExercise',
                        'keyword_value' => 'Are you certain you wish to complete this exercise?',
                    ],
                ],
            ],
            [
                'screenID' => '18',
                'ScreenName' => 'ProgressScreen',
                'keyword_data' => [
                    [
                        'screenId' => '18',
                        'keyword_id' => 395,
                        'keyword_name' => 'lblGoal',
                        'keyword_value' => 'Goal',
                    ],
                    [
                        'screenId' => '18',
                        'keyword_id' => 396,
                        'keyword_name' => 'lblAchived',
                        'keyword_value' => 'Achieved',
                    ],
                    [
                        'screenId' => '18',
                        'keyword_id' => 397,
                        'keyword_name' => 'lblConsumed',
                        'keyword_value' => 'Consumed',
                    ],
                    [
                        'screenId' => '18',
                        'keyword_id' => 398,
                        'keyword_name' => 'valueGreaterZero',
                        'keyword_value' => 'Value must be greater than 0',
                    ],
                ],
            ],
            [
                'screenID' => '19',
                'ScreenName' => 'ProfileScreen',
                'keyword_data' => [
                    [
                        'screenId' => '19',
                        'keyword_id' => 393,
                        'keyword_name' => 'lblExerHtr',
                        'keyword_value' => 'Exercise History',
                    ],
                ],
            ],
            [
                'screenID' => '24',
                'ScreenName' => 'CommunityScreen',
                'keyword_data' => [
                    [
                        'screenId' => '24',
                        'keyword_id' => 394,
                        'keyword_name' => 'lblPosts',
                        'keyword_value' => 'Posts',
                    ],
                ],
            ],
        ];

        createAppLanguageSetting( $screen_data );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
