<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\DeadLetters;
use Raso\ModuleKit\Events\OutboxMessage;

final class InMemoryDeadLetters implements DeadLetters
{
    /** @var list<array{message: OutboxMessage, error: string}> */
    public array $records = [];

    public function record(OutboxMessage $message, string $error): void
    {
        $this->records[] = ['message' => $message, 'error' => $error];
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function byName(): array
    {
        $counts = [];

        foreach ($this->records as $record) {
            $name = $record['message']->name;
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return $counts;
    }
}
