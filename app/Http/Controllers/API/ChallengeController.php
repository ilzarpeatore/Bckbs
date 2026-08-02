<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Challenge;
use App\Models\ChallengeScore;

class ChallengeController extends Controller
{
    public function getList(Request $request)
    {
        $challenge = Challenge::with('scores.client')->active();

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $challenge->count();
            }
        }

        $challenge = $challenge->orderByDesc('start_date')->paginate($per_page);

        $response = [
            'pagination' => json_pagination_response($challenge),
            'data'       => $challenge->items(),
        ];

        return json_custom_response($response);
    }

    /** Ranking completo de un reto, ordenado. */
    public function getLeaderboard(Request $request)
    {
        $challenge = Challenge::with(['scores' => function ($q) {
            $q->with('client')->orderBy('rank');
        }])->find($request->id);

        if ($challenge == null) {
            return json_message_response(__('message.not_found_entry', ['name' => 'Challenge']));
        }

        return json_custom_response(['data' => $challenge]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'metric_type' => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'scope'       => 'required|in:shared,personal',
        ]);

        $challenge = Challenge::create([
            'coach_id'      => auth('sanctum')->id(),
            'title'         => $request->title,
            'description'   => $request->description,
            'metric_type'   => $request->metric_type,
            'target_metric' => $request->target_metric,
            'start_date'    => $request->start_date,
            'end_date'      => $request->end_date,
            'scope'         => $request->scope,
        ]);

        return json_message_response(__('message.save_form', ['form' => 'Challenge']));
    }

    /**
     * Actualiza (o crea) la puntuación de un cliente en un reto, y
     * recalcula el ranking de todo el reto tras la actualización.
     */
    public function updateScore(Request $request)
    {
        $request->validate([
            'challenge_id'  => 'required|exists:challenges,id',
            'client_id'     => 'required|exists:users,id',
            'current_value' => 'required|numeric',
        ]);

        ChallengeScore::updateOrCreate(
            ['challenge_id' => $request->challenge_id, 'client_id' => $request->client_id],
            ['current_value' => $request->current_value]
        );

        $this->recalculateRanking($request->challenge_id);

        return json_message_response(__('message.save_form', ['form' => 'Challenge Score']));
    }

    private function recalculateRanking(int $challenge_id): void
    {
        $scores = ChallengeScore::where('challenge_id', $challenge_id)
            ->orderByDesc('current_value')
            ->pluck('id');

        foreach ($scores as $index => $id) {
            ChallengeScore::where('id', $id)->update(['rank' => $index + 1]);
        }
    }
}
