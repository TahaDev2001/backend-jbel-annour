<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ApmNewUser extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'facturation';
    protected $table = 'apm_new_users';

    protected $fillable = [
        'nom_complet',
        'societe',
        'ice',
        'username',
        'email',
        'password',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'validated_at' => 'datetime',
    ];
}