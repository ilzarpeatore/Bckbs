<?php
namespace App\Helpers;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

class LanguageHelper
{
    public static function flattenToMultiDimensional(array $array, $delimiter = '.')
    {
        $result = [];
        foreach ($array as $notations => $value) {
            // extract keys
            $keys = explode($delimiter, $notations);
            // reverse keys for assignments
            $keys = array_reverse($keys);
    
            // set initial value
            $lastVal = $value;
            foreach ($keys as $key) {
                // wrap value with key over each iteration
                $lastVal = [
                    $key => $lastVal
                ];
            }
            // merge result
            $result = array_merge_recursive($result, $lastVal);
        }
        return $result;
    }


    public static function createLangFile($lang = '')
    {
        $langDir = resource_path() . '/lang/';
        $enDir = $langDir . 'en';
        $currentLang = $langDir . $lang;

        if (!File::exists($currentLang)) {
            File::makeDirectory($currentLang, 0755, true);
            if (File::exists($enDir)) {
                File::copyDirectory($enDir, $currentLang);
            }
        }

        if (Module::has('Frontend') && Module::isEnabled('Frontend')) {
            $frontendLangDir = base_path('Modules/Frontend/resources/lang/');
            $frontendDir = $frontendLangDir . 'en';
            $currentFrontendLang = $frontendLangDir . $lang; 

            if (!File::exists($currentFrontendLang)) {
                File::makeDirectory($currentFrontendLang, 0755, true);
                if (File::exists($frontendDir)) {
                    File::copyDirectory($frontendDir, $currentFrontendLang);
                }
            }
        }
    }
}