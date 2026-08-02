<?php

namespace App\Traits;
use App\Models\AdminLoginHistory;
use App\Models\AdminLoginDevice;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use App\Mail\LoginAlertMail;
use Illuminate\Support\Facades\Mail;

trait LoginHistoryTrait {

    public function saveLoginHistory($user)
    {
        $login_alert = SettingData('mail_alert', 'login_alert');
        $login_history = SettingData('mail_alert', 'login_history');

        if ( !$login_alert && !$login_history ) {
            return;
        }
        $ip = request()->ip();
        $agent = new Agent();

        $response = Http::get("https://ipinfo.io/{$ip}/json");

        $ip_details = $response->json();

        $country_code   = $ip_details['country'] ?? null;
        $country_data   = $country_code ? country($country_code) : null;

        $browser = $agent->browser();
        $platform = $agent->platform();

        $store_data = [
            'user_id'       => $user->id,
            'ip_address'    => $ip,
            'city'          => $ip_details['city']    ?? null,
            'region'        => $ip_details['region']  ?? null,

            'country'       => $country_data['countryNameEn'] ?? $country_code,

            'lat_long'      => $ip_details['loc']     ?? null,
            'postal_code'   => $ip_details['postal']  ?? null,
            'timezone'      => $ip_details['timezone'] ?? null,

            'org'           => isset($ip_details['org']) ? Str::after($ip_details['org'], ' ') : null,

            'browser'           => $browser,
            'browser_version'   => $agent->version($browser),

            'platform'          => $platform,
            'platform_version'  => $agent->version($platform),

            'device'        => $agent->device(),
            'is_mobile'     => (int)$agent->isMobile(),
            'is_desktop'    => (int)$agent->isDesktop(),
            'is_tablet'     => (int)$agent->isTablet(),
        ];

        if ( $login_history ) {
            AdminLoginHistory::create($store_data);

            $existing_login_device = AdminLoginDevice::where('ip_address', $ip)->where('user_id', $user->id)->first();

            if ($existing_login_device) {
                $existing_login_device->update([
                    'session_id' => Session::getId(),
                    'login_at'   => now(),
                    'is_active'  => true,
                    'logout_at'  => null,
                ]);
            } else {
                AdminLoginDevice::create([
                    'user_id'    => $user->id,
                    'ip_address' => $ip,
                    'user_agent' => request()->header('User-Agent'),
                    'session_id' => Session::getId(),
                    'login_at'   => now(),
                    'is_active'  => true,
                ]);
            }
        }
        $store_data['email'] = $user->email;

        if( $login_alert ) {
            $this->loginAlert($store_data);
        }
    }

    public function loginAlert($data)
    {
        app()->terminating(function() use ($data) {
            try {
                Mail::to($data['email'])->send(new LoginAlertMail($data));
            } catch (\Throwable $e) {
                \Log::error('Login alert send failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

}
