<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserGraph;
use App\Http\Resources\UserGraphResource;
use App\Models\DailyStepsGoal;
use App\Models\DailyWaterGoal;
use Carbon\Carbon;

class UserGraphController extends Controller
{
    public function saveGraphData(Request $request)
    {
        $user_id = auth()->id();
        $date   = $request->date ?? now()->toDateString();

        $data = $request->all();
        $data['user_id'] = $user_id;

        // SEGURIDAD (barrido sistematico 2026-09-01, CRIT): updateOrCreate(['id'
        // => request('id')], ...) emparejaba solo por id, sin comprobar user_id
        // -- un atacante podia pasar el id de OTRO usuario y su registro de
        // datos de salud (peso, agua, pasos...) se reasignaba al atacante,
        // perdiendo/corrompiendo el valor original de la victima.
        if ($request->filled('id')) {
            $result = UserGraph::where('id', $request->id)->where('user_id', $user_id)->first();

            if ($result === null) {
                return json_message_response(__('message.not_found_entry', ['name' => __('message.data')]), 400);
            }

            unset($data['id']);
            $result->fill($data)->save();
        } else {
            unset($data['id']);
            $result = UserGraph::create($data);
        }

        $dailyGoal = null;
        if ($result->type === 'water_track') {
            $dailyGoal = DailyWaterGoal::lastRecordByDate($user_id, $date)->first();
        } elseif ($result->type === 'step_track') {
            $dailyGoal = DailyStepsGoal::lastRecordByDate($user_id, $date)->first();
        }
        
        if ($dailyGoal) {
            $dailyGoal->update([
                'goal_value' => $result->value
            ]);
        }
        $message = __('message.update_form', ['form' => __('message.data')]);

        if ($result->wasRecentlyCreated) {
            $message = __('message.save_form', ['form' => __('message.data')]);
        }

        return json_custom_response(['message' => $message, 'status' => true]);
    }

    public function getGraphDataList(Request $request)
    {
        $usergraph = UserGraph::myGraph();

        $usergraph->when(request('type'), function ($q) {
            return $q->where('type', request('type'));
        });

        $duration = request('duration');
        $today = Carbon::now();

        $usergraph->when(request('date'), function ($q) {
            return $q->whereDate('date', request('date'));
        });

        $usergraph->when($duration == 'week', function ($q) use ($today) {
            $from_date = Carbon::parse($today)->startOfWeek()->toDateTimeString();
            $to_date = Carbon::parse($today)->endOfWeek()->toDateTimeString();
            return $q->whereBetween('date', [$from_date, $to_date]);
        });

        $usergraph->when($duration == 'month', function ($q) use ($today) {
            return $q->whereMonth('date', $today->month);
        });

        $usergraph->when($duration == 'year', function ($q) use ($today) {
            return $q->where('date', '>=', $today->subYear());
        });

        $usergraph->when($duration == '3month', function ($q) use ($today) {
            return $q->where('date', '>=', Carbon::now()->subMonths(3));
        });

        $orderby = request('orderby') ?? 'desc';
        $per_page = min($usergraph->count(), 365);
        $usergraph = $usergraph->orderBy('date', $orderby)->paginate($per_page);

        $items = UserGraphResource::collection($usergraph);

        $response = [
            'pagination'    => json_pagination_response($items),
            'data'          => $items,
        ];

        return json_custom_response($response);
    }

    public function deleteGraphData(Request $request)
    {
        $usergraph = UserGraph::myGraph();

        $usergraph->when(request('id'), function ($q) {
            $id = explode(',', request('id'));
            return $q->whereIn('id', $id);
        });

        $usergraph->delete();
        $message = __('message.delete_form', ['form' => __('message.data')]);

        return json_message_response($message);
    }

    public function getGraphDetails(Request $request)
    {
        $item = UserGraph::myGraph()
            ->when($request->filled('type'), function ($q) {
                $q->where('type', request('type'));
            })
            ->latest('id')
            ->first();

        $response = [
            'data' => $item ? new UserGraphResource($item) : null,
        ];

        return json_custom_response($response);
    }

}
