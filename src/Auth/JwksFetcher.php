<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Raso\ModuleKit\Auth\Exception\JwksUnavailable;

/**
 * IdP'ning JWKS hujjatini oladigan port.
 *
 * Alohida interfeys, chunki testlar tarmoqqa CHIQMASLIGI kerak
 * (`Raso\ModuleKit\Testing\FakeJwtIssuer` shu portni bajaradi).
 */
interface JwksFetcher
{
    /**
     * @return array{keys: list<array<string, mixed>>}
     *
     * @throws JwksUnavailable tarmoq, HTTP yoki format xatosi
     */
    public function fetch(string $jwksUri): array;
}
