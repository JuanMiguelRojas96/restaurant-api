<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Waiter = 'mesero';
    case Kitchen = 'cocina';
    case Customer = 'cliente';
}
