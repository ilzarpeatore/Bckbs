<?php
namespace App\Helpers;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;

class EnvChange{
    public static function envChanges($type,$value){
        $path = base_path('.env');
        $checkType = $type.'="';
        if(strpos($value,' ') || strpos(file_get_contents($path),$checkType) || preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $value)){
            $value = '"'.$value.'"';
        }

        $value = str_replace('\\', '\\\\', $value);

        if (file_exists($path)) {
            $typeValue = env($type);

            if(strpos(env($type),' ') || strpos(file_get_contents($path),$checkType)){
                $typeValue = '"'.env($type).'"';
            }

            file_put_contents($path, str_replace(
                $type.'='.$typeValue, $type.'='.$value, file_get_contents($path)
            ));

            $checkArray = Arr::collapse([['DEFAULT_LANGUAGE']]);
    
            if( in_array( $type, $checkArray) ){
                if(env($type) === null){
                    file_put_contents($path,"\n".$type.'='.$value ,FILE_APPEND);
                }
            }
        }
    }
}