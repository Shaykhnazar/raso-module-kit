<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

/**
 * Aggregate root bazaviy klassi. Domen hodisalarini ichida to'playdi;
 * repository ularni saqlash tranzaksiyasida outbox'ga yozadi.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * To'plangan hodisalarni TOZALAMASDAN qaytaradi.
     *
     * Repository shuni ishlatsin: hodisalarni o'qib tranzaksiyada outbox'ga
     * yozadi, `releaseEvents()` ni esa faqat commit muvaffaqiyatli bo'lgach
     * chaqiradi. Aks holda rollback'da hodisalar aggregate'dan yo'qolib,
     * qayta urinishda jimgina tushib qolardi.
     *
     * @return list<DomainEvent>
     */
    public function pendingEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * To'plangan hodisalarni chiqaradi va ichki ro'yxatni tozalaydi.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
