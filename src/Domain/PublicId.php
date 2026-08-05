<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Tashqi identifikator qiymat obyekti. UUIDv7 — vaqt bo'yicha tartiblangan,
 * indeks lokalligini saqlaydi. API/URL/token faqat shuni ko'rsatadi.
 *
 * ⚠️ Ichki `bigint id` HECH QACHON bu yerga tushmaydi va modulga berilmaydi:
 * u sanaladigan (enumerable), ya'ni «nechta foydalanuvchi bor» ma'lumotini oshkor qiladi.
 */
final readonly class PublicId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self((string) new UuidV7);
    }

    public static function fromString(string $value): self
    {
        // `UuidV7::isValid()` versiyani ham tekshiradi — v4 shu yerda rad etiladi.
        if (! UuidV7::isValid($value)) {
            throw new InvalidArgumentException("Noto'g'ri PublicId (UUIDv7 kutilgan): {$value}");
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return UuidV7::isValid($value);
    }

    /** Symfony obyektiga — DB drayveri yoki tashqi kutubxona uchun. */
    public function toUuid(): Uuid
    {
        return Uuid::fromString($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
