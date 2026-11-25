<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * Every arrow of the lifecycle diagram, and nothing else.
     *
     * @return array<string, array{0: OrderStatus, 1: OrderStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'pendiente → en_preparacion' => [OrderStatus::Pending, OrderStatus::InPreparation],
            'pendiente → cancelado' => [OrderStatus::Pending, OrderStatus::Cancelled],
            'en_preparacion → listo' => [OrderStatus::InPreparation, OrderStatus::Ready],
            'en_preparacion → cancelado' => [OrderStatus::InPreparation, OrderStatus::Cancelled],
            'listo → entregado' => [OrderStatus::Ready, OrderStatus::Delivered],
            'entregado → pagado' => [OrderStatus::Delivered, OrderStatus::Paid],
        ];
    }

    #[Test]
    public function should_allow_exactly_the_transitions_of_the_lifecycle(): void
    {
        $expected = array_map(fn (array $pair) => $pair[0]->value.'→'.$pair[1]->value, self::allowedTransitions());

        $actual = [];
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                if ($from->canTransitionTo($to)) {
                    $actual[] = $from->value.'→'.$to->value;
                }
            }
        }

        $this->assertEqualsCanonicalizing(array_values($expected), $actual);
    }

    #[Test]
    #[DataProvider('allowedTransitions')]
    public function should_not_allow_going_backwards(OrderStatus $from, OrderStatus $to): void
    {
        $backwards = $to->canTransitionTo($from);

        $this->assertFalse($backwards);
    }

    #[Test]
    public function should_accept_new_items_only_before_the_kitchen_finishes(): void
    {
        $accepting = array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->acceptsNewItems());

        $this->assertEqualsCanonicalizing([OrderStatus::Pending, OrderStatus::InPreparation], array_values($accepting));
    }

    #[Test]
    public function should_be_closed_only_when_paid_or_cancelled(): void
    {
        $closed = array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->isClosed());

        $this->assertEqualsCanonicalizing([OrderStatus::Paid, OrderStatus::Cancelled], array_values($closed));
    }
}
