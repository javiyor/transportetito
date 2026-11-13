<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionCorreo extends Model
{
    protected $table = 'configuracion_correos';

    protected $fillable = [
        'mailer',
        'scheme',
        'host',
        'port',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
    ];
}
