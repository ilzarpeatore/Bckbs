<?php

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\AppSetting;
use App\Models\LanguageVersionDetail;
use App\Models\Setting;
use App\Models\Screen;
use App\Models\DefaultKeyword;
use App\Models\LanguageList;
use App\Models\LanguageWithKeyword;
use Illuminate\Support\Number;

function removeSession($session){
    if(Session::has($session)){
        Session::forget($session);
    }
    return true;
}

function appSettingData($type = 'get')
{
    if(Session::get('setting_data') == ''){
        $type='set';
    }
    switch ($type){
        case 'set' :
            $settings = AppSetting::first();
            Session::put('setting_data',$settings);
            break;
        default :
            break;
    }
    return Session::get('setting_data');
}

function randomString($length,$type = 'token'){
    if($type == 'password')
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
    elseif($type == 'username')
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    else
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $token = substr( str_shuffle( $chars ), 0, $length );
    return $token;
}

function activeRoute($route, $isClass = false): string
{
    $requestUrl = request()->fullUrl() === $route ? true : false;

    if($isClass) {
        return $requestUrl ? $isClass : '';
    } else {
        return $requestUrl ? 'active' : '';
    }
}

function checkMenuRoleAndPermission($menu)
{
    if (auth()->check()) {
        if ($menu->data('role') == null && auth()->user()->hasRole('admin')) {
            return true;
        }

        if($menu->data('permission') == null && $menu->data('role') == null) {
            return true;
        }

        if($menu->data('role') != null) {
            if(auth()->user()->hasAnyRole(explode(',', $menu->data('role')))) {
                return true;
            }
        }

        if($menu->data('permission') != null) {
            if( is_array($menu->data('permission')) ) {
                foreach($menu->data('permission') as $permission) {
                    if(auth()->user()->can($permission) ) {
                        return true;
                    }
                }
            } else {
                if(auth()->user()->can($menu->data('permission')) ) {
                    return true;
                }
            }
        }
    }
    return false;
}

function checkRecordExist($table_list,$column_name,$id){
    if(count($table_list) > 0){
        foreach($table_list as $table){
            $check_data = \DB::table($table)->where($column_name,$id)->count();
            if($check_data > 0) return false ;
        }
        return true;
    }
    return true;
}

// Model file save to storage by spatie media library
function storeMediaFile($model,$file,$name)
{
    if($file) {
        $model->clearMediaCollection($name);
        if (is_array($file)){
            foreach ($file as $key => $value){
                $model->addMedia($value)->toMediaCollection($name);
            }
        }else{
            $model->addMedia($file)->toMediaCollection($name);
        }
    }
    return true;
}

// Model file get by storage by spatie media library
function getSingleMedia($model, $collection = 'image_icon',$skip=true)
{
    if (!Auth::check() && $skip) {
        return asset('images/avatars/01.png');
    }
    if ($model !== null) {
        $media = $model->getFirstMedia($collection);
    }
    $imgurl= isset($media)?$media->getPath():'';
    if (file_exists($imgurl)) {
        return $media->getFullUrl();
    }
    else
    {
        switch ($collection) {
            case 'image_icon':
                $media = asset('images/avatars/01.png');
                break;
            case 'profile_image':
                $media = asset('images/avatars/01.png');
                break;
            case 'site_favicon':
                $media = asset('images/favicon.ico');
                break;
            case 'site_logo':
                $media = asset('images/logo.png');
                break;
            case 'site_dark_logo':
                $media = asset('images/dark_logo.png');
                break;
            case 'site_mini_logo':
                $media = asset('images/site_mini_logo.png');
                break;
            case 'site_dark_mini_logo':
                $media = asset('images/site_dark_mini_logo.png');
                break;
            default:
                $media = asset('images/default.png');
                break;
        }
        return $media;
    }
}

// File exist check
function getFileExistsCheck($media)
{
    $mediaCondition = false;
    if($media) {
        if($media->disk == 'public') {
            $mediaCondition = file_exists($media->getPath());
        } else {
            $mediaCondition = Storage::disk($media->disk)->exists($media->getPath());
        }
    }
    return $mediaCondition;
}

function getMediaFileExit($model, $collection = 'profile_image')
{
    if($model == null){
        return asset('images/avatars/01.png');
    }

    $media = $model->getFirstMedia($collection);

    return getFileExistsCheck($media);
}

function json_message_response( $message, $status_code = 200)
{	
	return response()->json( [ 'message' => $message ], $status_code );
}

function json_custom_response( $response, $status_code = 200 )
{
    return response()->json($response,$status_code);
}

function json_pagination_response($items)
{
    return [
        'total_items'   => $items->total(),
        'per_page'      => $items->perPage(),
        'currentPage'   => $items->currentPage(),
        'totalPages'    => $items->lastPage()
    ];
}


