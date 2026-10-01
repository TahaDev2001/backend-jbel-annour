<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $table = 'produits';

    protected $fillable = [
        'quantite_b7',
        'quantite_b10',
        'quantite_10',
        'societe',
        'email',
        'telephone',
    ];
}
