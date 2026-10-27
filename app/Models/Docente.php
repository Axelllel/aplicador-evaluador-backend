<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Docente extends Authenticatable
{
    protected $table = 'docente';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'paquete_id',
        'estado_pago'
    ];
}