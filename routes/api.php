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

// Auditoría de seguridad 2026-08-26: throttle explícito en rutas de auth
// (antes solo dependían del throttle:api genérico del Kernel) — mitiga
// fuerza bruta / credential stuffing en login, registro y recuperación.
Route::middleware('throttle:6,1')->group(function () {
    Route::post('login',[ API\UserController::class, 'login']);
    Route::post('forget-password',[ API\UserController::class, 'forgetPassword']);
    Route::post('social-mail-login',[ API\UserController::class, 'socialMailLogin' ]);
    Route::post('social-otp-login',[ API\UserController::class, 'socialOTPLogin' ]);
});
// Packs vendidos en la web (docs/PACKS_WEB.md): públicos, sin cuenta.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('pack-catalog', [ API\PackController::class, 'catalog' ]);
    Route::get('pack-detail', [ API\PackController::class, 'detail' ]);
    Route::get('pack-checkout-status', [ API\PackController::class, 'checkoutStatus' ]);
});
// Por visitante real (la web llama desde su servidor; ver App\Support\WebClient).
Route::middleware('throttle:web-forms')->post('pack-checkout', [ API\PackController::class, 'checkout' ]);

// Marketing de la web (docs/MARKETING_WEB.md): newsletter con doble opt-in,
// formulario de contacto y analítica propia sin cookies.
Route::middleware('throttle:web-forms')->group(function () {
    Route::post('newsletter-subscribe', [ API\NewsletterController::class, 'subscribe' ]);
    Route::post('contact-message', [ API\ContactMessageController::class, 'store' ]);
});
Route::middleware('throttle:30,1')->group(function () {
    Route::post('newsletter-confirm', [ API\NewsletterController::class, 'confirm' ]);
    Route::post('newsletter-unsubscribe', [ API\NewsletterController::class, 'unsubscribe' ]);
});
Route::middleware('throttle:web-track')->post('track', [ API\TrackController::class, 'pageview' ]);
// Stripe firma cada llamada (verificada en el controlador); sin auth ni throttle.
Route::post('webhooks/stripe', [ API\StripeWebhookController::class, 'handle' ]);
Route::middleware(['auth:sanctum', 'throttle:10,1'])->post('v1/pack-redeem', [ API\PackController::class, 'redeem' ]);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('register',[ API\UserController::class, 'register']);
    Route::post('check-invite-code',[ API\UserController::class, 'checkInviteCode']);
});

// SEGURIDAD (auditoría 2026-09-13): sin auth:sanctum era un IDOR público --
// ver comentario en UserController::userDetail() para el detalle.
Route::middleware('auth:sanctum')->get('user-detail',[ API\UserController::class, 'userDetail']);
Route::get('get-appsetting', [ API\UserController::class, 'getAppSetting'] );
Route::get('language-table-list',[API\LanguageTableController::class, 'getList']);
Route::get('get-macro-nutrient',[API\DashboardController::class,'getMacroNurtrient']);

    Route::get('get-setting',[ API\DashboardController::class, 'getSetting']);
    Route::get('dashboard-detail',[ API\DashboardController::class, 'dashboard']);
    Route::get('motivational-phrase', [API\MotivationalPhraseController::class, 'getPhrase']);

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

