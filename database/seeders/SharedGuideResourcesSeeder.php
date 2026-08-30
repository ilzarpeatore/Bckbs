<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class SharedGuideResourcesSeeder extends Seeder
{
    /**
     * Siembra las 6 guías compartidas (item 13 del backlog). El HTML de cada
     * guía vive en database/seeders/resources_html/*.html -- ya sanitizado,
     * sin <style>/<script>, listo para pegar tal cual en `resources.content`.
     *
     * Usa firstOrCreate (clave: title) para que el seeder sea seguro de
     * re-ejecutar sin duplicar filas.
     */
    public function run(): void
    {
        // resources.coach_id no es nullable (FK a users, onDelete cascade),
        // así que estos recursos globales necesitan un "autor" -- se usa el
        // primer usuario admin (o, a falta de uno, el primer usuario que
        // exista) como coach_id.
        $coachId = User::where('user_type', 'admin')->value('id')
            ?? User::query()->value('id');

        if (!$coachId) {
            $this->command?->warn('SharedGuideResourcesSeeder: no hay ningún usuario en la BD, se omite.');
            return;
        }

        $guides = [
            [
                'title'    => 'Guía de Autogestión',
                'category' => 'entrenamiento',
                'file'     => 'guia-autogestion.html',
            ],
            [
                'title'    => 'Guía de Sobrentrenamiento',
                'category' => 'entrenamiento',
                'file'     => 'guia-sobrentrenamiento.html',
            ],
            [
                'title'    => 'Guía de Suplementación',
                'category' => 'nutricion',
                'file'     => 'guia-suplementacion.html',
            ],
            [
                'title'    => 'Guía de Sueño y Recuperación',
                'category' => 'habitos_mindset',
                'file'     => 'guia-sueno.html',
            ],
            [
                'title'    => 'Guía de Gestión del Estrés',
                'category' => 'habitos_mindset',
                'file'     => 'guia-gestion-estres.html',
            ],
            [
                'title'    => 'Manual de Mentalidad',
                'category' => 'habitos_mindset',
                'file'     => 'guia-mentalidad.html',
            ],
        ];

        foreach ($guides as $guide) {
            $path = __DIR__ . '/resources_html/' . $guide['file'];

            Resource::firstOrCreate(
                ['title' => $guide['title']],
                [
                    'type'     => 'article',
                    'scope'    => 'shared',
                    'category' => $guide['category'],
                    'coach_id' => $coachId,
                    'content'  => file_exists($path) ? file_get_contents($path) : null,
                ]
            );
        }
    }
}
