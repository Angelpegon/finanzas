<?php

namespace App\Enums;

enum SeguridadAccion: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Registro = 'registro';
    case CorreccionAsiento = 'correccion_asiento';
    case Obligacion = 'obligacion';
    case Importacion = 'importacion';
}
