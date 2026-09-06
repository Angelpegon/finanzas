<?php

namespace App\Enums;

enum TipoHechoTesoreria: string
{
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';
    case Transferencia = 'transferencia';
    case Apertura = 'apertura';
    case AporteMeta = 'aporte_meta';
    /** Cierre/baja de liquidez: reduce disponible contra patrimonio (inverso de apertura). */
    case Cierre = 'cierre';
}
