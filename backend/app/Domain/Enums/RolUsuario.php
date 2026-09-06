<?php

namespace App\Domain\Enums;

enum RolUsuario: string
{
    case BARMAN = 'barman';
    case GARZON = 'garzon';
    case ADMIN = 'admin';
}
