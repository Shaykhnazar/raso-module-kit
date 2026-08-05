<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

use DateTimeImmutable;

/**
 * Barcha domen hodisalari uchun bazaviy shartnoma.
 *
 * Modul platformasida hodisa — servislararo YAGONA muloqot vositasi
 * (outbox → relay → Redis Stream → consumer). Sinxron servis-servis chaqiruvi
 * yo'q, shuning uchun bu interfeys chegara hisoblanadi: framework'ga bog'liq
 * emas, sof PHP.
 */
interface DomainEvent
{
    /** Hodisa yuz bergan payt (UTC). */
    public function occurredAt(): DateTimeImmutable;

    /**
     * Barqaror nom — consumer'lar shu bo'yicha marshrutlaydi.
     * Shakli: `<context>.<hodisa_o'tgan_zamonda>` — `identity.user_deleted`.
     */
    public function eventName(): string;

    /**
     * Serializatsiya uchun payload (`outbox_messages.payload` jsonb).
     *
     * ⚠️ Payload'ga PII solmang: u Redis'da va boshqa servis log'ida qoladi.
     * Foydalanuvchi identifikatori sifatida `sub` (uuid) yetadi.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
