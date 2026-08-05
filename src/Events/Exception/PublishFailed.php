<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events\Exception;

use RuntimeException;
use Throwable;

/** Xabarni stream'ga yozib bo'lmadi (Redis yiqilgan, tarmoq, konfig). */
final class PublishFailed extends RuntimeException
{
    public static function forStream(string $stream, ?Throwable $previous = null): self
    {
        return new self("Stream'ga yozilmadi: {$stream}", 0, $previous);
    }
}
