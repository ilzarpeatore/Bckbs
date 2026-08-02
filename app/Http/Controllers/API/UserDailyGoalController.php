<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyWaterGoal;
use App\Models\DailyStepsGoal;
use App\Http\Resources\DailyWaterGoalResource;
use App\Http\Resources\DailyStepsGoalResource;
use App\Models\UserGraph;
use Carbon\Carbon;

class UserDailyGoalController extends Controller
{
    public function getDailyWaterGoalList(Request $request)
    {
        $water_goal = DailyWaterGoal::myWaterGoal();

        $water_goal->when(request('date'), function ($q) {
            return $q->whereDate('date', request('date'));
        });

        $duration = request('duration');
        $today = Carbon::now();

        $water_goal->when($duration == 'week', function ($q) use($today) {
            $from_date = Carbon::parse($today)->startOfWeek()->toDateTimeString();
            $to_date = Carbon::parse($today)->endOfWeek()->toDateTimeString();
            return $q->whereBetween('date',[ $from_date, $to_date ]);
        });

        $water_goal->when($duration == 'month', function ($q) use($today) {
            return $q->whereMonth('date',$today->month);
        });
        
        $water_goal->when($duration == 'year', function ($q) use($today) {
            return $q->where('date', '>=', $today->subYear());
        });

        $water_goal->when($duration == '3month', function ($q) use($today) { 
            return $q->where('date','>=',Carbon::now()->subMonths(3));
        });

        $orderby = request('orderby') ?? 'desc';
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if ( is_numeric($request->per_page) ) {
                $per_page = $request->per_page;
            }

            if ($request->per_page == -1 ) {
                $per_page = $water_goal->count();
            }
        }

        if( request('type') == 'graph' ) {
            $water_goal = $water_goal->whereIn('time', function ($sub) {
                        $sub->selectRaw('MAX(time)')
                            ->from('daily_water_goals as d2')
                            ->whereColumn('d2.user_id', 'daily_water_goals.user_id')
                            ->whereColumn('d2.date', 'daily_water_goals.date')
                            ->groupBy('d2.user_id', 'd2.date');
                        })
                    ->orderBy('date', $orderby);
        } else {
            $water_goal = $water_goal->orderBy('date', $orderby)->orderBy('time', $orderby);
        }
        $water_goal = $water_goal->paginate($per_page);

        $items = DailyWaterGoalResource::collection($water_goal);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function saveDailyWaterGoal(Request $request)
    {
        $user_id = auth()->id();
        $data = $request->all();
        $data['user_id'] = $user_id;
        $userGraphValue = UserGraph::myGraph()->where('type', 'water_track')->whereDate('date', $data['date'])->latest('id')->value('value') ?? 0;
        $data['goal_value'] = $userGraphValue;
        $result = DailyWaterGoal::create($data);
        
        $message = __('message.save_form',[ 'form' => __('message.data') ] );
        

        return json_custom_response(['message' => $message, 'status' => true ]);
    }

    public function getDailyStepsGoalList(Request $request)
    {
        $step_goal = DailyStepsGoal::myStepsGoal();

        $step_goal->when(request('date'), function ($q) {
            return $q->whereDate('date', request('date'));
        });

        $duration = request('duration');
        $today = Carbon::now();

        $step_goal->when($duration == 'week', function ($q) use($today) {
            $from_date = Carbon::parse($today)->startOfWeek()->toDateTimeString();
            $to_date = Carbon::parse($today)->endOfWeek()->toDateTimeString();
            return $q->whereBetween('date',[ $from_date, $to_date ]);
        });

        $step_goal->when($duration == 'month', function ($q) use($today) {
            return $q->whereMonth('date',$today->month);
        });
        
        $step_goal->when($duration == 'year', function ($q) use($today) {
            return $q->where('date', '>=', $today->subYear());
        });

        $step_goal->when($duration == '3month', function ($q) use($today) { 
            return $q->where('date','>=',Carbon::now()->subMonths(3));
        });

        $orderby = request('orderby') ?? 'desc';
        
        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)) {
            if ( is_numeric($request->per_page) ) {
                $per_page = $request->per_page;
            }

            if ($request->per_page == -1 ) {
                $per_page = $step_goal->count();
            }
        }

        if( request('type') == 'graph' ) {
            $step_goal = $step_goal->whereIn('time', function ($sub) {
                        $sub->selectRaw('MAX(time)')
                            ->from('daily_steps_goals as d2')
                            ->whereColumn('d2.user_id', 'daily_steps_goals.user_id')
                            ->whereColumn('d2.date', 'daily_steps_goals.date')
                            ->groupBy('d2.user_id', 'd2.date');
                        })
                    ->orderBy('date', $orderby);
        } else {
            $step_goal = $step_goal->orderBy('date', $orderby)->orderBy('time', $orderby);
        }

        $step_goal = $step_goal->paginate($per_page);
        $items = DailyStepsGoalResource::collection($step_goal);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];
        
        return json_custom_response($response);
    }

    public function saveDailyStepsGoal(Request $request)
    {
        $user_id = auth()->id();
        $data = $request->all();
        $data['user_id'] = $user_id;

        $userGraphValue = UserGraph::myGraph()->where('type', 'step_track')->whereDate('date', $data['date'])->latest('id')->value('value') ?? 0;
 
        $data['goal_value'] = $userGraphValue;
        $result = DailyStepsGoal::create($data);
        
        $message = __('message.save_form',[ 'form' => __('message.data') ] );
        

        return json_custom_response(['message' => $message, 'status' => true ]);
    }

    public function getV1DailyWaterGoalList(Request $request)
    {
        $filter = $request->get('filter', 'week');        
        $query = DailyWaterGoal::myWaterGoal()->filter($filter);
        
        $recordsByDate = $query->whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')
                ->from('daily_water_goals')
                ->where('user_id', auth()->id())
                ->groupBy('date');
        })->orderBy('date', 'asc')->get()->keyBy('date');

        return response()->json([
            'data' => $recordsByDate                
                ->map(fn($row) => [
                    'today_goal' => (int) ($row->goal_value ?? 0),
                    'value'      => (int) ($row->value ?? 0),
                    'date'       => $row->date,
                ])
                ->values()
        ]);
    }

    public function getV1DailyStepGoalList(Request $request)
    {
        $filter = $request->get('filter', 'week');        
        $query = DailyStepsGoal::myStepsGoal()->filter($filter);
        
        $recordsByDate = $query->whereIn('id', function ($q) {
            $q->selectRaw('MAX(id)')
                ->from('daily_steps_goals')
                ->where('user_id', auth()->id())
                ->groupBy('date');
        })->orderBy('date', 'asc')->get()->keyBy('date');

        return response()->json([
            'data' => $recordsByDate                
                ->map(fn($row) => [
                    'today_goal' => (int) ($row->goal_value ?? 0),
                    'value'      => (int) ($row->value ?? 0),
                    'date'       => $row->date,
                ])
                ->values()
        ]);
    }

}