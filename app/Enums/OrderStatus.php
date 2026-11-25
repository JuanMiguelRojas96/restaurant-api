<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pendiente';
    case InPreparation = 'en_preparacion';
    case Ready = 'listo';
    case Delivered = 'entregado';
    case Paid = 'pagado';
    case Cancelled = 'cancelado';
}
