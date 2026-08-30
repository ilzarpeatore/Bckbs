<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Herramienta temporal de desarrollo — ver migracion
 * create_screen_review_marks_table para el contexto completo. Se borrara
 * junto con el resto del feature (FAB, controller, rutas) cuando ya no
 * haga falta.
 */
class ScreenReviewMark extends Model
{
    protected $fillable = ['user_id', 'route_name', 'status', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
