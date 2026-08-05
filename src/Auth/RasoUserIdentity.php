<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Raso\ModuleKit\Domain\RasoUser;

/**
 * `RasoUser` (sof domen) ↔ Laravel `Authenticatable` ko'prigi.
 *
 * NEGA adapter: `RasoUser` Laravel interfeysini bajarsa, Domain qatlami
 * framework'ga bog'lanib qolardi — `deptrac` buni to'sadi va Octane'da
 * memory-safe bo'lish kafolati yo'qolardi. Adapter `Auth` qatlamida turadi.
 *
 * Parol/«remember me» tushunchasi YO'Q: sessiya IdP'da, modul faqat
 * tokenni tekshiradi.
 */
final readonly class RasoUserIdentity implements Authenticatable
{
    public function __construct(public RasoUser $rasoUser) {}

    public function getAuthIdentifierName(): string
    {
        return 'sub';
    }

    public function getAuthIdentifier(): string
    {
        return $this->rasoUser->sub->value;
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // Stateless: modul sessiya saqlamaydi.
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
