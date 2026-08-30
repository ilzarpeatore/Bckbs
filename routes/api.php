<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('register',[ API\UserController::class, 'register']);
Route::post('check-invite-code',[ API\UserController::class, 'checkInviteCode']);
Route::post('login',[ API\UserController::class, 'login']);
Route::post('forget-password',[ API\UserController::class, 'forgetPassword']);
Route::post('social-mail-login',[ API\UserController::class, 'socialMailLogin' ]);
Route::post('social-otp-login',[ API\UserController::class, 'socialOTPLogin' ]);
Route::get('user-detail',[ API\UserController::class, 'userDetail']);
Route::get('get-appsetting', [ API\UserController::class, 'getAppSetting'] );
Route::get('language-table-list',[API\LanguageTableController::class, 'getList']);
Route::get('get-macro-nutrient',[API\DashboardController::class,'getMacroNurtrient']);

    Route::get('get-setting',[ API\DashboardController::class, 'getSetting']);
    Route::get('dashboard-detail',[ API\DashboardController::class, 'dashboard']);

    Route::get('equipment-list', [ API\EquipmentController::class, 'getList' ]);

    Route::get('categorydiet-list', [ API\CategoryDietController::class, 'getList' ]);

    Route::get('workouttype-list', [ API\WorkoutTypeController::class, 'getList' ]);

    Route::get('diet-list', [ API\DietController::class, 'getList' ]);
    Route::post('diet-detail', [ API\DietController::class, 'getDetail' ]);

    Route::get('category-list', [ API\CategoryController::class, 'getList' ]);
    Route::get('tags-list', [ API\TagsController::class, 'getList' ]);

    Route::get('level-list', [ API\LevelController::class, 'getList' ]);
    
    Route::get('bodypart-list', [ API\BodyPartController::class, 'getList' ]);
    
    Route::get('workout-list', [ API\WorkoutController::class, 'getList' ]);
    Route::get('workout-detail', [ API\WorkoutController::class, 'getDetail' ]);

    Route::get('exercise-list', [ API\ExerciseController::class, 'getList' ]);
    Route::get('exercise-detail', [ API\ExerciseController::class, 'getDetail' ]);

    Route::get('post-list', [ API\PostController::class, 'getList' ]);
    Route::post('post-detail', [ API\PostController::class, 'getDetail' ]);

    Route::get('blog-category-list', [ API\BlogCategoryController::class, 'getList' ]);

    Route::get('product-list', [ API\ProductController::class, 'getlist']);
    Route::get('productcategory-list', [ API\ProductCategoryController::class, 'getlist']);
    Route::post('product-detail', [ API\ProductController::class, 'getDetail']);

    Route::get('diet-dashboard', [ API\DietController::class, 'dashboard' ]);
    Route::get('product-dashboard', [ API\ProductController::class, 'dashboard' ]);

    Route::get('workout-exercise-detail', [ API\V1\WorkoutController::class, 'exerciseDetail' ]);

    // AÑADIDO: catálogo de Packages (planes recurrentes + programas
    // individuales de pago único) visible SIN sesión para la web (webbs) --
    // reutiliza el mismo controlador/lógica que la ya-existente 'package-list'
    // autenticada (no filtra nada por usuario, solo status=active), así que
    // no hace falta duplicar código, solo un segundo nombre de ruta público.
    Route::get('package-catalog', [ API\PackageController::class, 'getList' ]);

