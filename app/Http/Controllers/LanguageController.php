<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Support\Facades\File;
use \RecursiveIteratorIterator;
use \RecursiveArrayIterator;
use \RecursiveDirectoryIterator;
use \DirectoryIterator;
use App\Helpers\LanguageHelper;

class LanguageController extends Controller
{
    public function getFile(Request $request)
    {
        $requestLangData = $request->all();
        $filename = $requestLangData['file'];
        $requestLang = $requestLangData['lang']; 
        $type = $request->type == 'frontend_msg' ? 'frontend-language-setting' : 'language-setting';
        $langData = ($request->type == 'frontend_msg') ? trans('frontend::' . $requestLangData['file'], [], $requestLangData['lang']) : trans($requestLangData['file'], [], $requestLangData['lang']);

        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($langData),RecursiveIteratorIterator::SELF_FIRST);
        $path = [];
        $flatArray = [];
        
        foreach ($iterator as $key => $value) {
            $path[$iterator->getDepth()] = $key;
        
            if (!is_array($value)) {
                $flatArray[
                    implode('|', array_slice($path, 0, $iterator->getDepth() + 1))
                ] = $value;
            }
        }
        $data  = view('language.index', compact('flatArray','filename','requestLang', 'type'))->render();
        return json_custom_response($data);
    }

    public function saveFileContent(Request $request)
    {
        $data = $request->all();
       
        $requestFile = $data['filename'] .'.php';

        $frontendLangDir = base_path() . '/Modules/Frontend/resources/lang/';
        $backendLangDir = resource_path().'/lang/';

        $isFrontend = ($request->type == 'frontend-language-setting');
        $langDir = $isFrontend ? $frontendLangDir : $backendLangDir;
        $page = $isFrontend ? 'frontend-language-setting' : 'language-setting';
        $filename = $langDir .$data['requestLang'] .'/' . $requestFile;         
    
        unset($data['_token'], $data['filename'], $data['requestLang'], $data['type']);

        $data = LanguageHelper::flattenToMultiDimensional($data, '|');
        $fp = fopen($filename, 'w');
        fwrite($fp, var_export($data, true));
        File::prepend($filename, '<?php return  ');
        File::append($filename, ';');
        return redirect()->route('setting.index', ['page' => $page])->withSuccess( __('message.updated'));
    }
}
