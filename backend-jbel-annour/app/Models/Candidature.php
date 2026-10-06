<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidature extends Model
{
    protected $connection = 'facturation';

    protected $table = 'apm_candidatures';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'cv',
    ];
}
