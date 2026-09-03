<?php

namespace App\Enums;

enum NaturalezaCuenta: string
{
    case Activo = 'activo';
    case Pasivo = 'pasivo';
    case Patrimonio = 'patrimonio';
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';
}
