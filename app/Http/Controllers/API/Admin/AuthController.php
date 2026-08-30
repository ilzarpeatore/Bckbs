<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password, 'user_type' => 'admin'])) {
            return json_message_response(__('auth.failed'), 401);
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            return json_message_response(__('message.account_banned'), 403);
        }

        if (!$user->hasRole('admin')) {
            Auth::logout();
            return json_message_response('Unauthorized. Admin access required.', 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        AuditLogger::log('login', 'users', $user->id, "Inicio de sesión de {$user->email}.", $user->id);

        $response = [
            'data' => [
                'id'          => $user->id,
                'name'        => $user->display_name ?? $user->first_name . ' ' . $user->last_name,
                'email'       => $user->email,
                'user_type'   => $user->user_type,
                'status'      => $user->status,
                'permissions' => $permissions,
            ],
            'token' => $token,
        ];

        return json_custom_response($response);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return json_message_response('Unauthorized. Admin access required.', 403);
        }

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        $response = [
            'data' => [
                'id'          => $user->id,
                'name'        => $user->display_name ?? $user->first_name . ' ' . $user->last_name,
                'email'       => $user->email,
                'user_type'   => $user->user_type,
                'status'      => $user->status,
                'permissions' => $permissions,
            ],
        ];

        return json_custom_response($response);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        AuditLogger::log('logout', 'users', $user->id, 'Cierre de sesión.', $user->id);

        $request->user()->currentAccessToken()->delete();

        return json_message_response('Logged out successfully.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'phone_number' => 'sometimes|nullable|string|max:20',
        ]);

        $user->update($request->only(['first_name', 'last_name', 'email', 'phone_number']));

        $response = [
            'message' => 'Profile updated successfully.',
            'data' => [
                'id'        => $user->id,
                'name'      => $user->display_name ?? $user->first_name . ' ' . $user->last_name,
                'email'     => $user->email,
                'user_type' => $user->user_type,
            ],
        ];

        return json_custom_response($response);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->old_password, $user->password)) {
            return json_message_response('Current password is incorrect.', 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return json_message_response('Password changed successfully.');
    }
}
