<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function getSettings(Request $request)
    {
        $page = $request->get('page', 'general');

        switch ($page) {
            case 'general':
                return $this->getGeneralSettings();
            case 'mail':
                return $this->getMailSettings();
            case 'firebase':
                return $this->getFirebaseSettings();
            case 'mobile':
                return $this->getMobileConfigSettings();
            case 'payment':
                return $this->getPaymentSettings();
            case 'subscription':
                return $this->getSubscriptionSettings();
            case 'mail_alerts':
                return $this->getMailAlertSettings();
            case 'login_enable':
                return $this->getLoginEnableSettings();
            case 'terms':
                return $this->getTermsSettings();
            case 'privacy':
                return $this->getPrivacySettings();
            default:
                return $this->getGeneralSettings();
        }
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->settings as $group => $values) {
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        Setting::updateOrCreate(
                            ['setting_group' => $group, 'setting_key' => $key],
                            ['setting_value' => $value]
                        );
                    }
                }
            }
        });

        return json_message_response('Settings updated successfully.');
    }

    public function getAppSettings(Request $request)
    {
        $settings = AppSetting::first();

        if (!$settings) {
            $settings = AppSetting::create([]);
        }

        return json_custom_response(['data' => $settings]);
    }

    public function updateAppSettings(Request $request)
    {
        $settings = AppSetting::first();

        if (!$settings) {
            $settings = AppSetting::create([]);
        }

        $settings->fill($request->all())->update();

        return json_custom_response([
            'message' => 'App settings updated successfully.',
            'data'    => $settings,
        ]);
    }

    private function getGeneralSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'general')->get(),
        ]);
    }

    private function getMailSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'MAIL')->get(),
        ]);
    }

    private function getFirebaseSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'FIREBASE')->get(),
        ]);
    }

    private function getMobileConfigSettings()
    {
        return json_custom_response([
            'data' => Setting::whereIn('setting_group', [
                'APPVERSION', 'ONESIGNAL', 'ADMOB', 'CRISP_CHAT_CONFIGURATION',
                'MOBILE_GAME_ENABLE', 'QUOTE', 'CURRENCY',
            ])->get(),
        ]);
    }

    private function getPaymentSettings()
    {
        return json_custom_response([
            'data' => Setting::whereIn('setting_group', [
                'STRIPE', 'RAZORPAY', 'PAYSTACK', 'FLUTTERWAVE',
                'PAYPAL', 'PAYTABS', 'PAYTM', 'ORANGEMONEY',
            ])->get(),
        ]);
    }

    private function getSubscriptionSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'subscription')->get(),
        ]);
    }

    private function getMailAlertSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'mail_alert')->get(),
        ]);
    }

    private function getLoginEnableSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'login_enable')->get(),
        ]);
    }

    private function getTermsSettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'terms')->get(),
        ]);
    }

    private function getPrivacySettings()
    {
        return json_custom_response([
            'data' => Setting::where('setting_group', 'privacy')->get(),
        ]);
    }
}
