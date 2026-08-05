<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Illuminate\Http\Client\Factory as Http;
use Raso\ModuleKit\Auth\Exception\JwksUnavailable;
use Throwable;

/**
 * JWKS ni IdP'dan HTTP orqali oladi.
 *
 * Timeout ATAYLAB qisqa: JWKS ni ololmaslik tokenni rad etishga olib keladi
 * (fail-closed), shuning uchun uzoq kutish — hamma so'rovni osib qo'yish.
 * Tezroq yiqilib, keshdagi kalit bilan davom etgan afzal.
 */
final readonly class HttpJwksFetcher implements JwksFetcher
{
    public function __construct(
        private Http $http,
        private int $timeoutSeconds = 3,
    ) {}

    public function fetch(string $jwksUri): array
    {
        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($jwksUri);
        } catch (Throwable $e) {
            throw JwksUnavailable::transport($jwksUri, $e);
        }

        if (! $response->successful()) {
            throw JwksUnavailable::transport($jwksUri);
        }

        /** @var mixed $document */
        $document = $response->json();

        if (! is_array($document) || ! isset($document['keys']) || ! is_array($document['keys'])) {
            throw JwksUnavailable::malformed($jwksUri);
        }

        /** @var array{keys: list<array<string, mixed>>} $document */
        return $document;
    }
}
