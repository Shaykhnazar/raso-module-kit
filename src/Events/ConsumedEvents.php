<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * Idempotentlik reestri — `consumed_events(event_id)` UNIQUE jadvali.
 *
 * At-least-once yetkazishda bitta xabar bir necha marta keladi. Bu port
 * «birinchi marta ko'ryapmanmi» degan savolga **atomik** javob beradi.
 */
interface ConsumedEvents
{
    /**
     * Xabarni ko'rilgan deb belgilaydi.
     *
     * @return bool `true` — birinchi marta (qayta ishlash kerak);
     *              `false` — allaqachon ko'rilgan (o'tkazib yuboriladi)
     *
     * ⚠️ Amal ATOMIK bo'lishi shart (`INSERT ... ON CONFLICT DO NOTHING`).
     * «Avval SELECT, keyin INSERT» — poyga: ikki worker bir xabarni
     * bir vaqtda qayta ishlaydi.
     */
    public function markConsumed(string $eventId): bool;
}
