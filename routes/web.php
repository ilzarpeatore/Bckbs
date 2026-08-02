<?php

// Controllers

use App\Http\Controllers\AdminLoginDeviceController;
use App\Http\Controllers\AdminLoginHistoryController;
use App\Http\Controllers\BannerSliderController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Security\RolePermission;
use App\Http\Controllers\Security\RoleController;
use App\Http\Controllers\Security\PermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\LanguageController;

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Artisan;
// Packages
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\CategoryDietController;
use App\Http\Controllers\WorkoutTypeController;
use App\Http\Controllers\DietController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\BodyPartController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PackageController;

use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;

use App\Http\Controllers\PushNotificationController;

use App\Http\Controllers\SubscriptionController;

use App\Http\Controllers\QuotesController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\DefaultkeywordController;
use App\Http\Controllers\LanguageListController;
use App\Http\Controllers\LanguageWithKeywordListController;
use App\Http\Controllers\SubAdminController;

use App\Http\Controllers\PostingController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\IngredientCategoryController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\IngredientUnitConversionController;
use App\Http\Controllers\RecipeCategoryContoller;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeTagContoller;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

require __DIR__.'/auth.php';

Route::get('/storage', function () {
    Artisan::call('storage:link');
});

Route::get('optimize', function () {
    Artisan::call('optimize:clear');
});

use Illuminate\Support\Facades\Schema;
Route::get('migrate', function(){
    try {
        // check user table exist or not
        $schema = Schema::hasTable('users');
        // Run migrations
        Artisan::call('migrate', ['--force' => true]);

        // if users table not exit than run seeder command
        if( !$schema ) {
            // Run seeders
            Artisan::call('db:seed', ['--force' => true]);
        }

        return redirect()->route('dashboard');
    } catch (\Exception $e) {
        return 'Migration failed: ' . $e->getMessage();
    }
});

Route::get('language/{locale}', [ HomeController::class, 'changeLanguage'])->name('change.language');

