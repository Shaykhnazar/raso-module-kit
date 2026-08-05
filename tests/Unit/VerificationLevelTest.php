<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\VerificationLevel;

it('to\'rt daraja, tartib bilan', function (): void {
    expect(array_map(fn (VerificationLevel $l): string => $l->value, VerificationLevel::cases()))
        ->toBe(['L0', 'L1', 'L2', 'L3']);
});

it('darajalarni taqqoslaydi', function (): void {
    expect(VerificationLevel::L2->atLeast(VerificationLevel::L1))->toBeTrue()
        ->and(VerificationLevel::L2->atLeast(VerificationLevel::L2))->toBeTrue()
        ->and(VerificationLevel::L1->atLeast(VerificationLevel::L2))->toBeFalse();
});

it('noma\'lum qiymat uchun ENG PAST darajaga qaytadi', function (string $raw): void {
    // Fail-closed: token'da tanishmagan qiymat kelsa, ruxsat KENGAYMAYDI.
    expect(VerificationLevel::fromClaim($raw))->toBe(VerificationLevel::L0);
})->with(['', 'L9', 'admin', 'l2 ']);

it('yaroqli claim to\'g\'ri o\'qiladi', function (): void {
    expect(VerificationLevel::fromClaim('L3'))->toBe(VerificationLevel::L3);
});

it('mehmon hech narsani ocholmaydi, L3 hammasini ochadi', function (): void {
    expect(VerificationLevel::L0->atLeast(VerificationLevel::L1))->toBeFalse()
        ->and(VerificationLevel::L3->atLeast(VerificationLevel::L3))->toBeTrue();
});
