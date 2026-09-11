<?php

namespace App\Http\Controllers\API\Admin;

use App\Helpers\LanguageHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Editor de archivos de idioma crudos (item 2 de la auditoria de migracion,
 * 2026-09-11) -- unico reemplazo real de LanguageController (Blade)
 * get-lang-file/save-lang-file, que edita directamente los .php de
 * resources/lang/{locale}/{file}.php (o Modules/Frontend para frontend_msg).
 * El sistema languages/language-keywords (BD) es un concepto distinto, no
 * sustituye poder tocar el archivo fuente.
 */
class LanguageFileController extends Controller
{
    private const ALLOWED_FILES = ['auth', 'message', 'pagination', 'passwords', 'validation'];

    private function resolvePath(string $type, string $lang, string $file): string
    {
        $dir = $type === 'frontend'
            ? base_path('Modules/Frontend/resources/lang/')
            : resource_path('lang/');

        return $dir . $lang . '/' . $file . '.php';
    }

    public function index(Request $request)
    {
        $lang = $request->get('lang', 'en');
        $type = $request->get('type', 'backend');

        $dir = $type === 'frontend'
            ? base_path('Modules/Frontend/resources/lang/' . $lang)
            : resource_path('lang/' . $lang);

        $files = File::exists($dir)
            ? collect(File::files($dir))->map(fn ($f) => pathinfo($f, PATHINFO_FILENAME))->values()
            : collect();

        return json_custom_response(['data' => $files]);
    }

    public function show(Request $request, string $lang, string $file)
    {
        $type = $request->get('type', 'backend');

        if (!in_array($file, self::ALLOWED_FILES, true)) {
            return json_message_response('Archivo de idioma no permitido.', 422);
        }

        $path = $this->resolvePath($type, $lang, $file);

        if (!File::exists($path)) {
            return json_message_response('Archivo de idioma no encontrado.', 404);
        }

        $langData = require $path;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($langData), \RecursiveIteratorIterator::SELF_FIRST);
        $path_stack = [];
        $flat = [];

        foreach ($iterator as $key => $value) {
            $path_stack[$iterator->getDepth()] = $key;
            if (!is_array($value)) {
                $flat[implode('|', array_slice($path_stack, 0, $iterator->getDepth() + 1))] = $value;
            }
        }

        return json_custom_response(['data' => $flat]);
    }

    public function update(Request $request, string $lang, string $file)
    {
        $type = $request->get('type', 'backend');

        if (!in_array($file, self::ALLOWED_FILES, true)) {
            return json_message_response('Archivo de idioma no permitido.', 422);
        }

        $request->validate(['data' => 'required|array']);

        $path = $this->resolvePath($type, $lang, $file);

        if (!File::exists(dirname($path))) {
            return json_message_response('Idioma no encontrado.', 404);
        }

        $data = LanguageHelper::flattenToMultiDimensional($request->input('data'), '|');

        $fp = fopen($path, 'w');
        fwrite($fp, var_export($data, true));
        fclose($fp);
        File::prepend($path, '<?php return  ');
        File::append($path, ';');

        return json_custom_response(['message' => 'Archivo de idioma actualizado.']);
    }
}