Route::group(['middleware' => [ 'auth', 'useractive' ]], function () {
    // Dashboard Routes
    Route::get('/', [HomeController::class, 'index']);

  Route::group(['prefix' => 'admin'], function () {
    // Permission Module
    Route::resource('permission', PermissionController::class);
    Route::get('permission/add/{type}',[ PermissionController::class, 'addPermission' ])->name('permission.add');
    Route::post('permission/save',[ PermissionController::class, 'savePermission' ])->name('permission.save');

	Route::resource('role', RoleController::class);

    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
	Route::get('changeStatus', [ HomeController::class, 'changeStatus'])->name('changeStatus');

    // Users Module
    Route::resource('users', UserController::class);
    Route::get('download-user-report/{fileType?}', [UserController::class, 'downloadUserReport'])->where('fileType', 'xlsx|xls|csv|ods|html')->name('download.user.report');
    Route::get('download-user-report-pdf', [UserController::class, 'downloadUserReportPdf'])->name('download.user.report.pdf');
    Route::get('users/{id}/{tab?}/', [UserController::class, 'show'])->name('users.show');

    Route::resource('equipment', EquipmentController::class);

    Route::resource('subadmin', SubAdminController::class);

    Route::get('users-graph',[ UserController::class, 'fetchUserGraph' ])->name('user.fetchGraph');

    //assign deit
    Route::get('assigndiet/{user_id}',[ UserController::class, 'assignDietForm' ])->name('add.assigndiet');
    Route::post('assigndiet',[ UserController::class, 'assignDietSave' ])->name('save.assigndiet');
    Route::post('assigndiet-delete',[ UserController::class, 'assignDietDestroy' ])->name('delete.assigndiet');

    Route::get('assigndiet-list',[ UserController::class, 'getAssignDietList'])->name('get.assigndietlist');
    //assign workout
    Route::get('assignworkout/{user_id}',[ UserController::class, 'assignWorkoutForm' ])->name('add.assignworkout');
    Route::post('assignworkout',[ UserController::class, 'assignWorkoutSave' ])->name('save.assignworkout');
    Route::post('assignworkout-delete',[ UserController::class, 'assignWorkoutDestroy' ])->name('delete.assignworkout');

    Route::get('assignworkout-list',[ UserController::class, 'getAssignWorkoutList'])->name('get.assignworkoutlist');

    //Fitness CategoryDiet
    Route::resource('categorydiet', CategoryDietController::class);

    //Fitness Workout
    Route::resource('workouttype', WorkoutTypeController::class);

    Route::resource('diet', DietController::class);
    Route::resource('category', CategoryController::class);

    //FitnessTags
    Route::resource('tags', TagsController::class);
    //Fitnessleval
    Route::resource('level', LevelController::class);

    Route::resource('bodypart', BodyPartController::class);

    Route::resource('exercise', ExerciseController::class);

    Route::resource('workout', WorkoutController::class);

    Route::post('workoutdays-exercise-delete', [ WorkoutController::class , 'workoutDaysExerciseDelete'])->name('workoutdays.exercise.delete');

    Route::resource('post', PostController::class);

    //product
    Route::resource('product',ProductController::class);
    Route::resource('productcategory',ProductCategoryController::class);

    Route::resource('package',PackageController::class);

    Route::post('remove-file',[ HomeController::class, 'removeFile' ])->name('remove.file');

    Route::get('setting/{page?}', [ SettingController::class, 'settings'])->name('setting.index');
    Route::post('layout-page', [ SettingController::class, 'layoutPage'])->name('layout_page');
    Route::post('settings/save', [ SettingController::class , 'settingsUpdates'])->name('settingsUpdates');
    Route::post('mobile-config-save',[ SettingController::class , 'settingUpdate'])->name('settingUpdate');
	Route::post('env-setting', [ SettingController::class , 'envChanges'])->name('envSetting');
    Route::post('payment-settings/save',[ SettingController::class , 'paymentSettingsUpdate'])->name('paymentSettingsUpdate');
    Route::post('subscription-settings/save',[ SettingController::class , 'subscriptionSettingsUpdate'])->name('subscriptionSettingsUpdate');
    Route::post('login-enable-setting/save',[ SettingController::class, 'loginEnableSettingsUpdate'])->name('login.enable.setting.save');

    Route::post('get-lang-file', [ LanguageController::class, 'getFile' ] )->name('getLanguageFile');
    Route::post('save-lang-file', [ LanguageController::class, 'saveFileContent' ] )->name('saveLangContent');

    Route::post('update-profile', [ SettingController::class , 'updateProfile'])->name('updateProfile');
    Route::post('change-password', [ SettingController::class , 'changePassword'])->name('changePassword');

    Route::get('pages/term-condition',[ SettingController::class, 'termAndCondition'])->name('pages.term_condition');
    Route::post('term-condition-save',[ SettingController::class, 'saveTermAndCondition'])->name('pages.term_condition_save');

    Route::get('pages/privacy-policy',[ SettingController::class, 'privacyPolicy'])->name('pages.privacy_policy');
    Route::post('privacy-policy-save',[ SettingController::class, 'savePrivacyPolicy'])->name('pages.privacy_policy_save');

    Route::post('mail-alert-settings/save',[ SettingController::class , 'mailAlertSettingsUpdate'])->name('mailAlertSettingsUpdate');

    Route::resource('pushnotification', PushNotificationController::class);
    Route::get('resend-pushnotification/{id}',[ PushNotificationController::class, 'edit'])->name('resend.pushnotification');

    Route::resource('subscription', SubscriptionController::class);

    Route::resource('quotes', QuotesController::class);

    Route::resource('classschedule', ClassScheduleController::class);
    Route::resource('bannerslider', BannerSliderController::class);

    // Language Setting Route
    Route::resource('screen', ScreenController::class);
    Route::resource('defaultkeyword', DefaultkeywordController::class);
    Route::resource('languagelist', LanguageListController::class);
    Route::resource('languagewithkeyword', LanguageWithKeywordListController::class);
    Route::get('download-language-with-keyword-list', [ LanguageWithKeywordListController::class, 'downloadLanguageWithKeywordList'])->name('download.language.with,keyword.list');

    Route::post('import-language-keyword', [ LanguageWithKeywordListController::class,'importlanguagewithkeyword' ])->name('import.languagewithkeyword');
    Route::get('bulklanguagedata', [ LanguageWithKeywordListController::class,'bulklanguagedata' ])->name('bulk.language.data');
    Route::get('help', [ LanguageWithKeywordListController::class,'help' ])->name('help');
    Route::get('download-template', [ LanguageWithKeywordListController::class,'downloadtemplate' ])->name('download.template');

    Route::resource('posting', PostingController::class);
    Route::get('reported-posting', [ PostingController::class, 'reportPostingList' ])->name('posting.reported');

    Route::post('save-comment-reply', [ CommunityController::class, 'saveCommentReply' ])->name('save.comment.reply');
    Route::get('edit-comment-reply/{id}', [ CommunityController::class, 'editCommentReply' ])->name('edit.comment.reply');
    Route::post('delete-comment/{id}', [ CommunityController::class, 'deleteComment' ])->name('delete.comment');
    Route::post('delete-comment-reply/{id}', [ CommunityController::class, 'deleteCommentReply' ])->name('delete.comment.reply');

    Route::resource('admin-login-history', AdminLoginHistoryController::class);
    Route::resource('admin-login-device', AdminLoginDeviceController::class);
    Route::get('admin/device/logout/{id}',[ AdminLoginDeviceController::class, 'logoutDevice' ])->name('admin.device.logout');

    Route::resource('recipe-category', RecipeCategoryContoller::class);
    Route::resource('recipe-tag', RecipeTagContoller::class);

    Route::resource('ingredientcategory', IngredientCategoryController::class);
    Route::resource('ingredient', IngredientController::class);
    Route::resource('measurementunit', MeasurementUnitController::class);
    Route::resource('ingredient-unit-conversion', IngredientUnitConversionController::class);

    Route::resource('recipe', RecipeController::class);
    Route::post('recipe/steps/reorder', [RecipeController::class, 'reorderSteps'])->name('recipe.steps.reorder');  
  });
});
Route::get('/ajax-list',[ HomeController::class, 'getAjaxList' ])->name('ajax-list');

