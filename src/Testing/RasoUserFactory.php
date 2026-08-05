<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Domain\RasoUser;

/**
 * Testlarda determinstik `RasoUser` yasaydi.
 *
 * Har modul o'z testida shu bittasini ishlatadi — nusxa ko'chirilmasin, aks
 * holda 6 ta repoda 6 xil "fake user" paydo bo'ladi va ular farqlanib ketadi.
 */
final class RasoUserFactory
{
    /**
     * @param  array<string, mixed>  $overrides  ID token claim'lari ustidan yoziladi
     */
    public static function make(array $overrides = []): RasoUser
    {
        return RasoUser::fromClaims([
            'sub' => PublicId::generate()->value,
            'name' => 'Test Foydalanuvchi',
            'picture' => null,
            'locale' => 'uz',
            'role' => 'user',
            'verification_level' => 'L2',
            'scope' => 'openid profile',
            ...$overrides,
        ]);
    }

    /**
     * Ma'lum `sub` bilan — event va o'chirish testlarida kerak.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function withSub(string $sub, array $overrides = []): RasoUser
    {
        return self::make([...$overrides, 'sub' => $sub]);
    }
}
