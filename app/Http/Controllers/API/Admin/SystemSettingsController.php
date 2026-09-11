<?php

namespace App\Http\Controllers\API\Admin;

use App\Helpers\EnvChange;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Migracion de las paginas de configuracion que solo existian en el admin
 * Blade viejo (routes/web.php -> SettingController) y no tenian ningun
 * equivalente en /api/admin -- item 4 y 5 de la auditoria de migracion
 * (docs/PENDIENTE... via sesion 2026-09-11). Misma logica/modelos que el
 * Blade (Setting key/value, PaymentGateway, EnvChange::envChanges), solo
 * expuesta como JSON en vez de vistas/redirects.
 */
class SystemSettingsController extends Controller
{
    /**
     * Whitelist de claves .env editables desde el admin -- exactamente las
     * mismas que ya expone el Blade (mobile-config aplanado con el mismo
     * criterio "SECCION_CLAVE" que SettingController::layoutPage() usa, mas
     * las 3 sueltas de settingsUpdates()/envChanges()). No se anade nada
     * nuevo: es la misma superficie de configuracion, solo con otra UI.
     */
    private function envWhitelist(): array
    {
        $keys = ['APP_NAME', 'APP_TIMEZONE', 'DEFAULT_LANGUAGE'];

        foreach (config('mobile-config') as $section => $fields) {
            foreach ($fields as $field => $default) {
                $keys[] = $section . '_' . $field;
            }
        }

        return $keys;
    }

    public function envSettings(Request $request)
    {
        $whitelist = $this->envWhitelist();
        $values = [];

        foreach ($whitelist as $key) {
            $values[$key] = env($key);
        }

        return json_custom_response(['data' => $values]);
    }

    public function updateEnvSettings(Request $request)
    {
        $whitelist = $this->envWhitelist();
        $settings = $request->input('settings', []);

        $rejected = [];
        foreach ($settings as $key => $value) {
            if (!in_array($key, $whitelist, true)) {
                $rejected[] = $key;
                continue;
            }
            EnvChange::envChanges($key, (string) $value);
        }

        \Illuminate\Support\Facades\Artisan::call('config:clear');

        return json_custom_response([
            'message'  => 'Configuracion actualizada.',
            'rejected' => $rejected,
        ]);
    }

    public function termsAndPrivacy(Request $request)
    {
        $terms = Setting::where('type', 'terms_condition')->where('key', 'terms_condition')->first();
        $privacy = Setting::where('type', 'privacy_policy')->where('key', 'privacy_policy')->first();

        return json_custom_response(['data' => [
            'terms_condition' => $terms->value ?? null,
            'privacy_policy'  => $privacy->value ?? null,
        ]]);
    }

    public function updateTermsCondition(Request $request)
    {
        $request->validate(['value' => 'required|string']);

        Setting::updateOrCreate(
            ['type' => 'terms_condition', 'key' => 'terms_condition'],
            ['value' => $request->value]
        );

        return json_custom_response(['message' => 'Terminos y condiciones actualizados.']);
    }

    public function updatePrivacyPolicy(Request $request)
    {
        $request->validate(['value' => 'required|string']);

        Setting::updateOrCreate(
            ['type' => 'privacy_policy', 'key' => 'privacy_policy'],
            ['value' => $request->value]
        );

        return json_custom_response(['message' => 'Politica de privacidad actualizada.']);
    }

    public function paymentGateway(Request $request, $type)
    {
        $gateway = PaymentGateway::where('type', $type)->first();

        return json_custom_response(['data' => $gateway]);
    }

    public function updatePaymentGateway(Request $request, $type)
    {
        $data = $request->except(['gateway_image']);
        $data['type'] = $type;

        $gateway = PaymentGateway::updateOrCreate(['type' => $type], $data);

        if ($request->hasFile('gateway_image')) {
            $gateway->clearMediaCollection('gateway_image');
            $gateway->addMediaFromRequest('gateway_image')->toMediaCollection('gateway_image');
        }

        return json_custom_response(['message' => 'Pasarela de pago actualizada.', 'data' => $gateway->fresh()]);
    }

    public function subscriptionSetting(Request $request)
    {
        $value = Setting::where('type', 'subscription')->where('key', 'subscription_system')->value('value') ?? '1';

        return json_custom_response(['data' => ['subscription_system' => $value]]);
    }

    public function updateSubscriptionSetting(Request $request)
    {
        $request->validate(['subscription_system' => 'required']);

        Setting::updateOrCreate(
            ['type' => 'subscription', 'key' => 'subscription_system'],
            ['value' => $request->subscription_system]
        );

        return json_custom_response(['message' => 'Configuracion de suscripcion actualizada.']);
    }

    public function loginEnableSetting(Request $request)
    {
        $value = Setting::where('type', 'login_enable')->where('key', 'login_enable')->value('value') ?? '0';

        return json_custom_response(['data' => ['login_enable' => $value]]);
    }

    public function updateLoginEnableSetting(Request $request)
    {
        $request->validate(['login_enable' => 'required']);

        Setting::updateOrCreate(
            ['type' => 'login_enable', 'key' => 'login_enable'],
            ['value' => $request->login_enable]
        );

        return json_custom_response(['message' => 'Configuracion de login actualizada.']);
    }

    public function mailAlertSettings(Request $request)
    {
        $values = [];
        foreach (config('constant.mail_alert') as $key => $default) {
            $values[$key] = Setting::where('key', $key)->value('value');
        }

        return json_custom_response(['data' => $values]);
    }

    public function updateMailAlertSettings(Request $request)
    {
        foreach (config('constant.mail_alert') as $key => $default) {
            if (!$request->has($key)) {
                continue;
            }
            Setting::updateOrCreate(
                ['key' => $key],
                ['type' => 'mail_alert', 'key' => $key, 'value' => $request->input($key)]
            );
        }

        return json_custom_response(['message' => 'Alertas de correo actualizadas.']);
    }
}
