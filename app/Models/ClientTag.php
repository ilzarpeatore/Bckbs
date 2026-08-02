<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientTag extends Model
{
    use HasFactory;

    protected $fillable = ['coach_id', 'title', 'color'];

    public function clients()
    {
        return $this->belongsToMany(User::class, 'client_tag_assignments', 'client_tag_id', 'client_id');
    }
}
