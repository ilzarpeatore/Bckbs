<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataTables\UsersDataTable;
use App\Models\User;
use App\Models\AssignDiet;
use App\Models\AssignWorkout;
use App\Models\Diet;
use App\Models\Workout;
use App\Helpers\AuthHelper;
use App\Models\Role;
use App\Http\Requests\UserRequest;
use App\DataTables\SubscriptionDataTable;
use App\Exports\UserReportExport;
use App\Models\UserGraph;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(UsersDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.user')]);
        $auth_user = AuthHelper::authSession();
        if (!$auth_user->can('user-list')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $assets = ['data-table'];

        $headerAction = $auth_user->can('user-add') ? '<a href="' . route('users.create') . '" class="btn btn-sm btn-primary" role="button">' . __('message.add_form_title', ['form' => __('message.user')]) . '</a>' : '';

        return $dataTable->render('global.user-datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('user-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['phone'];
        $pageTitle = __('message.add_form_title', ['form' => __('message.user')]);
        $roles = Role::where('status', 1)->where('name', 'user')->get()->pluck('title', 'name');
        return view('users.form', compact('pageTitle', 'roles', 'assets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {
        if (!auth()->user()->can('user-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $request['password'] = bcrypt($request->password);
        $request['username'] = $request->username ?? stristr($request->email, "@", true) . rand(100, 1000);
        $request['display_name'] = $request['first_name'] . " " . $request['last_name'];
        $user = User::create($request->all());

        storeMediaFile($user, $request->profile_image, 'profile_image');

        $user->assignRole($request->user_type);

        // Save user Profile data...
        // $user->userProfile()->create($request->userProfile);

        return redirect()->route('users.index')->withSuccess(__('message.save_form', ['form' => __('message.user')]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(SubscriptionDataTable $dataTable, $id, $tab = 'detail')
    {
        $user = auth()->user();
        if (!$user->can('user-show')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $pageTitle = __('message.view_form_title', ['form' => __('message.user')]);
        $data = User::with('userProfile', 'roles')->findOrFail($id);
        $profileImage = getSingleMedia($data, 'profile_image');

        $type = $tab ?? 'detail';

        switch ($type) {
            case 'detail':
                return view('users.profile', compact('pageTitle', 'data', 'type'));

            case 'subscription':
                return $dataTable->with('user_id', $id)->render('users.profile', compact('pageTitle', 'data', 'profileImage', 'type', 'pageTitle'));

            case 'matrix':
                return view('users.profile', compact('pageTitle', 'data', 'type'));

            case 'daily-step-water-tracking':
                $goals = $data->userGraph()->latestPerType()->pluck('value','type');                
                $filter = request('type', 'week');

                $stepGraph = $data->userProfile ? $data->userProfile->dailyStepsGoals()->latestData($data->id)->filter($filter)->orderBy('date', 'asc')->get(['date', 'value', 'goal_value']) : collect();   
    
                $waterGraph = $data->userProfile ? $data->userProfile->dailyWaterGoals()->latestData($data->id)->filter($filter)->orderBy('date', 'asc')->get(['date', 'value', 'goal_value']) : collect();

                if (request()->ajax()) {
                    $graph = request('step_value') ? $stepGraph : $waterGraph;
                    $dates = $graph->pluck('date')->unique()->values();
                    $values = [];
                    $goals  = [];
                    foreach ($dates as $date) {
                        $row = $graph->firstWhere('date', $date);

                        $values[] = $row->value ?? 0;
                        $goals[]  = $row->goal_value ?? 0;
                    }
                    return response()->json([
                        'dates'       => $dates,
                        'values'      => $values,
                        'goal_value'  => $goals,
                    ]);
                }
                $tracking = [
                    'step_goal'   => data_get($goals, 'step_track', 0),
                    'water_goal'  => data_get($goals, 'water_track', 0),
                    'today_steps' => $data->userProfile->today_daily_steps_value ?? 0,
                    'today_water' => $data->userProfile->today_daily_water_value ?? 0,
                    'step_graph'  => $stepGraph,
                    'water_graph' => $waterGraph,
                    'step_circle' => $data->userProfile?->step_circle ?? [],
                    'water_circle' => $data->userProfile?->water_circle ?? [],
                ];                
                return view('users.profile', compact('pageTitle', 'data', 'type', 'tracking'));

            default:
                return view('users.profile', compact('pageTitle', 'data', 'type'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('user-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = User::with('userProfile')->findOrFail($id);
        $assets = ['phone'];

        $pageTitle = __('message.update_form_title', ['form' => __('message.user')]);

        $profileImage = getSingleMedia($data, 'profile_image');
        $roles = Role::where('status', 1)->where('name', 'user')->get()->pluck('title', 'name');

        return view('users.form', compact('data', 'id', 'profileImage', 'pageTitle', 'roles', 'assets'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, $id)
    {
        if (!auth()->user()->can('user-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $user = User::with('userProfile')->findOrFail($id);
        $request['display_name'] = $request['first_name'] . " " . $request['last_name'];
        $user->removeRole($user->user_type);

        // User user data..
        $user->fill($request->all())->update();

        $user->assignRole($request['user_type']);
        // Save user image...
        if (isset($request->profile_image) && $request->profile_image != null) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        // user profile data....
        // $user->userProfile->fill($request->userProfile)->update();

        if (auth()->check()) {
            return redirect()->route('users.index')->withSuccess(__('message.update_form', ['form' => __('message.user')]));
        }
        return redirect()->back()->withSuccess(__('message.update_form', ['form' => __('message.user')]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('user-delete')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $user = User::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.user')]);

        if ($user != '') {
            $user->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.user')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message, 'datatable_reload' => 'dataTable_wrapper']);
        }

        return redirect()->back()->with($status, $message);
    }

    public function assignDietForm(Request $request)
    {
        $user_id = request('user_id');
        $view = view('users.assign_diet', compact('user_id'))->render();
        return response()->json(['data' =>  $view, 'status' => true]);
    }

    public function getAssignDietList(Request $request)
    {
        $user_id = request('user_id');
        $data = Diet::myDiet($user_id)->orderBy('id', 'desc')->get();
        $view = view('users.assign-diet-list', compact('user_id', 'data'))->render();
        return response()->json(['data' => $view, 'status' => true]);
    }

    public function assignDietSave(Request $request)
    {
        $data = $request->all();
        unset($data['_token']);
        AssignDiet::updateOrCreate(['user_id' => request('user_id'), 'diet_id' => request('diet_id')]);

        $message = __('message.assigndiet');

        return response()->json(['status' => true, 'type' => 'diet', 'event' => 'norefresh', 'message' => $message]);
    }

    public function getAssignWorkoutList(Request $request)
    {
        $user_id = request('user_id');
        $data = Workout::myWorkout($user_id)->orderBy('id', 'desc')->get();
        $view = view('users.assign-workout-list', compact('user_id', 'data'))->render();
        return response()->json(['data' => $view, 'status' => true]);
    }

    public function assignDietDestroy(Request $request)
    {
        $assigndiet = AssignDiet::where('user_id', $request->user_id)->where('diet_id', $request->diet_id)->first();

        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.assigndiet')]);
        if ($assigndiet != '') {
            $assigndiet->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.assigndiet')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message, 'type' => 'diet', 'event' => 'norefresh']);
        }

        return redirect()->back()->with($status, $message);
    }

    public function assignWorkoutForm(Request $request)
    {
        $user_id = request('user_id');
        $view = view('users.assign_workout', compact('user_id'))->render();
        return response()->json(['data' =>  $view, 'status' => true]);
    }
    public function assignWorkoutSave(Request $request)
    {
        $data = $request->all();
        unset($data['_token']);
        AssignWorkout::updateOrCreate(['user_id' => request('user_id'), 'workout_id' => request('workout_id')]);

        $message = __('message.assignworkout');

        return response()->json(['status' => true,  'type' => 'workout', 'event' => 'norefresh', 'message' => $message]);
    }

    public function assignWorkoutDestroy(Request $request)
    {
        $assignworkout = AssignWorkout::where('user_id', $request->user_id)->where('workout_id', $request->workout_id)->first();

        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.assignworkout')]);
        if ($assignworkout != '') {
            $assignworkout->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.assignworkout')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message, 'type' => 'workout', 'event' => 'norefresh']);
        }

        return redirect()->back()->with($status, $message);
    }

    public function fetchUserGraphData($type, $unit, $dateValue, $user_id)
    {

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $dataArray = [];
        $dateArray = [];

        switch ($dateValue) {
            case 'month':
                $data = UserGraph::whereYear('date', $currentYear)->whereMonth('date', $currentMonth);
                break;
            case 'week':
                $data = UserGraph::whereBetween('date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'year':
                $data = UserGraph::whereYear('date', $currentYear);
                break;
            default:
                $data = UserGraph::query();
                break;
        }

        $userWeightValues = $data->where('user_id', $user_id)->where('type', $type)->where('unit', $unit)->orderBy('date', 'asc')->get(['value', 'date']);
        foreach ($userWeightValues as $record) {
            $dataArray[] = $record->value;
            $dateArray[] = $record->date;
        }

        return [
            'data' => $dataArray,
            'category' => $dateArray
        ];
    }

    public function fetchUserGraph(Request $request)
    {
        if (!$request->ajax()) {
            abort(403, __('message.action_is_unauthorized'));
        }

        $unit = $request->unit;
        $type = $request->type;
        $dateValue = $request->dateValue;
        $user_id = $request->id;

        $graphData = $this->fetchUserGraphData($type, $unit, $dateValue, $user_id);
        $data = $graphData['data'];
        $category = $graphData['category'];

        return response()->json([
            'data' => $data,
            'category' => $category
        ]);
    }

    public function downloadUserReport(Request $request, $fileType = 'xlsx')
    {
        $startDate = $request->input('from_date');
        $endDate   = $request->input('to_date');

        $user_data = User::userReport()->get();
        $export = new UserReportExport($user_data, $request);
        $filenameDatePart = '';
        if ($startDate && $endDate) {
            $filenameDatePart = "_{$startDate}_to_{$endDate}";
        } elseif ($startDate) {
            $filenameDatePart = "_from_{$startDate}";
        } elseif ($endDate) {
            $filenameDatePart = "_to_{$endDate}";
        } else {
            $filenameDatePart = "_" . now()->format('Y-m-d');
        }

        $filename = "user-report{$filenameDatePart}.{$fileType}";
        $format = match (strtolower($fileType)) {
            'csv'  => \Maatwebsite\Excel\Excel::CSV,
            'xls'  => \Maatwebsite\Excel\Excel::XLS,
            'ods'  => \Maatwebsite\Excel\Excel::ODS,
            'html' => \Maatwebsite\Excel\Excel::HTML,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download($export, $filename, $format);
    }

    public function downloadUserReportPdf(Request $request)
    {

        $user_data = User::userReport()->get();

        $export     = new UserReportExport($user_data, $request);
        $collection = $export->collection();
        $mappedData = $collection->map([$export, 'map']);
        $headings   = $export->headings();

        $pdf = Pdf::loadView('users.user-report', [
            'headings'   => $headings,
            'mappedData' => $mappedData,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('user-report.pdf');
    }
}