// AÑADIDO: webhook de Stripe -- ruta pública a propósito (Stripe la llama
// directamente, no un cliente logueado con token; se verifica por firma
// Stripe-Signature dentro del controlador, no por auth:sanctum). Ver
// docs/PLAN_VENTAS_PROGRAMAS_Y_BLOG.md en el repo bsa.
Route::post('webhooks/stripe', [ API\V1\CheckoutController::class, 'stripeWebhook' ]);

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::post('update-profile', [ API\UserController::class, 'updateProfile']);
    Route::post('change-password', [ API\UserController::class, 'changePassword']);
    Route::post('update-user-status', [ API\UserController::class, 'updateUserStatus']);
    Route::post('delete-user-account', [ API\UserController::class, 'deleteUserAccount']);
    Route::get('logout',[ API\UserController::class, 'logout']);

    Route::get('payment-gateway-list', [ API\PaymentGatewayController::class, 'getList'] );

    Route::get('assign-diet-list', [ API\AssignUserController::class, 'getAssignDiet' ]);
    Route::get('assign-workout-list', [ API\AssignUserController::class, 'getAssignWorkout' ]);

    Route::get('workoutday-list', [ API\WorkoutController::class, 'workoutDayList' ]);
    Route::get('workoutday-exercise-list', [ API\WorkoutController::class, 'workoutDayExerciseList' ]);
    
    Route::get('get-favourite-diet', [ API\DietController::class, 'getUserFavouriteDiet' ]);
    Route::post('set-favourite-diet', [ API\DietController::class, 'userFavouriteDiet' ]);


    Route::get('get-favourite-workout', [ API\WorkoutController::class, 'getUserFavouriteWorkout' ]);
    Route::post('set-favourite-workout', [ API\WorkoutController::class, 'userFavouriteWorkout' ]);
    
    Route::post('store-user-exercise', [ API\ExerciseController::class, 'storeUserExercise' ]);
    Route::get('get-user-exercise', [ API\ExerciseController::class, 'getUserExercise' ]);
    

    Route::get('package-list', [ API\PackageController::class, 'getList' ]);

    Route::get('subscriptionplan-list',[ API\SubscriptionController::class, 'getList']);
    Route::post('subscribe-package',[ API\SubscriptionController::class, 'subscriptionSave']);
    Route::post('subscribe-to-package',[ API\SubscriptionController::class, 'subscribeToPackage']);
    Route::post('cancel-subscription',[ API\SubscriptionController::class, 'cancelSubscription']);



    Route::post('usergraph-save', [ API\UserGraphController::class, 'saveGraphData']);
    Route::get('usergraph-list', [ API\UserGraphController::class, 'getGraphDataList']);
    Route::post('usergraph-delete', [ API\UserGraphController::class, 'deleteGraphData']);
    Route::get('usergraph-detail', [ API\UserGraphController::class, 'getGraphDetails']);

    Route::post('notification-list', [ API\NotificationController::class, 'getList'] );
    Route::get('notification-detail', [ API\NotificationController::class, 'getNotificationDetail'] );

    Route::get('user-profile-detail',[ API\UserController::class, 'userProfileDetail']); 

    Route::get('chatgpt-fit-bot-list',[ API\ChatgptFitBotController::class, 'getList']); 
    Route::post('chatgpt-fit-bot-save',[ API\ChatgptFitBotController::class, 'store']); 
    Route::post('chatgpt-fit-bot-delete',[ API\ChatgptFitBotController::class, 'destroy']); 

    Route::get('class-schedule-list',[ API\ClassScheduleController::class, 'getList']);
    Route::post('class-schedule-plan-save',[ API\ClassScheduleController::class, 'storeClassSchedulePlan']);

    Route::post('save-score', [ API\GameDataController::class, 'saveScore']);
    Route::get('get-score', [ API\GameDataController::class, 'getScore']);
    
    Route::get('userpost-list', [ API\PostingController::class, 'getPostList']);
    Route::get('userpost-detail', [ API\PostingController::class, 'getPostDetail']);
    Route::post('save-userpost', [ API\PostingController::class, 'savePostData']);
    Route::post('update-userpost', [ API\PostingController::class, 'updatePostData']);
    Route::post('delete-userpost', [ API\PostingController::class, 'deletePostData']);
    Route::post('remove-userpost-media', [ API\PostingController::class, 'removePostMedia']);

    Route::post('like-userpost', [ API\PostingController::class, 'userLikePost']);
    Route::get('like-userpost-list', [ API\PostingController::class, 'getUserLikePostList']);
    
    Route::post('bookmark-userpost', [ API\PostingController::class, 'userBookmarkPost']);
    Route::get('my-bookmark-post-list', [ API\PostingController::class, 'getMyBookmarkPostList']);

    Route::get('comment-list', [ API\CommentController::class, 'getCommentList']);
    Route::post('save-comment', [ API\CommentController::class, 'saveComment']);
    Route::post('update-comment', [ API\CommentController::class, 'updateComment']);
    Route::post('delete-comment', [ API\CommentController::class, 'deleteComment']);
    
    Route::get('comment-reply-list', [ API\CommentReplyController::class, 'getList']);
    Route::post('save-comment-reply', [ API\CommentReplyController::class, 'saveCommentReply']);
    Route::post('delete-comment-reply', [ API\CommentReplyController::class, 'deleteCommentReply']);
    
    Route::post('report-on-posting', [ API\PostingController::class, 'reportOnPosting']);

    Route::get('user-daily-water-goal-list', [ API\UserDailyGoalController::class, 'getDailyWaterGoalList']);
    Route::post('user-daily-water-goal-save', [ API\UserDailyGoalController::class, 'saveDailyWaterGoal']);
    
    Route::get('user-daily-steps-goal-list', [ API\UserDailyGoalController::class, 'getDailyStepsGoalList']);
    Route::post('user-daily-steps-goal-save', [ API\UserDailyGoalController::class, 'saveDailyStepsGoal']);
   
    Route::group(['prefix' => 'v1'], function () {

        Route::get('assign-diet-list', [ API\AssignUserController::class, 'getAssignDietV1' ]);
        Route::get('assign-workout-list', [ API\AssignUserController::class, 'getAssignWorkoutV1' ]);

        Route::get('workoutday-exercise-list', [ API\V1\WorkoutController::class, 'upNext' ]);

        Route::post('store-user-workout-exercise', [ API\V1\WorkoutController::class, 'storeUserWorkoutExercise' ]);
        Route::get('get-user-workout-exercise', [ API\V1\WorkoutController::class, 'getUserWorkoutExercise' ]);
        Route::get('user-daily-water-goal-list', [ API\UserDailyGoalController::class, 'getV1DailyWaterGoalList']);
        Route::get('user-daily-steps-goal-list', [ API\UserDailyGoalController::class, 'getV1DailyStepGoalList']);

        Route::get('workout-template-detail', [ API\WorkoutTemplateController::class, 'getClientDetail' ]);
        Route::get('workout-template-list', [ API\WorkoutTemplateController::class, 'getClientList' ]);
        Route::post('workout-template-set-favourite', [ API\WorkoutTemplateController::class, 'toggleFavourite' ]);

        // AÑADIDO: metrics_catalog solo era accesible desde admin; el cliente
        // necesita leerlo (solo lectura) para poder construir la tabla
        // dinámica de series por ejercicio (enabled_metrics) en la app.
        Route::get('metrics-catalog-list', [ API\MetricController::class, 'getList' ]);

        // AÑADIDO: pantalla de Detalle de Ejercicio (4 pestañas).
        Route::get('exercise-detail', [ API\ExerciseInfoController::class, 'getDetail' ]);
        Route::get('exercise-analysis', [ API\ExerciseInfoController::class, 'getAnalysis' ]);
        Route::post('exercise-feedback', [ API\ExerciseFeedbackController::class, 'store' ]);

        // AÑADIDO: rutas para ClientCalendarController, que ya estaba
        // implementado pero nunca se había conectado a ninguna ruta.
        Route::get('my-calendar', [ API\ClientCalendarController::class, 'getMyMonth' ]);
        Route::get('my-calendar-day-detail', [ API\ClientCalendarController::class, 'getDayDetail' ]);
        Route::post('my-calendar-log-sets', [ API\ClientCalendarController::class, 'logSets' ]);
        Route::post('my-calendar-finish-session', [ API\ClientCalendarController::class, 'finishSession' ]);

        // AÑADIDO: readiness diario obligatorio antes de Workout Preview.
        Route::get('readiness-today', [ API\ReadinessController::class, 'today' ]);
        Route::post('readiness-store', [ API\ReadinessController::class, 'store' ]);

        // AÑADIDO: checkout de Packages desde la web (webbs) -- ver
        // docs/PLAN_VENTAS_PROGRAMAS_Y_BLOG.md en el repo bsa. Autenticado a
        // propósito (el usuario ya tiene que haber iniciado sesión antes de
        // pagar, decisión de producto explícita -- así el backend siempre
        // sabe qué usuario es en el momento del pago). El webhook que
        // confirma el pago (webhooks/stripe) es una ruta pública aparte, ver
        // fuera de este grupo auth:sanctum.
        Route::post('checkout/stripe/create-session', [ API\V1\CheckoutController::class, 'createStripeSession' ]);

        // AÑADIDO: checkout de Packages con PayPal, alternativa a Stripe --
        // mismo Package/Subscription, distinto flujo (PayPal no tiene una
        // URL de checkout hospedada como Stripe: create-order devuelve un
        // link de aprobación de paypal.com, capture-order la confirma
        // cuando el frontend recibe la vuelta desde PayPal).
        Route::post('checkout/paypal/create-order', [ API\V1\CheckoutController::class, 'createPaypalOrder' ]);
        Route::post('checkout/paypal/capture-order', [ API\V1\CheckoutController::class, 'capturePaypalOrder' ]);

        // AÑADIDO: Onboarding v2, etapas 2-4 + marcado de completado -- la
        // etapa 1 reutiliza update-profile y no vive aquí. Ver
        // docs/ONBOARDING_V2.md para el contrato completo.
        Route::prefix('onboarding')->group(function () {
            Route::post('par-q', [ API\OnboardingController::class, 'parq' ]);
            Route::post('training-questionnaire', [ API\OnboardingController::class, 'trainingQuestionnaire' ]);
            Route::post('nutrition-questionnaire', [ API\OnboardingController::class, 'nutritionQuestionnaire' ]);
            Route::post('complete', [ API\OnboardingController::class, 'complete' ]);
        });

        // AÑADIDO: borrado de cuenta -- el cliente (app) llama a esta URL
        // exacta (authApi.deleteAccount() -> POST v1/delete-account), no a
        // 'delete-user-account'. Mismo método que esa ruta antigua (se deja
        // por compatibilidad), ver docs/BORRADO_CUENTA_BACKEND.md.
        Route::post('delete-account', [ API\UserController::class, 'deleteUserAccount' ]);
    });

    Route::get('daily-plan-detail', [ API\DailyPlanController::class, 'getDailyPlanDetail' ]);
    Route::get('assigned-meals-summary', [ API\DailyPlanController::class, 'getAssignedMealsSummary' ]);
    Route::get('diet-meal-items', [ API\DietController::class, 'getDietMealItems' ]);
    Route::post('save-daily-plan-recipe', [ API\DailyPlanController::class, 'saveDailyPlanRecipeData']);
    Route::post('daily-plan-delete', [ API\DailyPlanController::class, 'deleteDailyPlan']);
    Route::post('daily-plan-recipe-delete', [ API\DailyPlanController::class, 'deleteDailyPlanRecipeData']);
    Route::post('daily-plan-recipe-delete-all', [ API\DailyPlanController::class, 'deleteDailyPlanRecipeAllData']);

    Route::get('shopping-list', [ API\ShoppingListController::class, 'getList' ]);
    Route::get('shopping-list-detail', [ API\ShoppingListController::class, 'getDetail' ]);
    Route::post('daily-plan-shopping-list-generate', [ API\ShoppingListController::class, 'generateFromDailyPlan' ]);
    Route::post('shopping-list-delete', [ API\ShoppingListController::class, 'deleteShoppingList' ]);
    Route::post('shopping-list-item-toggle', [ API\ShoppingListController::class, 'toggleItem' ]);
    Route::post('shopping-list-item-delete', [ API\ShoppingListController::class, 'deleteItem' ]);
    Route::post('shopping-list-item-add', [ API\ShoppingListController::class, 'addCustomItem' ]);
    Route::post('shopping-list-item-update', [ API\ShoppingListController::class, 'updateItem' ]);

    Route::post('set-reminder-settings',[API\UserController::class,'setReminderSetting']);
    Route::post('save-recipe-review', [ API\RecipeReviewController::class, 'saveRecipeReview']);

    Route::get('get-favourite-recipe', [ API\RecipeController::class, 'getUserFavouriteRecipe' ]);
    Route::post('set-favourite-recipe', [ API\RecipeController::class, 'saveUserFavouriteRecipe' ]);

    // ═══ V2: Forms (Check-ins) — Client API ═══════════════════════════
    Route::get('form-assigned-list', [API\FormController::class, 'getAssignedList']);
    Route::get('form-detail', [API\FormController::class, 'getDetail']);
    Route::post('form-submit', [API\FormController::class, 'submit']);
    Route::post('form-feedback', [API\FormController::class, 'leaveFeedback']);

});

