<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth\Exception;

use RuntimeException;
use Throwable;

/** IdP'ning JWKS hujjati olinmadi (tarmoq, HTTP status yoki format xatosi). */
final class JwksUnavailable extends RuntimeException
{
    public static function transport(string $uri, ?Throwable $previous = null): self
    {
        return new self("JWKS olinmadi: {$uri}", 0, $previous);
    }

    public static function malformed(string $uri): self
    {
        return new self("JWKS shakli buzuq (`keys` massivi yo'q): {$uri}");
    }
}
