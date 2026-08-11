<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * DLQ holati — monitoring va `/health` uchun.
 *
 * ⚠️ DLQ jadvali BO'SH TURISHI kerak. Ichida xabar qolishi «hodisa
 * yetkazilmadi» degani, va uni hech kim sezmasligi mumkin: tizim
 * ishlayveradi, faqat bir narsa bajarilmay qoladi.
 *
 * ⚠️ Daraja ikkiga bo'linadi va bu ATAYLAB. `identity.user_deleted`
 * qolishi oddiy nosozlik EMAS: foydalanuvchi «meni unut» tugmasini
 * bosgan, tizim «bajarildi» degan, lekin ma'lumot o'chirilmagan — bu
 * O'zR «Shaxsiy ma'lumotlar to'g'risida»gi qonuni buzilishi.
 *
 * Bir xil darajada belgilasak, monitoring kechikkan eslatmani ham,
 * bajarilmagan qonuniy talabni ham bir xil ko'rsatardi va haqiqiy
 * muammo shovqin ichida yo'qolardi.
 */
final readonly class DeadLetterAlarm
{
    /** @param array<string, int> $byName hodisa nomi → qolgan xabarlar soni */
    private function __construct(
        public int $total,
        public array $byName,
    ) {}

    public static function from(DeadLetters $letters): self
    {
        $byName = $letters->byName();

        return new self(array_sum($byName), $byName);
    }

    public function isHealthy(): bool
    {
        return $this->total === 0;
    }

    /**
     * «Meni unut» bajarilmay qolganmi.
     *
     * Bu savol alohida metod sifatida ochiq turadi: hisobot ham,
     * `/health` ham, kelajakdagi admin paneli ham bir xil javob olsin.
     */
    public function hasUnfulfilledErasure(): bool
    {
        return ($this->byName[EventNames::UserDeleted] ?? 0) > 0;
    }

    /** `ok` · `warning` · `critical` — monitoring shu satrga qaraydi. */
    public function severity(): string
    {
        if ($this->hasUnfulfilledErasure()) {
            return 'critical';
        }

        return $this->isHealthy() ? 'ok' : 'warning';
    }
}