Route::get('recipetag-list', [ API\RecipeTagController::class, 'getList' ]);
Route::get('recipecategory-list', [ API\RecipeCategoryController::class, 'getList' ]);

Route::get('recipe-filter-list', [ API\RecipeController::class, 'getRecipeFilterList' ]);
Route::get('recipe-detail/{id}', [ API\RecipeController::class, 'recipeDetail']);

Route::get('recipe-review-detail', [ API\RecipeReviewController::class, 'getReviewDetail']);

Route::get('get-measurementunit', [ API\ShoppingListController::class, 'getMeasurementUnit' ]);

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Routes for the Next.js admin panel. Protected by sanctum + admin role.
|
*/

use App\Http\Controllers\API\Admin\AuthController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\UserController as AdminUserController;
use App\Http\Controllers\API\Admin\SubAdminController;
use App\Http\Controllers\API\Admin\EquipmentController as AdminEquipmentController;
use App\Http\Controllers\API\Admin\WorkoutTypeController as AdminWorkoutTypeController;
use App\Http\Controllers\API\Admin\LevelController as AdminLevelController;
use App\Http\Controllers\API\Admin\BodyPartController as AdminBodyPartController;
use App\Http\Controllers\API\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\API\Admin\TagsController as AdminTagsController;
use App\Http\Controllers\API\Admin\CategoryDietController as AdminCategoryDietController;
use App\Http\Controllers\API\Admin\DietController as AdminDietController;
use App\Http\Controllers\API\Admin\ExerciseController as AdminExerciseController;
use App\Http\Controllers\API\Admin\WorkoutController as AdminWorkoutController;
use App\Http\Controllers\API\Admin\RecipeController as AdminRecipeController;
use App\Http\Controllers\API\Admin\RecipeCategoryController as AdminRecipeCategoryController;
use App\Http\Controllers\API\Admin\RecipeTagController as AdminRecipeTagController;
use App\Http\Controllers\API\Admin\IngredientController as AdminIngredientController;
use App\Http\Controllers\API\Admin\IngredientCategoryController as AdminIngredientCategoryController;
use App\Http\Controllers\API\Admin\MeasurementUnitController as AdminMeasurementUnitController;
use App\Http\Controllers\API\Admin\IngredientUnitConversionController;
use App\Http\Controllers\API\Admin\ProductController as AdminProductController;
use App\Http\Controllers\API\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\API\Admin\PostController as AdminPostController;
use App\Http\Controllers\API\Admin\BlogCategoryController as AdminBlogCategoryController;
use App\Http\Controllers\API\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\API\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\API\Admin\PersonalClientInviteController;
use App\Http\Controllers\API\Admin\QuotesController;
use App\Http\Controllers\API\Admin\BannerSliderController;
use App\Http\Controllers\API\Admin\ClassScheduleController;
use App\Http\Controllers\API\Admin\PushNotificationController;
use App\Http\Controllers\API\Admin\PostingController;
use App\Http\Controllers\API\Admin\LanguageController;
use App\Http\Controllers\API\Admin\LanguageKeywordController;
use App\Http\Controllers\API\Admin\ScreenController;
use App\Http\Controllers\API\Admin\DefaultKeywordController;
use App\Http\Controllers\API\Admin\RoleController;
use App\Http\Controllers\API\Admin\PermissionController;
use App\Http\Controllers\API\Admin\SettingController;
use App\Http\Controllers\API\Admin\AdminLoginHistoryController;
use App\Http\Controllers\API\Admin\AdminLoginDeviceController;
use App\Http\Controllers\API\Admin\AssignController;
use App\Http\Controllers\API\Admin\ClientMealPlanController;
use App\Http\Controllers\API\Admin\MealPlanTemplateController;
use App\Http\Controllers\API\Admin\DietMealItemController;

