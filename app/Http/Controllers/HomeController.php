<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Equipment;
use App\Models\Level;
use App\Models\WorkoutType;
use App\Models\Workout;
use App\Models\Diet;
use App\Models\CategoryDiet;
use Illuminate\Support\Facades\App;
use App\Models\BodyPart;
use App\Models\Exercise;
use App\Models\Tags;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Models\Product;
use App\Models\Package;
use App\Models\ProductCategory;
use App\Helpers\AuthHelper;
use App\Models\AppSetting;
use App\Models\BannerSlider;
use App\Models\DefaultKeyword;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\IngredientCategory;
use App\Models\IngredientUnitConversion;
use App\Models\LanguageDefaultList;
use App\Models\LanguageList;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTag;
use App\Models\Screen;
use App\Models\Subscription;
use Modules\Frontend\Models\Pages;
use Nwidart\Modules\Facades\Module;
use Modules\Frontend\Models\UserPreference;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /*
     * Dashboard Pages Routs
     */
    public function index(Request $request)
    {
        $auth_user = AuthHelper::authSession();

        if ($auth_user->hasRole('user') && Module::has('Frontend') && Module::isEnabled('Frontend')) {
            return redirect()->route('user.dashboard');
        }

        $assets = ['animation'];
        
        $data['subscription_setting'] = (int) SettingData('subscription', 'subscription_system') ?? 1;
        
        if( $data['subscription_setting'] == 1 ) {

            array_push($assets, 'chart');
            $filter = $request->filter ?? 'week';
            $type   = $request->type ?? 'line';

            $currency_code = SettingData('CURRENCY', 'CURRENCY_CODE') ?? 'USD';
            $currecy = currencyArray($currency_code);

            $data['currency_symbol'] = $currecy['symbol'] ?? '$';
            $data['currency_position'] = SettingData('CURRENCY', 'CURRENCY_POSITION') ?? 'left';

            if ($request->ajax()) {
                $data = $this->getSubscriptionChartData($filter, $type, $data['currency_symbol'], $data['currency_position']);
                return response()->json($data);
            }

            $subscription_amount = Subscription::where('payment_status', 'paid')->sum('total_amount');
            $data['total_subscription'] = Subscription::where('payment_status', 'paid')->count();
            $data['total_subscription_amount'] = humanReadableNumber($subscription_amount, 2, true);
            $data['subscription_amount'] = getPriceFormat($subscription_amount);
            $data['recent_subscription'] = Subscription::active()->orderBy('id', 'desc')->take(10)->get();
            $data['expire_soon_subscription'] = Subscription::active()->orderBy('subscription_end_date', 'asc')->take(10)->get();

            $data['line_chart'] = $this->getSubscriptionChartData('week', 'line', $data['currency_symbol'], $data['currency_position']);
            $data['pie_chart']  = $this->getSubscriptionChartData('week', 'pie', $data['currency_symbol'], $data['currency_position']);
        } else {
            $data['line_chart'] = $data['pie_chart'] = [];
        }

        $data['dashboard'] = [
            'total_user'        => User::role('user')->count(),
            'total_equipment'   => Equipment::count(),
            'total_level'       => Level::count(),
            'total_bodypart'    => BodyPart::count(),
            'total_workouttype' => WorkoutType::count(),
            'total_exercise'    => Exercise::count(),
            'total_workout'     => Workout::count(),
            'total_diet'        => Diet::count(),
            'total_post'        => Post::count(),
        ];               

        $data['exercise'] = Exercise::orderBy('id', 'desc')->take(10)->get();
        $data['workout'] = Workout::orderBy('id', 'desc')->take(10)->get();
        $data['diet'] = Diet::orderBy('id', 'desc')->take(10)->get();
        $data['post'] = Post::orderBy('id', 'desc')->take(10)->get();

        return view('dashboards.dashboard', compact('assets', 'data', 'auth_user'));
    }

    private function getSubscriptionChartData($filter, $type, $currency_symbol, $currency_position)
    {
        $now = now();
        $query = Subscription::query();

        switch ($filter) {
            case 'week':
                $query->whereBetween('created_at', [ $now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
            break;

            case 'month':
                $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
            break;

            case 'year':
                $query->whereYear('created_at', $now->year);
            break;
        }

        switch ($type) {

            // LINE CHART
            case 'line':
                $labels = [];
                $plan_counts = [];
                $amounts = [];

                switch ($filter) {
                    case 'week':
                        $start = $now->copy()->startOfWeek();
                        $stats = $query->selectRaw('DATE(created_at) as period, COUNT(*) as plan_count, COALESCE(SUM(total_amount), 0) as amount')
                                        ->groupBy('period')
                                        ->get()
                                        ->keyBy('period');

                        for ($i = 0; $i < 7; $i++) {
                            $day = $start->copy()->addDays($i);
                            $key = $day->toDateString();
                            $item = $stats->get($key);

                            $labels[] = $day->format('D');
                            $plan_counts[] = (int) ($item->plan_count ?? 0);
                            $amounts[] = (float) ($item->amount ?? 0);
                        }
                    break;

                    case 'month':
                        $days = $now->daysInMonth;
                        $stats = $query->selectRaw('DAY(created_at) as period, COUNT(*) as plan_count, COALESCE(SUM(total_amount), 0) as amount')
                                        ->groupBy('period')
                                        ->get()
                                        ->keyBy('period');

                        for ($d = 1; $d <= $days; $d++) {
                            $item = $stats->get($d);

                            $labels[] = $d;
                            $plan_counts[] = (int) ($item->plan_count ?? 0);
                            $amounts[] = (float) ($item->amount ?? 0);
                        }
                    break;

                    case 'year':
                        $stats = $query->selectRaw('MONTH(created_at) as period, COUNT(*) as plan_count, COALESCE(SUM(total_amount), 0) as amount')
                                        ->groupBy('period')
                                        ->get()
                                        ->keyBy('period');

                        for ($m = 1; $m <= 12; $m++) {
                            $item = $stats->get($m);

                            $labels[] = Carbon::create()->month($m)->format('M');
                            $plan_counts[] = (int) ($item->plan_count ?? 0);
                            $amounts[] = (float) ($item->amount ?? 0);
                        }
                    break;

                    default:
                        $startYear = (int) (Subscription::min(DB::raw('YEAR(created_at)')) ?? $now->year);
                        $endYear = $now->year;
                        $stats = Subscription::query()->selectRaw('YEAR(created_at) as period, COUNT(*) as plan_count, COALESCE(SUM(total_amount), 0) as amount')
                                    ->groupBy('period')
                                    ->get()
                                    ->keyBy('period');

                        for ($y = $startYear; $y <= $endYear; $y++) {
                            $item = $stats->get($y);

                            $labels[] = (string) $y;
                            $plan_counts[] = (int) ($item->plan_count ?? 0);
                            $amounts[] = (float) ($item->amount ?? 0);
                        }
                    break;
                }

                return [
                    'labels' => $labels,
                    'plan_counts' => $plan_counts,
                    'amounts' => $amounts,
                    'currency_symbol' => $currency_symbol,
                    'currency_position' => $currency_position,
                ];
            break;
            
            // PIE CHART
            case 'pie':
                $packageCounts = $query->selectRaw('package_id, COUNT(*) as total')
                                    ->groupBy('package_id')
                                    ->orderByDesc('total')
                                    ->get();

                $packages = Package::whereIn('id', $packageCounts->pluck('package_id')->filter()->all())->pluck('name', 'id');

                $package_names = [];
                $package_percentages = [];

                foreach ($packageCounts as $packageCount) {
                    $package_names[] = $packages[$packageCount->package_id] ?? 'Unknown';
                    $package_percentages[] = (int) $packageCount->total;
                }

                return [
                    'package_names' => $package_names,
                    'package_percentages' => $package_percentages,
                ];
            break;
        }
        return [];
    }

    public function changeStatus(Request $request)
    {
        $type = $request->type;
        $message_form = "";
        $message = __('message.update_form',['form' => __('message.status')]);
        switch ($type) {
            case 'role':
                    $role = Role::find($request->id);
                    $role->status = $request->status;
                    $role->save();
                    break;
            case 'pages':
                $user = Pages::find($request->id);
                $status = $request->status == 0 ? '0' : '1';
                $user->status = $status;
                $user->save();
                break;
            default:
                    $message = 'error';
                break;
        }

        if($message_form != null){
            $message =  __('message.added_form',['form' => $message_form ]);
            if($request->status == 0){
                $message = __('message.remove_form',['form' => $message_form ]);
            }
        }

        return json_custom_response(['message'=> $message , 'status' => true]);
    }

    public function removeFile(Request $request)
    {
        $type = $request->type;
        $data = null;

        switch ($type) {
            case 'equipment_image':
                $data = Equipment::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.equipment') ]);
                break;
            case 'workout_image':
                $data = Workout::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.workout') ]);
                break;
            case 'categorydiet_image':
                $data = CategoryDiet::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.categorydiet') ]);
                break;
            case 'diet_image':
                $data = Diet::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.diet') ]);
                break;
             case 'category_image':
                $data = Category::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.category') ]);
                break;
             case 'level_image':
                $data = Level::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.level') ]);
                break;
            case 'bodypart_image':
                $data = BodyPart::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.bodypart') ]);
                break;
            case 'exercise_image':
                $data = Exercise::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.exercise') ]);
                break;
            case 'exercise_video':
                $data = Exercise::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.exercise') ]);
                break;
            case 'post_image':
                $data = Post::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.post') ]);
                break;
            case 'productcategory_image':
                $data = ProductCategory::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.productcategory') ]);
                break;
            case 'product_image':
                $data = Product::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.product') ]);
                break;
            case 'language_image':
                $data = LanguageList::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.language') ]);
                break;
            case 'bannerslider_image':
                $data = BannerSlider::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.bannerslider') ]);
            break;
            case 'recipe_category_image':
                $data = RecipeCategory::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.recipecategory') ]);
            break;
            case 'recipe_tag_image':
                $data = RecipeTag::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.recipetag') ]);
            break;
            case 'recipe_image':
                $data = Recipe::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.recipe') ]);
            break;
            default:
                $data = AppSetting::find($request->id);
                $message = __('message.msg_removed',[ 'name' => __('message.image') ]);
            break;

        }

        if($data != null){
            $data->clearMediaCollection($type);
        }

        $response = [
                'status' => true,
                'id' => $request->id,
                'image' => getSingleMedia($data,$type),
                'preview' => $type."_preview",
                'message' => $message
        ];
        return json_custom_response($response);
    }

    public function changeLanguage($locale)
    {
        App::setLocale($locale);
        session()->put('locale', $locale);
        if( Module::has('Frontend') && Module::isEnabled('Frontend') ) {
            $user = auth()->user() ?? null;

            if( $user != null ) {
                UserPreference::myPreference()->updateOrCreate(
                    [ 'key' => 'language_code', 'user_id' => $user->id ],
                    [ 'value' => $locale ]
                );
            }
        }
        return redirect()->back();
    }

    public function getAjaxList(Request $request)
    {
        $items = array();
        $value = $request->q;

        switch ($request->type) {
            case 'permission':
                $items = Permission::select('id','name as text')->whereNull('parent_id');
                if($value != ''){
                    $items->where('name', 'LIKE', $value.'%');
                }
                $items = $items->get();
                break;
        case 'categorydiet':
            $items = CategoryDiet::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'level':
            $items = Level::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'equipment':
            $items = Equipment::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'ingredient_category':
            $items = IngredientCategory::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;

        case 'workout_type':
            $items = WorkoutType::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'bodypart':
            $items = BodyPart::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'exercise':
            $items = Exercise::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'tags':
                $items = Tags::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'category':
                $items = Category::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'diet':
            $items = Diet::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items->when(request('visibility'), function ($q) {
                        return $q->where('visibility', request('visibility'));
                });
                $items->when(request('user_id'), function ($q) {
                    $q->myNotAssignDiet();
                });
                $items = $items->get();
            break;
            case 'user':
                    $items = User::select('id','id as text')->where('status','active');
                    if($value != ''){
                        $items->where('id', 'LIKE', '%'.$value.'%');
                    }
                    $items = $items->get();
                    break;
        case 'productcategory':
                $items = ProductCategory::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'workout':
                $items = Workout::select('id','title as text');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items->when(request('visibility'), function ($q) {
                    return $q->where('visibility', request('visibility'));
                });

                $items->when(request('user_id'), function ($q) {
                    $q->myNotAssignWorkout();
                });

                $items = $items->get();

                if ( request('sub_type') == 'class_schedule_workout' ) {
                    $items = $items->push((object)[
                        'id' => 'other',
                        'text' => 'Other',
                    ]);
                }

                break;
        case 'hours':
                $data = [];
                for ($x = 0; $x < 24; $x++) {

                    $val = $x < 10 ? '0'.$x : $x ;
                    $data[] = [
                        'id' => $val,
                        'text' => $val,
                    ];
                }
               $items = $data;
                break;

        case 'minute':
                    $data = [];
                    for ($x = 0; $x < 60; $x++) {
                        $val = $x < 10 ? '0'.$x : $x ;
                        $data[] = [
                            'id' => $val,
                            'text' => $val,
                        ];
                    }
                   $items = $data;
                    break;

        case 'second':
                        $data = [];
                        for ($x = 0; $x < 60; $x++) {
                            $val = $x < 10 ? '0'.$x : $x ;
                            $data[] = [
                                'id' => $val,
                                'text' => $val,
                            ];
                        }
                       $items = $data;
                        break;
        case 'package':
            $items = Package::select('id','name as text')->where('status','active');
                if($value != ''){
                    $items->where('name', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'screen':
            $items = Screen::select('screenId','screenName as text');
            if($value != ''){
                $items->where('screenName', 'LIKE', '%'.$value.'%');
            }
            $items = $items->get()->map(function ($screen_id) {
                return ['id' => $screen_id->screenId, 'text' => $screen_id->text];
            });
            $items = $items;
            break;
        case 'language-list-data':
                $languageId = $request->id;
                $items = LanguageDefaultList::where('id', $languageId);
                $items = $items->first();
                break;
        case 'languagelist':
                $data = LanguageList::pluck('language_id')->toArray();
                $items = LanguageDefaultList::whereNotIn('id',$data)->select('id','default_language_name as text');
                    if($value != ''){
                        $items->where('default_language_name', 'LIKE', '%'.$value.'%');
                    }
                    $items = $items->get();
                break;
        case 'defaultkeyword':
                $items = DefaultKeyword::select('keyword_id as id','keyword_name as text');
                if($value != ''){
                    $items->where('keyword_name', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
                break;
        case 'languagetable':
                $items = LanguageList::select('id','language_name as text')->where('status', 1);
                    if($value != ''){
                        $items->where('language_name', 'LIKE', '%'.$value.'%');
                    }
                $items = $items->get();
                break;

        case 'timezone':
            $items = timeZoneList();
            foreach ($items as $k => $v) {
                if($value !=''){
                    if (strpos($v, $value) !== false) {

                    } else {
                        unset($items[$k]);
                    }
                }
            }
            $data = [];
            $i = 0;
            foreach ($items as $key => $row) {
                $data[$i] = [
                    'id'    => $key,
                    'text'  => $row,
                ];
                $i++;
            }
            $items=$data ;
            break;
        case 'ingredient':
            $items = Ingredient::select('id', 'title as text', 'density')->where('status', 'active');
            if ($value != '') {
                $items->where('title', 'LIKE', '%' . $value . '%');
            }
            if ($request->has('excluded_ids')) {
                $excludedIds = is_array($request->excluded_ids) ? $request->excluded_ids : explode(',', $request->excluded_ids);
                $items->whereNotIn('id', array_filter($excludedIds));
            }
            $items = $items->get();
        break;
        case 'measurement_unit':
            $items = MeasurementUnit::select('id', 'title as text')->where('status', 'active');
            if ($value != '') {
                $items->where('title', 'LIKE', '%' . $value . '%');
            }
            $items = $items->get();
        break;
        case 'recipecategory':
            $items = RecipeCategory::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
                $items = $items->get();
        break;
        case 'recipetag':
            $items = RecipeTag::select('id','title as text')->where('status','active');
                if($value != ''){
                    $items->where('title', 'LIKE', '%'.$value.'%');
                }
            $items = $items->get();
        break;
        case 'ingredient_units':
            $ingredientId = request('ingredient_id');
            $ingredient = Ingredient::find($ingredientId);
            
            $conversions = IngredientUnitConversion::where('ingredient_id', $ingredientId)
                ->get()
                ->keyBy('measurement_unit_id');

            $items = MeasurementUnit::active()
                ->get()
                ->map(function ($unit) use ($conversions) {
                    $conversion = $conversions->get($unit->id);
                    $titleText = $unit->title;
                    
                    $gramEquivalent = null;
                    $hasConversion = 0;
                    if ($conversion && $conversion->gram_equivalent) {
                        $titleText .= ' (1 ' . $unit->title . ' = ' . $conversion->gram_equivalent . 'g)';
                        $gramEquivalent = $conversion->gram_equivalent;
                        $hasConversion = 1;
                    } else {
                        $gramEquivalent = $unit->base_conversion_factor;
                        if ($unit->unit_type === 'volume' && $gramEquivalent) {
                            $titleText .= ' (1 ' . $unit->title . ' = ' . $gramEquivalent . 'ml)';
                        }
                    }

                    return [
                        'id' => $unit->id,
                        'text' => $titleText,
                        'gram_equivalent' => $gramEquivalent,
                        'unit_type' => $unit->unit_type,
                        'is_standard' => $unit->is_standard,
                        'has_conversion' => $hasConversion,
                        'is_amount_readonly' => 1,
                        'is_grams_readonly' => $hasConversion ? 1 : 0,
                    ];
                })->sortByDesc('has_conversion')->values();
            
            return response()->json([
                'status' => true, 
                'results' => $items,
                'density' => $ingredient->density ?? 1.0
            ]);
        break;

        default :
            break;
        }

        return response()->json(['status' => true, 'results' => $items]);
    }

    /*
     * Auth Routs
     */
    public function signin(Request $request)
    {
        return view('auth.login');
    }
    public function signup(Request $request)
    {
        return view('auth.register');
    }
    public function confirmmail(Request $request)
    {
        return view('auth.confirm-mail');
    }
    public function lockscreen(Request $request)
    {
        return view('auth.lockscreen');
    }
    public function recoverpw(Request $request)
    {
        return view('auth.forgot-password');
    }
    public function userprivacysetting(Request $request)
    {
        return view('auth.user-privacy-setting');
    }

    /*
     * Error Page Routs
     */

    public function error404(Request $request)
    {
        return view('errors.error404');
    }

    public function error500(Request $request)
    {
        return view('errors.error500');
    }
    public function maintenance(Request $request)
    {
        return view('errors.maintenance');
    }

    public function privacyPolicy()
    {
        $data = SettingData('privacy_policy', 'privacy_policy');
        if( Module::has('Frontend') && Module::isEnabled('Frontend') ) {
            return view('frontend::frontend.pages.privacy-policy', compact('data'));
        } else {
            return view('pages.privacy-policy', compact('data'));
        }
    }

    public function termsCondition()
    {
        $data = SettingData('terms_condition', 'terms_condition');
        if( Module::has('Frontend') && Module::isEnabled('Frontend') ) {
            return view('frontend::frontend.pages.terms-condition', compact('data'));
        } else {
            return view('pages.terms-condition', compact('data'));
        }
    }
}
