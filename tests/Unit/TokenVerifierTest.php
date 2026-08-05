<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Raso\ModuleKit\Auth\Exception\TokenRejected;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Domain\VerificationLevel;
use Raso\ModuleKit\Testing\AuthKit;

it('yaroqli token o\'tadi va foydalanuvchi quriladi', function (): void {
    $kit = AuthKit::make();
    $sub = PublicId::generate()->value;

    $user = $kit->verifier->verify($kit->issuer->issue(['sub' => $sub, 'scope' => 'chat:read']));

    expect($user->sub->value)->toBe($sub)
        ->and($user->can('chat:read'))->toBeTrue()
        ->and($user->verificationLevel)->toBe(VerificationLevel::L2);
});

it('muddati o\'tgan tokenni rad etadi', function (): void {
    $kit = AuthKit::make();

    // Leeway 30s — undan aniq uzoqroqqa suramiz.
    $token = $kit->issuer->issue(['iat' => time() - 3600, 'exp' => time() - 600]);

    expect(fn () => $kit->verifier->verify($token))->toThrow(TokenRejected::class);
});

it('leeway ichidagi endigina o\'tgan tokenni QABUL QILADI', function (): void {
    // Soat farqi real muammo: 5 sekundlik farq odamni chiqarib yubormasin.
    $kit = AuthKit::make();

    $user = $kit->verifier->verify($kit->issuer->issue(['exp' => time() - 5]));

    expect($user->role)->toBe('user');
});

it('noto\'g\'ri `aud` ni rad etadi — chat tokeni calendar\'da ishlamaydi', function (): void {
    $kit = AuthKit::make();

    expect(fn () => $kit->verifier->verify($kit->issuer->issue(['aud' => 'calendar'])))
        ->toThrow(TokenRejected::class);
});

it('`aud` massiv bo\'lsa ichidan qidiradi (RFC 7519 §4.1.3)', function (): void {
    $kit = AuthKit::make();

    $user = $kit->verifier->verify($kit->issuer->issue(['aud' => ['boshqa', 'test-module']]));

    expect($user->role)->toBe('user');
});

it('noto\'g\'ri `iss` ni rad etadi', function (): void {
    $kit = AuthKit::make();

    expect(fn () => $kit->verifier->verify($kit->issuer->issue(['iss' => 'https://yolgonchi.uz'])))
        ->toThrow(TokenRejected::class);
});

it('BEGONA kalit bilan imzolangan tokenni rad etadi', function (): void {
    // `kid` bizniki — kalit TOPILADI, lekin imzo mos kelmaydi.
    $kit = AuthKit::make();

    expect(fn () => $kit->verifier->verify($kit->issuer->issueSignedByStranger()))
        ->toThrow(TokenRejected::class);
});

it('buzilgan tokenni rad etadi', function (string $token): void {
    AuthKit::make()->verifier->verify($token);
})->with(['', 'axlat', 'a.b', 'a.b.c.d'])->throws(TokenRejected::class);

it('`kid` yo\'q bo\'lsa rad etadi', function (): void {
    $kit = AuthKit::make();

    // `kid`siz imzolangan token. Sir 32+ bayt — aks holda kutubxona
    // tokenni bizning tekshiruvimizgacha yetkazmasdan rad etadi.
    $token = JWT::encode(['iss' => $kit->issuer->issuer], str_repeat('a', 64), 'HS256');

    expect(fn () => $kit->verifier->verify($token))->toThrow(TokenRejected::class);
});

/**
 * ⚠️ ALGORITHM CONFUSION — eng klassik JWT hujumi.
 *
 * Hujumchi JWKS'dagi OCHIQ kalitni HS256 uchun MAXFIY kalit sifatida ishlatib
 * istalgan token yasay oladi. Shuning uchun `alg` imzodan OLDIN tekshiriladi
 * va faqat RS256 qabul qilinadi.
 */
it('HS256 ni rad etadi — algorithm confusion hujumi', function (): void {
    $kit = AuthKit::make();

    $token = JWT::encode(
        [
            'iss' => $kit->issuer->issuer,
            'aud' => $kit->issuer->audience,
            'sub' => PublicId::generate()->value,
        ],
        str_repeat('ochiq-kalit-maxfiy-sifatida', 4),
        'HS256',
        $kit->issuer->currentKid(),
    );

    $rejected = null;

    try {
        $kit->verifier->verify($token);
    } catch (TokenRejected $e) {
        $rejected = $e;
    }

    expect($rejected)->toBeInstanceOf(TokenRejected::class)
        ->and($rejected?->reason)->toBe('algoritm_qollanmaydi:HS256');
});

it('`alg: none` ni rad etadi', function (): void {
    $kit = AuthKit::make();

    $header = rtrim(strtr(base64_encode((string) json_encode(
        ['alg' => 'none', 'typ' => 'JWT', 'kid' => $kit->issuer->currentKid()],
    )), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode((string) json_encode(['sub' => 'x'])), '+/', '-_'), '=');

    expect(fn () => $kit->verifier->verify("{$header}.{$payload}."))
        ->toThrow(TokenRejected::class);
});

it('`sub` uuidv7 bo\'lmasa rad etadi — bigint id o\'tib ketmasin', function (): void {
    $kit = AuthKit::make();

    expect(fn () => $kit->verifier->verify($kit->issuer->issue(['sub' => '42'])))
        ->toThrow(TokenRejected::class);
});

it('KALIT ROTATSIYASI: noma\'lum `kid` kelsa JWKS bir marta yangilanadi', function (): void {
    $kit = AuthKit::make();

    // 1-token eski kalit bilan — JWKS keshga tushadi.
    $kit->verifier->verify($kit->issuer->issue());
    expect($kit->issuer->fetchCount)->toBe(1);

    // IdP kalitni almashtirdi.
    $kit->issuer->rotate('test-key-2');
    $user = $kit->verifier->verify($kit->issuer->issue());

    expect($user->role)->toBe('user')
        ->and($kit->issuer->fetchCount)->toBe(2);
});

/**
 * ⚠️ FAIL-CLOSED. JWKS olinmasa tekshiruv mumkin emas — demak token
 * QABUL QILINMAYDI. `TokenRejected` (401 yo'li), tizim xatosi (500) emas.
 */
it('JWKS olinmasa tokenni RAD ETADI (401 yo\'lidan, 500 emas)', function (): void {
    $kit = AuthKit::make();
    $token = $kit->issuer->issue();

    $kit->issuer->unavailable = true;

    $rejected = null;

    try {
        $kit->verifier->verify($token);
    } catch (TokenRejected $e) {
        $rejected = $e;
    }

    expect($rejected)->toBeInstanceOf(TokenRejected::class)
        ->and($rejected?->reason)->toBe('kalitlar_olinmadi');
});
