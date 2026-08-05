<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\PublicId;

it('uuidv7 generatsiya qiladi', function (): void {
    $id = PublicId::generate();

    expect($id->value)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('vaqt bo\'yicha tartiblangan — indeks lokalligi shunga tayanadi', function (): void {
    $first = PublicId::generate();
    usleep(2000);
    $second = PublicId::generate();

    expect(strcmp($second->value, $first->value))->toBeGreaterThan(0);
});

it('yaroqli satrdan quriladi', function (): void {
    $id = PublicId::generate();

    expect(PublicId::fromString($id->value)->equals($id))->toBeTrue();
});

it('uuidv4 ni RAD ETADI — versiya ham tekshiriladi', function (): void {
    // v4: uchinchi guruh `4` bilan boshlanadi. Faqat "uuidga o'xshaydi" yetarli emas.
    PublicId::fromString('9f1b7c3e-2a4d-4f6b-8c1d-2e3f4a5b6c7d');
})->throws(InvalidArgumentException::class);

it('axlat satrni rad etadi', function (string $value): void {
    PublicId::fromString($value);
})->with(['', 'salom', '123', '9f1b7c3e2a4d7f6b8c1d2e3f4a5b6c7d'])
    ->throws(InvalidArgumentException::class);

it('satrga aylanadi', function (): void {
    $id = PublicId::generate();

    expect((string) $id)->toBe($id->value);
});

it('turli id\'lar teng emas', function (): void {
    expect(PublicId::generate()->equals(PublicId::generate()))->toBeFalse();
});
