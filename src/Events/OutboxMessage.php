<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Raso\ModuleKit\Domain\DomainEvent;
use Raso\ModuleKit\Domain\PublicId;

/**
 * Outbox'dagi bitta xabar — servislararo yetkaziladigan birlik.
 *
 * `id` — UUIDv7 va **idempotentlik kaliti**: iste'molchi shu bo'yicha
 * «bu xabarni ko'rganmanmi» deb hal qiladi. At-least-once yetkazishda bitta
 * xabar bir necha marta kelishi NORMAL holat, xato emas.
 */
final readonly class OutboxMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $name,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public int $attempts = 0,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException("Hodisa nomi bo'sh bo'lmaydi.");
        }
    }

    public static function fromDomainEvent(DomainEvent $event): self
    {
        return new self(
            id: PublicId::generate()->value,
            name: $event->eventName(),
            payload: $event->toPayload(),
            occurredAt: $event->occurredAt(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'payload' => $this->payload,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'attempts' => $this->attempts,
        ];
    }

    /**
     * Stream'dan yoki DB qatoridan qayta quradi.
     *
     * Tashqi manba — ishonchsiz: har maydon tekshiriladi. Buzuq xabar
     * `InvalidArgumentException` beradi va iste'molchi uni DLQ'ga tashlaydi.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        $payload = $row['payload'] ?? [];
        $occurredAt = $row['occurred_at'] ?? null;

        if (! is_string($id) || ! is_string($name) || ! is_array($payload)) {
            throw new InvalidArgumentException('Outbox xabari shakli buzuq.');
        }

        /** @var array<string, mixed> $payload */
        return new self(
            id: $id,
            name: $name,
            payload: $payload,
            occurredAt: is_string($occurredAt)
                ? new DateTimeImmutable($occurredAt)
                : new DateTimeImmutable('now', new DateTimeZone('UTC')),
            attempts: is_int($row['attempts'] ?? null) ? $row['attempts'] : 0,
        );
    }

    public function withAttempt(): self
    {
        return new self($this->id, $this->name, $this->payload, $this->occurredAt, $this->attempts + 1);
    }
}
