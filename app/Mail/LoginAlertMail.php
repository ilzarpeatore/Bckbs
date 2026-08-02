<?php

namespace App\Mail;
use App\Models\AppSetting;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public $storeData;


    /**
     * Create a new message instance.
     */
     public function __construct($storeData)
    {
        $this->storeData = $storeData;
    }

    public function build()
    {
        $app_setting = AppSetting::first();

        // Use Markdown-friendly text (not raw HTML)
        $introLines = [
            __('message.new_login_detected_on_your_account') . ':',
        ];

        // Build a clean bullet list (Markdown style)
        $outroLines = [
            '- **' . __('message.ip_address') . ':** ' . $this->storeData['ip_address'],
            '- **' . __('message.city') . ':** ' . ($this->storeData['city']),
            '- **' . __('message.region') . ':** ' . ($this->storeData['region']),
            '- **' . __('message.country') . ':** ' . ($this->storeData['country']),
            '- **' . __('message.browser') . ':** ' . $this->storeData['browser'],
            '- **' . __('message.platform') . ':** ' . $this->storeData['platform'],
            '',
            __('message.thank_you'),
        ];

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('message.login_alert_subject'))
            ->markdown('emails.sendmail')
            ->with([
                'greeting' => __('message.hello'),
                'level' => 'success',
                'introLines' => $introLines,
                'outroLines' => $outroLines,
                'salutation' => "Regards,\n" . config('app.name'),
                'app_settings' => $app_setting,
            ]);
    }
}
