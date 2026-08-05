<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

use InvalidArgumentException;

/**
 * Modul ichidagi foydalanuvchi — ID token claim'laridan quriladi.
 *
 * ⚠️ BU KLASSGA PII QO'SHILMAYDI. Email, telefon, `telegram_chat_id`, hujjat
 * ma'lumoti — hech qachon. Sabab: `Modul platformasi/00 - Umumiy arxitektura`
 * §4.4 — modul foydalanuvchining kontaktini ko'rmaydi, bildirishnomani core
 * (Engagement) yuboradi. `RasoUserTest` buni refleksiya bilan majburlaydi:
 * `email` yoki `phone` nomli maydon qo'shilsa test yiqiladi.
 *
 * Modul DB'sida faqat `sub` (`user_ref uuid`) saqlanadi, bu obyekt emas.
 */
final readonly class RasoUser
{
    /** Tanilgan rollar. Boshqasi `user` ga tushiriladi — huquq kengaymasin. */
    private const array ROLES = ['user', 'mentor', 'moderator', 'admin'];

    public function __construct(
        public PublicId $sub,
        public string $name,
        public ?string $avatarUrl,
        public Locale $locale,
        public string $role,
        public VerificationLevel $verificationLevel,
        public Scope $scopes,
    ) {}

    /**
     * ID token / access token claim'laridan quradi.
     *
     * Tanimagan claim'lar JIM TASHLANADI — kelajakda core yangi claim qo'shsa,
     * modul uni tasodifan saqlab qo'ymasin.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $sub = $claims['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new InvalidArgumentException("Token'da `sub` yo'q — foydalanuvchi aniqlanmadi.");
        }

        $role = is_string($claims['role'] ?? null) ? $claims['role'] : '';

        return new self(
            sub: PublicId::fromString($sub),
            name: is_string($claims['name'] ?? null) ? $claims['name'] : '',
            avatarUrl: is_string($claims['picture'] ?? null) && $claims['picture'] !== ''
                ? $claims['picture']
                : null,
            locale: Locale::fromTag(is_string($claims['locale'] ?? null) ? $claims['locale'] : ''),
            role: in_array($role, self::ROLES, strict: true) ? $role : 'user',
            verificationLevel: VerificationLevel::fromClaim(
                is_string($claims['verification_level'] ?? null) ? $claims['verification_level'] : null,
            ),
            scopes: Scope::fromString(is_string($claims['scope'] ?? null) ? $claims['scope'] : ''),
        );
    }

    public function can(string $scope): bool
    {
        return $this->scopes->has($scope);
    }

    public function isVerifiedAtLeast(VerificationLevel $required): bool
    {
        return $this->verificationLevel->atLeast($required);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Log va debug uchun. PII yo'qligi `RasoUserTest` da tasdiqlangan.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sub' => $this->sub->value,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
            'locale' => $this->locale->value,
            'role' => $this->role,
            'verification_level' => $this->verificationLevel->value,
            'scope' => (string) $this->scopes,
        ];
    }
}
