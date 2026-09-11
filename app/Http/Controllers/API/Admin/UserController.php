<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\WelcomeMailService;
use App\Exports\UserReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class UserController extends BaseController
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }

    public function index(Request $request)
    {
        $query = User::role('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', config('constant.PER_PAGE_LIMIT', 10));
        if ($perPage == -1 || $perPage > 250) {
            $perPage = 250;
        }
        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $items = UserResource::collection($items);

        $response = [
            'pagination' => json_pagination_response($items),
            'data'       => $items,
        ];

        return json_custom_response($response);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'username'     => 'required|unique:users,username',
            'password'     => 'required|string|min:8',
            'phone_number' => 'nullable|string|max:20',
            'gender'       => 'nullable|in:male,female,other',
            'is_personal_client' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);
        $data['user_type'] = 'user';
        $data['status'] = $request->get('status', 'active');
        $data['display_name'] = $data['first_name'] . ' ' . $data['last_name'];

        $user = User::create($data);
        $user->assignRole('user');
        WelcomeMailService::sendFor($user);

        $response = [
            'message' => 'User created successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response, 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $request->validate([
            'first_name'   => 'sometimes|required|string|max:255',
            'last_name'    => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|required|email|unique:users,email,' . $id,
            'username'     => 'sometimes|required|unique:users,username,' . $id,
            'phone_number' => 'nullable|string|max:20',
            'gender'       => 'nullable|in:male,female,other',
            'status'       => 'sometimes|in:active,banned,pending',
            'password'     => 'nullable|string|min:8',
            'is_personal_client' => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'email', 'username',
            'phone_number', 'gender', 'status', 'is_personal_client',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $response = [
            'message' => 'User updated successfully.',
            'data'    => new UserResource($user),
        ];

        return json_custom_response($response);
    }

    public function destroy($id)
    {
        $user = User::where('id', $id)->where('user_type', 'user')->first();

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $user->delete();

        return json_message_response('User deleted successfully.');
    }

    public function graph(Request $request)
    {
        $user = User::with('userGraph')->find($request->user_id);

        if (!$user) {
            return json_message_response('User not found.', 404);
        }

        $graphs = $user->userGraph()->orderBy('id', 'desc')->paginate(
            $request->get('per_page', 10)
        );

        return json_custom_response([
            'pagination' => json_pagination_response($graphs),
            'data'       => $graphs,
        ]);
    }

    /**
     * Export de informe de usuarios (item 8, auditoria de migracion
     * 2026-09-11) -- mismo UserReportExport que ya usa
     * UserController::downloadUserReport/downloadUserReportPdf (Blade).
     */
    public function report(Request $request)
    {
        $fileType = $request->get('format', 'xlsx');
        $userData = User::userReport()->get();
        $export = new UserReportExport($userData, $request);

        if ($fileType === 'pdf') {
            $collection = $export->collection();
            $mappedData = $collection->map([$export, 'map']);
            $headings = $export->headings();

            $pdf = Pdf::loadView('users.user-report', [
                'headings'   => $headings,
                'mappedData' => $mappedData,
            ])->setPaper('a4', 'landscape');

            return $pdf->download('user-report.pdf');
        }

        $format = match (strtolower($fileType)) {
            'csv'   => \Maatwebsite\Excel\Excel::CSV,
            'xls'   => \Maatwebsite\Excel\Excel::XLS,
            'ods'   => \Maatwebsite\Excel\Excel::ODS,
            'html'  => \Maatwebsite\Excel\Excel::HTML,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download($export, 'user-report_' . now()->format('Y-m-d') . '.' . $fileType, $format);
    }
}
