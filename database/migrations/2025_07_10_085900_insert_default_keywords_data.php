<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
                'screenID' => '8',
                'ScreenName' => 'HomeScreen',
                'keyword_data' => [
                    [
                        'screenId' => '8',
                        'keyword_id' => 246,
                        'keyword_name' => 'lblUpdateNow',
                        'keyword_value' => 'Update Now',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 247,
                        'keyword_name' => 'lblUpdateAvailable',
                        'keyword_value' => 'Update Available',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 248,
                        'keyword_name' => 'lblUpdateNote',
                        'keyword_value' => 'A new version is ready! Update now for the latest features and improvements.',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 249,
                        'keyword_name' => 'lblGameOver',
                        'keyword_value' => 'Game Over!',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 250,
                        'keyword_name' => 'lblMainMenu',
                        'keyword_value' => 'Main menu',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 251,
                        'keyword_name' => 'lblBetterLuckNextTime',
                        'keyword_value' => 'Better luck next time!',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 252,
                        'keyword_name' => 'lblExit',
                        'keyword_value' => 'Exit',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 253,
                        'keyword_name' => 'lblMightyBrainWorkout',
                        'keyword_value' => 'Mighty brain workout',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 254,
                        'keyword_name' => 'lblGameTitle',
                        'keyword_value' => 'Find a different color to check brain workout',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 255,
                        'keyword_name' => 'lblStart',
                        'keyword_value' => 'Start',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 256,
                        'keyword_name' => 'lblPleaseWait',
                        'keyword_value' => 'Please Wait',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 257,
                        'keyword_name' => 'lblHoursAfterPlayAgain',
                        'keyword_value' => 'hours after play again',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 258,
                        'keyword_name' => 'lblBuildMuscle',
                        'keyword_value' => 'Build muscle',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 259,
                        'keyword_name' => 'lblKeepFit',
                        'keyword_value' => 'Keep Fit',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 260,
                        'keyword_name' => 'lblLoseWeight',
                        'keyword_value' => 'Lose weight',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 261,
                        'keyword_name' => 'lblFirstDescriptions1',
                        'keyword_value' => 'Lower weight with higher reps and work on medium and small muscles',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 262,
                        'keyword_name' => 'lblFirstDescriptions2',
                        'keyword_value' => 'Start with basic muscle workout plans and keep your muscles fit and toned',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 263,
                        'keyword_name' => 'lblFirstDescriptions3',
                        'keyword_value' => 'Lower weight with higher reps and shorter rest times with cardio exercises',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 264,
                        'keyword_name' => 'lblTotallyNewbie',
                        'keyword_value' => 'Totally newbie',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 265,
                        'keyword_name' => 'lblBeginner',
                        'keyword_value' => 'beginner',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 266,
                        'keyword_name' => 'lblIntermediate',
                        'keyword_value' => 'Intermediate',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 267,
                        'keyword_name' => 'lblAdvanced',
                        'keyword_value' => 'Advanced',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 268,
                        'keyword_name' => 'LblSecDesc1',
                        'keyword_value' => 'I never workedout before',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 269,
                        'keyword_name' => 'LblSecDesc2',
                        'keyword_value' => 'I worked out before but not seriously',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 270,
                        'keyword_name' => 'LblSecDesc3',
                        'keyword_value' => 'I worked out before',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 271,
                        'keyword_name' => 'LblSecDesc4',
                        'keyword_value' => 'I have been working out for years',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 272,
                        'keyword_name' => 'lblNoEquipment',
                        'keyword_value' => 'No Equipment',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 273,
                        'keyword_name' => 'lblDumbbells',
                        'keyword_value' => 'Dumbbells',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 274,
                        'keyword_name' => 'lblGarageGym',
                        'keyword_value' => 'Garage Gym',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 275,
                        'keyword_name' => 'lblFullGym',
                        'keyword_value' => 'Full Gym',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 276,
                        'keyword_name' => 'lblCustom',
                        'keyword_value' => 'Custom',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 277,
                        'keyword_name' => 'lblThirdDescriptions1',
                        'keyword_value' => 'Home workouts with body weight exercises',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 278,
                        'keyword_name' => 'lblThirdDescriptions2',
                        'keyword_value' => 'Only exercises with dumbbell and body weight',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 279,
                        'keyword_name' => 'lblThirdDescriptions3',
                        'keyword_value' => 'Exercises with barbell,dumbbell and body weight',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 280,
                        'keyword_name' => 'lblThirdDescriptions4',
                        'keyword_value' => 'All exercises with machines,barbell and all',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 281,
                        'keyword_name' => 'lblThirdDescriptions5',
                        'keyword_value' => 'Choose the equipments you have or wish to use',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 282,
                        'keyword_name' => 'lblHomeScreenTitle',
                        'keyword_value' => 'Enter your height, weight, gender and age to access advanced features.',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 307,
                        'keyword_name' => 'lblRepsWeight',
                        'keyword_value' => 'Reps-Weight',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 309,
                        'keyword_name' => 'lblLeaderboard',
                        'keyword_value' => 'Leaderboard',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 308,
                        'keyword_name' => 'lblRest',
                        'keyword_value' => 'Rest',
                    ],
                ],
            ],
            [
                'screenID' => '14',
                'ScreenName' => 'PaymentScreen',
                'keyword_data' => [
                    [
                        'screenId' => '14',
                        'keyword_id' => 294,
                        'keyword_name' => 'lblFailed',
                        'keyword_value' => 'Failed',
                    ],
                    [
                        'screenId' => '14',
                        'keyword_id' => 295,
                        'keyword_name' => 'lblPaymentSuccessful',
                        'keyword_value' => 'Payment Successful!',
                    ],
                    [
                        'screenId' => '14',
                        'keyword_id' => 296,
                        'keyword_name' => 'lblPaymentCancelled',
                        'keyword_value' => 'Payment Cancelled!',
                    ],
                ],
            ],
            [
                'screenID' => '19',
                'ScreenName' => 'ProfileScreen',
                'keyword_data' => [
                    [
                        'screenId' => '19',
                        'keyword_id' => 354,
                        'keyword_name' => 'lblPostBmk',
                        'keyword_value' => 'Post Bookmark',
                    ],
                    [
                        'screenId' => '19',
                        'keyword_id' => 355,
                        'keyword_name' => 'lblWOHtr',
                        'keyword_value' => 'Workout History',
                    ],
                ],
            ],
            [
                'screenID' => '21',
                'ScreenName' => 'ChattingImageScreen',
                'keyword_data' => [
                    [
                        'screenId' => '21',
                        'keyword_id' => 283,
                        'keyword_name' => 'lblNoConversationFound',
                        'keyword_value' => 'No Conversation Found',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 284,
                        'keyword_name' => 'lblViewContact',
                        'keyword_value' => 'View Contact',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 285,
                        'keyword_name' => 'lblUnblock',
                        'keyword_value' => 'Unblock',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 286,
                        'keyword_name' => 'lblBlock',
                        'keyword_value' => 'Block',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 287,
                        'keyword_name' => 'lblClearChat',
                        'keyword_value' => 'Clear Chat',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 288,
                        'keyword_name' => 'lblChatCleared',
                        'keyword_value' => 'Chat cleared',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 289,
                        'keyword_name' => 'lblBlockMsg',
                        'keyword_value' => 'Blocked contact will no longer be able to call you or send you messages.',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 290,
                        'keyword_name' => 'lblOnline',
                        'keyword_value' => 'Online',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 291,
                        'keyword_name' => 'lblLastSeen',
                        'keyword_value' => 'Last seen',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 292,
                        'keyword_name' => 'lblSearchHere',
                        'keyword_value' => 'Search Here',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 293,
                        'keyword_name' => 'lblNewChat',
                        'keyword_value' => 'New Chat',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 297,
                        'keyword_name' => 'lblDeleteChat',
                        'keyword_value' => 'Delete Chat',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 298,
                        'keyword_name' => 'lblDeleteDialogTitle',
                        'keyword_value' => 'All Chat will be cleared and deleted',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 299,
                        'keyword_name' => 'lblChatDeleted',
                        'keyword_value' => 'Chat Deleted',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 300,
                        'keyword_name' => 'lblDeleteMessage',
                        'keyword_value' => 'Delete Message',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 301,
                        'keyword_name' => 'lblChat',
                        'keyword_value' => 'Chat',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 302,
                        'keyword_name' => 'lblMinRead',
                        'keyword_value' => 'min read',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 305,
                        'keyword_name' => 'lblToSendMsg',
                        'keyword_value' => 'to send a message',
                    ],
                    [
                        'screenId' => '21',
                        'keyword_id' => 306,
                        'keyword_name' => 'lblMsg',
                        'keyword_value' => 'Message',
                    ],
                ],
            ],
            [
                'screenID' => '23',
                'ScreenName' => 'ScheduleScreen',
                'keyword_data' => [
                    [
                        'screenId' => '23',
                        'keyword_id' => 303,
                        'keyword_name' => 'lblFree',
                        'keyword_value' => 'Free',
                    ],
                    [
                        'screenId' => '23',
                        'keyword_id' => 304,
                        'keyword_name' => 'lblPurchases',
                        'keyword_value' => 'Purchases',
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
        //
    }
};
