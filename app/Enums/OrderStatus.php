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

    /**
     * The order lifecycle: which statuses can follow this one.
     *
     * @return array<int, self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::InPreparation, self::Cancelled],
            self::InPreparation => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered],
            self::Delivered => [self::Paid],
            self::Paid, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextStatuses(), true);
    }

    /**
     * Paid and cancelled orders are finished: they free the table and accept no changes.
     */
    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    /**
     * New dishes can only be added before the kitchen finishes the order.
     */
    public function acceptsNewItems(): bool
    {
        return in_array($this, [self::Pending, self::InPreparation], true);
    }

    /**
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [self::Paid, self::Cancelled];
    }
}
