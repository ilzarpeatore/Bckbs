<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginHistory extends Model
{
    protected $table = 'admin_login_history';

    protected $fillable = [
        'user_id',
        'ip_address',
        'city',
        'region',
        'country',
        'lat_long',
        'org',
        'postal_code',
        'timezone',
        'browser',
        'browser_version',
        'platform',
        'platform_version',
        'device',
        'is_mobile',
        'is_desktop',
        'is_tablet',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}

