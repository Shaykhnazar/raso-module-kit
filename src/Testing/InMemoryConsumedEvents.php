<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\ConsumedEvents;

/**
 * Xotiradagi idempotentlik reestri.
 *
 * Haqiqiy implementatsiyada bu `INSERT ... ON CONFLICT DO NOTHING` bo'ladi;
 * bu yerda `isset` tekshiruvi ATOMIK, chunki PHP bitta oqimda ishlaydi.
 */
final class InMemoryConsumedEvents implements ConsumedEvents
{
    /** @var array<string, true> */
    public array $seen = [];

    public function markConsumed(string $eventId): bool
    {
        if (isset($this->seen[$eventId])) {
            return false;
        }

        $this->seen[$eventId] = true;

        return true;
    }

    /** Tranzaksiya qaytarilishini taqlid qiladi. */
    public function forget(string $eventId): void
    {
        unset($this->seen[$eventId]);
    }
}