// SEGURIDAD (auditoria 2026-09-01, HIGH-1): ruta publica a proposito -- la
// firma de la URL (generada solo desde progress-photo-list/store, ambas
// detras de auth:sanctum+admin.api) es el unico credencial necesario, igual
// que una URL firmada de S3. Ver SECURITY_AUDIT_BACKEND.md.
Route::get('progress-photo-signed/{media}', [ API\Admin\ProgressPhotoController::class, 'showSigned'])
    ->middleware('signed')->name('progress-photo.signed');

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::post('update-profile', [ API\UserController::class, 'updateProfile']);
    Route::post('change-password', [ API\UserController::class, 'changePassword']);
    Route::post('update-user-status', [ API\UserController::class, 'updateUserStatus']);
    Route::post('delete-user-account', [ API\UserController::class, 'deleteUserAccount']);
    Route::get('logout',[ API\UserController::class, 'logout']);
    Route::post('logout-all-devices', [ API\UserController::class, 'logoutAllDevices']);
    // AÑADIDO 2026-09-11: registro del token de Expo Push (ver ExpoPushChannel).
    Route::post('update-push-token', [ API\UserController::class, 'updatePushToken']);

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
    

    // RETIRADAS 2026-08-13 (package-list, subscriptionplan-list, subscribe-package,
    // subscribe-to-package, cancel-subscription, payment-gateway-list): autoservicio
    // de compra dentro de la app, sistema Package/Subscription legacy. Apple/Google
    // exigen que la app no venda nada dentro (ver plan de migración) — la compra
    // pasa a ser 100% externa (web) y el acceso se concede vía Plan/PlanSubscription
    // (PlanSubscriptionController::grantPlan() -- alta manual, modelo de negocio real
    // confirmado 2026-09-13: pago presencial, nunca por app ni web; el webhook de
    // Stripe que existía aquí se retiró por no estar conectado a ninguna cuenta real,
    // ver docs/PLAN_VENTAS_PROGRAMAS_Y_BLOG.md en el repo bsa). El estado
    // de solo lectura del cliente ahora vive en GET my-plan, más abajo.
    Route::get('my-plan', [ API\SubscriptionController::class, 'myPlan']);



    Route::post('usergraph-save', [ API\UserGraphController::class, 'saveGraphData']);
    Route::get('usergraph-list', [ API\UserGraphController::class, 'getGraphDataList']);
    Route::post('usergraph-delete', [ API\UserGraphController::class, 'deleteGraphData']);
    Route::get('usergraph-detail', [ API\UserGraphController::class, 'getGraphDetails']);

    Route::post('notification-list', [ API\NotificationController::class, 'getList'] );
    Route::get('notification-detail', [ API\NotificationController::class, 'getNotificationDetail'] );

    Route::get('user-profile-detail',[ API\UserController::class, 'userProfileDetail']);
    Route::get('user-social-stats',[ API\UserController::class, 'userSocialStats']);

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
    Route::post('report-on-comment', [ API\CommentController::class, 'reportOnComment']);

    // AÑADIDO: bloqueo de usuario (item 11 del roadmap), ver
    // docs/PENDIENTE_BACKEND_ADMIN.md en el repo bsa.
    Route::post('block-user', [ API\UserBlockController::class, 'block']);
    Route::post('unblock-user', [ API\UserBlockController::class, 'unblock']);
    Route::get('my-blocked-users', [ API\UserBlockController::class, 'myBlockedUsers']);

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
        // Catálogo de técnicas especiales (App\Support\TrainingTechniques), para mostrarlas al entrenar.
        Route::get('training-technique-list', [ API\TrainingTechniqueController::class, 'getList' ]);

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
        // AÑADIDO: reorganizar el calendario semanal ("Guardar cambios" tras arrastrar entre días).
        Route::post('my-calendar-move-assignments', [ API\ClientCalendarController::class, 'moveAssignments' ]);

        // AÑADIDO (2026-09-24): entrenamientos personalizados creados por el
        // propio cliente (se guardan en su calendario personal, ver
        // ClientCustomWorkoutController) + programas asignados para la
        // sección "Entrenamientos" del Home.
        Route::post('my-custom-workouts', [ API\ClientCustomWorkoutController::class, 'store' ]);
        Route::post('my-custom-workouts-delete', [ API\ClientCustomWorkoutController::class, 'destroy' ]);
        // AÑADIDO (2026-09-24): editar un entrenamiento personalizado ya
        // creado (detalle para abrir el editor + guardar esta ocurrencia o
        // esta y las siguientes de la serie).
        Route::get('my-custom-workout-detail', [ API\ClientCustomWorkoutController::class, 'detail' ]);
        Route::post('my-custom-workouts-update', [ API\ClientCustomWorkoutController::class, 'update' ]);
        Route::get('my-active-programs', [ API\ClientCustomWorkoutController::class, 'activePrograms' ]);

        // AÑADIDO: volumen por grupo muscular (heatmap + progreso semanal/mensual).
        Route::get('my-muscle-volume', [ API\ClientCalendarController::class, 'getMyMuscleVolume' ]);
        Route::post('muscle-volume-compute', [ API\ClientCalendarController::class, 'computeMuscleVolume' ]);

        // AÑADIDO: KPIs de sesión (entrenamientos/duración/volumen) por rango de fechas — pantalla Estadísticas.
        Route::get('my-period-stats', [ API\ClientCalendarController::class, 'getMyPeriodStats' ]);

        // AÑADIDO: ranking de ejercicios por frecuencia — pantalla "Ejercicios principales" de Estadísticas.
        Route::get('my-top-exercises', [ API\ClientCalendarController::class, 'getMyTopExercises' ]);

        // AÑADIDO: sesiones del mes + PRs del mes — pantalla "Informe mensual" de Estadísticas.
        Route::get('my-monthly-extras', [ API\ClientCalendarController::class, 'getMyMonthlyExtras' ]);

        // AÑADIDO (temporal): FAB de revisión de pantallas — borrar cuando ya no haga falta.
        Route::post('screen-review-mark', [ API\ScreenReviewMarkController::class, 'store' ]);
        Route::get('screen-review-marks', [ API\ScreenReviewMarkController::class, 'index' ]);

        // AÑADIDO: readiness diario obligatorio antes de Workout Preview.
        Route::get('readiness-today', [ API\ReadinessController::class, 'today' ]);
        Route::post('readiness-store', [ API\ReadinessController::class, 'store' ]);
        // AÑADIDO: resumen ligero de readiness (stopgap subjetivo, ver ReadinessController::summary()).
        Route::get('readiness-summary', [ API\ReadinessController::class, 'summary' ]);
        // AÑADIDO (item 1 del roadmap): readiness real (combined_score/band/acwr
        // de readiness_scores), ver ReadinessController::latest().
        Route::get('readiness-scores-latest', [ API\ReadinessController::class, 'latest' ]);
        // AÑADIDO (2026-09-26): historial propio de readiness (Check-ins > Historial).
        Route::get('readiness-history', [ API\ReadinessController::class, 'history' ]);

        // AÑADIDO (2026-09-26): estadisticas publicas opt-in del perfil de otro usuario + ajuste de privacidad.
        Route::get('user-public-stats', [ API\PrivacyStatsController::class, 'show' ]);
        Route::get('my-privacy-settings', [ API\PrivacyStatsController::class, 'mySettings' ]);
        Route::post('my-privacy-settings', [ API\PrivacyStatsController::class, 'updateMySettings' ]);

        // AÑADIDO: rutas para ClientHabitController, que ya estaba
        // implementado (espejo cliente de HabitController) pero nunca se
        // había conectado a ninguna ruta -- la pantalla de Hábitos de la
        // app llamaba a estos paths y siempre recibía 404.
        Route::get('habit-my-list', [ API\ClientHabitController::class, 'getMyList' ]);
        Route::get('habit-library', [ API\ClientHabitController::class, 'getLibrary' ]);
        Route::post('habit-adopt', [ API\ClientHabitController::class, 'adopt' ]);
        Route::post('habit-personal-store', [ API\ClientHabitController::class, 'storePersonal' ]);
        Route::post('habit-my-log', [ API\ClientHabitController::class, 'logHabit' ]);
        Route::post('habit-my-delete', [ API\ClientHabitController::class, 'destroy' ]);

        // AÑADIDO: rutas para BodyMetricController, espejo cliente de
        // Admin\ClientBodyMetricController que tampoco tenía ninguna ruta
        // conectada -- la pantalla de Métricas de la app recibía 404.
        Route::get('my-body-metric-types', [ API\BodyMetricController::class, 'types' ]);
        Route::get('my-body-metrics', [ API\BodyMetricController::class, 'index' ]);
        Route::get('my-body-metrics-chart', [ API\BodyMetricController::class, 'chart' ]);
        Route::post('my-body-metrics-store', [ API\BodyMetricController::class, 'store' ]);
        Route::post('my-body-metrics-delete', [ API\BodyMetricController::class, 'destroy' ]);

        // AÑADIDO: Onboarding v2, etapas 2-4 + marcado de completado -- la
        // etapa 1 reutiliza update-profile y no vive aquí. Ver
        // docs/ONBOARDING_V2.md para el contrato completo.
        Route::prefix('onboarding')->group(function () {
            Route::get('my-answers', [ API\OnboardingController::class, 'myAnswers' ]);
            Route::post('par-q', [ API\OnboardingController::class, 'parq' ]);
            Route::post('training-questionnaire', [ API\OnboardingController::class, 'trainingQuestionnaire' ]);
            Route::post('nutrition-questionnaire', [ API\OnboardingController::class, 'nutritionQuestionnaire' ]);
            Route::post('training-availability-update', [ API\OnboardingController::class, 'updateTrainingAvailability' ]);
            Route::post('complete', [ API\OnboardingController::class, 'complete' ]);
        });

        // AÑADIDO: borrado de cuenta -- el cliente (app) llama a esta URL
        // exacta (authApi.deleteAccount() -> POST v1/delete-account), no a
        // 'delete-user-account'. Mismo método que esa ruta antigua (se deja
        // por compatibilidad), ver docs/BORRADO_CUENTA_BACKEND.md.
        Route::post('delete-account', [ API\UserController::class, 'deleteUserAccount' ]);

        // AÑADIDO: feedback in-app (item 5 del backlog) -- feature_request/bug_report.
        Route::post('app-feedback', [ API\AppFeedbackController::class, 'store' ]);
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

    // ═══ FatSecret -- buscador de sustitución desde la app (2026-09-19,
    // ver docs/FATSECRET_INTEGRATION.md sección 9). throttle:100,1440 = red
    // de seguridad por cliente/día, independiente del límite real de la
    // cuenta FatSecret (5.000/día en Basic, compartido entre TODOS los
    // clientes) -- evita que un bug o un uso desmedido de un solo cliente
    // agote el cupo del día para el resto.
    Route::middleware('throttle:100,1440')->group(function () {
        Route::get('fatsecret/recipes/search', [ API\FatSecretController::class, 'search' ]);
        Route::get('fatsecret/recipe-types', [ API\FatSecretController::class, 'recipeTypes' ]);
        Route::get('fatsecret/recipes/{recipe_id}', [ API\FatSecretController::class, 'show' ]);
    });

    // ═══ V2: Forms (Check-ins) — Client API ═══════════════════════════
    Route::get('form-assigned-list', [API\FormController::class, 'getAssignedList']);
    Route::get('form-assigned-calendar', [API\FormController::class, 'getAssignedCalendar']);
    Route::get('form-detail', [API\FormController::class, 'getDetail']);
    Route::post('form-submit', [API\FormController::class, 'submit']);
    // AÑADIDO (2026-09-26): historial propio de check-ins enviados + detalle de solo lectura.
    Route::get('form-my-submissions', [API\FormController::class, 'mySubmissions']);
    Route::get('form-submission-detail', [API\FormController::class, 'submissionDetail']);
    // SEGURIDAD (auditoría 2026-09-13): eliminada 'form-feedback' -- duplicado
    // sin protección de admin-form-feedback (Admin\FormController, tras
    // admin.api). Cualquier usuario normal podía dejar coach_feedback en el
    // check-in de otro cliente pasando su submission_id. Sin uso real en la
    // app móvil (solo el admin usa admin-form-feedback).

    // ═══ V2: Habits — Client API ═══════════════════════════════════════
    Route::get('habit-my-list', [API\ClientHabitController::class, 'getMyList']);
    Route::get('habit-library', [API\ClientHabitController::class, 'getLibrary']);
    Route::post('habit-adopt', [API\ClientHabitController::class, 'adopt']);
    Route::post('habit-personal-store', [API\ClientHabitController::class, 'storePersonal']);
    Route::post('habit-my-log', [API\ClientHabitController::class, 'logHabit']);
    Route::post('habit-my-delete', [API\ClientHabitController::class, 'destroy']);

    // ═══ V2: Resources — Client API ════════════════════════════════════
    // CORREGIDO: estas 5 rutas vivian por error dentro del grupo
    // admin+admin.api mas abajo en este archivo, haciendolas inalcanzables
    // para un cliente normal de la app (siempre 403). Movidas al grupo
    // auth:sanctum real que usa el resto de endpoints de cliente.
    Route::get('resource-list', [API\ResourceController::class, 'getList']);
    Route::get('resource-detail', [API\ResourceController::class, 'getDetail']);
    Route::post('resource-store', [API\ResourceController::class, 'store']);
    Route::post('resource-update', [API\ResourceController::class, 'update']);
    Route::post('resource-delete', [API\ResourceController::class, 'destroy']);

    // ═══ V2: Exercise History / PRs — CORREGIDO: vivian por error dentro
    // del grupo admin+admin.api mas abajo en este archivo (mismo problema
    // que resource-list arriba), haciendolas inalcanzables para un cliente
    // normal (404, la ruta con ese path no existia fuera de /admin). Estas
    // 3 usan auth('sanctum')->id() (el propio usuario), no un client_id de
    // admin, asi que van aqui con el resto de rutas de cliente. ═══
    Route::get('exercise-history', [API\PersonalRecordController::class, 'getExerciseHistory']);
    Route::get('exercise-last-performance', [API\PersonalRecordController::class, 'getLastPerformance']);
    Route::get('my-personal-records', [API\PersonalRecordController::class, 'getMyPersonalRecords']);

    // AÑADIDO: antropometría — espejo cliente de Admin\ClientBodyMetricController,
    // pantalla Report rediseñada.
    Route::get('my-body-metric-types', [API\BodyMetricController::class, 'types']);
    Route::get('my-body-metrics', [API\BodyMetricController::class, 'index']);
    Route::get('my-body-metrics-chart', [API\BodyMetricController::class, 'chart']);
    Route::post('my-body-metrics-store', [API\BodyMetricController::class, 'store']);
    Route::post('my-body-metrics-delete', [API\BodyMetricController::class, 'destroy']);

    // AÑADIDO: adherencia de entrenamiento — pantalla Report rediseñada.
    Route::get('my-workout-adherence', [API\ClientCalendarController::class, 'getMyAdherence']);

    // AÑADIDO: historial real de entrenamientos completados — mismo dato que
    // ya ve el coach en "Entrenamientos completados" del panel admin.
    Route::get('my-completed-sessions', [API\SessionDetailController::class, 'listMyCompletedSessions']);
    Route::get('my-session-detail', [API\SessionDetailController::class, 'getMySessionDetail']);

    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 1) — bloqueo por
    // dolor (sin gate de tier, aplica a todos los clientes) y consulta de
    // métricas interpretadas (debug/coach). logSets()/finishSession() ya
    // existen (arriba, ClientCalendarController) y no se duplican aquí.
    Route::post('sessions/{id}/pain-report', [API\SessionInterpretationController::class, 'painReport']);
    Route::get('clients/{id}/exercises/{exerciseId}/metrics', [API\SessionInterpretationController::class, 'exerciseMetrics']);

    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 2) — reglas de
    // progresión, panel de excepciones y auditoría. Mismo criterio que las
    // rutas de Fase 1 justo arriba: sin prefijo /admin (no existe un rol
    // "coach" separado de "admin" en este esquema), autorización resuelta
    // dentro del controlador (SessionProgressionRuleController::requireCoach)
    // comparando el {id} de la URL / el coach dueño de la regla contra
    // auth()->id(), igual que el resto de recursos coach_id=auth()->id()
    // del proyecto (WorkoutTemplateController, ClientTagController, etc.).
    Route::post('coaches/{id}/rules', [API\SessionProgressionRuleController::class, 'store']);
    Route::get('coaches/{id}/rules', [API\SessionProgressionRuleController::class, 'index']);
    Route::put('rules/{id}', [API\SessionProgressionRuleController::class, 'update']);
    Route::delete('rules/{id}', [API\SessionProgressionRuleController::class, 'destroy']);
    Route::post('rules/{id}/simulate', [API\SessionProgressionRuleController::class, 'simulate']);
    Route::get('rules/{id}/shadow-evaluations', [API\SessionProgressionRuleController::class, 'shadowEvaluations']);
    Route::get('rules/{id}/audit', [API\SessionProgressionRuleController::class, 'audit']);
    Route::get('clients/{id}/pending-suggestions', [API\SessionProgressionRuleController::class, 'pendingSuggestions']);
    Route::post('suggestions/{id}/approve', [API\SessionProgressionRuleController::class, 'approve']);
    Route::post('suggestions/{id}/edit', [API\SessionProgressionRuleController::class, 'edit']);
    Route::post('suggestions/{id}/reject', [API\SessionProgressionRuleController::class, 'reject']);
    Route::get('exercises/{id}/progression-history', [API\SessionProgressionRuleController::class, 'progressionHistory']);

    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 4) — readiness score
    // (sync de lecturas de salud del dispositivo) y modo vida real (plan
    // adaptativo de semana reducida). Sin gate de tier en el sync (ver
    // HealthDataPointController); el gate real vive en el job diario de
    // ReadinessCalculationService. generate()/approve() sí gatean por
    // coach_id del cliente (mismo criterio que exerciseMetrics() arriba).
    Route::post('health-data-points/sync', [API\HealthDataPointController::class, 'sync']);
    Route::post('adaptive-week-plans/generate', [API\AdaptiveWeekPlanController::class, 'generate']);
    Route::post('adaptive-week-plans/{id}/approve', [API\AdaptiveWeekPlanController::class, 'approve']);
    // AÑADIDO 2026-08-12: rechazar -- hueco real, no existía ningún camino
    // para denegar una propuesta sin dejarla huérfana en 'propuesto'.
    Route::post('adaptive-week-plans/{id}/reject', [API\AdaptiveWeekPlanController::class, 'reject']);
    // AÑADIDO 2026-08-12: trigger del propio cliente -- selecciona desde su
    // calendario qué días no puede entrenar (en vez del coach dando un
    // número de sesiones + estrategia). Sigue naciendo 'propuesto', pasa
    // por el mismo Panel de Excepciones y requiere aprobación del coach.
    Route::post('adaptive-week-plans/request-unavailable', [API\AdaptiveWeekPlanController::class, 'requestFromClient']);

    // AÑADIDO: Motor de Auto-Regulación de Carga -- espejo admin de
    // suggestions/{id}/approve|edit|reject y adaptive-week-plans/{id}/
    // approve arriba (Plan_Cierre_Motor_UI.md, Fase 1). Definidos también
    // dentro del grupo /admin (mismo bloque que coach-exceptions*), ver
    // más abajo en este archivo.

    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 3) — sistema de
    // evidencia visible (feed de logros), gateado a paid-tier dentro del
    // controlador (mismo criterio que pending-suggestions arriba). La
    // sustitución de ejercicio (exercise_substitutions) no tiene endpoint
    // propio — vive dentro del motor de reglas de Fase 2 (SessionProgressionRuleEngine),
    // sin necesidad de una ruta nueva.
    Route::get('clients/{id}/achievements', [API\AchievementEventController::class, 'index']);
    Route::post('clients/{id}/achievements/{achievementId}/seen', [API\AchievementEventController::class, 'markSeen']);
    Route::get('coaches/{id}/achievement-settings', [API\AchievementEventController::class, 'achievementSettings']);

    // AÑADIDO: Panel de Excepciones del Coach (docs/Panel_Excepciones_
    // Implementacion.md §4) -- feed unificado sobre datos que el Motor ya
    // genera (dolor, estancamiento, sugerencia_carga, readiness_bajo,
    // semana_adaptativa_pendiente, inactividad). Mismo criterio de
    // autorización que el resto de rutas coach-owned de este bloque.
    Route::get('coaches/{id}/exceptions', [API\CoachExceptionItemController::class, 'index']);
    Route::post('exceptions/{id}/resolve', [API\CoachExceptionItemController::class, 'resolve']);
    Route::post('exceptions/{id}/dismiss', [API\CoachExceptionItemController::class, 'dismiss']);

    // AÑADIDO: Score de Riesgo de Abandono (docs/Score_Riesgo_Abandono_
    // Implementacion.md §9) -- sustituye check:client-inactivity. Mismo
    // criterio de autorización que el resto de rutas coach-owned de este
    // bloque (coach_id = auth()->id(), sin prefijo /admin).
    Route::get('coaches/{id}/retention-risk-summary', [API\RetentionRiskController::class, 'summary']);
    Route::get('coaches/{id}/clients/{clientId}/retention-risk', [API\RetentionRiskController::class, 'detail']);
    Route::get('coaches/{id}/retention-risk-config', [API\RetentionRiskController::class, 'getConfig']);
    Route::put('coaches/{id}/retention-risk-weights', [API\RetentionRiskController::class, 'updateWeights']);
    Route::put('coaches/{id}/retention-settings', [API\RetentionRiskController::class, 'updateSettings']);

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
use App\Http\Controllers\API\Admin\SystemSettingsController;
use App\Http\Controllers\API\Admin\LanguageFileController;
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
use App\Http\Controllers\API\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\API\Admin\PlanFeatureController;
use App\Http\Controllers\API\Admin\PlanSubscriptionController as AdminPlanSubscriptionController;
use App\Http\Controllers\API\Admin\ReportController;
use App\Http\Controllers\API\Admin\SubscriptionPaymentController;
use App\Http\Controllers\API\Admin\TwoFactorController;
use App\Http\Controllers\API\Admin\AuditLogController;
use App\Http\Controllers\API\Admin\ExerciseSubstitutionController;
use App\Http\Controllers\API\Admin\NextSessionTargetController;

