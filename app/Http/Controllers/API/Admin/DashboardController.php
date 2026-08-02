<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Equipment;
use App\Models\Level;
use App\Models\WorkoutType;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\Diet;
use App\Models\Post;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Recipe;
use App\Models\Ingredient;
use App\Models\RecipeCategory;
use App\Models\RecipeTag;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'admin_dashboard_' . $request->get('filter', 'week');
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            $data = [
                'total_users'           => User::role('user')->count(),
                'total_equipment'       => Equipment::count(),
                'total_levels'          => Level::count(),
                'total_workout_types'   => WorkoutType::count(),
                'total_exercises'       => Exercise::count(),
                'total_workouts'        => Workout::count(),
                'total_diets'           => Diet::count(),
                'total_posts'           => Post::count(),
                'total_products'        => Product::count(),
                'total_recipes'         => Recipe::count(),
                'total_ingredients'     => Ingredient::count(),
                'total_recipe_categories' => RecipeCategory::count(),
                'total_recipe_tags'     => RecipeTag::count(),
            ];

            $subscription_setting = (int) settingData('subscription', 'subscription_system') ?? 1;

            if ($subscription_setting == 1) {
                $sub_stats = Subscription::where('payment_status', 'paid')
                    ->selectRaw('COUNT(*) as total, COALESCE(SUM(total_amount), 0) as amount')
                    ->first();
                $data['subscription'] = [
                    'total'    => $sub_stats->total,
                    'amount'   => $sub_stats->amount,
                    'recent'   => Subscription::active()->with('user', 'package')->orderBy('id', 'desc')->take(10)->get(),
                    'expiring' => Subscription::active()->with('user', 'package')->orderBy('subscription_end_date', 'asc')->take(10)->get(),
                ];

                $data['charts'] = $this->getChartData($request->get('filter', 'week'));
            } else {
                $data['subscription'] = null;
                $data['charts'] = null;
            }

            $data['recent_exercise'] = Exercise::orderBy('id', 'desc')->take(10)->get();
            $data['recent_workout'] = Workout::orderBy('id', 'desc')->take(10)->get();
            $data['recent_diet'] = Diet::orderBy('id', 'desc')->take(10)->get();
            $data['recent_post'] = Post::orderBy('id', 'desc')->take(10)->get();

            return $data;
        });

        return json_custom_response(['data' => $data]);
    }

    private function getChartData($filter)
    {
        $now = now();
        $query = Subscription::where('payment_status', 'paid');

        switch ($filter) {
            case 'week':
                $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'month':
                $query->whereYear('created_at', $now->year)->whereMonth('created_at', $now->month);
                break;
            case 'year':
                $query->whereYear('created_at', $now->year);
                break;
        }

        $stats = $query->selectRaw('DATE(created_at) as period, COUNT(*) as plan_count, COALESCE(SUM(total_amount), 0) as amount')
                        ->groupBy('period')
                        ->get();

        return $stats;
    }
}