function languagesArray($ids = [])
{
    $language = [
        [ 'title' => 'Abkhaz' , 'id' => 'ab'],
        [ 'title' => 'Afar' , 'id' => 'aa'],
        [ 'title' => 'Afrikaans' , 'id' => 'af'],
        [ 'title' => 'Akan' , 'id' => 'ak'],
        [ 'title' => 'Albanian' , 'id' => 'sq'],
        [ 'title' => 'Amharic' , 'id' => 'am'],
        [ 'title' => 'Arabic' , 'id' => 'ar'],
        [ 'title' => 'Aragonese' , 'id' => 'an'],
        [ 'title' => 'Armenian' , 'id' => 'hy'],
        [ 'title' => 'Assamese' , 'id' => 'as'],
        [ 'title' => 'Avaric' , 'id' => 'av'],
        [ 'title' => 'Avestan' , 'id' => 'ae'],
        [ 'title' => 'Aymara' , 'id' => 'ay'],
        [ 'title' => 'Azerbaijani' , 'id' => 'az'],
        [ 'title' => 'Bambara' , 'id' => 'bm'],
        [ 'title' => 'Bashkir' , 'id' => 'ba'],
        [ 'title' => 'Basque' , 'id' => 'eu'],
        [ 'title' => 'Belarusian' , 'id' => 'be'],
        [ 'title' => 'Bengali' , 'id' => 'bn'],
        [ 'title' => 'Bihari' , 'id' => 'bh'],
        [ 'title' => 'Bislama' , 'id' => 'bi'],
        [ 'title' => 'Bosnian' , 'id' => 'bs'],
        [ 'title' => 'Breton' , 'id' => 'br'],
        [ 'title' => 'Bulgarian' , 'id' => 'bg'],
        [ 'title' => 'Burmese' , 'id' => 'my'],
        [ 'title' => 'Catalan; Valencian' , 'id' => 'ca'],
        [ 'title' => 'Chamorro' , 'id' => 'ch'],
        [ 'title' => 'Chechen' , 'id' => 'ce'],
        [ 'title' => 'Chichewa; Chewa; Nyanja' , 'id' => 'ny'],
        [ 'title' => 'Chinese' , 'id' => 'zh'],
        [ 'title' => 'Chuvash' , 'id' => 'cv'],
        [ 'title' => 'Cornish' , 'id' => 'kw'],
        [ 'title' => 'Corsican' , 'id' => 'co'],
        [ 'title' => 'Cree' , 'id' => 'cr'],
        [ 'title' => 'Croatian' , 'id' => 'hr'],
        [ 'title' => 'Czech' , 'id' => 'cs'],
        [ 'title' => 'Danish' , 'id' => 'da'],
        [ 'title' => 'Divehi; Dhivehi; Maldivian;' , 'id' => 'dv'],
        [ 'title' => 'Dutch' , 'id' => 'nl'],
        [ 'title' => 'English' , 'id' => 'en'],
        [ 'title' => 'Esperanto' , 'id' => 'eo'],
        [ 'title' => 'Estonian' , 'id' => 'et'],
        [ 'title' => 'Ewe' , 'id' => 'ee'],
        [ 'title' => 'Faroese' , 'id' => 'fo'],
        [ 'title' => 'Fijian' , 'id' => 'fj'],
        [ 'title' => 'Finnish' , 'id' => 'fi'],
        [ 'title' => 'French' , 'id' => 'fr'],
        [ 'title' => 'Fula; Fulah; Pulaar; Pular' , 'id' => 'ff'],
        [ 'title' => 'Galician' , 'id' => 'gl'],
        [ 'title' => 'Georgian' , 'id' => 'ka'],
        [ 'title' => 'German' , 'id' => 'de'],
        [ 'title' => 'Greek, Modern' , 'id' => 'el'],
        [ 'title' => 'Guaraní' , 'id' => 'gn'],
        [ 'title' => 'Gujarati' , 'id' => 'gu'],
        [ 'title' => 'Haitian; Haitian Creole' , 'id' => 'ht'],
        [ 'title' => 'Hausa' , 'id' => 'ha'],
        [ 'title' => 'Hebrew (modern)' , 'id' => 'he'],
        [ 'title' => 'Herero' , 'id' => 'hz'],
        [ 'title' => 'Hindi' , 'id' => 'hi'],
        [ 'title' => 'Hiri Motu' , 'id' => 'ho'],
        [ 'title' => 'Hungarian' , 'id' => 'hu'],
        [ 'title' => 'Interlingua' , 'id' => 'ia'],
        [ 'title' => 'Indonesian' , 'id' => 'id'],
        [ 'title' => 'Interlingue' , 'id' => 'ie'],
        [ 'title' => 'Irish' , 'id' => 'ga'],
        [ 'title' => 'Igbo' , 'id' => 'ig'],
        [ 'title' => 'Inupiaq' , 'id' => 'ik'],
        [ 'title' => 'Ido' , 'id' => 'io'],
        [ 'title' => 'Icelandic' , 'id' => 'is'],
        [ 'title' => 'Italian' , 'id' => 'it'],
        [ 'title' => 'Inuktitut' , 'id' => 'iu'],
        [ 'title' => 'Japanese' , 'id' => 'ja'],
        [ 'title' => 'Javanese' , 'id' => 'jv'],
        [ 'title' => 'Kalaallisut, Greenlandic' , 'id' => 'kl'],
        [ 'title' => 'Kannada' , 'id' => 'kn'],
        [ 'title' => 'Kanuri' , 'id' => 'kr'],
        [ 'title' => 'Kashmiri' , 'id' => 'ks'],
        [ 'title' => 'Kazakh' , 'id' => 'kk'],
        [ 'title' => 'Khmer' , 'id' => 'km'],
        [ 'title' => 'Kikuyu, Gikuyu' , 'id' => 'ki'],
        [ 'title' => 'Kinyarwanda' , 'id' => 'rw'],
        [ 'title' => 'Kirghiz, Kyrgyz' , 'id' => 'ky'],
        [ 'title' => 'Komi' , 'id' => 'kv'],
        [ 'title' => 'Kongo' , 'id' => 'kg'],
        [ 'title' => 'Korean' , 'id' => 'ko'],
        [ 'title' => 'Kurdish' , 'id' => 'ku'],
        [ 'title' => 'Kwanyama, Kuanyama' , 'id' => 'kj'],
        [ 'title' => 'Latin' , 'id' => 'la'],
        [ 'title' => 'Luxembourgish, Letzeburgesch' , 'id' => 'lb'],
        [ 'title' => 'Luganda' , 'id' => 'lg'],
        [ 'title' => 'Limburgish, Limburgan, Limburger' , 'id' => 'li'],
        [ 'title' => 'Lingala' , 'id' => 'ln'],
        [ 'title' => 'Lao' , 'id' => 'lo'],
        [ 'title' => 'Lithuanian' , 'id' => 'lt'],
        [ 'title' => 'Luba-Katanga' , 'id' => 'lu'],
        [ 'title' => 'Latvian' , 'id' => 'lv'],
        [ 'title' => 'Manx' , 'id' => 'gv'],
        [ 'title' => 'Macedonian' , 'id' => 'mk'],
        [ 'title' => 'Malagasy' , 'id' => 'mg'],
        [ 'title' => 'Malay' , 'id' => 'ms'],
        [ 'title' => 'Malayalam' , 'id' => 'ml'],
        [ 'title' => 'Maltese' , 'id' => 'mt'],
        [ 'title' => 'Māori' , 'id' => 'mi'],
        [ 'title' => 'Marathi (Marāṭhī)' , 'id' => 'mr'],
        [ 'title' => 'Marshallese' , 'id' => 'mh'],
        [ 'title' => 'Mongolian' , 'id' => 'mn'],
        [ 'title' => 'Nauru' , 'id' => 'na'],
        [ 'title' => 'Navajo, Navaho' , 'id' => 'nv'],
        [ 'title' => 'Norwegian Bokmål' , 'id' => 'nb'],
        [ 'title' => 'North Ndebele' , 'id' => 'nd'],
        [ 'title' => 'Nepali' , 'id' => 'ne'],
        [ 'title' => 'Ndonga' , 'id' => 'ng'],
        [ 'title' => 'Norwegian Nynorsk' , 'id' => 'nn'],
        [ 'title' => 'Norwegian' , 'id' => 'no'],
        [ 'title' => 'Nuosu' , 'id' => 'ii'],
        [ 'title' => 'South Ndebele' , 'id' => 'nr'],
        [ 'title' => 'Occitan' , 'id' => 'oc'],
        [ 'title' => 'Ojibwe, Ojibwa' , 'id' => 'oj'],
        [ 'title' => 'Oromo' , 'id' => 'om'],
        [ 'title' => 'Oriya' , 'id' => 'or'],
        [ 'title' => 'Ossetian, Ossetic' , 'id' => 'os'],
        [ 'title' => 'Panjabi, Punjabi' , 'id' => 'pa'],
        [ 'title' => 'Pāli' , 'id' => 'pi'],
        [ 'title' => 'Persian' , 'id' => 'fa'],
        [ 'title' => 'Polish' , 'id' => 'pl'],
        [ 'title' => 'Pashto, Pushto' , 'id' => 'ps'],
        [ 'title' => 'Portuguese' , 'id' => 'pt'],
        [ 'title' => 'Quechua' , 'id' => 'qu'],
        [ 'title' => 'Romansh' , 'id' => 'rm'],
        [ 'title' => 'Kirundi' , 'id' => 'rn'],
        [ 'title' => 'Romanian, Moldavian, Moldovan' , 'id' => 'ro'],
        [ 'title' => 'Russian' , 'id' => 'ru'],
        [ 'title' => 'Sanskrit (Saṁskṛta)' , 'id' => 'sa'],
        [ 'title' => 'Sardinian' , 'id' => 'sc'],
        [ 'title' => 'Sindhi' , 'id' => 'sd'],
        [ 'title' => 'Northern Sami' , 'id' => 'se'],
        [ 'title' => 'Samoan' , 'id' => 'sm'],
        [ 'title' => 'Sango' , 'id' => 'sg'],
        [ 'title' => 'Serbian' , 'id' => 'sr'],
        [ 'title' => 'Scottish Gaelic; Gaelic' , 'id' => 'gd'],
        [ 'title' => 'Shona' , 'id' => 'sn'],
        [ 'title' => 'Sinhala, Sinhalese' , 'id' => 'si'],
        [ 'title' => 'Slovak' , 'id' => 'sk'],
        [ 'title' => 'Slovene' , 'id' => 'sl'],
        [ 'title' => 'Somali' , 'id' => 'so'],
        [ 'title' => 'Southern Sotho' , 'id' => 'st'],
        [ 'title' => 'Spanish; Castilian' , 'id' => 'es'],
        [ 'title' => 'Sundanese' , 'id' => 'su'],
        [ 'title' => 'Swahili' , 'id' => 'sw'],
        [ 'title' => 'Swati' , 'id' => 'ss'],
        [ 'title' => 'Swedish' , 'id' => 'sv'],
        [ 'title' => 'Tamil' , 'id' => 'ta'],
        [ 'title' => 'Telugu' , 'id' => 'te'],
        [ 'title' => 'Tajik' , 'id' => 'tg'],
        [ 'title' => 'Thai' , 'id' => 'th'],
        [ 'title' => 'Tigrinya' , 'id' => 'ti'],
        [ 'title' => 'Tibetan Standard, Tibetan, Central' , 'id' => 'bo'],
        [ 'title' => 'Turkmen' , 'id' => 'tk'],
        [ 'title' => 'Tagalog' , 'id' => 'tl'],
        [ 'title' => 'Tswana' , 'id' => 'tn'],
        [ 'title' => 'Tonga (Tonga Islands)' , 'id' => 'to'],
        [ 'title' => 'Turkish' , 'id' => 'tr'],
        [ 'title' => 'Tsonga' , 'id' => 'ts'],
        [ 'title' => 'Tatar' , 'id' => 'tt'],
        [ 'title' => 'Twi' , 'id' => 'tw'],
        [ 'title' => 'Tahitian' , 'id' => 'ty'],
        [ 'title' => 'Uighur, Uyghur' , 'id' => 'ug'],
        [ 'title' => 'Ukrainian' , 'id' => 'uk'],
        [ 'title' => 'Urdu' , 'id' => 'ur'],
        [ 'title' => 'Uzbek' , 'id' => 'uz'],
        [ 'title' => 'Venda' , 'id' => 've'],
        [ 'title' => 'Vietnamese' , 'id' => 'vi'],
        [ 'title' => 'Volapük' , 'id' => 'vo'],
        [ 'title' => 'Walloon' , 'id' => 'wa'],
        [ 'title' => 'Welsh' , 'id' => 'cy'],
        [ 'title' => 'Wolof' , 'id' => 'wo'],
        [ 'title' => 'Western Frisian' , 'id' => 'fy'],
        [ 'title' => 'Xhosa' , 'id' => 'xh'],
        [ 'title' => 'Yiddish' , 'id' => 'yi'],
        [ 'title' => 'Yoruba' , 'id' => 'yo'],
        [ 'title' => 'Zhuang, Chuang' , 'id' => 'za']
    ];

    if(!empty($ids))
    {
        $language = collect($language)->whereIn('id',$ids)->values();
    }

    return $language;
}

function SettingData($type, $key = null)
{
    $setting = Setting::where('type',$type);
   
    $setting->when($key != null, function ($q) use($key) {
        return $q->where('key', $key);
    });

    if( $key != null ) {
        $setting_data = $setting->pluck('value')->first();
    } else {
        $setting_data = $setting->get();
    }
   return $setting_data;
}
function getPriceFormat($price)
{
    if (gettype($price) == 'string') {
        return $price;
    }
    if($price === null){
        $price = 0;
    }
    
    $currency_code = SettingData('CURRENCY', 'CURRENCY_CODE') ?? 'USD';
    $currecy = currencyArray($currency_code);

    $code = $currecy['symbol'] ?? '$';
    $position = SettingData('CURRENCY', 'CURRENCY_POSITION') ?? 'left';
    
    if ($position == 'left') {
        $price = $code."".number_format( (float) $price,2,'.','');
    } else {
        $price = number_format( (float) $price, 2,'.','')."".$code;
    }

    return $price;
}
function imageExtention($media)
{
    $extention = null;
    if($media != null){
        $path_info = pathinfo($media);
        $extention = $path_info['extension'];
    }
    return $extention;
}

