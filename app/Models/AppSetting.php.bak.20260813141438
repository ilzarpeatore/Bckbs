<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AppSetting extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [ 'site_name', 'site_email', 'site_description', 'site_copyright', 'facebook_url', 'twitter_url', 'linkedin_url' , 'language_option', 'contact_email', 'contact_number', 'instagram_url', 'help_support_url', 'color' ];

	public $timestamps = false;

	protected static function getData()
    {
		$data =	self::get()->first();

		if($data == null){
			$data = new self;
		}
		
        $data->site_logo = getSingleMedia($data,'site_logo');
		$data->site_favicon = getSingleMedia($data,'site_favicon');

		return $data;
	}

	public function setLanguageOptionAttribute($value)
    {
        $this->attributes['language_option'] = isset($value) ? json_encode($value):null;
    }

    public function getLanguageOptionAttribute($value)
    {
        $val = isset($value) ? json_decode($value, true) : null;

        if($val == null){
            $val = collect(languagesArray())->pluck('id')->toArray();
        }

        if(!in_array(env('DEFAULT_LANGUAGE'), $val)){
            array_push($val, env('DEFAULT_LANGUAGE'));
        }

        return $val;
    }

    public function getNotificationSettingsAttribute($value)
    {
        return isset($value) ? json_decode($value, TRUE) : [];
    }

    public function setNotificationSettingsAttribute($value)
    {
        $this->attributes['notification_settings'] = isset($value) ? json_encode($value) : null;
    }
}
