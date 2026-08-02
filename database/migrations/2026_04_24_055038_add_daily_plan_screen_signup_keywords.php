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
                        'keyword_id' => 460,
                        'keyword_name' => 'lblSetGoalInsights',
                        'keyword_value' => 'Set your daily goal to get better insights.',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 461,
                        'keyword_name' => 'lblSetWaterGoal',
                        'keyword_value' => 'Set Water Goal',
                    ],
                    [
                        'screenId' => '8',
                        'keyword_id' => 462,
                        'keyword_name' => 'lblSetStepGoal',
                        'keyword_value' => 'Set Step Goal',
                    ],
                ],
            ],
            [
                'screenID' => '25',
                'ScreenName' => 'DailyPlanScreen',
                'keyword_data' => [
                    [
                        'screenId' => '25',
                        'keyword_id' => 399,
                        'keyword_name' => 'lblQuantity',
                        'keyword_value' => 'Quantity',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 400,
                        'keyword_name' => 'lblServing',
                        'keyword_value' => 'Serving',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 401,
                        'keyword_name' => 'lblRecipesteps',
                        'keyword_value' => 'Recipe Steps',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 403,
                        'keyword_name' => 'lblNorecipesfound',
                        'keyword_value' => 'No recipes found',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 404,
                        'keyword_name' => 'lblMeals',
                        'keyword_value' => 'Meals',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 405,
                        'keyword_name' => 'lblWater',
                        'keyword_value' => 'Water',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 406,
                        'keyword_name' => 'lblDoyouwantustorecalculateyourcaloriesandmacrosaccordingtoyournewinformation',
                        'keyword_value' => 'Do you want us to recalculate your calories and macros according to your new information?',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 407,
                        'keyword_name' => 'lblNodatafound',
                        'keyword_value' => 'No data found',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 408,
                        'keyword_name' => 'lblTitle',
                        'keyword_value' => 'title',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 409,
                        'keyword_name' => 'lblReminders',
                        'keyword_value' => 'Reminders',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 410,
                        'keyword_name' => 'lblTime',
                        'keyword_value' => 'Time',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 411,
                        'keyword_name' => 'lblBreakfast',
                        'keyword_value' => 'Breakfast',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 412,
                        'keyword_name' => 'lblSnacks',
                        'keyword_value' => 'Snacks',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 413,
                        'keyword_name' => 'lblLunch',
                        'keyword_value' => 'Lunch',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 414,
                        'keyword_name' => 'lblDinner',
                        'keyword_value' => 'Dinner',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 415,
                        'keyword_name' => 'lblStopcasting',
                        'keyword_value' => 'Stop Casting',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 416,
                        'keyword_name' => 'lblEditlist',
                        'keyword_value' => 'Edit list',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 417,
                        'keyword_name' => 'lblDeletelist',
                        'keyword_value' => 'Delete list',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 418,
                        'keyword_name' => 'lblAdditem',
                        'keyword_value' => 'Add Item',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 419,
                        'keyword_name' => 'lblItem',
                        'keyword_value' => 'Item',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 420,
                        'keyword_name' => 'lblUnit',
                        'keyword_value' => 'Unit',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 421,
                        'keyword_name' => 'lblNone',
                        'keyword_value' => 'None',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 422,
                        'keyword_name' => 'lblDeleteshoppinglist',
                        'keyword_value' => 'Delete Shopping List',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 423,
                        'keyword_name' => 'lblSpecificdate',
                        'keyword_value' => 'Specific Date',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 424,
                        'keyword_name' => 'lblDate',
                        'keyword_value' => 'Date:',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 425,
                        'keyword_name' => 'lblDaterange',
                        'keyword_value' => 'Date Range:',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 426,
                        'keyword_name' => 'lblMealtypes',
                        'keyword_value' => 'Meal Types',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 427,
                        'keyword_name' => 'lblServings',
                        'keyword_value' => 'Servings:',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 428,
                        'keyword_name' => 'lblIscompleteonly',
                        'keyword_value' => 'Is Complete Only',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 429,
                        'keyword_name' => 'lblEvery',
                        'keyword_value' => 'Every',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 430,
                        'keyword_name' => 'lblFrom',
                        'keyword_value' => 'From',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 431,
                        'keyword_name' => 'lblUntil',
                        'keyword_value' => 'Until',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 432,
                        'keyword_name' => 'lblAt',
                        'keyword_value' => 'At',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 433,
                        'keyword_name' => 'lblMealswater',
                        'keyword_value' => 'Meals & Water',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 434,
                        'keyword_name' => 'lblGenerateshoppinglist',
                        'keyword_value' => 'Generate shopping list',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 435,
                        'keyword_name' => 'lblChoosewhichplannedmealstoinclude',
                        'keyword_value' => 'Choose which planned meals to include.',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 436,
                        'keyword_name' => 'lblCancel',
                        'keyword_value' => 'Cancel',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 437,
                        'keyword_name' => 'lblSelectaspecificdate',
                        'keyword_value' => 'Select a specific date',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 438,
                        'keyword_name' => 'lblPickstartenddates',
                        'keyword_value' => 'Pick start & end dates',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 439,
                        'keyword_name' => 'lblTags',
                        'keyword_value' => 'Tags',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 440,
                        'keyword_name' => 'lblCategories',
                        'keyword_value' => 'Categories',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 441,
                        'keyword_name' => 'lblRecipes',
                        'keyword_value' => 'Recipes',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 442,
                        'keyword_name' => 'lblViewmore',
                        'keyword_value' => 'View More',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 443,
                        'keyword_name' => 'lblWaterreminder',
                        'keyword_value' => 'Water Reminder',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 444,
                        'keyword_name' => 'lblDataaccessed',
                        'keyword_value' => 'Data accessed:',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 445,
                        'keyword_name' => 'lblStepcount',
                        'keyword_value' => 'Step Count',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 446,
                        'keyword_name' => 'lblCompleteyourprofile',
                        'keyword_value' => 'Complete Your Profile',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 447,
                        'keyword_name' => 'lblMightyfitness',
                        'keyword_value' => 'Mighty Fitness',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 448,
                        'keyword_name' => 'lblAdloading',
                        'keyword_value' => 'Ad loading...',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 449,
                        'keyword_name' => 'lblDatafromapplehealth',
                        'keyword_value' => 'Data from Apple Health',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 450,
                        'keyword_name' => 'lblFilters',
                        'keyword_value' => 'Filters',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 451,
                        'keyword_name' => 'lblClearday',
                        'keyword_value' => 'Clear Day',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 452,
                        'keyword_name' => 'lblAreyousureyouwanttocleartheentiredayplan',
                        'keyword_value' => 'Are you sure you want to clear the entire day plan?',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 453,
                        'keyword_name' => 'lblWhichtypeofdietdoyouwant',
                        'keyword_value' => 'Which type of diet do you want?',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 454,
                        'keyword_name' => 'lblDeleteall',
                        'keyword_value' => 'Delete All',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 455,
                        'keyword_name' => 'lblNihroleofdietinautoimmunediseases',
                        'keyword_value' => 'NIH - Role of Diet in Autoimmune Diseases',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 456,
                        'keyword_name' => 'lblHarvardinflammatoryfoodsimpact',
                        'keyword_value' => 'Harvard - Inflammatory Foods Impact',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 457,
                        'keyword_name' => 'lblWhonutritionalguidelinesforillness',
                        'keyword_value' => 'WHO - Nutritional Guidelines for Illness',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 458,
                        'keyword_name' => 'lblFindadifferentcolortocheckbrainworkout',
                        'keyword_value' => 'Find a different color to check brain workout',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 459,
                        'keyword_name' => 'lblConfirmDeleteShoppingList',
                        'keyword_value' => 'Are you sure you want to delete this shopping list?',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 468,
                        'keyword_name' => 'lblProteins',
                        'keyword_value' => 'Proteins',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 469,
                        'keyword_name' => 'lblCarbs',
                        'keyword_value' => 'Carbs',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 470,
                        'keyword_name' => 'lblFats',
                        'keyword_value' => 'Fats',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 471,
                        'keyword_name' => 'lblPleaseselectadiettype',
                        'keyword_value' => 'Please select a diet type',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 472,
                        'keyword_name' => 'lblRetry',
                        'keyword_value' => 'Retry',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 473,
                        'keyword_name' => 'lblPleaseenteryourageweightandhei',
                        'keyword_value' => 'Please enter your age, weight, and height to access this feature',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 474,
                        'keyword_name' => 'plan',
                        'keyword_value' => 'Plan',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 475,
                        'keyword_name' => 'lblCastingnotsupported',
                        'keyword_value' => 'Casting not supported',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 476,
                        'keyword_name' => 'lblDatasaved',
                        'keyword_value' => 'Data saved',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 477,
                        'keyword_name' => 'lblPleaseenteratitle',
                        'keyword_value' => 'Please enter a title',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 478,
                        'keyword_name' => 'lblNodailyplanfoundforthisdate',
                        'keyword_value' => 'No daily plan found for this date',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 479,
                        'keyword_name' => 'lblPleasewaitfordailyplantoload',
                        'keyword_value' => 'Please wait for daily plan to load',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 480,
                        'keyword_name' => 'lblPleaseselectatleastonemealtype',
                        'keyword_value' => 'Please select at least one meal type',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 481,
                        'keyword_name' => 'lblPleaseselectadaterange',
                        'keyword_value' => 'Please select a date range',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 482,
                        'keyword_name' => 'lblSelectday',
                        'keyword_value' => 'Select Day',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 483,
                        'keyword_name' => 'lblFailed',
                        'keyword_value' => 'Failed',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 486,
                        'keyword_name' => 'lblMissingrequiredinformation',
                        'keyword_value' => 'Missing required information',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 487,
                        'keyword_name' => 'lblStatusupdatedsuccessfully',
                        'keyword_value' => 'Status updated successfully',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 488,
                        'keyword_name' => 'lblRecipedeletedsuccessfully',
                        'keyword_value' => 'Recipe deleted successfully',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 490,
                        'keyword_name' => 'lblDailyplanclearedsuccessfully',
                        'keyword_value' => 'Daily plan cleared successfully',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 492,
                        'keyword_name' => 'lblInvalidrecipedata',
                        'keyword_value' => 'Invalid recipe data',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 493,
                        'keyword_name' => 'lblRecipeaddedsuccessfully',
                        'keyword_value' => 'Recipe added successfully',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 494,
                        'keyword_name' => 'lblEditShoppingList',
                        'keyword_value' => 'Edit Shopping List',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 495,
                        'keyword_name' => 'lblAddShoppingList',
                        'keyword_value' => 'Add Shopping List',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 496,
                        'keyword_name' => 'lblAddNewItemToYourShoppingList',
                        'keyword_value' => 'Add a new item to your shopping list',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 497,
                        'keyword_name' => 'lblFailedToLoadVideo',
                        'keyword_value' => 'Failed to load video',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 498,
                        'keyword_name' => 'lblFailedToLoadImage',
                        'keyword_value' => 'Failed to load image',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 499,
                        'keyword_name' => 'lblReadLess',
                        'keyword_value' => 'Read less',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 500,
                        'keyword_name' => 'lblReadMore',
                        'keyword_value' => 'Read more',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 501,
                        'keyword_name' => 'lblReplyTo',
                        'keyword_value' => 'Reply to ',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 502,
                        'keyword_name' => 'lblApplyFilters',
                        'keyword_value' => 'Apply Filters',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 503,
                        'keyword_name' => 'lblAppleHealthIntegration',
                        'keyword_value' => 'Apple Health Integration',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 504,
                        'keyword_name' => 'lblConnectAppleHealth',
                        'keyword_value' => 'Connect Apple Health',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 505,
                        'keyword_name' => 'lblStepDataFromAppleHealth',
                        'keyword_value' => 'Step data from Apple Health',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 506,
                        'keyword_name' => 'lblTenSecondRemaining',
                        'keyword_value' => 'Ten second remaining',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 507,
                        'keyword_name' => 'lblThreeSecondRemainingForRest',
                        'keyword_value' => 'Three second remaining for rest',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 508,
                        'keyword_name' => 'lblThreeSecondRemaining',
                        'keyword_value' => 'Three second remaining',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 509,
                        'keyword_name' => 'lblExerciseComplete',
                        'keyword_value' => 'Exercise Complete',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 510,
                        'keyword_name' => 'lblPayWithCard',
                        'keyword_value' => 'Pay with Card',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 511,
                        'keyword_name' => 'lblShoppingLists',
                        'keyword_value' => 'Shopping Lists',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 512,
                        'keyword_name' => 'lblNoShoppingListsFound',
                        'keyword_value' => 'No shopping lists found',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 513,
                        'keyword_name' => 'lblSimpleList',
                        'keyword_value' => 'Simple List',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 514,
                        'keyword_name' => 'lblCategorized',
                        'keyword_value' => 'Categorized',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 515,
                        'keyword_name' => 'lblEnterItemName',
                        'keyword_value' => 'Enter item name',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 516,
                        'keyword_name' => 'lblItemNameIsRequired',
                        'keyword_value' => 'Item name is required',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 517,
                        'keyword_name' => 'lblGoalCaloriesMacros',
                        'keyword_value' => 'Goal, Calories & Macros',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 518,
                        'keyword_name' => 'lblActivityLevel',
                        'keyword_value' => 'Activity level',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 519,
                        'keyword_name' => 'lblMacrosDietType',
                        'keyword_value' => 'Macros & Diet type',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 520,
                        'keyword_name' => 'lblUpdatedSuccessfully',
                        'keyword_value' => 'Updated successfully',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 521,
                        'keyword_name' => 'lblGeneric',
                        'keyword_value' => 'Generic',
                    ],
                    [
                        'screenId' => '25',
                        'keyword_id' => 522,
                        'keyword_name' => 'lblMarkThisRecipeAsCompleted',
                        'keyword_value' => 'Mark this recipe as completed',
                    ],
                ],
            ],
            [
                'screenID' => '26',
                'ScreenName' => 'SignUpStep5Component',
                'keyword_data' => [
                    [
                        'screenId' => '26',
                        'keyword_id' => 463,
                        'keyword_name' => 'lblWhatsYourGoal',
                        'keyword_value' => 'What\'s your goal?',
                    ],
                    [
                        'screenId' => '26',
                        'keyword_id' => 464,
                        'keyword_name' => 'lblGoalSubtitle',
                        'keyword_value' => 'We\'ll help you find the right calorie intake to achieve it',
                    ],
                    [
                        'screenId' => '26',
                        'keyword_id' => 465,
                        'keyword_name' => 'lblSelectGoal',
                        'keyword_value' => 'Please select a goal',
                    ],
                ],
            ],
            [
                'screenID' => '27',
                'ScreenName' => 'SignUpStep6Component',
                'keyword_data' => [
                    [
                        'screenId' => '27',
                        'keyword_id' => 466,
                        'keyword_name' => 'lblWhatsYourActivityLevel',
                        'keyword_value' => 'What\'s your activity level?',
                    ],
                    [
                        'screenId' => '27',
                        'keyword_id' => 467,
                        'keyword_name' => 'lblSelectActivityLevel',
                        'keyword_value' => 'Please select an activity level',
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
