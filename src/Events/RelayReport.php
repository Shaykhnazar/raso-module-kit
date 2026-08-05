<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/** Bitta relay aylanishining natijasi — monitoring va console chiqishi uchun. */
final readonly class RelayReport
{
    public function __construct(
        public int $published,
        public int $failed,
        public int $exhausted,
    ) {}

    public function total(): int
    {
        return $this->published + $this->failed + $this->exhausted;
    }

    /** `true` bo'lsa — diqqat talab qiladi (ogohlantirish yuborilsin). */
    public function needsAttention(): bool
    {
        return $this->exhausted > 0;
    }
}
