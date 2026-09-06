<?php

namespace App\Domain\Enums;

enum TipoMovimiento: string
{
    case INGRESO = 'ingreso';
    case TRANSFORMACION_CONSUMO = 'transformacion_consumo';
    case TRANSFORMACION_PRODUCCION = 'transformacion_produccion';
    case BAJA_ROTURA = 'baja_rotura';
    case TRASPASO_SALIDA = 'traspaso_salida';
    case TRASPASO_ENTRADA = 'traspaso_entrada';
    case MERMA_TRANSITO = 'merma_transito';
}
