<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;
use Raso\ModuleKit\Auth\Exception\JwksUnavailable;
use Raso\ModuleKit\Auth\Exception\TokenRejected;
use Raso\ModuleKit\Domain\RasoUser;
use UnexpectedValueException;

/**
 * Access token'ni OFFLINE tekshiradi — `Modul platformasi/01` §5.
 *
 * Har so'rovda IdP'ga borilmaydi: core yiqilsa hamma modul yiqilishi kerak
 * emas. Kalitlar `JwksProvider` orqali keshdan olinadi.
 *
 * Tekshiruv tartibi: alg → kid → imzo → exp/nbf → iss → aud → claim'lar.
 * Har bosqichda XATO = `TokenRejected` (401). Boshqa turdagi istisno
 * chiqmasligi kerak — aks holda 500 qaytadi va bu monitoringda auth
 * muammosi sifatida ko'rinmaydi.
 */
final readonly class TokenVerifier
{
    /**
     * @param  string  $issuer  kutilayotgan `iss` — `https://api.raso.uz`
     * @param  string  $audience  shu modulning `code` i — `chat`
     * @param  int  $leeway  soat farqi uchun bag'rikenglik (sekund)
     */
    public function __construct(
        private JwksProvider $jwks,
        private string $issuer,
        private string $audience,
        private int $leeway = 30,
    ) {}

    /**
     * @throws TokenRejected
     */
    public function verify(string $token): RasoUser
    {
        $kid = $this->readKid($token);
        $keys = $this->keysFor($kid);

        JWT::$leeway = $this->leeway;

        try {
            // `$keys` — faqat RS256 `Key` obyektlari. Kutubxona alg'ni kalitning
            // alg'iga qarab tekshiradi, ya'ni `none`/HS256 almashtirish hujumi
            // shu yerda to'xtaydi.
            $claims = (array) JWT::decode($token, $keys);
        } catch (ExpiredException $e) {
            throw TokenRejected::expired($e);
        } catch (SignatureInvalidException $e) {
            throw TokenRejected::invalidSignature($e);
        } catch (BeforeValidException|UnexpectedValueException|InvalidArgumentException|DomainException $e) {
            throw TokenRejected::malformed($e);
        }

        $this->assertIssuer($claims);
        $this->assertAudience($claims);

        try {
            return RasoUser::fromClaims($claims);
        } catch (InvalidArgumentException $e) {
            throw TokenRejected::invalidClaims($e);
        }
    }

    /**
     * `kid` uchun kalitlarni beradi. Topilmasa — kalit rotatsiyasi bo'lgan
     * deb hisoblab JWKS ni bir marta majburiy yangilaydi.
     *
     * @return array<string, Key>
     */
    private function keysFor(string $kid): array
    {
        try {
            $keys = $this->jwks->keys();

            if (! isset($keys[$kid])) {
                $keys = $this->jwks->refresh();
            }
        } catch (JwksUnavailable $e) {
            // ⚠️ FAIL-CLOSED: kalitni tekshira olmayapmiz → tokenga ISHONMAYMIZ.
            throw TokenRejected::keysUnavailable($e);
        }

        if (! isset($keys[$kid])) {
            throw TokenRejected::unknownKey($kid);
        }

        return $keys;
    }

    /** Sarlavhadan `kid` ni o'qiydi va algoritmni tekshiradi (imzodan OLDIN). */
    private function readKid(string $token): string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw TokenRejected::malformed();
        }

        // `strict: false` — hech qachon `false` qaytarmaydi; buzuq base64
        // axlat satrga aylanadi va keyingi `json_decode` uni tutadi.
        $header = json_decode(
            base64_decode(strtr($parts[0], '-_', '+/'), strict: false),
            associative: true,
        );

        if (! is_array($header)) {
            throw TokenRejected::malformed();
        }

        $alg = $header['alg'] ?? null;

        // Faqat RS256. `none` va HS256 aniq rad etiladi: HS256 da JWKS'dagi
        // PUBLIC kalit maxfiy kalit sifatida ishlatilib, har kim token
        // qalbakilashtira olardi (algorithm confusion).
        if ($alg !== 'RS256') {
            throw TokenRejected::unsupportedAlgorithm(is_string($alg) ? $alg : 'yoq');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw TokenRejected::malformed();
        }

        return $kid;
    }

    /** @param array<string, mixed> $claims */
    private function assertIssuer(array $claims): void
    {
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw TokenRejected::wrongIssuer();
        }
    }

    /**
     * `aud` satr ham, massiv ham bo'lishi mumkin (RFC 7519 §4.1.3).
     *
     * @param  array<string, mixed>  $claims
     */
    private function assertAudience(array $claims): void
    {
        $aud = $claims['aud'] ?? null;

        $accepted = match (true) {
            is_string($aud) => $aud === $this->audience,
            is_array($aud) => in_array($this->audience, $aud, strict: true),
            default => false,
        };

        if (! $accepted) {
            throw TokenRejected::wrongAudience();
        }
    }
}
