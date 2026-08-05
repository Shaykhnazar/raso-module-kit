<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\Locale;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Domain\VerificationLevel;
use Raso\ModuleKit\Testing\RasoUserFactory;

it('sukut bo\'yicha yaroqli foydalanuvchi yasaydi', function (): void {
    $user = RasoUserFactory::make();

    expect($user->name)->toBe('Test Foydalanuvchi')
        ->and($user->locale)->toBe(Locale::Uz)
        ->and($user->role)->toBe('user')
        ->and($user->verificationLevel)->toBe(VerificationLevel::L2);
});

it('har chaqiruvda boshqa `sub`', function (): void {
    expect(RasoUserFactory::make()->sub->value)
        ->not->toBe(RasoUserFactory::make()->sub->value);
});

it('claim ustidan yozadi', function (): void {
    $user = RasoUserFactory::make(['role' => 'admin', 'scope' => 'chat:write']);

    expect($user->isAdmin())->toBeTrue()
        ->and($user->can('chat:write'))->toBeTrue();
});

it('ma\'lum `sub` bilan yasaydi — event testlari uchun', function (): void {
    $sub = PublicId::generate()->value;

    expect(RasoUserFactory::withSub($sub)->sub->value)->toBe($sub);
});

it('global `fakeRasoUser()` yordamchisi mavjud', function (): void {
    expect(fakeRasoUser(['role' => 'mentor'])->role)->toBe('mentor');
});
