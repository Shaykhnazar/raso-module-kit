<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Events\OutboxStore;

/** Xotiradagi outbox — modul testlari Postgres'siz o'tsin. */
final class InMemoryOutboxStore implements OutboxStore
{
    /** @var array<string, OutboxMessage> */
    private array $messages = [];

    /** @var array<string, string> id → holat: published | failed | exhausted */
    public array $states = [];

    /** @var array<string, string> id → oxirgi xato matni */
    public array $errors = [];

    public function push(OutboxMessage $message): void
    {
        $this->messages[$message->id] = $message;
    }

    public function pending(int $limit): array
    {
        $pending = array_filter(
            $this->messages,
            fn (OutboxMessage $m): bool => ! isset($this->states[$m->id]) || $this->states[$m->id] === 'failed',
        );

        return array_slice(array_values($pending), 0, $limit);
    }

    public function markPublished(string $id): void
    {
        $this->states[$id] = 'published';
    }

    public function markFailed(string $id, string $error): void
    {
        $this->states[$id] = 'failed';
        $this->errors[$id] = $error;
        $this->messages[$id] = $this->messages[$id]->withAttempt();
    }

    public function markExhausted(string $id, string $error): void
    {
        $this->states[$id] = 'exhausted';
        $this->errors[$id] = $error;
    }
}
