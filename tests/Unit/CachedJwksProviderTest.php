<?php

declare(strict_types=1);

use Raso\ModuleKit\Auth\Exception\JwksUnavailable;
use Raso\ModuleKit\Testing\AuthKit;

it('birinchi so\'rovda oladi, keyin KESHDAN beradi', function (): void {
    $kit = AuthKit::make();

    $kit->jwks->keys();
    $kit->jwks->keys();
    $kit->jwks->keys();

    expect($kit->issuer->fetchCount)->toBe(1);
});

it('`refresh()` keshni chetlab o\'tadi', function (): void {
    $kit = AuthKit::make();

    $kit->jwks->keys();
    $kit->jwks->refresh();

    expect($kit->issuer->fetchCount)->toBe(2);
});

/**
 * ⚠️ AMPLIFIKATSIYA HIMOYASI.
 *
 * Qalbaki `kid` bilan yuborilgan so'rovlar oqimi har birida JWKS yangilashni
 * qo'zg'atsa, modul IdP'ga DDoS qiluvchiga aylanadi. Shuning uchun majburiy
 * yangilash sovish davri bilan cheklangan.
 */
it('sovish davri ichida QAYTA OLMAYDI', function (): void {
    $kit = AuthKit::make(refreshCooldownSeconds: 300);

    $kit->jwks->keys();       // 1 — dastlabki
    $kit->jwks->refresh();    // 2 — birinchi majburiy yangilash, qulf qo'yiladi
    $kit->jwks->refresh();    // qulf ichida — IdP'ga BORMAYDI
    $kit->jwks->refresh();

    expect($kit->issuer->fetchCount)->toBe(2);
});

it('sovish davri 0 bo\'lsa har safar oladi', function (): void {
    $kit = AuthKit::make(refreshCooldownSeconds: 0);

    $kit->jwks->keys();
    $kit->jwks->refresh();
    $kit->jwks->refresh();

    expect($kit->issuer->fetchCount)->toBe(3);
});

it('`kid` bo\'yicha kalit qaytaradi', function (): void {
    $kit = AuthKit::make();

    expect($kit->jwks->keys())->toHaveKey($kit->issuer->currentKid());
});

it('IdP javob bermasa `JwksUnavailable` tashlaydi', function (): void {
    $kit = AuthKit::make();
    $kit->issuer->unavailable = true;

    expect(fn () => $kit->jwks->keys())->toThrow(JwksUnavailable::class);
});

it('rotatsiyadan keyin YANGI kalit keladi', function (): void {
    $kit = AuthKit::make();

    $kit->jwks->keys();
    $kit->issuer->rotate('yangi-kalit');

    expect($kit->jwks->refresh())->toHaveKey('yangi-kalit');
});
