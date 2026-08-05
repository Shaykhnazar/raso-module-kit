<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use Raso\ModuleKit\Events\Exception\PublishFailed;

/** Xabarni servisdan tashqariga chiqaradi (Redis Stream). */
interface EventPublisher
{
    /**
     * @throws PublishFailed
     */
    public function publish(OutboxMessage $message): void;
}