function currencyArray($code = null)
{
    $currency = [
        [ 'code' => 'AED', 'name' => 'United Arab Emirates dirham', 'symbol' => 'د.إ'],
        [ 'code' => 'AFN', 'name' => 'Afghan afghani', 'symbol' => '؋'],
        [ 'code' => 'ALL', 'name' => 'Albanian lek', 'symbol' => 'L'],
        [ 'code' => 'AMD', 'name' => 'Armenian dram', 'symbol' => 'AMD'],
        [ 'code' => 'ANG', 'name' => 'Netherlands Antillean guilder', 'symbol' => 'ƒ'],
        [ 'code' => 'AOA', 'name' => 'Angolan kwanza', 'symbol' => 'Kz'],
        [ 'code' => 'ARS', 'name' => 'Argentine peso', 'symbol' => '$'],
        [ 'code' => 'AUD', 'name' => 'Australian dollar', 'symbol' => '$'],
        [ 'code' => 'AWG', 'name' => 'Aruban florin', 'symbol' => 'Afl.'],
        [ 'code' => 'AZN', 'name' => 'Azerbaijani manat', 'symbol' => 'AZN'],
        [ 'code' => 'BAM', 'name' => 'Bosnia and Herzegovina convertible mark', 'symbol' => 'KM'],
        [ 'code' => 'BBD', 'name' => 'Barbadian dollar', 'symbol' => '$'],
        [ 'code' => 'BDT', 'name' => 'Bangladeshi taka', 'symbol' => '৳ '],
        [ 'code' => 'BGN', 'name' => 'Bulgarian lev', 'symbol' => 'лв.'],
        [ 'code' => 'BHD', 'name' => 'Bahraini dinar', 'symbol' => '.د.ب'],
        [ 'code' => 'BIF', 'name' => 'Burundian franc', 'symbol' => 'Fr'],
        [ 'code' => 'BMD', 'name' => 'Bermudian dollar', 'symbol' => '$'],
        [ 'code' => 'BND', 'name' => 'Brunei dollar', 'symbol' => '$'],
        [ 'code' => 'BOB', 'name' => 'Bolivian boliviano', 'symbol' => 'Bs.'],
        [ 'code' => 'BRL', 'name' => 'Brazilian real', 'symbol' => 'R$'],
        [ 'code' => 'BSD', 'name' => 'Bahamian dollar', 'symbol' => '$'],
        [ 'code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => '฿'],
        [ 'code' => 'BTN', 'name' => 'Bhutanese ngultrum', 'symbol' => 'Nu.'],
        [ 'code' => 'BWP', 'name' => 'Botswana pula', 'symbol' => 'P'],
        [ 'code' => 'BYR', 'name' => 'Belarusian ruble (old)', 'symbol' => 'Br'],
        [ 'code' => 'BYN', 'name' => 'Belarusian ruble', 'symbol' => 'Br'],
        [ 'code' => 'BZD', 'name' => 'Belize dollar', 'symbol' => '$'],
        [ 'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$'],
        [ 'code' => 'CDF', 'name' => 'Congolese franc', 'symbol' => 'Fr'],
        [ 'code' => 'CHF', 'name' => 'Swiss franc', 'symbol' => 'CHF'],
        [ 'code' => 'CLP', 'name' => 'Chilean peso', 'symbol' => '$'],
        [ 'code' => 'CNY', 'name' => 'Chinese yuan', 'symbol' => '¥'],
        [ 'code' => 'COP', 'name' => 'Colombian peso', 'symbol' => '$'],
        [ 'code' => 'CRC', 'name' => 'Costa Rican colón', 'symbol' => '₡'],
        [ 'code' => 'CUC', 'name' => 'Cuban convertible peso', 'symbol' => '$'],
        [ 'code' => 'CUP', 'name' => 'Cuban peso', 'symbol' => '$'],
        [ 'code' => 'CVE', 'name' => 'Cape Verdean escudo', 'symbol' => '$'],
        [ 'code' => 'CZK', 'name' => 'Czech koruna', 'symbol' => 'Kč'],
        [ 'code' => 'DJF', 'name' => 'Djiboutian franc', 'symbol' => 'Fr'],
        [ 'code' => 'DKK', 'name' => 'Danish krone', 'symbol' => 'kr.'],
        [ 'code' => 'DOP', 'name' => 'Dominican peso', 'symbol' => 'RD$'],
        [ 'code' => 'DZD', 'name' => 'Algerian dinar', 'symbol' => 'د.ج'],
        [ 'code' => 'EGP', 'name' => 'Egyptian pound', 'symbol' => 'EGP'],
        [ 'code' => 'ERN', 'name' => 'Eritrean nakfa', 'symbol' => 'Nfk'],
        [ 'code' => 'ETB', 'name' => 'Ethiopian birr', 'symbol' => 'Br'],
        [ 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
        [ 'code' => 'FJD', 'name' => 'Fijian dollar', 'symbol' => '$'],
        [ 'code' => 'FKP', 'name' => 'Falkland Islands pound', 'symbol' => '£'],
        [ 'code' => 'GBP', 'name' => 'Pound sterling', 'symbol' => '£'],
        [ 'code' => 'GEL', 'name' => 'Georgian lari', 'symbol' => 'ლ'],
        [ 'code' => 'GGP', 'name' => 'Guernsey pound', 'symbol' => '£'],
        [ 'code' => 'GHS', 'name' => 'Ghana cedi', 'symbol' => '₵'],
        [ 'code' => 'GIP', 'name' => 'Gibraltar pound', 'symbol' => '£'],
        [ 'code' => 'GMD', 'name' => 'Gambian dalasi', 'symbol' => 'D'],
        [ 'code' => 'GNF', 'name' => 'Guinean franc', 'symbol' => 'Fr'],
        [ 'code' => 'GTQ', 'name' => 'Guatemalan quetzal', 'symbol' => 'Q'],
        [ 'code' => 'GYD', 'name' => 'Guyanese dollar', 'symbol' => '$'],
        [ 'code' => 'HKD', 'name' => 'Hong Kong dollar', 'symbol' => '$'],
        [ 'code' => 'HNL', 'name' => 'Honduran lempira', 'symbol' => 'L'],
        [ 'code' => 'HRK', 'name' => 'Croatian kuna', 'symbol' => 'kn'],
        [ 'code' => 'HTG', 'name' => 'Haitian gourde', 'symbol' => 'G'],
        [ 'code' => 'HUF', 'name' => 'Hungarian forint', 'symbol' => 'Ft'],
        [ 'code' => 'IDR', 'name' => 'Indonesian rupiah', 'symbol' => 'Rp'],
        [ 'code' => 'ILS', 'name' => 'Israeli new shekel', 'symbol' => '₪'],
        [ 'code' => 'IMP', 'name' => 'Manx pound', 'symbol' => '£'],
        [ 'code' => 'INR', 'name' => 'Indian rupee', 'symbol' => '₹'],
        [ 'code' => 'IQD', 'name' => 'Iraqi dinar', 'symbol' => 'د.ع'],
        [ 'code' => 'IRR', 'name' => 'Iranian rial', 'symbol' => '﷼'],
        [ 'code' => 'IRT', 'name' => 'Iranian toman', 'symbol' => 'تومان'],
        [ 'code' => 'ISK', 'name' => 'Icelandic króna', 'symbol' => 'kr.'],
        [ 'code' => 'JEP', 'name' => 'Jersey pound', 'symbol' => '£'],
        [ 'code' => 'JMD', 'name' => 'Jamaican dollar', 'symbol' => '$'],
        [ 'code' => 'JOD', 'name' => 'Jordanian dinar', 'symbol' => 'د.ا'],
        [ 'code' => 'JPY', 'name' => 'Japanese yen', 'symbol' => '¥'],
        [ 'code' => 'KES', 'name' => 'Kenyan shilling', 'symbol' => 'KSh'],
        [ 'code' => 'KGS', 'name' => 'Kyrgyzstani som', 'symbol' => 'сом'],
        [ 'code' => 'KHR', 'name' => 'Cambodian riel', 'symbol' => '៛'],
        [ 'code' => 'KMF', 'name' => 'Comorian franc', 'symbol' => 'Fr'],
        [ 'code' => 'KPW', 'name' => 'North Korean won', 'symbol' => '₩'],
        [ 'code' => 'KRW', 'name' => 'South Korean won', 'symbol' => '₩'],
        [ 'code' => 'KWD', 'name' => 'Kuwaiti dinar', 'symbol' => 'د.ك'],
        [ 'code' => 'KYD', 'name' => 'Cayman Islands dollar', 'symbol' => '$'],
        [ 'code' => 'KZT', 'name' => 'Kazakhstani tenge', 'symbol' => '₸'],
        [ 'code' => 'LAK', 'name' => 'Lao kip', 'symbol' => '₭'],
        [ 'code' => 'LBP', 'name' => 'Lebanese pound', 'symbol' => 'ل.ل'],
        [ 'code' => 'LKR', 'name' => 'Sri Lankan rupee', 'symbol' => 'රු'],
        [ 'code' => 'LRD', 'name' => 'Liberian dollar', 'symbol' => '$'],
        [ 'code' => 'LSL', 'name' => 'Lesotho loti', 'symbol' => 'L'],
        [ 'code' => 'LYD', 'name' => 'Libyan dinar', 'symbol' => 'ل.د'],
        [ 'code' => 'MAD', 'name' => 'Moroccan dirham', 'symbol' => 'د.م.'],
        [ 'code' => 'MDL', 'name' => 'Moldovan leu', 'symbol' => 'MDL'],
        [ 'code' => 'MGA', 'name' => 'Malagasy ariary', 'symbol' => 'Ar'],
        [ 'code' => 'MKD', 'name' => 'Macedonian denar', 'symbol' => 'ден'],
        [ 'code' => 'MMK', 'name' => 'Burmese kyat', 'symbol' => 'Ks'],
        [ 'code' => 'MNT', 'name' => 'Mongolian tögrög', 'symbol' => '₮'],
        [ 'code' => 'MOP', 'name' => 'Macanese pataca', 'symbol' => 'P'],
        [ 'code' => 'MRU', 'name' => 'Mauritanian ouguiya', 'symbol' => 'UM'],
        [ 'code' => 'MUR', 'name' => 'Mauritian rupee', 'symbol' => '₨'],
        [ 'code' => 'MVR', 'name' => 'Maldivian rufiyaa', 'symbol' => '.ރ'],
        [ 'code' => 'MWK', 'name' => 'Malawian kwacha', 'symbol' => 'MK'],
        [ 'code' => 'MXN', 'name' => 'Mexican peso', 'symbol' => '$'],
        [ 'code' => 'MYR', 'name' => 'Malaysian ringgit', 'symbol' => 'RM'],
        [ 'code' => 'MZN', 'name' => 'Mozambican metical', 'symbol' => 'MT'],
        [ 'code' => 'NAD', 'name' => 'Namibian dollar', 'symbol' => 'N$'],
        [ 'code' => 'NGN', 'name' => 'Nigerian naira', 'symbol' => '₦'],
        [ 'code' => 'NIO', 'name' => 'Nicaraguan córdoba', 'symbol' => 'C$'],
        [ 'code' => 'NOK', 'name' => 'Norwegian krone', 'symbol' => 'kr'],
        [ 'code' => 'NPR', 'name' => 'Nepalese rupee', 'symbol' => '₨'],
        [ 'code' => 'NZD', 'name' => 'New Zealand dollar', 'symbol' => '$'],
        [ 'code' => 'OMR', 'name' => 'Omani rial', 'symbol' => 'ر.ع.'],
        [ 'code' => 'PAB', 'name' => 'Panamanian balboa', 'symbol' => 'B/.'],
        [ 'code' => 'PEN', 'name' => 'Sol', 'symbol' => 'S/'],
        [ 'code' => 'PGK', 'name' => 'Papua New Guinean kina', 'symbol' => 'K'],
        [ 'code' => 'PHP', 'name' => 'Philippine peso', 'symbol' => '₱'],
        [ 'code' => 'PKR', 'name' => 'Pakistani rupee', 'symbol' => '₨'],
        [ 'code' => 'PLN', 'name' => 'Polish złoty', 'symbol' => 'zł'],
        [ 'code' => 'PRB', 'name' => 'Transnistrian ruble', 'symbol' => 'р.'],
        [ 'code' => 'PYG', 'name' => 'Paraguayan guaraní', 'symbol' => '₲'],
        [ 'code' => 'QAR', 'name' => 'Qatari riyal', 'symbol' => 'ر.ق'],
        [ 'code' => 'RON', 'name' => 'Romanian leu', 'symbol' => 'lei'],
        [ 'code' => 'RSD', 'name' => 'Serbian dinar', 'symbol' => 'рсд'],
        [ 'code' => 'RUB', 'name' => 'Russian ruble', 'symbol' => '₽'],
        [ 'code' => 'RWF', 'name' => 'Rwandan franc', 'symbol' => 'Fr'],
        [ 'code' => 'SAR', 'name' => 'Saudi riyal', 'symbol' => 'ر.س'],
        [ 'code' => 'SBD', 'name' => 'Solomon Islands dollar', 'symbol' => '$'],
        [ 'code' => 'SCR', 'name' => 'Seychellois rupee', 'symbol' => '₨'],
        [ 'code' => 'SDG', 'name' => 'Sudanese pound', 'symbol' => 'ج.س.'],
        [ 'code' => 'SEK', 'name' => 'Swedish krona', 'symbol' => 'kr'],
        [ 'code' => 'SGD', 'name' => 'Singapore dollar', 'symbol' => '$'],
        [ 'code' => 'SHP', 'name' => 'Saint Helena pound', 'symbol' => '£'],
        [ 'code' => 'SLL', 'name' => 'Sierra Leonean leone', 'symbol' => 'Le'],
        [ 'code' => 'SOS', 'name' => 'Somali shilling', 'symbol' => 'Sh'],
        [ 'code' => 'SRD', 'name' => 'Surinamese dollar', 'symbol' => '$'],
        [ 'code' => 'SSP', 'name' => 'South Sudanese pound', 'symbol' => '£'],
        [ 'code' => 'STN', 'name' => 'São Tomé and Príncipe dobra', 'symbol' => 'Db'],
        [ 'code' => 'SYP', 'name' => 'Syrian pound', 'symbol' => 'ل.س'],
        [ 'code' => 'SZL', 'name' => 'Swazi lilangeni', 'symbol' => 'E'],
        [ 'code' => 'THB', 'name' => 'Thai baht', 'symbol' => '฿'],
        [ 'code' => 'TJS', 'name' => 'Tajikistani somoni', 'symbol' => 'ЅМ'],
        [ 'code' => 'TMT', 'name' => 'Turkmenistan manat', 'symbol' => 'm'],
        [ 'code' => 'TND', 'name' => 'Tunisian dinar', 'symbol' => 'د.ت'],
        [ 'code' => 'TOP', 'name' => 'Tongan paʻanga', 'symbol' => 'T$'],
        [ 'code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => '₺'],
        [ 'code' => 'TTD', 'name' => 'Trinidad and Tobago dollar', 'symbol' => '$'],
        [ 'code' => 'TWD', 'name' => 'New Taiwan dollar', 'symbol' => 'NT$'],
        [ 'code' => 'TZS', 'name' => 'Tanzanian shilling', 'symbol' => 'Sh'],
        [ 'code' => 'UAH', 'name' => 'Ukrainian hryvnia', 'symbol' => '₴'],
        [ 'code' => 'UGX', 'name' => 'Ugandan shilling', 'symbol' => 'UGX'],
        [ 'code' => 'USD', 'name' => 'United States (US) dollar', 'symbol' => '$'],
        [ 'code' => 'UYU', 'name' => 'Uruguayan peso', 'symbol' => '$'],
        [ 'code' => 'UZS', 'name' => 'Uzbekistani som', 'symbol' => 'UZS'],
        [ 'code' => 'VEF', 'name' => 'Venezuelan bolívar', 'symbol' => 'Bs F'],
        [ 'code' => 'VES', 'name' => 'Bolívar soberano', 'symbol' => 'Bs.S'],
        [ 'code' => 'VND', 'name' => 'Vietnamese đồng', 'symbol' => '₫'],
        [ 'code' => 'VUV', 'name' => 'Vanuatu vatu', 'symbol' => 'Vt'],
        [ 'code' => 'WST', 'name' => 'Samoan tālā', 'symbol' => 'T'],
        [ 'code' => 'XAF', 'name' => 'Central African CFA franc', 'symbol' => 'CFA'],
        [ 'code' => 'XCD', 'name' => 'East Caribbean dollar', 'symbol' => '$'],
        [ 'code' => 'XOF', 'name' => 'West African CFA franc', 'symbol' => 'CFA'],
        [ 'code' => 'XPF', 'name' => 'CFP franc', 'symbol' => 'Fr'],
        [ 'code' => 'YER', 'name' => 'Yemeni rial', 'symbol' => '﷼'],
        [ 'code' => 'ZAR', 'name' => 'South African rand', 'symbol' => 'R'],
        [ 'code' => 'ZMW', 'name' => 'Zambian kwacha', 'symbol' => 'ZK'],
    ];

    if($code != null)
    {
        $currency = collect($currency)->where('code', $code)->first();
    }
    return $currency;
}

function stringLong($str = '', $type = 'title', $length = 0) //Add … if string is too long
{
    if ($length != 0) {
        return strlen($str) > $length ? mb_substr($str, 0, $length) . "..." : $str;
    }
    if ($type == 'desc') {
        return strlen($str) > 150 ? mb_substr($str, 0, 150) . "..." : $str;
    } elseif ($type == 'title') {
        return strlen($str) > 15 ? mb_substr($str, 0, 25) . "..." : $str;
    } else {
        return $str;
    }
}
function mighty_language_direction($language = null)
{
    if (empty($language)) {
        $language = app()->getLocale();
    }
    $language = strtolower(substr($language, 0, 2));
    $rtlLanguages = [
        'ar', //  'العربية', Arabic
        'arc', //  'ܐܪܡܝܐ', Aramaic
        'bcc', //  'بلوچی مکرانی', Southern Balochi`
        'bqi', //  'بختياري', Bakthiari
        'ckb', //  'Soranî / کوردی', Sorani Kurdish
        'dv', //  'ދިވެހިބަސް', Dhivehi
        'fa', //  'فارسی', Persian
        'glk', //  'گیلکی', Gilaki
        'he', //  'עברית', Hebrew
        'lrc', //- 'لوری', Northern Luri
        'mzn', //  'مازِرونی', Mazanderani
        'pnb', //  'پنجابی', Western Punjabi
        'ps', //  'پښتو', Pashto
        'sd', //  'سنڌي', Sindhi
        'ug', //  'Uyghurche / ئۇيغۇرچە', Uyghur
        'ur', //  'اردو', Urdu
        'yi', //  'ייִדיש', Yiddish
    ];
    if (in_array($language, $rtlLanguages)) {
        return 'rtl';
    }

    return 'ltr';
}
function maskSensitiveInfo($type, $info)
{
    if ($type === 'email' && empty($info) or $type === 'contact_number' && empty($info) || ($type === 'ip_address' && empty($info))) {
        return '-';
    }
    switch ($type) {
        case 'email':
            $parts = explode('@', $info);
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = substr($username, 0, 1) . str_repeat('*', strlen($username) - 1);
            return $maskedUsername . '@' . $domain;

        case 'contact_number':
            return substr($info, 0, 3) . str_repeat('*', strlen($info) - 4) . substr($info, -2);

        case 'ip_address':
                $parts = explode('.', $info);
                if(count($parts) === 4) {
                    return $parts[0] . '.' . $parts[1] . '.*.*';
                }
                return substr($info, 0, 5) . '***';
        default:
            return $info;
    }
}

function timeAgoFormate($date)
{
    if($date==null){
        return '-';
    }

    // date_default_timezone_set('UTC');
    // $diff_time= \Carbon\Carbon::createFromTimeStamp(strtotime($date))->diffForHumans();
    $timezone = auth()->user()->timezone ?? SettingData ('string', 'timezone') ?? config('app.timezone');
    
    $diff_time = $date->setTimezone($timezone)->diffForHumans();

    return $diff_time;
}
if (!function_exists('extractRelativeTime')) {
    function extractRelativeTime(string $created_at): string
    {
        if (strpos($created_at, 'on') !== false) {
            return trim(explode('on', $created_at)[0]);
        }
        return $created_at;
    }
}

function timeZoneList()
{
    $list = \DateTimeZone::listAbbreviations();
    $idents = \DateTimeZone::listIdentifiers();

    $data = $offset = $added = array();
    foreach ($list as $abbr => $info) {
        foreach ($info as $zone) {
            if (!empty($zone['timezone_id']) and !in_array($zone['timezone_id'], $added) and in_array($zone['timezone_id'], $idents)) {

                $z = new \DateTimeZone($zone['timezone_id']);
                $c = new \DateTime(null, $z);
                $zone['time'] = $c->format('H:i a');
                $offset[] = $zone['offset'] = $z->getOffset($c);
                $data[] = $zone;
                $added[] = $zone['timezone_id'];
            }
        }
    }

    array_multisort($offset, SORT_ASC, $data);
    $options = array();
    foreach ($data as $key => $row) {
        $options[$row['timezone_id']] = $row['time'] . ' - ' . formatOffset($row['offset'])  . ' ' . $row['timezone_id'];
    }
    return $options;
}

function formatOffset($offset)
{
    $hours = $offset / 3600;
    $remainder = $offset % 3600;
    $sign = $hours > 0 ? '+' : '-';
    $hour = (int) abs($hours);
    $minutes = (int) abs($remainder / 60);

    if ($hour == 0 and $minutes == 0) {
        $sign = ' ';
    }
    return 'GMT' . $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');
}

function dateAgoFormate($date,$type2='')
{
    if($date == null || $date == '0000-00-00 00:00:00') {
        return '-';
    }

    $diff_time1 = \Carbon\Carbon::createFromTimeStamp(strtotime($date))->diffForHumans();
    $datetime = new \DateTime($date);
    $la_time = new \DateTimeZone(auth()->check() ? auth()->user()->timezone ?? 'UTC' : 'UTC');
    $datetime->setTimezone($la_time);
    $diff_date = $datetime->format('Y-m-d H:i:s');

    $diff_time = \Carbon\Carbon::parse($diff_date)->isoFormat('LLL');

    if($type2 != ''){
        return $diff_time;
    }

    return $diff_time1 .' on '.$diff_time;
}

function maskedEmail($email) {
    // return preg_replace('/^(\w{2}).*(@.*)$/', '$1**$2', $email);
    return substr($email, 0, 1) . str_repeat('*', strpos($email, '@') - 1) . strstr($email, '@');
}

function maskedPhoneNumber($phone_number)
{
    if (strlen($phone_number) >= 4) {
        // Get the first two digits and the last two digits
        $prefix = substr($phone_number, 0, 2);
        $suffix = substr($phone_number, -2);

        // Mask all characters between the first two digits and the last two digits with asterisks
        $masked = $prefix . str_repeat('*', strlen($phone_number) - 4) . $suffix;
        return $masked;
    }
    return $phone_number;
}

function updateLanguageVersion()
{
    $language_version_data = LanguageVersionDetail::firstOrCreate([], ['version_no' => 1]);
    return $language_version_data->increment('version_no',1);
}

function createAppLanguageSetting($data)
{
    foreach ($data as $screen) {
        $screen_record = Screen::where('screenID', $screen['screenID'])->first();
        if ( empty($screen_record) ) {
            $screen_record = Screen::create([
                'screenId'   => $screen['screenID'],
                'screenName' => $screen['ScreenName']
            ]);
        }

        if ( !empty($screen['keyword_data']) ) {
            foreach ($screen['keyword_data'] as $keyword_data) {
                $check_default_keyword = DefaultKeyword::where('keyword_id', $keyword_data['keyword_id'])->first();
                if ( empty($check_default_keyword) ) {
                    $default_keyword = DefaultKeyword::create([
                        'screen_id' => $screen_record['screenId'],
                        'keyword_id' => $keyword_data['keyword_id'],
                        'keyword_name' => $keyword_data['keyword_name'],
                        'keyword_value' => $keyword_data['keyword_value']
                    ]);

                    $language_list = LanguageList::get();
                    if ( count($language_list) > 0 ) {
                        foreach ($language_list as $value) {
                            $language_with_data = [
                                'id' => null,
                                'keyword_id' => $default_keyword->keyword_id,
                                'screen_id' => $default_keyword->screen_id,
                                'language_id' => $value->id,
                                'keyword_value' => $default_keyword->keyword_value,
                            ];
                            LanguageWithKeyword::create($language_with_data);
                        }
                    }
                }
            }
        }
    }
}

function getAttachments($attchments)
{
    $files = [];
    if (count($attchments) > 0) {
        foreach ($attchments as $attchment) {
            if (getFileExistsCheck($attchment)) {
                array_push($files, $attchment->getFullUrl());
            }
        }
    }
    return $files;
}

function getAttachmentArray($attchments)
{
    $files = [];
    if (count($attchments) > 0) {
        foreach ($attchments as $attchment) {
            if (getFileExistsCheck($attchment)) {
                $file = [
                    'id'        => $attchment->id,
                    'url'       => $attchment->getFullUrl(),
                    'mime_type' => $attchment->mime_type,
                ];
                array_push($files, $file);
            }
        }
    }
    return $files;
}
function country($code = null)
{
    $countries = [
        [
            "countryNameEn" => "Andorra",
            "countryCode" => "AD",
            "region" => "Europe",
            "flag" => "🇦🇩"
        ],
        [
            "countryNameEn" => "Afghanistan",
            "countryCode" => "AF",
            "region" => "Asia & Pacific",
            "flag" => "🇦🇫"
        ],
        [
            "countryNameEn" => "Antigua and Barbuda",
            "countryCode" => "AG",
            "region" => "South/Latin America",
            "flag" => "🇦🇬"
        ],
        [
            "countryNameEn" => "Anguilla",
            "countryCode" => "AI",
            "region" => "South/Latin America",
            "flag" => "🇦🇮"
        ],
        [
            "countryNameEn" => "Albania",
            "countryCode" => "AL",
            "region" => "Europe",
            "flag" => "🇦🇱"
        ],
        [
            "countryNameEn" => "Armenia",
            "countryCode" => "AM",
            "region" => "Europe",
            "flag" => "🇦🇲"
        ],
        [
            "countryNameEn" => "Angola",
            "countryCode" => "AO",
            "region" => "Africa",
            "flag" => "🇦🇴"
        ],
        [
            "countryNameEn" => "Antarctica",
            "countryCode" => "AQ",
            "region" => "Asia & Pacific",
            "flag" => "🇦🇶"
        ],
        [
            "countryNameEn" => "Argentina",
            "countryCode" => "AR",
            "region" => "South/Latin America",
            "flag" => "🇦🇷"
        ],
        [
            "countryNameEn" => "American Samoa",
            "countryCode" => "AS",
            "region" => "Asia & Pacific",
            "flag" => "🇦🇸"
        ],
        [
            "countryNameEn" => "Austria",
            "countryCode" => "AT",
            "region" => "Europe",
            "flag" => "🇦🇹"
        ],
        [
            "countryNameEn" => "Australia",
            "countryCode" => "AU",
            "region" => "Asia & Pacific",
            "flag" => "🇦🇺"
        ],
        [
            "countryNameEn" => "Aruba",
            "countryCode" => "AW",
            "region" => "South/Latin America",
            "flag" => "🇦🇼"
        ],
        [
            "countryNameEn" => "Åland Islands",
            "countryCode" => "AX",
            "region" => "Europe",
            "flag" => "🇦🇽"
        ],
        [
            "countryNameEn" => "Azerbaijan",
            "countryCode" => "AZ",
            "region" => "Asia & Pacific",
            "flag" => "🇦🇿"
        ],
        [
            "countryNameEn" => "Bosnia and Herzegovina",
            "countryCode" => "BA",
            "region" => "Europe",
            "flag" => "🇧🇦"
        ],
        [
            "countryNameEn" => "Barbados",
            "countryCode" => "BB",
            "region" => "South/Latin America",
            "flag" => "🇧🇧"
        ],
        [
            "countryNameEn" => "Bangladesh",
            "countryCode" => "BD",
            "region" => "Asia & Pacific",
            "flag" => "🇧🇩"
        ],
        [
            "countryNameEn" => "Belgium",
            "countryCode" => "BE",
            "region" => "Europe",
            "flag" => "🇧🇪"
        ],
        [
            "countryNameEn" => "Burkina Faso",
            "countryCode" => "BF",
            "region" => "Africa",
            "flag" => "🇧🇫"
        ],
        [
            "countryNameEn" => "Bulgaria",
            "countryCode" => "BG",
            "region" => "Europe",
            "flag" => "🇧🇬"
        ],
        [
            "countryNameEn" => "Bahrain",
            "countryCode" => "BH",
            "region" => "Arab States",
            "flag" => "🇧🇭"
        ],
        [
            "countryNameEn" => "Burundi",
            "countryCode" => "BI",
            "region" => "Africa",
            "flag" => "🇧🇮"
        ],
        [
            "countryNameEn" => "Benin",
            "countryCode" => "BJ",
            "region" => "Africa",
            "flag" => "🇧🇯"
        ],
        [
            "countryNameEn" => "Saint Barthélemy",
            "countryCode" => "BL",
            "region" => "South/Latin America",
            "flag" => "🇧🇱"
        ],
        [
            "countryNameEn" => "Bermuda",
            "countryCode" => "BM",
            "region" => "North America",
            "flag" => "🇧🇲"
        ],
        [
            "countryNameEn" => "Brunei Darussalam",
            "countryCode" => "BN",
            "region" => "Asia & Pacific",
            "flag" => "🇧🇳"
        ],
        [
            "countryNameEn" => "Bolivia (Plurinational State of)",
            "countryCode" => "BO",
            "region" => "South/Latin America",
            "flag" => "🇧🇴"
        ],
        [
            "countryNameEn" => "Bonaire, Sint Eustatius and Saba",
            "countryCode" => "BQ",
            "region" => "Unknown",
            "flag" => "🇧🇶"
        ],
        [
            "countryNameEn" => "Brazil",
            "countryCode" => "BR",
            "region" => "South/Latin America",
            "flag" => "🇧🇷"
        ],
        [
            "countryNameEn" => "Bhutan",
            "countryCode" => "BT",
            "region" => "Asia & Pacific",
            "flag" => "🇧🇹"
        ],
        [
            "countryNameEn" => "Bouvet Island",
            "countryCode" => "BV",
            "region" => "South/Latin America",
            "flag" => "🇧🇻"
        ],
        [
            "countryNameEn" => "Botswana",
            "countryCode" => "BW",
            "region" => "Africa",
            "flag" => "🇧🇼"
        ],
        [
            "countryNameEn" => "Belarus",
            "countryCode" => "BY",
            "region" => "Europe",
            "flag" => "🇧🇾"
        ],
        [
            "countryNameEn" => "Belize",
            "countryCode" => "BZ",
            "region" => "South/Latin America",
            "flag" => "🇧🇿"
        ],
        [
            "countryNameEn" => "Canada",
            "countryCode" => "CA",
            "region" => "North America",
            "flag" => "🇨🇦"
        ],
        [
            "countryNameEn" => "Switzerland",
            "countryCode" => "CH",
            "region" => "Europe",
            "flag" => "🇨🇭"
        ],
        [
            "countryNameEn" => "Côte d'Ivoire",
            "countryCode" => "CI",
            "region" => "Africa",
            "flag" => "🇨🇮"
        ],
        [
            "countryNameEn" => "Chile",
            "countryCode" => "CL",
            "region" => "South/Latin America",
            "flag" => "🇨🇱"
        ],
        [
            "countryNameEn" => "Cameroon",
            "countryCode" => "CM",
            "region" => "Africa",
            "flag" => "🇨🇲"
        ],
        [
            "countryNameEn" => "China",
            "countryCode" => "CN",
            "region" => "Asia & Pacific",
            "flag" => "🇨🇳"
        ],
        [
            "countryNameEn" => "Colombia",
            "countryCode" => "CO",
            "region" => "South/Latin America",
            "flag" => "🇨🇴"
        ],
        [
            "countryNameEn" => "Costa Rica",
            "countryCode" => "CR",
            "region" => "South/Latin America",
            "flag" => "🇨🇷"
        ],
        [
            "countryNameEn" => "Cuba",
            "countryCode" => "CU",
            "region" => "South/Latin America",
            "flag" => "🇨🇺"
        ],
        [
            "countryNameEn" => "Cabo Verde",
            "countryCode" => "CV",
            "region" => "Africa",
            "flag" => "🇨🇻"
        ],
        [
            "countryNameEn" => "Curaçao",
            "countryCode" => "CW",
            "region" => "Unknown",
            "flag" => "🇨🇼"
        ],
        [
            "countryNameEn" => "Christmas Island",
            "countryCode" => "CX",
            "region" => "Asia & Pacific",
            "flag" => "🇨🇽"
        ],
        [
            "countryNameEn" => "Cyprus",
            "countryCode" => "CY",
            "region" => "Europe",
            "flag" => "🇨🇾"
        ],
        [
            "countryNameEn" => "Germany",
            "countryCode" => "DE",
            "region" => "Europe",
            "flag" => "🇩🇪"
        ],
        [
            "countryNameEn" => "Djibouti",
            "countryCode" => "DJ",
            "region" => "East Africa",
            "flag" => "🇩🇯"
        ],
        [
            "countryNameEn" => "Denmark",
            "countryCode" => "DK",
            "region" => "Europe",
            "flag" => "🇩🇰"
        ],
        [
            "countryNameEn" => "Dominica",
            "countryCode" => "DM",
            "region" => "South/Latin America",
            "flag" => "🇩🇲"
        ],
        [
            "countryNameEn" => "Algeria",
            "countryCode" => "DZ",
            "region" => "Arab States",
            "flag" => "🇩🇿"
        ],
        [
            "countryNameEn" => "Ecuador",
            "countryCode" => "EC",
            "region" => "South/Latin America",
            "flag" => "🇪🇨"
        ],
        [
            "countryNameEn" => "Estonia",
            "countryCode" => "EE",
            "region" => "Europe",
            "flag" => "🇪🇪"
        ],
        [
            "countryNameEn" => "Egypt",
            "countryCode" => "EG",
            "region" => "Arab States",
            "flag" => "🇪🇬"
        ],
        [
            "countryNameEn" => "Western Sahara",
            "countryCode" => "EH",
            "region" => "Africa",
            "flag" => "🇪🇭"
        ],
        [
            "countryNameEn" => "Eritrea",
            "countryCode" => "ER",
            "region" => "Africa",
            "flag" => "🇪🇷"
        ],
        [
            "countryNameEn" => "Spain",
            "countryCode" => "ES",
            "region" => "Europe",
            "flag" => "🇪🇸"
        ],
        [
            "countryNameEn" => "Ethiopia",
            "countryCode" => "ET",
            "region" => "Africa",
            "flag" => "🇪🇹"
        ],
        [
            "countryNameEn" => "Finland",
            "countryCode" => "FI",
            "region" => "Europe",
            "flag" => "🇫🇮"
        ],
        [
            "countryNameEn" => "Fiji",
            "countryCode" => "FJ",
            "region" => "Asia & Pacific",
            "flag" => "🇫🇯"
        ],
        [
            "countryNameEn" => "Micronesia (Federated States of)",
            "countryCode" => "FM",
            "region" => "Asia & Pacific",
            "flag" => "🇫🇲"
        ],
        [
            "countryNameEn" => "France",
            "countryCode" => "FR",
            "region" => "Europe",
            "flag" => "🇫🇷"
        ],
        [
            "countryNameEn" => "Gabon",
            "countryCode" => "GA",
            "region" => "Africa",
            "flag" => "🇬🇦"
        ],
        [
            "countryNameEn" => "Grenada",
            "countryCode" => "GD",
            "region" => "South/Latin America",
            "flag" => "🇬🇩"
        ],
        [
            "countryNameEn" => "Georgia",
            "countryCode" => "GE",
            "region" => "Europe",
            "flag" => "🇬🇪"
        ],
        [
            "countryNameEn" => "French Guiana",
            "countryCode" => "GF",
            "region" => "South/Latin America",
            "flag" => "🇬🇫"
        ],
        [
            "countryNameEn" => "Guernsey",
            "countryCode" => "GG",
            "region" => "Europe",
            "flag" => "🇬🇬"
        ],
        [
            "countryNameEn" => "Ghana",
            "countryCode" => "GH",
            "region" => "Africa",
            "flag" => "🇬🇭"
        ],
        [
            "countryNameEn" => "Gibraltar",
            "countryCode" => "GI",
            "region" => "Europe",
            "flag" => "🇬🇮"
        ],
        [
            "countryNameEn" => "Greenland",
            "countryCode" => "GL",
            "region" => "Europe",
            "flag" => "🇬🇱"
        ],
        [
            "countryNameEn" => "Guinea",
            "countryCode" => "GN",
            "region" => "Africa",
            "flag" => "🇬🇳"
        ],
        [
            "countryNameEn" => "Guadeloupe",
            "countryCode" => "GP",
            "region" => "South/Latin America",
            "flag" => "🇬🇵"
        ],
        [
            "countryNameEn" => "Equatorial Guinea",
            "countryCode" => "GQ",
            "region" => "Africa",
            "flag" => "🇬🇶"
        ],
        [
            "countryNameEn" => "Greece",
            "countryCode" => "GR",
            "region" => "Europe",
            "flag" => "🇬🇷"
        ],
        [
            "countryNameEn" => "South Georgia and the South Sandwich Islands",
            "countryCode" => "GS",
            "region" => "South/Latin America",
            "flag" => "🇬🇸"
        ],
        [
            "countryNameEn" => "Guatemala",
            "countryCode" => "GT",
            "region" => "South/Latin America",
            "flag" => "🇬🇹"
        ],
        [
            "countryNameEn" => "Guam",
            "countryCode" => "GU",
            "region" => "Asia & Pacific",
            "flag" => "🇬🇺"
        ],
        [
            "countryNameEn" => "Guinea-Bissau",
            "countryCode" => "GW",
            "region" => "Africa",
            "flag" => "🇬🇼"
        ],
        [
            "countryNameEn" => "Guyana",
            "countryCode" => "GY",
            "region" => "South/Latin America",
            "flag" => "🇬🇾"
        ],
        [
            "countryNameEn" => "Hong Kong",
            "countryCode" => "HK",
            "region" => "Asia & Pacific",
            "flag" => "🇭🇰"
        ],
        [
            "countryNameEn" => "Honduras",
            "countryCode" => "HN",
            "region" => "South/Latin America",
            "flag" => "🇭🇳"
        ],
        [
            "countryNameEn" => "Croatia",
            "countryCode" => "HR",
            "region" => "Europe",
            "flag" => "🇭🇷"
        ],
        [
            "countryNameEn" => "Haiti",
            "countryCode" => "HT",
            "region" => "South/Latin America",
            "flag" => "🇭🇹"
        ],
        [
            "countryNameEn" => "Hungary",
            "countryCode" => "HU",
            "region" => "Europe",
            "flag" => "🇭🇺"
        ],
        [
            "countryNameEn" => "Indonesia",
            "countryCode" => "ID",
            "region" => "Asia & Pacific",
            "flag" => "🇮🇩"
        ],
        [
            "countryNameEn" => "Ireland",
            "countryCode" => "IE",
            "region" => "Europe",
            "flag" => "🇮🇪"
        ],
        [
            "countryNameEn" => "Israel",
            "countryCode" => "IL",
            "region" => "Europe",
            "flag" => "🇮🇱"
        ],
        [
            "countryNameEn" => "Isle of Man",
            "countryCode" => "IM",
            "region" => "Europe",
            "flag" => "🇮🇲"
        ],
        [
            "countryNameEn" => "India",
            "countryCode" => "IN",
            "region" => "Asia & Pacific",
            "flag" => "🇮🇳"
        ],
        [
            "countryNameEn" => "British Indian Ocean Territories",
            "countryCode" => "IO",
            "region" => "Indian Ocean",
            "flag" => "🇮🇴",
        ],
        [
            "countryNameEn" => "Iraq",
            "countryCode" => "IQ",
            "region" => "Arab States",
            "flag" => "🇮🇶"
        ],
        [
            "countryNameEn" => "Iran (Islamic Republic of)",
            "countryCode" => "IR",
            "region" => "Asia & Pacific",
            "flag" => "🇮🇷"
        ],
        [
            "countryNameEn" => "Iceland",
            "countryCode" => "IS",
            "region" => "Europe",
            "flag" => "🇮🇸"
        ],
        [
            "countryNameEn" => "Italy",
            "countryCode" => "IT",
            "region" => "Europe",
            "flag" => "🇮🇹"
        ],
        [
            "countryNameEn" => "Jersey",
            "countryCode" => "JE",
            "region" => "Europe",
            "flag" => "🇯🇪"
        ],
        [
            "countryNameEn" => "Jamaica",
            "countryCode" => "JM",
            "region" => "South/Latin America",
            "flag" => "🇯🇲"
        ],
        [
            "countryNameEn" => "Jordan",
            "countryCode" => "JO",
            "region" => "Arab States",
            "flag" => "🇯🇴"
        ],
        [
            "countryNameEn" => "Japan",
            "countryCode" => "JP",
            "region" => "Asia & Pacific",
            "flag" => "🇯🇵"
        ],
        [
            "countryNameEn" => "Kenya",
            "countryCode" => "KE",
            "region" => "Africa",
            "flag" => "🇰🇪"
        ],
        [
            "countryNameEn" => "Kyrgyzstan",
            "countryCode" => "KG",
            "region" => "Asia & Pacific",
            "flag" => "🇰🇬"
        ],
        [
            "countryNameEn" => "Cambodia",
            "countryCode" => "KH",
            "region" => "Asia & Pacific",
            "flag" => "🇰🇭"
        ],
        [
            "countryNameEn" => "North Korea",
            "countryCode" => "KP",
            "region" => "Asia",
            "flag" => "🇰🇵"
        ],
        [
            "countryNameEn" => "South Korea",
            "countryCode" => "KR",
            "region" => "Asia",
            "flag" => "🇰🇷"
        ],
        [
            "countryNameEn" => "Kiribati",
            "countryCode" => "KI",
            "region" => "Asia & Pacific",
            "flag" => "🇰🇮"
        ],
        [
            "countryNameEn" => "Saint Kitts and Nevis",
            "countryCode" => "KN",
            "region" => "South/Latin America",
            "flag" => "🇰🇳"
        ],
        [
            "countryNameEn" => "Kuwait",
            "countryCode" => "KW",
            "region" => "Arab States",
            "flag" => "🇰🇼"
        ],
        [
            "countryNameEn" => "Kazakhstan",
            "countryCode" => "KZ",
            "region" => "Asia & Pacific",
            "flag" => "🇰🇿"
        ],
        [
            "countryNameEn" => "Lebanon",
            "countryCode" => "LB",
            "region" => "Arab States",
            "flag" => "🇱🇧"
        ],
        [
            "countryNameEn" => "Saint Lucia",
            "countryCode" => "LC",
            "region" => "South/Latin America",
            "flag" => "🇱🇨"
        ],
        [
            "countryNameEn" => "Liechtenstein",
            "countryCode" => "LI",
            "region" => "Europe",
            "flag" => "🇱🇮"
        ],
        [
            "countryNameEn" => "Sri Lanka",
            "countryCode" => "LK",
            "region" => "Asia & Pacific",
            "flag" => "🇱🇰"
        ],
        [
            "countryNameEn" => "Liberia",
            "countryCode" => "LR",
            "region" => "Africa",
            "flag" => "🇱🇷"
        ],
        [
            "countryNameEn" => "Lesotho",
            "countryCode" => "LS",
            "region" => "Africa",
            "flag" => "🇱🇸"
        ],
        [
            "countryNameEn" => "Lithuania",
            "countryCode" => "LT",
            "region" => "Europe",
            "flag" => "🇱🇹"
        ],
        [
            "countryNameEn" => "Luxembourg",
            "countryCode" => "LU",
            "region" => "Europe",
            "flag" => "🇱🇺"
        ],
        [
            "countryNameEn" => "Latvia",
            "countryCode" => "LV",
            "region" => "Europe",
            "flag" => "🇱🇻"
        ],
        [
            "countryNameEn" => "Libya",
            "countryCode" => "LY",
            "region" => "Arab States",
            "flag" => "🇱🇾"
        ],
        [
            "countryNameEn" => "Morocco",
            "countryCode" => "MA",
            "region" => "Arab States",
            "flag" => "🇲🇦"
        ],
        [
            "countryNameEn" => "Monaco",
            "countryCode" => "MC",
            "region" => "Europe",
            "flag" => "🇲🇨"
        ],
        [
            "countryNameEn" => "Montenegro",
            "countryCode" => "ME",
            "region" => "Europe",
            "flag" => "🇲🇪"
        ],
        [
            "countryNameEn" => "Saint Martin (French part)",
            "countryCode" => "MF",
            "region" => "South/Latin America",
            "flag" => "🇲🇫"
        ],
        [
            "countryNameEn" => "Madagascar",
            "countryCode" => "MG",
            "region" => "Africa",
            "flag" => "🇲🇬"
        ],
        [
            "countryNameEn" => "Mali",
            "countryCode" => "ML",
            "region" => "Africa",
            "flag" => "🇲🇱"
        ],
        [
            "countryNameEn" => "Myanmar",
            "countryCode" => "MM",
            "region" => "Asia & Pacific",
            "flag" => "🇲🇲"
        ],
        [
            "countryNameEn" => "Mongolia",
            "countryCode" => "MN",
            "region" => "Asia & Pacific",
            "flag" => "🇲🇳"
        ],
        [
            "countryNameEn" => "Macao",
            "countryCode" => "MO",
            "region" => "Asia & Pacific",
            "flag" => "🇲🇴"
        ],
        [
            "countryNameEn" => "Martinique",
            "countryCode" => "MQ",
            "region" => "South/Latin America",
            "flag" => "🇲🇶"
        ],
        [
            "countryNameEn" => "Mauritania",
            "countryCode" => "MR",
            "region" => "Arab States",
            "flag" => "🇲🇷"
        ],
        [
            "countryNameEn" => "Montserrat",
            "countryCode" => "MS",
            "region" => "South/Latin America",
            "flag" => "🇲🇸"
        ],
        [
            "countryNameEn" => "Malta",
            "countryCode" => "MT",
            "region" => "Europe",
            "flag" => "🇲🇹"
        ],
        [
            "countryNameEn" => "Mauritius",
            "countryCode" => "MU",
            "region" => "Africa",
            "flag" => "🇲🇺"
        ],
        [
            "countryNameEn" => "Maldives",
            "countryCode" => "MV",
            "region" => "Asia & Pacific",
            "flag" => "🇲🇻"
        ],
        [
            "countryNameEn" => "Malawi",
            "countryCode" => "MW",
            "region" => "Africa",
            "flag" => "🇲🇼"
        ],
        [
            "countryNameEn" => "Mexico",
            "countryCode" => "MX",
            "region" => "South/Latin America",
            "flag" => "🇲🇽"
        ],
        [
            "countryNameEn" => "Malaysia",
            "countryCode" => "MY",
            "region" => "Asia & Pacific",
            "flag" => "🇲🇾"
        ],
        [
            "countryNameEn" => "Mozambique",
            "countryCode" => "MZ",
            "region" => "Africa",
            "flag" => "🇲🇿"
        ],
        [
            "countryNameEn" => "Namibia",
            "countryCode" => "NA",
            "region" => "Africa",
            "flag" => "🇳🇦"
        ],
        [
            "countryNameEn" => "New Caledonia",
            "countryCode" => "NC",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇨"
        ],
        [
            "countryNameEn" => "Norfolk Island",
            "countryCode" => "NF",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇫"
        ],
        [
            "countryNameEn" => "Nigeria",
            "countryCode" => "NG",
            "region" => "Africa",
            "flag" => "🇳🇬"
        ],
        [
            "countryNameEn" => "Nicaragua",
            "countryCode" => "NI",
            "region" => "South/Latin America",
            "flag" => "🇳🇮"
        ],
        [
            "countryNameEn" => "Norway",
            "countryCode" => "NO",
            "region" => "Europe",
            "flag" => "🇳🇴"
        ],
        [
            "countryNameEn" => "Nepal",
            "countryCode" => "NP",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇵"
        ],
        [
            "countryNameEn" => "Nauru",
            "countryCode" => "NR",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇷"
        ],
        [
            "countryNameEn" => "Niue",
            "countryCode" => "NU",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇺"
        ],
        [
            "countryNameEn" => "New Zealand",
            "countryCode" => "NZ",
            "region" => "Asia & Pacific",
            "flag" => "🇳🇿"
        ],
        [
            "countryNameEn" => "Oman",
            "countryCode" => "OM",
            "region" => "Arab States",
            "flag" => "🇴🇲"
        ],
        [
            "countryNameEn" => "Panama",
            "countryCode" => "PA",
            "region" => "South/Latin America",
            "flag" => "🇵🇦"
        ],
        [
            "countryNameEn" => "Peru",
            "countryCode" => "PE",
            "region" => "South/Latin America",
            "flag" => "🇵🇪"
        ],
        [
            "countryNameEn" => "French Polynesia",
            "countryCode" => "PF",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇫"
        ],
        [
            "countryNameEn" => "Papua New Guinea",
            "countryCode" => "PG",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇬"
        ],
        [
            "countryNameEn" => "Pakistan",
            "countryCode" => "PK",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇰"
        ],
        [
            "countryNameEn" => "Poland",
            "countryCode" => "PL",
            "region" => "Europe",
            "flag" => "🇵🇱"
        ],
        [
            "countryNameEn" => "Saint Pierre and Miquelon",
            "countryCode" => "PM",
            "region" => "North America",
            "flag" => "🇵🇲"
        ],
        [
            "countryNameEn" => "Pitcairn",
            "countryCode" => "PN",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇳"
        ],
        [
            "countryNameEn" => "Puerto Rico",
            "countryCode" => "PR",
            "region" => "South/Latin America",
            "flag" => "🇵🇷"
        ],
        [
            "countryNameEn" => "Palestine, State of",
            "countryCode" => "PS",
            "region" => "Arab States",
            "flag" => "🇵🇸"
        ],
        [
            "countryNameEn" => "Portugal",
            "countryCode" => "PT",
            "region" => "Europe",
            "flag" => "🇵🇹"
        ],
        [
            "countryNameEn" => "Palau",
            "countryCode" => "PW",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇼"
        ],
        [
            "countryNameEn" => "Paraguay",
            "countryCode" => "PY",
            "region" => "South/Latin America",
            "flag" => "🇵🇾"
        ],
        [
            "countryNameEn" => "Qatar",
            "countryCode" => "QA",
            "region" => "Arab States",
            "flag" => "🇶🇦"
        ],
        [
            "countryNameEn" => "Réunion",
            "countryCode" => "RE",
            "region" => "Asia & Pacific",
            "flag" => "🇷🇪"
        ],
        [
            "countryNameEn" => "Romania",
            "countryCode" => "RO",
            "region" => "Europe",
            "flag" => "🇷🇴"
        ],
        [
            "countryNameEn" => "Serbia",
            "countryCode" => "RS",
            "region" => "Europe",
            "flag" => "🇷🇸"
        ],
        [
            "countryNameEn" => "Russia",
            "countryCode" => "RU",
            "region" => "Europe",
            "flag" => "🇷🇺"
        ],
        [
            "countryNameEn" => "Rwanda",
            "countryCode" => "RW",
            "region" => "Africa",
            "flag" => "🇷🇼"
        ],
        [
            "countryNameEn" => "Saudi Arabia",
            "countryCode" => "SA",
            "region" => "Arab States",
            "flag" => "🇸🇦"
        ],
        [
            "countryNameEn" => "Solomon Islands",
            "countryCode" => "SB",
            "region" => "Asia & Pacific",
            "flag" => "🇸🇧"
        ],
        [
            "countryNameEn" => "Seychelles",
            "countryCode" => "SC",
            "region" => "Africa",
            "flag" => "🇸🇨"
        ],
        [
            "countryNameEn" => "Sweden",
            "countryCode" => "SE",
            "region" => "Europe",
            "flag" => "🇸🇪"
        ],
        [
            "countryNameEn" => "Singapore",
            "countryCode" => "SG",
            "region" => "Asia & Pacific",
            "flag" => "🇸🇬"
        ],
        [
            "countryNameEn" => "Saint Helena, Ascension and Tristan da Cunha",
            "countryCode" => "SH",
            "region" => "Africa",
            "flag" => "🇸🇭"
        ],
        [
            "countryNameEn" => "Slovenia",
            "countryCode" => "SI",
            "region" => "Europe",
            "flag" => "🇸🇮"
        ],
        [
            "countryNameEn" => "Svalbard and Jan Mayen",
            "countryCode" => "SJ",
            "region" => "Europe",
            "flag" => "🇸🇯"
        ],
        [
            "countryNameEn" => "Slovakia",
            "countryCode" => "SK",
            "region" => "Europe",
            "flag" => "🇸🇰"
        ],
        [
            "countryNameEn" => "Sierra Leone",
            "countryCode" => "SL",
            "region" => "Africa",
            "flag" => "🇸🇱"
        ],
        [
            "countryNameEn" => "Republic of San Marino",
            "countryCode" => "SM",
            "region" => "Europe",
            "flag" => "🇸🇲"
        ],
        [
            "countryNameEn" => "Senegal",
            "countryCode" => "SN",
            "region" => "Africa",
            "flag" => "🇸🇳"
        ],
        [
            "countryNameEn" => "Somalia",
            "countryCode" => "SO",
            "region" => "Arab States",
            "flag" => "🇸🇴"
        ],
        [
            "countryNameEn" => "Suriname",
            "countryCode" => "SR",
            "region" => "South/Latin America",
            "flag" => "🇸🇷"
        ],
        [
            "countryNameEn" => "South Sudan",
            "countryCode" => "SS",
            "region" => "Africa",
            "flag" => "🇸🇸"
        ],
        [
            "countryNameEn" => "Sao Tome and Principe",
            "countryCode" => "ST",
            "region" => "Africa",
            "flag" => "🇸🇹"
        ],
        [
            "countryNameEn" => "El Salvador",
            "countryCode" => "SV",
            "region" => "South/Latin America",
            "flag" => "🇸🇻"
        ],
        [
            "countryNameEn" => "Sint Maarten (Dutch part)",
            "countryCode" => "SX",
            "region" => "Unknown",
            "flag" => "🇸🇽"
        ],
        [
            "countryNameEn" => "Syrian Arab Republic",
            "countryCode" => "SY",
            "region" => "Asia & Pacific",
            "flag" => "🇸🇾"
        ],
        [
            "countryNameEn" => "Chad",
            "countryCode" => "TD",
            "region" => "Africa",
            "flag" => "🇹🇩"
        ],
        [
            "countryNameEn" => "Togo",
            "countryCode" => "TG",
            "region" => "Africa",
            "flag" => "🇹🇬"
        ],
        [
            "countryNameEn" => "Thailand",
            "countryCode" => "TH",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇭"
        ],
        [
            "countryNameEn" => "Tajikistan",
            "countryCode" => "TJ",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇯"
        ],
        [
            "countryNameEn" => "Tokelau",
            "countryCode" => "TK",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇰"
        ],
        [
            "countryNameEn" => "Timor-Leste",
            "countryCode" => "TL",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇱"
        ],
        [
            "countryNameEn" => "Turkmenistan",
            "countryCode" => "TM",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇲"
        ],
        [
            "countryNameEn" => "Tunisia",
            "countryCode" => "TN",
            "region" => "Arab States",
            "flag" => "🇹🇳"
        ],
        [
            "countryNameEn" => "Tonga",
            "countryCode" => "TO",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇴"
        ],
        [
            "countryNameEn" => "Turkey",
            "countryCode" => "TR",
            "region" => "Europe",
            "flag" => "🇹🇷"
        ],
        [
            "countryNameEn" => "Trinidad and Tobago",
            "countryCode" => "TT",
            "region" => "South/Latin America",
            "flag" => "🇹🇹"
        ],
        [
            "countryNameEn" => "Tuvalu",
            "countryCode" => "TV",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇻"
        ],
        [
            "countryNameEn" => "United Republic of Tanzania",
            "countryCode" => "TZ",
            "region" => "Africa",
            "flag" => "🇹🇿"
        ],
        [
            "countryNameEn" => "Ukraine",
            "countryCode" => "UA",
            "region" => "Europe",
            "flag" => "🇺🇦"
        ],
        [
            "countryNameEn" => "Uganda",
            "countryCode" => "UG",
            "region" => "Africa",
            "flag" => "🇺🇬"
        ],
        [
            "countryNameEn" => "United States",
            "countryCode" => "US",
            "region" => "North America",
            "flag" => "🇺🇸"
        ],
        [
            "countryNameEn" => "Uruguay",
            "countryCode" => "UY",
            "region" => "South/Latin America",
            "flag" => "🇺🇾"
        ],
        [
            "countryNameEn" => "Uzbekistan",
            "countryCode" => "UZ",
            "region" => "Asia & Pacific",
            "flag" => "🇺🇿"
        ],
        [
            "countryNameEn" => "Saint Vincent and the Grenadines",
            "countryCode" => "VC",
            "region" => "South/Latin America",
            "flag" => "🇻🇨"
        ],
        [
            "countryNameEn" => "Venezuela (Bolivarian Republic of)",
            "countryCode" => "VE",
            "region" => "South/Latin America",
            "flag" => "🇻🇪"
        ],
        [
            "countryNameEn" => "Virgin Islands (British)",
            "countryCode" => "VG",
            "region" => "South/Latin America",
            "flag" => "🇻🇬"
        ],
        [
            "countryNameEn" => "Virgin Islands (U.S.)",
            "countryCode" => "VI",
            "region" => "South/Latin America",
            "flag" => "🇻🇮"
        ],
        [
            "countryNameEn" => "Vietnam",
            "countryCode" => "VN",
            "region" => "Asia & Pacific",
            "flag" => "🇻🇳"
        ],
        [
            "countryNameEn" => "Vanuatu",
            "countryCode" => "VU",
            "region" => "Asia & Pacific",
            "flag" => "🇻🇺"
        ],
        [
            "countryNameEn" => "Wallis and Futuna",
            "countryCode" => "WF",
            "region" => "Asia & Pacific",
            "flag" => "🇼🇫"
        ],
        [
            "countryNameEn" => "Samoa",
            "countryCode" => "WS",
            "region" => "Asia & Pacific",
            "flag" => "🇼🇸"
        ],
        [
            "countryNameEn" => "Yemen",
            "countryCode" => "YE",
            "region" => "Arab States",
            "flag" => "🇾🇪"
        ],
        [
            "countryNameEn" => "Mayotte",
            "countryCode" => "YT",
            "region" => "Africa",
            "flag" => "🇾🇹"
        ],
        [
            "countryNameEn" => "South Africa",
            "countryCode" => "ZA",
            "region" => "Africa",
            "flag" => "🇿🇦"
        ],
        [
            "countryNameEn" => "Zambia",
            "countryCode" => "ZM",
            "region" => "Africa",
            "flag" => "🇿🇲"
        ],
        [
            "countryNameEn" => "Zimbabwe",
            "countryCode" => "ZW",
            "region" => "Africa",
            "flag" => "🇿🇼"
        ],
        [
            "countryNameEn" => "Eswatini",
            "countryCode" => "SZ",
            "region" => "Africa",
            "flag" => "🇸🇿"
        ],
        [
            "countryNameEn" => "North Macedonia",
            "countryCode" => "MK",
            "region" => "Europe",
            "flag" => "🇲🇰"
        ],
        [
            "countryNameEn" => "Philippines",
            "countryCode" => "PH",
            "region" => "Asia & Pacific",
            "flag" => "🇵🇭"
        ],
        [
            "countryNameEn" => "Netherlands",
            "countryCode" => "NL",
            "region" => "Europe",
            "flag" => "🇳🇱"
        ],
        [
            "countryNameEn" => "United Arab Emirates",
            "countryCode" => "AE",
            "region" => "Arab States",
            "flag" => "🇦🇪"
        ],
        [
            "countryNameEn" => "Republic of Moldova",
            "countryCode" => "MD",
            "region" => "Europe",
            "flag" => "🇲🇩"
        ],
        [
            "countryNameEn" => "Gambia",
            "countryCode" => "GM",
            "region" => "Africa",
            "flag" => "🇬🇲"
        ],
        [
            "countryNameEn" => "Dominican Republic",
            "countryCode" => "DO",
            "region" => "South/Latin America",
            "flag" => "🇩🇴"
        ],
        [
            "countryNameEn" => "Sudan",
            "countryCode" => "SD",
            "region" => "Arab States",
            "flag" => "🇸🇩"
        ],
        [
            "countryNameEn" => "Lao People's Democratic Republic",
            "countryCode" => "LA",
            "region" => "Asia & Pacific",
            "flag" => "🇱🇦"
        ],
        [
            "countryNameEn" => "Taiwan, Province of China",
            "countryCode" => "TW",
            "region" => "Asia & Pacific",
            "flag" => "🇹🇼"
        ],
        [
            "countryNameEn" => "Republic of the Congo",
            "countryCode" => "CG",
            "region" => "Central Africa",
            "flag" => "🇨🇬"
        ],
        [
            "countryNameEn" => "Czechia",
            "countryCode" => "CZ",
            "region" => "Europe",
            "flag" => "🇨🇿"
        ],
        [
            "countryNameEn" => "United Kingdom",
            "countryCode" => "GB",
            "region" => "Europe",
            "flag" => "🇬🇧"
        ],
        [
            "countryNameEn" => "Niger",
            "countryCode" => "NE",
            "region" => "Africa",
            "flag" => "🇳🇪"
        ],
        [
            "countryNameEn" => "Democratic Republic of the Congo",
            "countryCode" => "CD",
            "region" => "Africa",
            "flag" => "🇨🇩",
        ],
        [
            "countryNameEn" => "Commonwealth of The Bahamas",
            "countryCode" => "BS",
            "region" => "Caribbean",
            "flag" => "🇧🇸",
        ],
        [
            "countryNameEn" => "Cocos (Keeling) Islands",
            "countryCode" => "CC",
            "region" => "Australia",
            "flag" => "🇨🇨",
        ],
        [
            "countryNameEn" => "Central African Republic",
            "countryCode" => "CF",
            "region" => "Africa",
            "flag" => "🇨🇫",
        ],
        [
            "countryNameEn" => "Cook Islands",
            "countryCode" => "CK",
            "region" => "South Pacific Ocean",
            "flag" => "🇨🇰",
        ],
        [
            "countryNameEn" => "Falkland Islands",
            "countryCode" => "FK",
            "region" => "South Atlantic Ocean",
            "flag" => "🇫🇰",
        ],
        [
            "countryNameEn" => "Faroe Islands",
            "countryCode" => "FO",
            "region" => "Europe",
            "flag" => "🇫🇴",
        ],
        [
            "countryNameEn" => "Territory of Heard Island and McDonald Islands",
            "countryCode" => "HM",
            "region" => "Indian Ocean",
            "flag" => "🇭🇲",
        ],
        [
            "countryNameEn" => "British Indian Ocean Territory",
            "countryCode" => "IO",
            "region" => "Indian Ocean",
            "flag" => "🇮🇴",
        ],
        [
            "countryNameEn" => "Comoros",
            "countryCode" => "KM",
            "region" => "Indian Ocean",
            "flag" => "🇰🇲",
        ],
        [
            "countryNameEn" => "Cayman Islands",
            "countryCode" => "KY",
            "region" => "Caribbean Sea",
            "flag" => "🇰🇾",
        ],
        [
            "countryNameEn" => "Republic of the Marshall Islands",
            "countryCode" => "MH",
            "region" => "Pacific Ocean",
            "flag" => "🇲🇭",
        ],
        [
            "countryNameEn" => "Commonwealth of the Northern Mariana Islands",
            "countryCode" => "MP",
            "region" => "Pacific Ocean",
            "flag" => "🇲🇵",
        ],
        [
            "countryNameEn" => "Turks and Caicos Islands",
            "countryCode" => "TC",
            "region" => "Atlantic Ocean",
            "flag" => "🇹🇨",
        ],
        [
            "countryNameEn" => "French Southern and Antarctic Lands",
            "countryCode" => "TF",
            "region" => "Indian Ocean",
            "flag" => "🇹🇫",
        ],
        [
            "countryNameEn" => "United States Minor Outlying Islands",
            "countryCode" => "UM",
            "region" => "Pacific Ocean",
            "flag" => "🇺🇲",
        ],
        [
            "countryNameEn" => "Holy See",
            "countryCode" => "VA",
            "region" => "Europe",
            "flag" => "🇻🇦",
        ],
        [
            "countryNameEn" => "Republic of Kosovo",
            "countryCode" => "XK",
            "region" => "Europe",
            "flag" => "🇽🇰",
        ],
        [
            "countryNameEn" => "Netherlands Antilles",
            "countryCode" => "AN",
            "region" => "Europe",
            "flag" => "🇧🇶",
        ],
    ];
    if($code != null) {
        $countries = collect($countries)->where('code', $code)->first();
    }
    return $countries ?? null;
}


function shortTime($date)
{
    if( $date==null ) {
        return '-';
    }
    $timezone = auth()->user()->timezone ?? SettingData ('string', 'timezone') ?? config('app.timezone');
    
    $diff_time = $date->setTimezone($timezone)->diffForHumans([
        'short'     => true,
        'syntax'    => Carbon\CarbonInterface::DIFF_ABSOLUTE
    ]);

    return $diff_time;
}

function humanReadableNumber($number, $precision = 0, $abbreviate = false ) {

    if($number === null){
        $number = 0;
    }
    
    $currency_code = SettingData('CURRENCY', 'CURRENCY_CODE') ?? 'USD';
    $currecy = currencyArray($currency_code);

    $code = $currecy['symbol'] ?? '$';
    $position = SettingData('CURRENCY', 'CURRENCY_POSITION') ?? 'left';
    $amount = Number::forHumans($number, $precision, null, $abbreviate );
    if ($position == 'left') {
        $amount = $code.''.$amount;
    } else {
        $amount = $amount.''.$code;
    }

    return $amount;
}
