<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Raso\ModuleKit\Auth\CachedJwksProvider;
use Raso\ModuleKit\Auth\TokenVerifier;

/**
 * Auth testlari uchun tayyor to'plam: soxta IdP + xotiradagi kesh + verifier.
 *
 * NEGA klass, test faylidagi `function` emas: `pest --parallel` ikki test
 * faylini bitta jarayonga yuklasa bir xil nomli funksiya `Cannot redeclare`
 * bilan yiqiladi — va bu xato ketma-ket ishlatilganda KO'RINMAYDI. Klassni
 * esa autoloader bir marta yuklaydi.
 */
final readonly class AuthKit
{
    public function __construct(
        public TokenVerifier $verifier,
        public FakeJwtIssuer $issuer,
        public CachedJwksProvider $jwks,
    ) {}

    public static function make(int $refreshCooldownSeconds = 300, int $leeway = 30): self
    {
        $issuer = new FakeJwtIssuer;

        $jwks = new CachedJwksProvider(
            new Repository(new ArrayStore),
            $issuer,
            'https://api.raso.uz/oauth/jwks',
            ttlSeconds: 86400,
            refreshCooldownSeconds: $refreshCooldownSeconds,
        );

        return new self(
            new TokenVerifier($jwks, $issuer->issuer, $issuer->audience, $leeway),
            $issuer,
            $jwks,
        );
    }
}
