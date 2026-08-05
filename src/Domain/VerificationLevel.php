<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

/**
 * Foydalanuvchi verifikatsiya darajasi — `04 - Modul SDK va Trust-Safety` §2.1.
 *
 * L0 mehmon · L1 email/telefon OTP yoki Telegram · L2 telefon tasdiqlangan ·
 * L3 shaxs hujjati yoki jonli intervyu.
 *
 * Modul «bu amal L3 talab qiladi» deb O'ZI qaror qiladi — core majburlamaydi.
 */
enum VerificationLevel: string
{
    case L0 = 'L0';
    case L1 = 'L1';
    case L2 = 'L2';
    case L3 = 'L3';

    /**
     * Token claim'idan o'qiydi. Tanilmasa — ENG PAST daraja (fail-closed):
     * noma'lum qiymat hech qachon ruxsatni kengaytirmasin.
     */
    public static function fromClaim(?string $raw): self
    {
        return self::tryFrom((string) $raw) ?? self::L0;
    }

    public function atLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::L0 => 0,
            self::L1 => 1,
            self::L2 => 2,
            self::L3 => 3,
        };
    }
}
