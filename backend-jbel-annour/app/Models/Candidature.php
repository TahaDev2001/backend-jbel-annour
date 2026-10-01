<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidature extends Model
{
    protected $table = 'candidatures';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'cv',
    ];
}
