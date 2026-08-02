<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BodyPart;
use App\Models\Level;
use App\Models\Diet;
use App\Models\Equipment;
use App\Http\Resources\BodyPartResource;
use App\Http\Resources\LevelResource;
use App\Http\Resources\EquipmentResource;
use App\Http\Resources\DietResource;
use App\Models\WorkoutType;
use App\Http\Resources\WorkoutTypeResource;
use App\Models\Workout;
use App\Http\Resources\WorkoutResource;
use App\Models\Exercise;
use App\Http\Resources\ExerciseResource;
use App\Models\Setting;
use App\Models\BannerSlider;
use App\Http\Resources\BannerSliderResource;
use App\Models\AssignWorkout;
use App\Http\Resources\AssignWorkoutResource;

class DashboardController extends Controller
{
    public function dashboardDetail(Request $request)
    {
        $bodypart = BodyPart::where('status','active')->orderBy('id','desc')->paginate(10);
        $level = Level::where('status','active')->orderBy('id','desc')->paginate(10);
        $equipment = Equipment::where('status','active')->orderBy('id','desc')->paginate(10);
        $diet = Diet::where('status','active')->orderBy('id','desc')->paginate(10);
        $workouttype = WorkoutType::where('status','active')->orderBy('id','desc')->paginate(10);
        $workout = Workout::where('status','active')->orderBy('id','desc')->paginate(10);
        $exercise = Exercise::where('status','active')->with('level', 'equipment')->orderBy('id','desc')->paginate(10);
        $featured_diet = Diet::where('status','active')->where('is_featured', 'yes')->orderBy('id', 'desc')->paginate(10);
        
        $response = [
            'bodypart'      => BodyPartResource::collection($bodypart),
            'level'         => LevelResource::collection($level),
            'equipment'     => EquipmentResource::collection($equipment),
            'exercise'      => ExerciseResource::collection($exercise),
            'diet'          => DietResource::collection($diet),
            'workouttype'   => WorkoutTypeResource::collection($workouttype),
            'workout'       => WorkoutResource::collection($workout),
            'featured_diet' => DietResource::collection($featured_diet),
        ];
        $response['subscription'] = SettingData('subscription', 'subscription_system') ?? '1';
        $response['AdsBannerDetail'] = SettingData('AdsBannerDetail') ?? [];
        return json_custom_response($response);
    }

    public function dashboard(Request $request)
    {
        $user = auth('sanctum')->user();

        $bodypart = BodyPart::where('status','active')->orderBy('id','desc')->paginate(10);
        $level = Level::where('status','active')->orderBy('id','desc')->paginate(10);
        $equipment = Equipment::where('status','active')->orderBy('id','desc')->paginate(10);
        $workout = Workout::active()->showAssignPrivateWorkout()->with('level', 'workouttype', 'userFavouriteWorkout')->orderBy('id','desc')->take(10)->get();
        $banner_slider = BannerSlider::active()->where(function ($query) {
                            $query->where('type', 'url')->orWhere(function ($q1) {
                                $q1->where('type', 'workout')
                                    ->whereHas('workout',  function ($q2) {
                                        $q2->active()->showAssignPrivateWorkout();
                                    });
                                });
                        })->orderBy('id','desc')->take(10)->get();
        $response = [
            'bodypart'      => BodyPartResource::collection($bodypart),
            'level'         => LevelResource::collection($level),
            'equipment'     => EquipmentResource::collection($equipment),
            'workout'       => WorkoutResource::collection($workout),
            'banner_slider' => BannerSliderResource::collection($banner_slider),
            'assign_workout'=> [],
        ];
        $response['subscription'] = SettingData('subscription', 'subscription_system') ?? '1';
        $response['AdsBannerDetail'] = SettingData('AdsBannerDetail') ?? [];

        $unread_count = 0;
        $unread_total_count = 0;

        if ( $user ) {
            $assign_workout = AssignWorkout::myAssignWorkout()->with('workout.level', 'workout.workouttype', 'workout.userFavouriteWorkout')->orderBy('id', 'desc')->take(10)->get();

            $response['assign_workout'] = AssignWorkoutResource::collection($assign_workout) ?? [];
            if(isset($user->unreadNotifications)){
                $unread_count = $user->unreadNotifications->where('created_at', '>', $user->last_notification_seen)->count() ;
                $unread_total_count = $user->unreadNotifications->count();
            }

            $goals = $user->userGraph()->latestPerType()->pluck('value','type');

            $response['daily_tracking_progress'] = [
                'today_steps' => (int) optional($user->userProfile)->today_daily_steps_value ?? 0,
                'today_water' => (int) optional($user->userProfile)->today_daily_water_value ?? 0,
                'step_goal'   => (int) ( $goals['step_track'] ?? 0 ),
                'water_goal'  => (int) ( $goals['water_track'] ?? 0 ),
            ];
        }

        $response['notification_data'] = [
            'counts'            => $unread_count,
            'unread_total_count'=> $unread_total_count
        ];
        
        return json_custom_response($response);
    }

    public function getSetting()
    {
        $setting = Setting::query();
        
        $setting->when(request('type'), function ($q) {
            return $q->where('type', request('type'));
        });

        $setting = $setting->get();
        $response = [
            'data' => $setting,
        ];
        $currency_code = SettingData('CURRENCY', 'CURRENCY_CODE') ?? 'USD';
        $currency = currencyArray($currency_code);
        $response['currency_setting'] = [
            'name' => $currency['name'] ?? 'United States (US) dollar',
            'symbol' => $currency['symbol'] ?? '$',
            'code' => strtolower($currency['code']) ?? 'usd',
            'position' => SettingData('CURRENCY', 'CURRENCY_POSITION') ?? 'left',
        ];

        return json_custom_response($response);
    }

    public function getMacroNurtrient()
    {
        $macrto_nutrieent = config('macro-nutrient');
        return $macrto_nutrieent;
    }
}