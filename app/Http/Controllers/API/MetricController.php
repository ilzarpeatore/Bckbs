<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Metric;

class MetricController extends Controller
{
    public function getList(Request $request)
    {
        $metrics = Cache::remember('metrics_ordered', 3600, fn () => Metric::ordered()->get());

        return json_custom_response(['data' => $metrics]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'key'   => 'required|string|unique:metrics_catalog,key',
            'label' => 'required|string',
        ]);

        $metric = Metric::create($request->only(['key', 'label', 'unit', 'input_type', 'higher_is_better', 'order']));
        Cache::forget('metrics_ordered');

        return json_message_response(__('message.save_form', ['form' => 'Metric']));
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:metrics_catalog,id',
        ]);

        $metric = Metric::findOrFail($request->id);
        $metric->update($request->only(['label', 'unit', 'input_type', 'higher_is_better', 'order']));
        Cache::forget('metrics_ordered');

        return json_message_response(__('message.save_form', ['form' => 'Metric']));
    }
}
