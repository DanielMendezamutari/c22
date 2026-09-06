<?php

namespace App\Domain\Enums;

enum EstadoTraspaso: string
{
    case EN_TRANSITO = 'en_transito';
    case RECIBIDO_CONFORME = 'recibido_conforme';
    case RECIBIDO_CON_DISCREPANCIA = 'recibido_con_discrepancia';
    case CANCELADO = 'cancelado';
}
