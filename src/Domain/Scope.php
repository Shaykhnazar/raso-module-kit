<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

use Stringable;

/**
 * OAuth 2.0 scope to'plami (RFC 6749 §3.3).
 *
 * ⚠️ Ataylab SODDA: mos kelish faqat AYNAN teng bo'lganda. Wildcard, prefiks
 * yoki ierarxiya YO'Q — `chat:read` `chat` ni ham, `chat:*` ni ham, hatto
 * `chat:read_all` ni ham ochmaydi. Implicit kengayish — ruxsatni jimgina
 * kengaytiradigan klassik xavfsizlik teshigi.
 */
final readonly class Scope implements Stringable
{
    /** @param list<string> $values */
    private function __construct(private array $values) {}

    public static function fromString(string $raw): self
    {
        /** @var list<string> $parts */
        $parts = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return new self(array_values(array_unique($parts)));
    }

    /** @param list<string> $values */
    public static function fromList(array $values): self
    {
        return new self(array_values(array_unique($values)));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function has(string $scope): bool
    {
        return in_array($scope, $this->values, strict: true);
    }

    /** @param list<string> $required */
    public function hasAll(array $required): bool
    {
        foreach ($required as $scope) {
            if (! $this->has($scope)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function __toString(): string
    {
        return implode(' ', $this->values);
    }
}
