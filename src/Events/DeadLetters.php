<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * Qayta ishlanmagan xabarlar (DLQ).
 *
 * ⚠️ Bu jadval BO'SH TURISHI kerak. Ichida `identity.user_deleted` paydo
 * bo'lsa — «meni unut» tugmasi ishlamagan degani va bu qonun buzilishi.
 * Admin panelda ko'rinishi shart (MP-11).
 */
interface DeadLetters
{
    public function record(OutboxMessage $message, string $error): void;

    /** Admin hisoboti uchun — nechta xabar qayta ishlanmagan. */
    public function count(): int;
}
