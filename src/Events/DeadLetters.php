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

    /**
     * Hodisa nomi bo'yicha taqsimot (MP-31).
     *
     * ⚠️ «Nechta» YETARLI EMAS: qaysi hodisa yiqilayotganini bilmasdan
     * sababni topib bo'lmaydi. Va eng muhimi — `identity.user_deleted`
     * o'nta oddiy xato orasida ko'rinmay qolmasligi kerak.
     *
     * @return array<string, int> hodisa nomi → qolgan xabarlar soni
     */
    public function byName(): array;
}