// Public admin routes (login)
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// Estado de suscripción de un cliente (app Flutter / consulta puntual).
// SEGURIDAD (auditoría 2026-09-13): sin auth:sanctum era un IDOR público --
// ver comentario en ReportController::clientSubscription() para el detalle.
Route::middleware('auth:sanctum')->get('client/subscription', [ReportController::class, 'clientSubscription']);

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
    // AÑADIDO: export de informe de usuarios (item 8, auditoria de migracion
    // 2026-09-11) -- debe ir ANTES del apiResource, si no 'report' choca con
    // el {user} de users/{user} (show).
    Route::get('users/report', [AdminUserController::class, 'report']);
    // Entrenador del cliente (ítem 49 del roadmap): ver el actual + candidatos y asignarlo a mano
    // desde la ficha del cliente. Solo user_type=admin puede reasignar (ver coachUpdate).
    Route::get('users/{user}/coach', [AdminUserController::class, 'coachShow'])->whereNumber('user');
    Route::put('users/{user}/coach', [AdminUserController::class, 'coachUpdate'])->whereNumber('user');
    // Teléfono -> cliente_id (Agente de Soporte / Customer Success, sin hoja
    // de mapeo manual). Debe ir ANTES del apiResource, igual que 'report'
    // arriba, o el {user} de users/{user} se lo traga.
    Route::get('users/lookup-by-phone', [AdminUserController::class, 'lookupByPhone']);
    // ->names('admin.users'): sin esto, el nombre implícito 'users.index'
    // choca con el resource del panel Blade legacy (routes/web.php,
    // Route::resource('users', UserController::class)) -- inofensivo en la
    // práctica (Laravel deja ganar al último registrado en route()) pero
    // rompe `php artisan route:cache`, que sí valida nombres duplicados y
    // aborta con error (bug real, bloqueaba el deploy a VPS por completo,
    // 2026-09-18: ver el mismo choque en equipment/tags/quotes más abajo).
    Route::apiResource('users', AdminUserController::class)->names('admin.users');
    Route::get('users-graph', [AdminUserController::class, 'graph']);

    // Sub Admins
    Route::apiResource('sub-admins', SubAdminController::class);

    // Fitness Content
    // ->names('admin.equipment'): mismo choque de nombre que 'users' de
    // arriba con routes/web.php (Route::resource('equipment', ...)).
    Route::apiResource('equipment', AdminEquipmentController::class)->names('admin.equipment');
    Route::apiResource('workout-types', AdminWorkoutTypeController::class);
    Route::apiResource('levels', AdminLevelController::class);
    Route::apiResource('body-parts', AdminBodyPartController::class);
    Route::apiResource('categories', AdminCategoryController::class);
    // ->names('admin.tags'): mismo choque de nombre que 'users' de arriba
    // con routes/web.php (Route::resource('tags', ...)).
    Route::apiResource('tags', AdminTagsController::class)->names('admin.tags');

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
    // AÑADIDO: reordenar pasos (item 7, auditoria de migracion 2026-09-11).
    Route::post('recipes/{recipe}/steps/reorder', [AdminRecipeController::class, 'reorderSteps']);
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

    // ═══ FatSecret (2026-09-19, ver docs/FATSECRET_INTEGRATION.md) ═════
    // Alimentos: solo para autocompletar nutrición de Ingredient al crearlo.
    // Recetas: para que el coach busque/asigne una receta de FatSecret a un
    // cliente (proxy en vivo, nunca se importa/guarda de forma permanente).
    Route::get('fatsecret/foods/search', [API\Admin\FatSecretController::class, 'searchFoods']);
    Route::get('fatsecret/foods/{food_id}', [API\Admin\FatSecretController::class, 'showFood']);
    Route::get('fatsecret/recipes/search', [API\Admin\FatSecretController::class, 'searchRecipes']);
    Route::get('fatsecret/recipe-types', [API\Admin\FatSecretController::class, 'recipeTypes']);
    Route::get('fatsecret/recipes/{recipe_id}', [API\Admin\FatSecretController::class, 'showRecipe']);

    // Products
    Route::apiResource('products', AdminProductController::class);
    Route::apiResource('product-categories', AdminProductCategoryController::class);

    // Blog Posts
    Route::apiResource('posts', AdminPostController::class);
    Route::post('posts/{id}/cover-image', [AdminPostController::class, 'uploadCoverImage']);

    // Blog Categories
    Route::apiResource('blog-categories', AdminBlogCategoryController::class);

    // Plans & Subscriptions (sistema laravel-subscriptions)
    Route::apiResource('plans', AdminPlanController::class);
    Route::apiResource('plan-features', PlanFeatureController::class);
    Route::apiResource('plan-subscriptions', AdminPlanSubscriptionController::class)->only(['index', 'show']);
    Route::post('plan-subscriptions-grant', [AdminPlanSubscriptionController::class, 'grantPlan']);
    // Compras de packs en la web (docs/PACKS_WEB.md)
    Route::get('pack-purchases', [\App\Http\Controllers\API\Admin\PackPurchaseController::class, 'index']);
    Route::post('pack-purchases-resend', [\App\Http\Controllers\API\Admin\PackPurchaseController::class, 'resend']);
    Route::post('pack-purchases-link', [\App\Http\Controllers\API\Admin\PackPurchaseController::class, 'link']);
    Route::post('pack-image', [\App\Http\Controllers\API\Admin\PackAdminController::class, 'uploadImage']);
    Route::get('pack-stats', [\App\Http\Controllers\API\Admin\PackAdminController::class, 'stats']);

    // Marketing (docs/MARKETING_WEB.md)
    Route::get('newsletter-subscribers', [\App\Http\Controllers\API\Admin\MarketingController::class, 'newsletterIndex']);
    Route::get('newsletter-stats', [\App\Http\Controllers\API\Admin\MarketingController::class, 'newsletterStats']);
    Route::get('newsletter-export', [\App\Http\Controllers\API\Admin\MarketingController::class, 'newsletterExport']);
    Route::post('newsletter-delete', [\App\Http\Controllers\API\Admin\MarketingController::class, 'newsletterDelete']);
    Route::get('contact-messages', [\App\Http\Controllers\API\Admin\MarketingController::class, 'contactIndex']);
    Route::post('contact-messages-update', [\App\Http\Controllers\API\Admin\MarketingController::class, 'contactUpdate']);
    Route::post('contact-messages-delete', [\App\Http\Controllers\API\Admin\MarketingController::class, 'contactDelete']);
    Route::get('checkout-attempts', [\App\Http\Controllers\API\Admin\MarketingController::class, 'checkoutAttempts']);
    Route::get('web-analytics', [\App\Http\Controllers\API\Admin\MarketingController::class, 'analytics']);

    // Comercio (panel React: usage, stats, reminder, revoke, transactions)
    Route::get('subscription-usage', [AdminPlanSubscriptionController::class, 'usage']);
    Route::get('subscriptions/stats', [AdminPlanSubscriptionController::class, 'stats']);
    Route::post('subscription/reminder', [AdminPlanSubscriptionController::class, 'reminder']);
    Route::post('users/{user}/revoke-access', [AdminPlanSubscriptionController::class, 'revokeAccess']);
    Route::get('transactions', [AdminPlanSubscriptionController::class, 'transactions']);

    // Reports (KPIs, informes, export)
    Route::get('reports/dashboard-kpis', [ReportController::class, 'dashboardKpis']);
    Route::get('reports/users-summary', [ReportController::class, 'usersSummary']);
    Route::get('reports/sessions', [ReportController::class, 'sessions']);
    Route::get('reports/checkins', [ReportController::class, 'checkins']);
    Route::get('reports/subscriptions', [ReportController::class, 'subscriptions']);
    Route::get('reports/payments', [ReportController::class, 'payments']);
    Route::get('reports/coaching-metrics', [ReportController::class, 'coachingMetrics']);
    Route::get('reports/export', [ReportController::class, 'export']);
    Route::get('reports/revenue', [ReportController::class, 'revenue']);
    Route::get('reports/transactions', [ReportController::class, 'transactions']);
    Route::get('users/{user}/billing', [ReportController::class, 'clientBilling']);
    // AÑADIDO: Motor de Auto-Regulación de Carga (Fase 4) -- visibilidad de
    // readiness/ACWR real en el admin (item 10, docs/PENDIENTE_BACKEND_ADMIN.md).
    Route::get('users/{user}/readiness', [ReportController::class, 'clientReadiness']);
    Route::get('revenue-summary', [ReportController::class, 'revenueSummary']);

    // Seguimiento manual de pagos de suscripción (Informes > Seguimiento de pagos)
    Route::get('subscription-payments', [SubscriptionPaymentController::class, 'index']);
    Route::get('subscription-payments/summary', [SubscriptionPaymentController::class, 'summary']);
    Route::get('subscription-payments/years', [SubscriptionPaymentController::class, 'years']);
    Route::get('subscription-payments/merge-candidates', [SubscriptionPaymentController::class, 'mergeCandidates']);
    Route::post('subscription-payments/external/{client}/merge', [SubscriptionPaymentController::class, 'mergeExternal']);
    Route::post('subscription-payments/external', [SubscriptionPaymentController::class, 'storeExternal']);
    Route::put('subscription-payments/external/{client}', [SubscriptionPaymentController::class, 'updateExternal']);
    Route::delete('subscription-payments/external/{client}', [SubscriptionPaymentController::class, 'destroyExternal']);
    Route::put('subscription-payments/external/{client}/{year}/{month}', [SubscriptionPaymentController::class, 'updateExternalPayment']);
    Route::put('subscription-payments/{user}/{year}/{month}', [SubscriptionPaymentController::class, 'updatePayment']);
    Route::put('users/{user}/monthly-fee', [SubscriptionPaymentController::class, 'updateTariff']);

    // AÑADIDO: Panel de Excepciones del Coach -- espejo admin del
    // self-service de arriba (ver docblock de CoachExceptionItemController
    // para por qué hace falta: esta es la única superficie real donde un
    // coach ve datos de sus clientes hoy). Staff elige coach_id, mismo
    // patrón que el selector de cliente en HabitsView.
    Route::get('coach-exceptions', [API\CoachExceptionItemController::class, 'adminIndex']);
    Route::get('coach-exceptions/coaches', [API\CoachExceptionItemController::class, 'adminCoachOptions']);
    Route::get('coach-exceptions/unread-summary', [API\CoachExceptionItemController::class, 'adminUnreadSummary']);
    Route::post('coach-exceptions/{id}/resolve', [API\CoachExceptionItemController::class, 'adminResolve']);
    Route::post('coach-exceptions/{id}/dismiss', [API\CoachExceptionItemController::class, 'adminDismiss']);

    // AÑADIDO 2026-08-12: espejo admin de RetentionRiskController -- petición
    // explícita del usuario para poder configurar pesos/mensajes y ver el
    // score de riesgo de abandono desde el panel, no pedido por el
    // documento original. Mismo criterio que coach-exceptions* de arriba.
    Route::get('retention-risk-summary', [API\RetentionRiskController::class, 'adminSummary']);
    Route::get('retention-risk-history', [API\RetentionRiskController::class, 'adminHistory']);
    Route::get('retention-risk-config', [API\RetentionRiskController::class, 'adminGetConfig']);
    Route::put('retention-risk-weights', [API\RetentionRiskController::class, 'adminUpdateWeights']);
    Route::put('retention-risk-settings', [API\RetentionRiskController::class, 'adminUpdateSettings']);

    // AÑADIDO: espejo admin de las sugerencias del Motor de Auto-Regulación
    // de Carga y de las semanas adaptativas (Plan_Cierre_Motor_UI.md, Fase
    // 1) -- mismo criterio que coach-exceptions* arriba: sin comparar
    // coach_id contra auth()->id(), staff actúa en nombre del coach.
    Route::post('session-progression/suggestions/{id}/approve', [API\SessionProgressionRuleController::class, 'adminApprove']);
    Route::post('session-progression/suggestions/{id}/edit', [API\SessionProgressionRuleController::class, 'adminEdit']);
    Route::post('session-progression/suggestions/{id}/reject', [API\SessionProgressionRuleController::class, 'adminReject']);
    Route::post('adaptive-week-plans/{id}/approve', [API\AdaptiveWeekPlanController::class, 'adminApprove']);
    Route::post('adaptive-week-plans/{id}/reject', [API\AdaptiveWeekPlanController::class, 'adminReject']);

    // AÑADIDO: CRUD de reglas de progresión desde el admin (todavía no
    // existía ninguna pantalla para crear/editar reglas -- solo se podía
    // actuar sobre sugerencias ya generadas por reglas creadas vía API
    // directa). Mismo criterio que el resto de este bloque.
    Route::get('session-progression/rules', [API\SessionProgressionRuleController::class, 'adminIndex']);
    Route::post('session-progression/rules', [API\SessionProgressionRuleController::class, 'adminStore']);
    Route::put('session-progression/rules/{id}', [API\SessionProgressionRuleController::class, 'adminUpdate']);
    Route::delete('session-progression/rules/{id}', [API\SessionProgressionRuleController::class, 'adminDestroy']);
    Route::post('session-progression/rules/{id}/simulate', [API\SessionProgressionRuleController::class, 'adminSimulate']);
    Route::get('session-progression/rules/{id}/shadow-evaluations', [API\SessionProgressionRuleController::class, 'adminShadowEvaluations']);
    Route::get('session-progression/rules/{id}/audit', [API\SessionProgressionRuleController::class, 'adminAudit']);

    // ═══ Motor de Auto-Regulación de Carga: sustituciones de ejercicio ═══
    Route::get('exercise-substitutions', [ExerciseSubstitutionController::class, 'index']);
    Route::post('exercise-substitutions', [ExerciseSubstitutionController::class, 'store']);
    Route::put('exercise-substitutions/{id}', [ExerciseSubstitutionController::class, 'update']);
    Route::delete('exercise-substitutions/{id}', [ExerciseSubstitutionController::class, 'destroy']);

    // ═══ Motor de Auto-Regulación de Carga: feed de logros (admin) ═══════
    Route::get('achievement-events', [API\AchievementEventController::class, 'adminIndex']);

    // ═══ Motor de Auto-Regulación de Carga: decisiones de próxima sesión ═
    Route::get('next-session-targets', [NextSessionTargetController::class, 'index']);

    // Códigos de invitación de cliente personal (Niveles de acceso, 2026-07-30)
    Route::get('personal-client-invites', [PersonalClientInviteController::class, 'index']);
    Route::post('personal-client-invites', [PersonalClientInviteController::class, 'store']);
    Route::delete('personal-client-invites/{id}', [PersonalClientInviteController::class, 'destroy']);

    // Quotes
    // ->names('admin.quotes'): mismo choque de nombre que 'users' de arriba
    // con routes/web.php (Route::resource('quotes', ...)) -- aquí incluso
    // con el mismo controller class en los dos sitios.
    Route::apiResource('quotes', QuotesController::class)->names('admin.quotes');

    // Banner Sliders
    Route::apiResource('banner-sliders', BannerSliderController::class);

    // Class Schedules
    Route::apiResource('class-schedules', ClassScheduleController::class);

    // Push Notifications
    Route::apiResource('push-notifications', PushNotificationController::class);
    Route::post('push-notifications-send', [PushNotificationController::class, 'send']);

    // Community Postings
    Route::apiResource('postings', PostingController::class)->only(['index', 'show']);
    Route::get('reported-postings', [PostingController::class, 'reportList']);
    // AÑADIDO: comentarios reportados (item 11 del roadmap), mismo patrón
    // que reported-postings.
    Route::get('reported-comments', [PostingController::class, 'reportedComments']);
    Route::post('postings/{id}/status', [PostingController::class, 'updateStatus']);
    // AÑADIDO: borrado admin de un post reportado (item 12 del backlog) --
    // no existía ninguna vía admin para borrar un post moderado.
    Route::post('admin-posting-delete', [PostingController::class, 'destroyReported']);
    // AÑADIDO: moderacion de comentarios como staff (item 6, auditoria de
    // migracion 2026-09-11) -- reutiliza Comment/CommentReply::canBeDeletedBy(),
    // que ya permite borrado admin, solo faltaba exponerlo via /admin.
    Route::post('postings/comments/{id}', [PostingController::class, 'destroyComment']);
    Route::post('postings/comment-replies/{id}', [PostingController::class, 'destroyCommentReply']);

    // Languages
    Route::apiResource('languages', LanguageController::class);
    Route::get('language-keywords', [LanguageKeywordController::class, 'index']);
    Route::post('language-keywords/bulk-update', [LanguageKeywordController::class, 'bulkUpdate']);
    // AÑADIDO: import/export de traducciones (item 8, auditoria de migracion 2026-09-11).
    Route::get('language-keywords/export', [LanguageKeywordController::class, 'export']);
    Route::post('language-keywords/import', [LanguageKeywordController::class, 'import']);
    Route::apiResource('screens', ScreenController::class);
    Route::apiResource('default-keywords', DefaultKeywordController::class);
    // AÑADIDO: editor de archivos de idioma crudos (item 2, auditoria de migracion 2026-09-11).
    Route::get('lang-files', [LanguageFileController::class, 'index']);
    Route::get('lang-files/{lang}/{file}', [LanguageFileController::class, 'show']);
    Route::post('lang-files/{lang}/{file}', [LanguageFileController::class, 'update']);

    // Security
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index']);
    // AÑADIDO: crear/borrar permisos (item 3, auditoria de migracion 2026-09-11).
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::delete('permissions/{id}', [PermissionController::class, 'destroy']);
    Route::get('admin-login-history', [AdminLoginHistoryController::class, 'index']);
    Route::apiResource('admin-login-devices', AdminLoginDeviceController::class)->only(['index', 'destroy']);

    // AÑADIDO: paginas de configuracion que solo existian en el Blade viejo
    // (item 1, 4, 5, 8 de la auditoria de migracion 2026-09-11) -- misma
    // logica/modelos que SettingController (Blade), solo como JSON.
    Route::get('system-settings/env', [SystemSettingsController::class, 'envSettings']);
    Route::post('system-settings/env', [SystemSettingsController::class, 'updateEnvSettings']);
    Route::get('system-settings/legal', [SystemSettingsController::class, 'termsAndPrivacy']);
    Route::post('system-settings/legal/terms', [SystemSettingsController::class, 'updateTermsCondition']);
    Route::post('system-settings/legal/privacy', [SystemSettingsController::class, 'updatePrivacyPolicy']);
    Route::get('system-settings/payment-gateway/{type}', [SystemSettingsController::class, 'paymentGateway']);
    Route::post('system-settings/payment-gateway/{type}', [SystemSettingsController::class, 'updatePaymentGateway']);
    Route::get('system-settings/subscription', [SystemSettingsController::class, 'subscriptionSetting']);
    Route::post('system-settings/subscription', [SystemSettingsController::class, 'updateSubscriptionSetting']);
    Route::get('system-settings/login-enable', [SystemSettingsController::class, 'loginEnableSetting']);
    Route::post('system-settings/login-enable', [SystemSettingsController::class, 'updateLoginEnableSetting']);
    Route::get('system-settings/mail-alerts', [SystemSettingsController::class, 'mailAlertSettings']);
    Route::post('system-settings/mail-alerts', [SystemSettingsController::class, 'updateMailAlertSettings']);

    // 2FA + Auditoría (panel React)
    Route::get('2fa/status', [TwoFactorController::class, 'status']);
    Route::post('2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);

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
    Route::get('users/{userId}/meal-plan-template-assignments', [MealPlanTemplateController::class, 'assignmentsForUser']);

    // Settings
    Route::get('settings', [SettingController::class, 'getSettings']);
    Route::post('settings', [SettingController::class, 'updateSettings']);
    Route::get('app-settings', [SettingController::class, 'getAppSettings']);
    Route::post('app-settings', [SettingController::class, 'updateAppSettings']);
    Route::post('backup-run-now', [SettingController::class, 'runBackupNow']);

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
    Route::post('workout-template-block-save-as-section', [API\WorkoutTemplateController::class, 'saveBlockAsSection']);
    Route::post('workout-template-block-update', [API\WorkoutTemplateController::class, 'updateBlock']);
    Route::post('workout-template-block-delete', [API\WorkoutTemplateController::class, 'destroyBlock']);
    Route::post('workout-template-exercise-save', [API\WorkoutTemplateController::class, 'saveExercise']);
    Route::post('workout-template-exercise-update-field', [API\WorkoutTemplateController::class, 'updatePrescribedField']);
    Route::post('workout-template-exercise-delete', [API\WorkoutTemplateController::class, 'deleteExercise']);
    Route::post('workout-template-exercise-notes', [API\WorkoutTemplateController::class, 'updateExerciseNotes']);
    Route::post('workout-template-exercise-technique', [API\WorkoutTemplateController::class, 'updateExerciseTechnique']);
    Route::post('workout-template-block-instructions', [API\WorkoutTemplateController::class, 'updateBlockInstructions']);

    // ═══ V2: Training Programs ════════════════════════════════════════
    Route::get('training-program-list', [API\TrainingProgramController::class, 'getList']);
    Route::get('training-program-macrocycles', [API\TrainingProgramController::class, 'getMacrocycles']);
    Route::post('training-program-set-macrocycle', [API\TrainingProgramController::class, 'setMacrocycle']);
    Route::post('training-program-set-macrocycle-bulk', [API\TrainingProgramController::class, 'setMacrocycleBulk']);
    Route::get('macrocycle-plan', [API\MacrocycleDashboardController::class, 'plan']);
    Route::get('training-technique-list', [API\TrainingTechniqueController::class, 'getList']);
    Route::post('training-technique-save', [API\TrainingTechniqueController::class, 'save']);
    Route::post('training-technique-reset', [API\TrainingTechniqueController::class, 'reset']);
    Route::get('macrocycle-references', [API\MacrocycleDashboardController::class, 'references']);
    Route::post('macrocycle-references-save', [API\MacrocycleDashboardController::class, 'saveReferences']);
    Route::get('training-program-detail', [API\TrainingProgramController::class, 'getDetail']);
    Route::post('training-program-store', [API\TrainingProgramController::class, 'store']);
    Route::post('training-program-update', [API\TrainingProgramController::class, 'update']);
    Route::post('training-program-delete', [API\TrainingProgramController::class, 'destroy']);
    Route::post('training-program-duplicate', [API\TrainingProgramController::class, 'duplicate']);
    Route::post('training-program-generate-weeks', [API\TrainingProgramController::class, 'generateWeeks']);
    Route::post('training-program-assign-client', [API\TrainingProgramController::class, 'assignClient']);
    Route::post('training-program-remove-assignment', [API\TrainingProgramController::class, 'removeAssignment']);
    Route::get('training-program-assignments', [API\TrainingProgramController::class, 'getAssignments']);
    Route::post('training-program-mark-week-deload', [API\TrainingProgramController::class, 'markWeekDeload']);

    // Envuelve programs:import excel para el agente importador -- ver
    // docs/AGENTE_IMPORTADOR.md (sección 7, punto 2) y ProgramImportController.
    Route::post('program-import', [API\ProgramImportController::class, 'store']);

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
    // Editor de sesiones a nivel programa (matriz ejercicios x semanas).
    Route::get('program-session-matrix', [API\ProgramSessionMatrixController::class, 'show']);
    Route::post('program-session-matrix-save', [API\ProgramSessionMatrixController::class, 'save']);
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
    // Vaciado en bloque (seleccion multiple, vaciar semana, vaciar calendario) -- solo lo no realizado.
    Route::post('client-calendar-bulk-remove', [API\ClientProfileCalendarController::class, 'bulkRemoveAssignments']);
    Route::get('client-session-feedback', [API\ClientProfileCalendarController::class, 'getSessionFeedback']);
    Route::get('client-readiness-checks', [API\ClientProfileCalendarController::class, 'getReadinessChecks']);
    Route::get('client-workout-adherence', [API\ClientProfileCalendarController::class, 'getAdherence']);

    // ═══ V2: Session Detail ═══════════════════════════════════════════
    Route::get('client-completed-sessions', [API\SessionDetailController::class, 'listCompletedSessions']);
    Route::get('client-exercise-history', [API\SessionDetailController::class, 'getClientExerciseHistory']);
    Route::get('client-muscle-volume', [API\SessionDetailController::class, 'getMuscleVolume']);
    Route::get('session-detail', [API\SessionDetailController::class, 'getSessionDetail']);
    Route::post('session-detail-duplicate', [API\SessionDetailController::class, 'duplicateToDate']);
    Route::post('session-detail-update-override-field', [API\SessionDetailController::class, 'updatePrescribedOverride']);
    Route::post('session-detail-update-override-notes', [API\SessionDetailController::class, 'updateOverrideNotes']);
    Route::post('session-detail-update-override-technique', [API\SessionDetailController::class, 'updateOverrideTechnique']);
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

    // ═══ V2: Habits ═══════════════════════════════════════════════════
    Route::get('habit-list', [API\HabitController::class, 'getList']); // ?templates=1 -> biblioteca global
    Route::post('habit-log', [API\HabitController::class, 'logHabit']);
    Route::post('habit-store', [API\HabitController::class, 'store']);
    Route::post('habit-update', [API\HabitController::class, 'update']);
    Route::post('habit-delete', [API\HabitController::class, 'destroy']);
    Route::post('habit-template-store', [API\HabitController::class, 'storeTemplate']);
    Route::post('habit-template-update', [API\HabitController::class, 'updateTemplate']);
    Route::post('habit-template-delete', [API\HabitController::class, 'destroyTemplate']);
    Route::get('client-habit-progress', [API\HabitController::class, 'getClientProgress']);

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
    // Token dedicado (ability `tasks:sync`), usado por Claude Code, no por la UI del panel.
    Route::post('task-sync', [API\Admin\TaskController::class, 'sync']);

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

    // AÑADIDO: catalogo dinamico de tipos de medida corporal — el frontend
    // admin (UserDetailView.tsx) ya llamaba a estas rutas exactas contra un
    // mock MSW que nunca tuvo backend real.
    Route::get('body-metric-type-list', [API\Admin\BodyMetricTypeController::class, 'getList']);
    Route::post('body-metric-type-store', [API\Admin\BodyMetricTypeController::class, 'store']);
    Route::post('body-metric-type-update', [API\Admin\BodyMetricTypeController::class, 'update']);
    Route::post('body-metric-type-delete', [API\Admin\BodyMetricTypeController::class, 'destroy']);

    // ═══ V2: Admin Resources ══════════════════════════════════════════
    Route::get('admin-resource-list', [API\Admin\ResourceController::class, 'getList']);
    Route::get('admin-resource-detail', [API\Admin\ResourceController::class, 'getDetail']);
    Route::post('admin-resource-store', [API\Admin\ResourceController::class, 'store']);
    Route::post('admin-resource-update', [API\Admin\ResourceController::class, 'update']);
    Route::post('admin-resource-delete', [API\Admin\ResourceController::class, 'destroy']);
    Route::post('resource-assign', [API\Admin\ResourceController::class, 'assign']);
    Route::post('resource-unassign', [API\Admin\ResourceController::class, 'unassign']);

    // ═══ V2: Onboarding ═══════════════════════════════════════════════
    Route::get('admin-onboarding-list', [API\Admin\OnboardingController::class, 'getList']);
    Route::get('admin-onboarding-detail', [API\Admin\OnboardingController::class, 'getDetail']);
    // AÑADIDO: Motor de Auto-Regulación de Carga -- Plan de Optimización,
    // Ronda 7 (docs/Motor_Autorregulacion_Analisis.md): corregir el nivel
    // de experiencia autoevaluado por el cliente.
    Route::post('admin-onboarding-training-experience-update', [API\Admin\OnboardingController::class, 'updateTrainingExperience']);
    // Edición admin de las respuestas del onboarding (mismas reglas que la app, ver OnboardingAnswersService).
    Route::post('admin-onboarding-par-q-update', [API\Admin\OnboardingController::class, 'updateParQ']);
    Route::post('admin-onboarding-training-questionnaire-update', [API\Admin\OnboardingController::class, 'updateTrainingQuestionnaire']);
    Route::post('admin-onboarding-nutrition-questionnaire-update', [API\Admin\OnboardingController::class, 'updateNutritionQuestionnaire']);

    // ═══ V2: App Feedback ═════════════════════════════════════════════
    Route::get('admin-app-feedback-list', [API\Admin\AppFeedbackController::class, 'getList']);
    Route::get('admin-app-feedback-detail', [API\Admin\AppFeedbackController::class, 'getDetail']);
    Route::post('admin-app-feedback-update', [API\Admin\AppFeedbackController::class, 'update']);
});
