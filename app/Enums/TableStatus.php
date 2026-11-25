<?php

namespace App\Enums;

enum TableStatus: string
{
    case Free = 'libre';
    case Occupied = 'ocupada';
    case Reserved = 'reservada';
}
