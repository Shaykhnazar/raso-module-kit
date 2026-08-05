<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;
use Raso\ModuleKit\Auth\Exception\JwksUnavailable;
use Raso\ModuleKit\Auth\JwksFetcher;
use Raso\ModuleKit\Domain\PublicId;
use RuntimeException;

/**
 * Testlar uchun soxta IdP: RSA kalit juftini yasaydi, token imzolaydi va
 * o'z JWKS'ini beradi. `JwksFetcher` portini bajaradi — ya'ni testlar
 * TARMOQQA CHIQMAYDI.
 *
 * Har modul o'z auth testlarida shu bittasini ishlatadi.
 */
final class FakeJwtIssuer implements JwksFetcher
{
    private OpenSSLAsymmetricKey $privateKey;

    private string $kid;

    /** Nechta marta JWKS so'ralgani — kesh va rate-limit testlari shuni sanaydi. */
    public int $fetchCount = 0;

    /** `true` bo'lsa `fetch()` xato tashlaydi — fail-closed testlari uchun. */
    public bool $unavailable = false;

    public function __construct(
        public readonly string $issuer = 'https://api.raso.uz',
        public readonly string $audience = 'test-module',
    ) {
        $this->rotate('test-key-1');
    }

    /** Yangi kalit juftini yasaydi (kalit rotatsiyasi testi uchun). */
    public function rotate(string $kid = 'test-key-2'): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new RuntimeException('RSA kalit yasalmadi (openssl).');
        }

        $this->privateKey = $key;
        $this->kid = $kid;
    }

    public function currentKid(): string
    {
        return $this->kid;
    }

    /**
     * Token imzolaydi. Sukut bo'yicha yaroqli; `$claims` bilan har qanday
     * qismini buzish mumkin (`exp`, `iss`, `aud`, `sub`, `scope`, …).
     *
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims = []): string
    {
        return JWT::encode($this->payload($claims), $this->exportPrivateKey(), 'RS256', $this->kid);
    }

    /**
     * `kid` BIZNIKI, lekin imzo BEGONA kalit bilan qo'yilgan.
     *
     * Ya'ni kalit topiladi va tekshiruv imzo bosqichigacha yetadi — bu
     * o'g'irlangan `kid` bilan qalbakilashtirishning aniq stsenariysi.
     *
     * @param  array<string, mixed>  $claims
     */
    public function issueSignedByStranger(array $claims = []): string
    {
        $stranger = new self($this->issuer, $this->audience);

        return JWT::encode($this->payload($claims), $stranger->exportPrivateKey(), 'RS256', $this->kid);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    private function payload(array $claims): array
    {
        $now = time();

        return [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => PublicId::generate()->value,
            'iat' => $now,
            'exp' => $now + 900,
            'name' => 'Test Foydalanuvchi',
            'locale' => 'uz',
            'role' => 'user',
            'verification_level' => 'L2',
            'scope' => 'openid profile',
            ...$claims,
        ];
    }

    /** @return array{keys: list<array<string, mixed>>} */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details($this->privateKey);

        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException("RSA public kalit tafsilotlari o'qilmadi.");
        }

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->kid,
            'n' => self::base64Url((string) $details['rsa']['n']),
            'e' => self::base64Url((string) $details['rsa']['e']),
        ]]];
    }

    /** @return array{keys: list<array<string, mixed>>} */
    public function fetch(string $jwksUri): array
    {
        $this->fetchCount++;

        if ($this->unavailable) {
            throw JwksUnavailable::transport($jwksUri);
        }

        return $this->jwks();
    }

    private function exportPrivateKey(): string
    {
        openssl_pkey_export($this->privateKey, $pem);

        return (string) $pem;
    }

    private static function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
