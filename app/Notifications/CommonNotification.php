<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Support\Facades\Log;

class CommonNotification extends Notification
{
    use Queueable;
    public $type, $data, $subject, $notification_message;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
        $this->subject = str_replace("_"," ",ucfirst($this->data['subject']));
        $this->notification_message = $this->data['message'] != '' ? $this->data['message'] : __('message.default_notification_body');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $notifications = [];

        if( $notifiable->player_id != null ) {
            array_push($notifications, OneSignalChannel::class);
        }

        // AÑADIDO 2026-09-11: Expo Push (ver ExpoPushChannel) -- decision de
        // usar el servicio de push de Expo en vez de terminar de integrar
        // OneSignal, que llevaba con credenciales vacias sin usarse nunca.
        if( $notifiable->expo_push_token != null ) {
            array_push($notifications, ExpoPushChannel::class);
        }

        // Log::info('notifiable-'.$notifiable);
        return $notifications;
    }

    public function toOneSignal($notifiable)
    {
        $msg = strip_tags($this->notification_message);
        if (!isset($msg) && $msg == ''){
            $msg = __('message.default_notification_body');
        }

        $type = 'new_workout';
        if (isset($this->data['type']) && $this->data['type'] !== ''){
            $type = $this->data['type'];
        }

        // Log::info('onesignal notifiable'.json_encode($this->data));
        if( $type == 'push_notification' && $this->data['image'] != null ) {

            return OneSignalMessage::create()
                ->setSubject($this->subject)
                ->setBody($msg)
                ->setData('id',$this->data['id'])
                ->setData('type',$type)
                ->setIosAttachment($this->data['image'])
                ->setAndroidBigPicture($this->data['image']);
        } else {
        return OneSignalMessage::create()
            ->setSubject($this->subject)
            ->setBody($msg)
            ->setData('id',$this->data['id'])
            ->setData('posting_id', $this->data['posting_id'] ?? null)
            ->setData('type',$type);
        }
    }

    /**
     * AÑADIDO 2026-09-11 -- mismo contenido que toOneSignal() (subject/body/
     * type/id/posting_id/imagen), en el formato que espera la API de Expo
     * (https://exp.host/--/api/v2/push/send): {to, title, body, data, sound}
     * mas image_url/image de forma directa (Expo no distingue iOS/Android
     * attachment como OneSignal, usa 'richContent'/'mutable-content' via
     * plugins del cliente -- fuera de alcance de este cambio, el payload de
     * imagen queda disponible en 'data' para que el cliente decida mostrarla).
     */
    public function toExpoPush($notifiable)
    {
        $msg = strip_tags($this->notification_message);
        if ($msg === '') {
            $msg = __('message.default_notification_body');
        }

        $type = $this->data['type'] ?? 'new_workout';

        return [
            'to'    => $notifiable->expo_push_token,
            'title' => $this->subject,
            'body'  => $msg,
            'sound' => 'default',
            'data'  => [
                'id'          => $this->data['id'] ?? null,
                'posting_id'  => $this->data['posting_id'] ?? null,
                'type'        => $type,
                'image'       => $this->data['image'] ?? null,
            ],
        ];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