// Public admin routes (login)
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Protected admin routes (auth:sanctum + admin role)
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.api'])->group(function () {

    // Auth
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Users
    Route::apiResource('users', AdminUserController::class);
    Route::get('users-graph', [AdminUserController::class, 'graph']);

    // Sub Admins
    Route::apiResource('sub-admins', SubAdminController::class);

    // Fitness Content
    Route::apiResource('equipment', AdminEquipmentController::class);
    Route::apiResource('workout-types', AdminWorkoutTypeController::class);
    Route::apiResource('levels', AdminLevelController::class);
    Route::apiResource('body-parts', AdminBodyPartController::class);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('tags', AdminTagsController::class);

    // Diet & Nutrition
    Route::apiResource('diet-categories', AdminCategoryDietController::class);
    Route::apiResource('diets', AdminDietController::class);
    Route::get('diets/{id}/meal-items', [DietMealItemController::class, 'index']);
    Route::post('diets/{id}/meal-items', [DietMealItemController::class, 'store']);
    Route::delete('diet-meal-items/{itemId}', [DietMealItemController::class, 'destroy']);

    // Exercises & Workouts
    Route::apiResource('exercises', AdminExerciseController::class);
    Route::apiResource('workouts', AdminWorkoutController::class);

    // Workout detail + day/block/exercise management
    Route::get('workout-detail', [AdminWorkoutController::class, 'getDetail']);
    Route::post('workout-day-store', [AdminWorkoutController::class, 'storeDay']);
    Route::post('workout-day-update', [AdminWorkoutController::class, 'updateDay']);
    Route::post('workout-day-delete', [AdminWorkoutController::class, 'destroyDay']);
    Route::post('workout-block-store', [AdminWorkoutController::class, 'storeBlock']);
    Route::post('workout-block-update', [AdminWorkoutController::class, 'updateBlock']);
    Route::post('workout-block-delete', [AdminWorkoutController::class, 'destroyBlock']);
    Route::post('workout-exercise-store', [AdminWorkoutController::class, 'storeExercise']);
    Route::post('workout-exercise-update', [AdminWorkoutController::class, 'updateExercise']);
    Route::post('workout-exercise-delete', [AdminWorkoutController::class, 'destroyExercise']);

    // Recipes
    Route::apiResource('recipes', AdminRecipeController::class);
    Route::apiResource('recipe-categories', AdminRecipeCategoryController::class);
    Route::apiResource('recipe-tags', AdminRecipeTagController::class);

    // ═══ Recipe Ingredients ═════════════════════════════════════════════
    Route::get('recipe-ingredients', [API\Admin\RecipeIngredientController::class, 'getList']);
    Route::post('recipe-ingredients-save', [API\Admin\RecipeIngredientController::class, 'store']);
    Route::post('recipe-ingredients-delete', [API\Admin\RecipeIngredientController::class, 'destroy']);

    Route::apiResource('ingredients', AdminIngredientController::class);
    Route::apiResource('ingredient-categories', AdminIngredientCategoryController::class);
    Route::apiResource('measurement-units', AdminMeasurementUnitController::class);
    Route::apiResource('unit-conversions', IngredientUnitConversionController::class);

    // Products
    Route::apiResource('products', AdminProductController::class);
    Route::apiResource('product-categories', AdminProductCategoryController::class);

    // Blog Posts
    Route::apiResource('posts', AdminPostController::class);
    Route::post('posts/{id}/cover-image', [AdminPostController::class, 'uploadCoverImage']);

    // Blog Categories
    Route::apiResource('blog-categories', AdminBlogCategoryController::class);

    // Packages & Subscriptions
    Route::apiResource('packages', AdminPackageController::class);
    Route::apiResource('subscriptions', AdminSubscriptionController::class)->only(['index', 'show']);
    Route::post('subscriptions-grant-package', [AdminSubscriptionController::class, 'grantPackage']);

    // Códigos de invitación de cliente personal (Niveles de acceso, 2026-07-30)
    Route::get('personal-client-invites', [PersonalClientInviteController::class, 'index']);
    Route::post('personal-client-invites', [PersonalClientInviteController::class, 'store']);
    Route::delete('personal-client-invites/{id}', [PersonalClientInviteController::class, 'destroy']);

    // Quotes
    Route::apiResource('quotes', QuotesController::class);

    // Banner Sliders
    Route::apiResource('banner-sliders', BannerSliderController::class);

    // Class Schedules
    Route::apiResource('class-schedules', ClassScheduleController::class);

    // Push Notifications
    Route::apiResource('push-notifications', PushNotificationController::class);

    // Community Postings
    Route::apiResource('postings', PostingController::class)->only(['index', 'show']);
    Route::get('reported-postings', [PostingController::class, 'reportList']);
    Route::post('postings/{id}/status', [PostingController::class, 'updateStatus']);

    // Languages
    Route::apiResource('languages', LanguageController::class);
    Route::get('language-keywords', [LanguageKeywordController::class, 'index']);
    Route::post('language-keywords/bulk-update', [LanguageKeywordController::class, 'bulkUpdate']);
    Route::apiResource('screens', ScreenController::class);
    Route::apiResource('default-keywords', DefaultKeywordController::class);

    // Security
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('admin-login-history', [AdminLoginHistoryController::class, 'index']);
    Route::apiResource('admin-login-devices', AdminLoginDeviceController::class)->only(['index', 'destroy']);

    // Assignments
    Route::get('assign-diet', [AssignController::class, 'assignDietList']);
    Route::post('assign-diet', [AssignController::class, 'assignDietStore']);
    Route::delete('assign-diet/{id}', [AssignController::class, 'assignDietDestroy']);
    Route::get('assign-workout', [AssignController::class, 'assignWorkoutList']);
    Route::post('assign-workout', [AssignController::class, 'assignWorkoutStore']);
    Route::delete('assign-workout/{id}', [AssignController::class, 'assignWorkoutDestroy']);

    // Client meal calendar (per-day recipe assignment, extends the same DailyPlan/DailyPlanRecipe
    // tables the client app uses in Plan Screen — not a separate assignment model).
    Route::get('client-meal-calendar', [ClientMealPlanController::class, 'getCalendar']);
    Route::post('client-meal-calendar/assign', [ClientMealPlanController::class, 'assignRecipe']);
    Route::delete('client-meal-calendar/{id}', [ClientMealPlanController::class, 'removeAssignedRecipe']);

    // Reusable meal plan templates (build once, import onto any client's calendar)
    Route::get('meal-plan-templates', [MealPlanTemplateController::class, 'index']);
    Route::post('meal-plan-templates', [MealPlanTemplateController::class, 'store']);
    Route::get('meal-plan-templates/{id}', [MealPlanTemplateController::class, 'show']);
    Route::delete('meal-plan-templates/{id}', [MealPlanTemplateController::class, 'destroy']);
    Route::post('meal-plan-templates/{id}/items', [MealPlanTemplateController::class, 'addItem']);
    Route::delete('meal-plan-template-items/{itemId}', [MealPlanTemplateController::class, 'removeItem']);
    Route::post('meal-plan-templates/{id}/export-from-calendar', [MealPlanTemplateController::class, 'exportFromCalendar']);
    Route::post('meal-plan-templates/{id}/import-to-calendar', [MealPlanTemplateController::class, 'importToCalendar']);

    // Settings
    Route::get('settings', [SettingController::class, 'getSettings']);
    Route::post('settings', [SettingController::class, 'updateSettings']);
    Route::get('app-settings', [SettingController::class, 'getAppSettings']);
    Route::post('app-settings', [SettingController::class, 'updateAppSettings']);

    // ═══ V2: Sections (reusable exercise block library) ═══════════════
    Route::get('section-template-list', [API\SectionTemplateController::class, 'getList']);
    Route::get('section-template-detail', [API\SectionTemplateController::class, 'getDetail']);
    Route::post('section-template-store', [API\SectionTemplateController::class, 'store']);
    Route::post('section-template-update', [API\SectionTemplateController::class, 'update']);
    Route::post('section-template-delete', [API\SectionTemplateController::class, 'destroy']);
    Route::post('section-template-exercise-save', [API\SectionTemplateController::class, 'saveExercise']);
    Route::post('section-template-exercise-update-field', [API\SectionTemplateController::class, 'updatePrescribedField']);
    Route::post('section-template-exercise-delete', [API\SectionTemplateController::class, 'deleteExercise']);

    // ═══ V2: Workout Templates ════════════════════════════════════════
    Route::get('workout-template-list', [API\WorkoutTemplateController::class, 'getList']);
    Route::get('workout-template-detail', [API\WorkoutTemplateController::class, 'getDetail']);
    Route::post('workout-template-store', [API\WorkoutTemplateController::class, 'store']);
    Route::post('workout-template-update', [API\WorkoutTemplateController::class, 'update']);
    Route::post('workout-template-delete', [API\WorkoutTemplateController::class, 'destroy']);
    Route::post('workout-template-block-store', [API\WorkoutTemplateController::class, 'storeBlock']);
    Route::post('workout-template-import-section', [API\WorkoutTemplateController::class, 'importSection']);
    Route::post('workout-template-block-update', [API\WorkoutTemplateController::class, 'updateBlock']);
    Route::post('workout-template-block-delete', [API\WorkoutTemplateController::class, 'destroyBlock']);
    Route::post('workout-template-exercise-save', [API\WorkoutTemplateController::class, 'saveExercise']);
    Route::post('workout-template-exercise-update-field', [API\WorkoutTemplateController::class, 'updatePrescribedField']);
    Route::post('workout-template-exercise-delete', [API\WorkoutTemplateController::class, 'deleteExercise']);
    Route::post('workout-template-exercise-notes', [API\WorkoutTemplateController::class, 'updateExerciseNotes']);
    Route::post('workout-template-block-instructions', [API\WorkoutTemplateController::class, 'updateBlockInstructions']);

    // ═══ V2: Training Programs ════════════════════════════════════════
    Route::get('training-program-list', [API\TrainingProgramController::class, 'getList']);
    Route::get('training-program-detail', [API\TrainingProgramController::class, 'getDetail']);
    Route::post('training-program-store', [API\TrainingProgramController::class, 'store']);
    Route::post('training-program-update', [API\TrainingProgramController::class, 'update']);
    Route::post('training-program-delete', [API\TrainingProgramController::class, 'destroy']);
    Route::post('training-program-generate-weeks', [API\TrainingProgramController::class, 'generateWeeks']);
    Route::post('training-program-assign-client', [API\TrainingProgramController::class, 'assignClient']);
    Route::post('training-program-remove-assignment', [API\TrainingProgramController::class, 'removeAssignment']);
    Route::get('training-program-assignments', [API\TrainingProgramController::class, 'getAssignments']);

    // ═══ V2: Program Calendar (abstract weeks) ════════════════════════
    Route::get('program-calendar', [API\ProgramCalendarController::class, 'getCalendar']);
    Route::post('program-calendar-assign-day', [API\ProgramCalendarController::class, 'assignDay']);
    Route::post('program-calendar-generate-weeks', [API\ProgramCalendarController::class, 'generateWeeks']);

    // ═══ V2: Real Calendar (month mode + weeks grid) ═══════════════════
    Route::get('real-calendar-data', [API\RealCalendarController::class, 'getMonth']);
    Route::post('real-calendar-assign', [API\RealCalendarController::class, 'assignDate']);
    Route::post('real-calendar-remove', [API\RealCalendarController::class, 'removeAssignment']);
    Route::post('real-calendar-move', [API\RealCalendarController::class, 'moveAssignment']);
    Route::get('real-calendar-weeks-grid', [API\RealCalendarController::class, 'getWeeksGrid']);
    Route::post('real-calendar-assign-week-day', [API\RealCalendarController::class, 'assignWeekDay']);
    Route::post('real-calendar-move-week-day', [API\RealCalendarController::class, 'moveWeekDay']);
    Route::post('real-calendar-duplicate-week-day', [API\RealCalendarController::class, 'duplicateWeekDay']);
    Route::post('real-calendar-duplicate-week', [API\RealCalendarController::class, 'duplicateWeek']);
    Route::post('real-calendar-clear-week', [API\RealCalendarController::class, 'clearWeek']);
    Route::post('real-calendar-swap-weeks', [API\RealCalendarController::class, 'swapWeeks']);

    // ═══ V2: Client Calendar (merged view) ════════════════════════════
    Route::get('client-calendar-data', [API\ClientProfileCalendarController::class, 'getMergedMonth']);
    Route::post('client-calendar-assign-direct', [API\ClientProfileCalendarController::class, 'assignDirect']);
    Route::post('client-calendar-import-program', [API\ClientProfileCalendarController::class, 'importProgram']);
    Route::post('client-calendar-remove', [API\ClientProfileCalendarController::class, 'removeAssignment']);
    Route::get('client-session-feedback', [API\ClientProfileCalendarController::class, 'getSessionFeedback']);

    // ═══ V2: Session Detail ═══════════════════════════════════════════
    Route::get('session-detail', [API\SessionDetailController::class, 'getSessionDetail']);
    Route::post('session-detail-duplicate', [API\SessionDetailController::class, 'duplicateToDate']);
    Route::post('session-detail-update-override-field', [API\SessionDetailController::class, 'updatePrescribedOverride']);
    Route::post('session-detail-update-override-notes', [API\SessionDetailController::class, 'updateOverrideNotes']);
    Route::post('session-detail-add-block', [API\SessionDetailController::class, 'addBlock']);
    Route::post('session-detail-add-exercise', [API\SessionDetailController::class, 'addExercise']);
    Route::post('session-detail-remove-exercise', [API\SessionDetailController::class, 'removeExercise']);
    Route::post('session-detail-batch-update-overrides', [API\SessionDetailController::class, 'batchUpdateOverrides']);

    // ═══ V2: Client Tags ══════════════════════════════════════════════
    Route::get('client-tag-list', [API\ClientTagController::class, 'getList']);
    Route::post('client-tag-store', [API\ClientTagController::class, 'store']);
    Route::post('client-tag-delete', [API\ClientTagController::class, 'destroy']);
    Route::get('client-tags-of-client', [API\ClientTagController::class, 'getClientTags']);
    Route::post('client-tag-assign', [API\ClientTagController::class, 'assignToClient']);
    Route::post('client-tag-remove', [API\ClientTagController::class, 'removeFromClient']);
    Route::get('clients-filter-by-tags', [API\ClientTagController::class, 'filterClientsByTags']);

    // ═══ V2: Resources ════════════════════════════════════════════════
    Route::get('resource-list', [API\ResourceController::class, 'getList']);
    Route::get('resource-detail', [API\ResourceController::class, 'getDetail']);
    Route::post('resource-store', [API\ResourceController::class, 'store']);
    Route::post('resource-update', [API\ResourceController::class, 'update']);
    Route::post('resource-delete', [API\ResourceController::class, 'destroy']);

    // ═══ V2: Habits ═══════════════════════════════════════════════════
    Route::get('habit-list', [API\HabitController::class, 'getList']);
    Route::post('habit-log', [API\HabitController::class, 'logHabit']);
    Route::post('habit-store', [API\HabitController::class, 'store']);
    Route::post('habit-delete', [API\HabitController::class, 'destroy']);

    // ═══ V2: Forms (Check-ins) — Admin API ════════════════════════════
    Route::get('admin-form-list', [API\Admin\FormController::class, 'getList']);
    Route::get('admin-form-assigned-list', [API\Admin\FormController::class, 'getAssignedList']);
    Route::get('admin-form-detail', [API\Admin\FormController::class, 'getDetail']);
    Route::post('admin-form-store', [API\Admin\FormController::class, 'store']);
    Route::post('admin-form-delete', [API\Admin\FormController::class, 'destroy']);
    Route::post('admin-form-question-store', [API\Admin\FormController::class, 'storeQuestion']);
    Route::post('admin-form-question-delete', [API\Admin\FormController::class, 'deleteQuestion']);
    Route::get('admin-form-submission-list', [API\Admin\FormController::class, 'getSubmissionList']);
    Route::post('admin-form-feedback', [API\Admin\FormController::class, 'leaveFeedback']);
    Route::post('admin-form-assign', [API\Admin\FormController::class, 'assign']);

    // ═══ V2: Challenges ═══════════════════════════════════════════════
    Route::get('challenge-list', [API\ChallengeController::class, 'getList']);
    Route::get('challenge-leaderboard', [API\ChallengeController::class, 'getLeaderboard']);
    Route::post('challenge-store', [API\ChallengeController::class, 'store']);
    Route::post('challenge-update-score', [API\ChallengeController::class, 'updateScore']);

    // ═══ V2: Exercise History / PRs ═══════════════════════════════════
    Route::get('exercise-history', [API\PersonalRecordController::class, 'getExerciseHistory']);
    Route::get('exercise-last-performance', [API\PersonalRecordController::class, 'getLastPerformance']);

    // ═══ V2: Exercise History Metrics (drill-down) ════════════════════
    Route::get('exercise-available-metrics', [API\ExerciseHistoryController::class, 'getAvailableMetrics']);
    Route::get('exercise-metric-history', [API\ExerciseHistoryController::class, 'getMetricHistory']);

    // ═══ V2: Workout Session Review ═══════════════════════════════════
    Route::post('workout-session-review-store', [API\WorkoutSessionReviewController::class, 'store']);
    Route::get('workout-session-review-list', [API\WorkoutSessionReviewController::class, 'getList']);

    // ═══ V2: Metrics ══════════════════════════════════════════════════
    Route::get('metric-list', [API\MetricController::class, 'getList']);
    Route::post('metric-store', [API\MetricController::class, 'store']);
    Route::post('metric-update', [API\MetricController::class, 'update']);

    // ═══ V2: Client Notes ══════════════════════════════════════════════
    Route::get('client-note-list', [API\Admin\ClientNoteController::class, 'getList']);
    Route::post('client-note-store', [API\Admin\ClientNoteController::class, 'store']);
    Route::post('client-note-update', [API\Admin\ClientNoteController::class, 'update']);
    Route::post('client-note-delete', [API\Admin\ClientNoteController::class, 'destroy']);

    // ═══ V2: Progress Photos ══════════════════════════════════════════
    Route::get('progress-photo-list', [API\Admin\ProgressPhotoController::class, 'getList']);
    Route::post('progress-photo-store', [API\Admin\ProgressPhotoController::class, 'store']);
    Route::post('progress-photo-delete', [API\Admin\ProgressPhotoController::class, 'destroy']);

    // ═══ V2: Tasks ════════════════════════════════════════════════════
    Route::get('task-list', [API\Admin\TaskController::class, 'getList']);
    Route::post('task-store', [API\Admin\TaskController::class, 'store']);
    Route::post('task-update', [API\Admin\TaskController::class, 'update']);
    Route::post('task-delete', [API\Admin\TaskController::class, 'destroy']);

    // ═══ V2: Client Feature Settings ══════════════════════════════════
    Route::get('client-feature-settings', [API\ClientFeatureSettingController::class, 'getMySettings']);
    Route::post('client-feature-settings-update', [API\ClientFeatureSettingController::class, 'update']);

    // ═══ V2: Client Goals ═════════════════════════════════════════════
    Route::get('client-goal-list', [API\Admin\ClientGoalController::class, 'getList']);
    Route::post('client-goal-store', [API\Admin\ClientGoalController::class, 'store']);
    Route::post('client-goal-update', [API\Admin\ClientGoalController::class, 'update']);
    Route::post('client-goal-delete', [API\Admin\ClientGoalController::class, 'destroy']);

    // ═══ V2: Client Limitations ═══════════════════════════════════════
    Route::get('client-limitation-list', [API\Admin\ClientLimitationController::class, 'getList']);
    Route::post('client-limitation-store', [API\Admin\ClientLimitationController::class, 'store']);
    Route::post('client-limitation-update', [API\Admin\ClientLimitationController::class, 'update']);
    Route::post('client-limitation-delete', [API\Admin\ClientLimitationController::class, 'destroy']);

    // ═══ V2: Client Body Metrics ══════════════════════════════════════
    Route::get('client-body-metric-list', [API\Admin\ClientBodyMetricController::class, 'getList']);
    Route::get('client-body-metric-chart', [API\Admin\ClientBodyMetricController::class, 'getChartData']);
    Route::post('client-body-metric-store', [API\Admin\ClientBodyMetricController::class, 'store']);
    Route::post('client-body-metric-update', [API\Admin\ClientBodyMetricController::class, 'update']);
    Route::post('client-body-metric-delete', [API\Admin\ClientBodyMetricController::class, 'destroy']);

    // ═══ V2: Admin Resources ══════════════════════════════════════════
    Route::get('admin-resource-list', [API\Admin\ResourceController::class, 'getList']);
    Route::get('admin-resource-detail', [API\Admin\ResourceController::class, 'getDetail']);
    Route::post('admin-resource-store', [API\Admin\ResourceController::class, 'store']);
    Route::post('admin-resource-update', [API\Admin\ResourceController::class, 'update']);
    Route::post('admin-resource-delete', [API\Admin\ResourceController::class, 'destroy']);

    // ═══ V2: Onboarding ═══════════════════════════════════════════════
    Route::get('admin-onboarding-list', [API\Admin\OnboardingController::class, 'getList']);
    Route::get('admin-onboarding-detail', [API\Admin\OnboardingController::class, 'getDetail']);
});
