<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\EventPublisher;
use Raso\ModuleKit\Events\Exception\PublishFailed;
use Raso\ModuleKit\Events\OutboxMessage;

/** Yozilgan xabarlarni eslab qoladi; `$broken` bo'lsa har safar yiqiladi. */
final class RecordingPublisher implements EventPublisher
{
    /** @var list<OutboxMessage> */
    public array $published = [];

    public bool $broken = false;

    public function publish(OutboxMessage $message): void
    {
        if ($this->broken) {
            throw PublishFailed::forStream('test');
        }

        $this->published[] = $message;
    }
}
