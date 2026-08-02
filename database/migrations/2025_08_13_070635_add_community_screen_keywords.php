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
        $screen_data =
        [
            [
                'screenID' => '13',
                'ScreenName' => 'ExerciseDetailScreen',
                'keyword_data' => [
                    [
                        'screenId' => '13',
                        'keyword_id' => 240,
                        'keyword_name' => 'lblExerciseDone',
                        'keyword_value' => 'Exercise Done',
                    ],
                ],
            ],
            [
                'screenID' => '16',
                'ScreenName' => 'DietScreen',
                'keyword_data' => [
                    [
                        'screenId' => '16',
                        'keyword_id' => 363,
                        'keyword_name' => 'disclaimer',
                        'keyword_value' => 'Disclaimer',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 364,
                        'keyword_name' => 'viewSourceReference',
                        'keyword_value' => 'View Sources & References',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 365,
                        'keyword_name' => 'sourceReference',
                        'keyword_value' => 'Sources & References',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 366,
                        'keyword_name' => 'close',
                        'keyword_value' => 'Close',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 367,
                        'keyword_name' => 'dietDesclaimerNote',
                        'keyword_value' => 'This app provides general health and nutrition information and is not intended to replace professional medical advice. ',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 368,
                        'keyword_name' => 'dietDesclaimerNote2',
                        'keyword_value' => ' Dietary needs vary, and you should consult a healthcare professional before making significant dietary changes.',
                    ],
                    [
                        'screenId' => '16',
                        'keyword_id' => 369,
                        'keyword_name' => 'dietDesclaimerNote3',
                        'keyword_value' => ' The information presented is based on available research and should not be considered medical advice.',
                    ],
                ],
            ],
            [
                'screenID' => '24',
                'ScreenName' => 'CommunityScreen',
                'keyword_data' => [
                    [
                        'screenId' => '24',
                        'keyword_id' => 310,
                        'keyword_name' => 'lblCommunity',
                        'keyword_value' => 'Community',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 311,
                        'keyword_name' => 'lblReportPost',
                        'keyword_value' => 'Report this post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 312,
                        'keyword_name' => 'lblEditPost',
                        'keyword_value' => 'Edit Post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 313,
                        'keyword_name' => 'lblDeletePost',
                        'keyword_value' => 'Are you sure you want to delete this post?',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 314,
                        'keyword_name' => 'lblDelPost',
                        'keyword_value' => 'Delete Post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 315,
                        'keyword_name' => 'gotoProfile',
                        'keyword_value' => 'Go to Profile',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 316,
                        'keyword_name' => 'lblNoPost',
                        'keyword_value' => 'No post available.',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 317,
                        'keyword_name' => 'lblComments',
                        'keyword_value' => 'Comments',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 318,
                        'keyword_name' => 'lblUpdate',
                        'keyword_value' => 'Update',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 319,
                        'keyword_name' => 'lblReply',
                        'keyword_value' => 'Reply',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 320,
                        'keyword_name' => 'lblViewR',
                        'keyword_value' => 'view reply',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 321,
                        'keyword_name' => 'lblHideR',
                        'keyword_value' => 'hide reply',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 322,
                        'keyword_name' => 'lblUpComments',
                        'keyword_value' => 'Update Comment',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 323,
                        'keyword_name' => 'lblAddComments',
                        'keyword_value' => 'Add a comment',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 324,
                        'keyword_name' => 'lblReports',
                        'keyword_value' => 'Report',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 325,
                        'keyword_name' => 'lblRepoDes',
                        'keyword_value' => 'Report Description',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 327,
                        'keyword_name' => 'lblRepo',
                        'keyword_value' => 'Reported:',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 328,
                        'keyword_name' => 'finishProfileSetting',
                        'keyword_value' => 'Finish setting up your profile',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 326,
                        'keyword_name' => 'lblChoseVideo',
                        'keyword_value' => 'Choose Video',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 329,
                        'keyword_name' => 'lblMaxVideoMsg',
                        'keyword_value' => 'Video size exceeds 30 MB. Please select a smaller video.',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 330,
                        'keyword_name' => 'lblNewPost',
                        'keyword_value' => 'New post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 331,
                        'keyword_name' => 'WriteSomeThing',
                        'keyword_value' => 'write something here....',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 332,
                        'keyword_name' => 'lblEditImg',
                        'keyword_value' => 'Edit Image',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 333,
                        'keyword_name' => 'lblEditVid',
                        'keyword_value' => 'Edit Video',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 334,
                        'keyword_name' => 'lblSelectImg',
                        'keyword_value' => 'Selected Image:',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 335,
                        'keyword_name' => 'lblSelectVid',
                        'keyword_value' => 'Video selected successfully.',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 336,
                        'keyword_name' => 'lblEmptyMsg',
                        'keyword_value' => 'Please add contain',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 337,
                        'keyword_name' => 'lblAddImg',
                        'keyword_value' => 'Add Image',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 338,
                        'keyword_name' => 'lblAddVid',
                        'keyword_value' => 'Add Video',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 339,
                        'keyword_name' => 'lblSharePost',
                        'keyword_value' => 'Share Post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 340,
                        'keyword_name' => 'lblCamera',
                        'keyword_value' => 'Camera',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 341,
                        'keyword_name' => 'lblChoseImg',
                        'keyword_value' => 'Choose image',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 342,
                        'keyword_name' => 'lblRecord',
                        'keyword_value' => 'Record',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 343,
                        'keyword_name' => 'lblPost',
                        'keyword_value' => 'Post',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 344,
                        'keyword_name' => 'edtCmt',
                        'keyword_value' => 'Edit Comment',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 345,
                        'keyword_name' => 'edtRpl',
                        'keyword_value' => 'Edit Reply',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 346,
                        'keyword_name' => 'dltRpl',
                        'keyword_value' => 'Delete Reply',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 347,
                        'keyword_name' => 'dltCmt',
                        'keyword_value' => 'Delete Comment',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 348,
                        'keyword_name' => 'share',
                        'keyword_value' => 'Share',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 349,
                        'keyword_name' => 'posted',
                        'keyword_value' => 'Posted',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 350,
                        'keyword_name' => 'lblCmt',
                        'keyword_value' => 'Comment',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 351,
                        'keyword_name' => 'lblLike',
                        'keyword_value' => 'Like',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 352,
                        'keyword_name' => 'lblLikes',
                        'keyword_value' => 'Likes',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 353,
                        'keyword_name' => 'lblUMedia',
                        'keyword_value' => 'Upload Media',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 356,
                        'keyword_name' => 'lblOpen',
                        'keyword_value' => 'Open',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 357,
                        'keyword_name' => 'lblPermissionDescription',
                        'keyword_value' => 'Media access permission is required to pick files. Please enable it in settings.',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 358,
                        'keyword_name' => 'confirmDeleteComment',
                        'keyword_value' => 'Are you sure you want to delete your comment?',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 359,
                        'keyword_name' => 'confirmDeleteCommentReply',
                        'keyword_value' => 'Are you sure you want to delete your comment reply?',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 360,
                        'keyword_name' => 'checkOutPost',
                        'keyword_value' => 'Check out our post:',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 361,
                        'keyword_name' => 'readMore',
                        'keyword_value' => 'Read more..',
                    ],
                    [
                        'screenId' => '24',
                        'keyword_id' => 362,
                        'keyword_name' => 'readLess',
                        'keyword_value' => 'Read less..',
                    ],
                ],
            ],
        ];
        
        createAppLanguageSetting( $screen_data );

        $permissions = [
            [
                'name' => 'posting', 
                'sub_permission' => ['posting-list', 'posting-show', 'posting-delete', 'reported-posting-list' ] 
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
