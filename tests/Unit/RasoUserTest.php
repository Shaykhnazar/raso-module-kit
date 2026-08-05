<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\Locale;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Domain\RasoUser;
use Raso\ModuleKit\Domain\Scope;
use Raso\ModuleKit\Domain\VerificationLevel;

it('ID token claim\'laridan quriladi', function (): void {
    $sub = PublicId::generate();

    $user = RasoUser::fromClaims([
        'sub' => $sub->value,
        'name' => 'Shaykhnazar',
        'picture' => 'https://cdn.raso.uz/a.png',
        'locale' => 'uz',
        'role' => 'user',
        'verification_level' => 'L2',
        'scope' => 'openid profile chat:read',
    ]);

    expect($user->sub->equals($sub))->toBeTrue()
        ->and($user->name)->toBe('Shaykhnazar')
        ->and($user->avatarUrl)->toBe('https://cdn.raso.uz/a.png')
        ->and($user->locale)->toBe(Locale::Uz)
        ->and($user->role)->toBe('user')
        ->and($user->verificationLevel)->toBe(VerificationLevel::L2)
        ->and($user->can('chat:read'))->toBeTrue();
});

it('`sub` MAJBURIY — yo\'q bo\'lsa quriladigan foydalanuvchi yo\'q', function (): void {
    RasoUser::fromClaims(['name' => 'Kimdir']);
})->throws(InvalidArgumentException::class);

it('`sub` uuidv7 bo\'lmasa rad etadi — bigint id hech qachon o\'tmaydi', function (): void {
    // Bu regressiya himoyasi: core `users.id` (bigint) tasodifan `sub` ga tushib
    // qolsa, modul uni JIM qabul qilmasin.
    RasoUser::fromClaims(['sub' => '42']);
})->throws(InvalidArgumentException::class);

it('ixtiyoriy claim\'lar yo\'q bo\'lsa xavfsiz sukut qiymati', function (): void {
    $user = RasoUser::fromClaims(['sub' => PublicId::generate()->value]);

    expect($user->name)->toBe('')
        ->and($user->avatarUrl)->toBeNull()
        ->and($user->locale)->toBe(Locale::Uz)
        ->and($user->role)->toBe('user')
        ->and($user->verificationLevel)->toBe(VerificationLevel::L0)
        ->and($user->scopes->isEmpty())->toBeTrue();
});

it('noma\'lum rol `user` ga tushiriladi — huquq KENGAYMAYDI', function (string $raw): void {
    $user = RasoUser::fromClaims(['sub' => PublicId::generate()->value, 'role' => $raw]);

    expect($user->role)->toBe('user');
})->with(['', 'superadmin', 'ADMIN', 'root']);

it('tanish rollar saqlanadi', function (string $role): void {
    $user = RasoUser::fromClaims(['sub' => PublicId::generate()->value, 'role' => $role]);

    expect($user->role)->toBe($role);
})->with(['user', 'mentor', 'moderator', 'admin']);

it('scope va verifikatsiya darajasini tekshiradi', function (): void {
    $user = RasoUser::fromClaims([
        'sub' => PublicId::generate()->value,
        'verification_level' => 'L2',
        'scope' => 'calendar:read',
    ]);

    expect($user->can('calendar:read'))->toBeTrue()
        ->and($user->can('calendar:write'))->toBeFalse()
        ->and($user->isVerifiedAtLeast(VerificationLevel::L2))->toBeTrue()
        ->and($user->isVerifiedAtLeast(VerificationLevel::L3))->toBeFalse();
});

/**
 * ⚠️ REGRESSIYA HIMOYASI — `Modul platformasi/00` §4.4 va `01` §3.3.
 *
 * Modul foydalanuvchining shaxsiy kontaktini KO'RMASLIGI kerak. Kimdir
 * `RasoUser` ga `email` yoki `phone` qo'shsa, bu test darhol yiqiladi va
 * qaror qayta ochilishini talab qiladi — jimgina o'tib ketmaydi.
 */
it('PII maydonini SAQLAMAYDI', function (): void {
    $properties = array_map(
        fn (ReflectionProperty $p): string => strtolower($p->getName()),
        (new ReflectionClass(RasoUser::class))->getProperties(),
    );

    $forbidden = ['email', 'phone', 'telegram', 'passport', 'birth', 'address', 'password'];

    foreach ($properties as $property) {
        foreach ($forbidden as $needle) {
            expect(str_contains($property, $needle))->toBeFalse(
                "RasoUser da taqiqlangan PII maydoni: {$property}",
            );
        }
    }
});

it('PII claim yuborilsa ham uni QABUL QILMAYDI', function (): void {
    $user = RasoUser::fromClaims([
        'sub' => PublicId::generate()->value,
        'email' => 'kimdir@raso.uz',
        'phone_number' => '+998901234567',
    ]);

    $serialized = json_encode($user->toArray(), JSON_THROW_ON_ERROR);

    expect(str_contains($serialized, 'kimdir@raso.uz'))->toBeFalse()
        ->and(str_contains($serialized, '998901234567'))->toBeFalse();
});

it('`scopes` VO sifatida saqlanadi — xom satr emas', function (): void {
    $user = RasoUser::fromClaims(['sub' => PublicId::generate()->value, 'scope' => 'a b']);

    expect($user->scopes)->toBeInstanceOf(Scope::class);
});
