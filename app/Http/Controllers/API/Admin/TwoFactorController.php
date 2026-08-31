<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();

        return json_custom_response([
            'data' => [
                'enabled' => (bool) $user->two_factor_enabled,
                'backup_codes' => [],
            ],
        ]);
    }

    public function setup(Request $request)
    {
        $user = $request->user();

        $secret = TwoFactorService::generateSecret();
        $backupCodes = TwoFactorService::generateBackupCodes();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_backup_codes' => TwoFactorService::hashBackupCodes($backupCodes),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        return json_custom_response([
            'data' => [
                'qr_code' => TwoFactorService::qrCodeUrl($secret, $user->email, config('app.name')),
                'secret' => $secret,
                'backup_codes' => $backupCodes,
            ],
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return json_message_response('2FA ya está activado.', 400);
        }

        $secret = $user->two_factor_secret;

        if (!$secret) {
            return json_message_response('Inicia la configuración de 2FA primero.', 422);
        }

        $valid = TwoFactorService::verify($secret, $request->code)
            || TwoFactorService::verifyBackupCode($user, $request->code);

        if (!$valid) {
            return json_message_response('Código inválido.', 422);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        AuditLogger::log('enable_2fa', 'users', $user->id, 'Autenticación en dos pasos activada', $user->id);

        return json_message_response('2FA activado correctamente.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return json_message_response('2FA ya está desactivado.', 400);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        AuditLogger::log('disable_2fa', 'users', $user->id, 'Autenticación en dos pasos desactivada', $user->id);

        return json_message_response('2FA desactivado correctamente.');
    }
}
