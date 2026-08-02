<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Quotes;
use App\Notifications\CommonNotification;

class SendQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:quotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Quotes to user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $quotes = Quotes::where('date', date('Y-m-d') )->get();
        if( count($quotes) > 0 ) {
            foreach ($quotes as $quote) {

                $user_list = User::where('status', 'active')->get();
                if( count($user_list) > 0 ) {
                    foreach ($user_list as $user) {
                        $notification_data = [
                            'id'        => $quote->id,
                            'quote_id'  => $quote->id,
                            'type'      => 'quotes',
                            'subject'   => $quote->title,
                            'message'   => $quote->message,
                        ];
                        $user->notify(new CommonNotification($notification_data['type'], $notification_data));
                    }
                }
            }
        }
    }
}
