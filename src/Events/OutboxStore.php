<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * Outbox jadvali bilan ishlash porti.
 *
 * Yozish qismi ATAYLAB bu yerda emas: xabar biznes amali bilan **bitta
 * tranzaksiyada** yoziladi (repository ichida). Bu port faqat relay uchun —
 * u alohida jarayon va tranzaksiyadan TASHQARIDA ishlaydi.
 */
interface OutboxStore
{
    /**
     * Yuborilmagan xabarlar, eng eskisidan boshlab.
     *
     * @return list<OutboxMessage>
     */
    public function pending(int $limit): array;

    public function markPublished(string $id): void;

    /** Urinishlar sonini oshiradi va xatoni saqlaydi. */
    public function markFailed(string $id, string $error): void;

    /** Urinishlar tugagan xabar — qo'lda ko'rib chiqiladi, qayta urinilmaydi. */
    public function markExhausted(string $id, string $error): void;
}
