<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\Locale;

it('to\'rt tilni qo\'llab-quvvatlaydi', function (): void {
    expect(array_map(fn (Locale $l): string => $l->value, Locale::cases()))
        ->toBe(['uz', 'uz_Cyrl', 'ru', 'en']);
});

it('BCP-47 tegini tilga aylantiradi', function (string $tag, Locale $expected): void {
    expect(Locale::fromTag($tag))->toBe($expected);
})->with([
    ['uz', Locale::Uz],
    ['uz-UZ', Locale::Uz],
    ['ru', Locale::Ru],
    ['ru-RU', Locale::Ru],
    ['en', Locale::En],
    ['en-US', Locale::En],
    ['uz-Cyrl', Locale::UzCyrl],
    ['uz-Cyrl-UZ', Locale::UzCyrl],
]);

it('registrga sezgir emas', function (): void {
    expect(Locale::fromTag('RU-ru'))->toBe(Locale::Ru)
        ->and(Locale::fromTag('UZ-CYRL'))->toBe(Locale::UzCyrl);
});

it('noma\'lum teg uchun o\'zbekchaga qaytadi', function (string $tag): void {
    expect(Locale::fromTag($tag))->toBe(Locale::Uz);
})->with(['', 'de', 'tr-TR', 'axlat']);

it('kirill yozuvi ALOHIDA til emas — ru dan oldin tekshirilmasin', function (): void {
    // `uz-Cyrl` ichida "u" bor, `ru` bilan chalkashmasligi kerak.
    expect(Locale::fromTag('uz-Cyrl'))->toBe(Locale::UzCyrl)
        ->and(Locale::fromTag('uz-Cyrl')->isCyrillicScript())->toBeTrue()
        ->and(Locale::Uz->isCyrillicScript())->toBeFalse();
});

it('lotin o\'zbek bilan kirill o\'zbek bitta til', function (): void {
    expect(Locale::UzCyrl->language())->toBe('uz')
        ->and(Locale::Uz->language())->toBe('uz')
        ->and(Locale::Ru->language())->toBe('ru');
});
