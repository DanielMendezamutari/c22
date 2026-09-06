<?php

namespace App\Domain\Enums;

enum EstadoTurno: string
{
    case ABIERTO = 'abierto';
    case COBRADO = 'cobrado';
    case CERRADO = 'cerrado';
    case AUDITADO = 'auditado';
}
