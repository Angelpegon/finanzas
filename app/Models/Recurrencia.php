<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class Recurrencia extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'recurrencias';
    protected $guarded = ['id'];
    protected $casts = ['activa' => 'boolean'];
}