//Auth pages Routs
Route::group(['prefix' => 'auth'], function() {
    Route::get('signin', [HomeController::class, 'signin'])->name('auth.signin');
    Route::get('signup', [HomeController::class, 'signup'])->name('auth.signup');
    Route::get('confirmmail', [HomeController::class, 'confirmmail'])->name('auth.confirmmail');
    Route::get('lockscreen', [HomeController::class, 'lockscreen'])->name('auth.lockscreen');
    Route::get('recover-password', [HomeController::class, 'recoverpw'])->name('auth.recover-password');
    Route::get('userprivacysetting', [HomeController::class, 'userprivacysetting'])->name('auth.userprivacysetting');
});

//Error Page Route
Route::group(['prefix' => 'errors'], function() {
    Route::get('error404', [HomeController::class, 'error404'])->name('errors.error404');
    Route::get('error500', [HomeController::class, 'error500'])->name('errors.error500');
    Route::get('maintenance', [HomeController::class, 'maintenance'])->name('errors.maintenance');
});

//Extra Page Route
Route::get('privacy-policy', [HomeController::class, 'privacyPolicy'])->name('privacy.policy');
Route::get('terms-condition', [HomeController::class, 'termsCondition'])->name('terms.condition');

Route::post('post-delete/{id}',[ CommunityController::class, 'deletePosting'])->name('postdelete');

Route::get('post-report',[ CommunityController::class, 'reportPosting'])->name('postreport');
Route::get('post-comment',[ CommunityController::class, 'postComment'])->name('post.comment');
Route::get('comment-reply',[ CommunityController::class, 'commentReply'])->name('comment.reply');
Route::post('report-posting', [CommunityController::class, 'reportOnPosting'])->name('posting.report');
Route::get('post-likes', [CommunityController::class, 'postLikes'])->name('postlikes');
