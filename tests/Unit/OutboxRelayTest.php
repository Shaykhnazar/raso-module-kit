<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Testing\EventKit;

/** Testlar ichida ishlatiladigan yordamchi — klosura, `function` emas. */
$message = static fn (string $name = EventNames::UserDeleted): OutboxMessage => new OutboxMessage(
    id: PublicId::generate()->value,
    name: $name,
    payload: ['sub' => PublicId::generate()->value],
    occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
);

it('kutayotgan xabarlarni chiqaradi va belgilaydi', function () use ($message): void {
    $kit = EventKit::make();
    $kit->outbox->push($message());
    $kit->outbox->push($message());

    $report = $kit->relay()->flush();

    expect($report->published)->toBe(2)
        ->and($report->failed)->toBe(0)
        ->and($kit->publisher->published)->toHaveCount(2);
});

it('yuborilgan xabar QAYTA yuborilmaydi', function () use ($message): void {
    $kit = EventKit::make();
    $kit->outbox->push($message());

    $kit->relay()->flush();
    $second = $kit->relay()->flush();

    expect($second->total())->toBe(0)
        ->and($kit->publisher->published)->toHaveCount(1);
});

it('nosozlikda xabar YO\'QOLMAYDI — qayta urinish uchun qoladi', function () use ($message): void {
    $kit = EventKit::make();
    $kit->outbox->push($message());
    $kit->publisher->broken = true;

    $report = $kit->relay()->flush();

    expect($report->failed)->toBe(1)
        ->and($report->published)->toBe(0)
        ->and($kit->outbox->pending(10))->toHaveCount(1);
});

it('nosozlik tuzalgach xabar yetkaziladi', function () use ($message): void {
    $kit = EventKit::make();
    $kit->outbox->push($message());
    $kit->publisher->broken = true;

    $kit->relay()->flush();
    $kit->publisher->broken = false;
    $report = $kit->relay()->flush();

    expect($report->published)->toBe(1)
        ->and($kit->publisher->published)->toHaveCount(1);
});

/**
 * ⚠️ Buzuq xabar navbatni ABADIY band qilmasligi kerak: `max_attempts` dan
 * keyin u chetga surib qo'yiladi va qo'lda ko'riladi.
 */
it('urinishlar tugagach xabar chetga suriladi', function () use ($message): void {
    $kit = EventKit::make();
    $kit->outbox->push($message());
    $kit->publisher->broken = true;

    $relay = $kit->relay(maxAttempts: 3);
    $relay->flush();  // 1
    $relay->flush();  // 2
    $report = $relay->flush();  // 3 — tugadi

    expect($report->exhausted)->toBe(1)
        ->and($report->needsAttention())->toBeTrue()
        ->and($kit->outbox->pending(10))->toBe([]);
});

it('batch hajmidan ortiq olmaydi', function () use ($message): void {
    $kit = EventKit::make();

    for ($i = 0; $i < 5; $i++) {
        $kit->outbox->push($message());
    }

    expect($kit->relay(batchSize: 2)->flush()->published)->toBe(2);
});

it('bo\'sh outbox\'da hech narsa qilmaydi', function (): void {
    $report = EventKit::make()->relay()->flush();

    expect($report->total())->toBe(0)
        ->and($report->needsAttention())->toBeFalse();
});
