<?php

declare(strict_types=1);

use Raso\ModuleKit\Events\DeadLetterAlarm;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Testing\InMemoryDeadLetters;

/*
 * MP-31 — DLQ hisoboti.
 *
 * ⚠️ DLQ jadvali BO'SH TURISHI kerak. Ichida xabar qolishi — «hodisa
 * yetkazilmadi» degani, va uni HECH KIM sezmasligi mumkin: tizim
 * ishlayveradi, faqat bir narsa bajarilmay qoladi.
 *
 * ⚠️ Ichida `identity.user_deleted` bo'lsa bu oddiy nosozlik EMAS:
 * foydalanuvchi «meni unut» tugmasini bosgan, tizim «bajarildi» degan,
 * lekin ma'lumot o'chirilmagan. Bu — O'zR «Shaxsiy ma'lumotlar
 * to'g'risida»gi qonuni buzilishi. Shuning uchun u alohida, eng yuqori
 * darajada belgilanadi.
 */

$message = fn (string $name): OutboxMessage => new OutboxMessage(
    id: 'id-'.$name,
    name: $name,
    payload: [],
    occurredAt: new DateTimeImmutable('2026-08-09 10:00:00'),
);

it('bo\'sh DLQ — sog\'lom', function (): void {
    $alarm = DeadLetterAlarm::from(new InMemoryDeadLetters);

    expect($alarm->isHealthy())->toBeTrue()
        ->and($alarm->severity())->toBe('ok')
        ->and($alarm->total)->toBe(0);
});

it('oddiy xabar qolsa — ogohlantirish', function () use ($message): void {
    $letters = new InMemoryDeadLetters;
    $letters->record($message('calendar.reminder_due'), 'timeout');

    $alarm = DeadLetterAlarm::from($letters);

    expect($alarm->isHealthy())->toBeFalse()
        ->and($alarm->severity())->toBe('warning')
        ->and($alarm->total)->toBe(1);
});

it('`identity.user_deleted` qolsa — KRITIK, ogohlantirish emas', function () use ($message): void {
    /*
     * ⚠️ Bu testning butun mohiyati. Ikkalasi ham «DLQ bo'sh emas»,
     * lekin oqibati BUTUNLAY boshqacha: biri kechikkan eslatma, ikkinchisi
     * bajarilmagan qonuniy talab.
     *
     * Bir xil darajada belgilasak, monitoring ikkalasini bir xil
     * ko'rsatardi va haqiqiy muammo shovqin ichida yo'qolardi.
     */
    $letters = new InMemoryDeadLetters;
    $letters->record($message(EventNames::UserDeleted), 'connection refused');

    $alarm = DeadLetterAlarm::from($letters);

    expect($alarm->severity())->toBe('critical')
        ->and($alarm->hasUnfulfilledErasure())->toBeTrue();
});

it('aralash holatda ham KRITIK ustun keladi', function () use ($message): void {
    // O'nta oddiy xato orasidagi bitta `user_deleted` ko'rinmay
    // qolmasligi kerak.
    $letters = new InMemoryDeadLetters;

    foreach (range(1, 10) as $i) {
        $letters->record($message('calendar.reminder_due'), 'timeout');
    }

    $letters->record($message(EventNames::UserDeleted), 'timeout');

    $alarm = DeadLetterAlarm::from($letters);

    expect($alarm->severity())->toBe('critical')
        ->and($alarm->total)->toBe(11);
});

it('hodisa nomlari bo\'yicha ajratadi', function () use ($message): void {
    // «Nechta» yetarli emas: qaysi hodisa yiqilayotganini bilmasdan
    // sababni topib bo'lmaydi.
    $letters = new InMemoryDeadLetters;
    $letters->record($message('a.one'), 'x');
    $letters->record($message('a.one'), 'x');
    $letters->record($message('b.two'), 'x');

    expect(DeadLetterAlarm::from($letters)->byName)->toBe(['a.one' => 2, 'b.two' => 1]);
});
