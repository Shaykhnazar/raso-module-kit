<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\EventConsumer;
use Raso\ModuleKit\Events\EventHandler;
use Raso\ModuleKit\Events\OutboxRelay;

/**
 * Event testlari uchun tayyor to'plam — `AuthKit` bilan bir naqshda.
 *
 * Redis ham, Postgres ham kerak emas: hammasi xotirada.
 */
final readonly class EventKit
{
    public function __construct(
        public InMemoryOutboxStore $outbox,
        public RecordingPublisher $publisher,
        public InMemoryConsumedEvents $consumed,
        public InMemoryDeadLetters $deadLetters,
        public FakeTransactionRunner $transaction,
    ) {}

    public static function make(): self
    {
        $consumed = new InMemoryConsumedEvents;

        return new self(
            new InMemoryOutboxStore,
            new RecordingPublisher,
            $consumed,
            new InMemoryDeadLetters,
            // Rollback `consumed_events` dagi izni ham qaytaradi — haqiqiy
            // tranzaksiya aynan shunday qiladi.
            new FakeTransactionRunner(static function () use ($consumed): void {
                $consumed->seen = [];
            }),
        );
    }

    public function relay(int $batchSize = 100, int $maxAttempts = 5): OutboxRelay
    {
        return new OutboxRelay($this->outbox, $this->publisher, $batchSize, $maxAttempts);
    }

    /** @param list<EventHandler> $handlers */
    public function consumer(array $handlers): EventConsumer
    {
        return new EventConsumer($this->consumed, $this->deadLetters, $this->transaction, $handlers);
    }
}
