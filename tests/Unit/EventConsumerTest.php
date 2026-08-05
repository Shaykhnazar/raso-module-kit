<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\ConsumeOutcome;
use Raso\ModuleKit\Events\EventHandler;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Testing\EventKit;

/** Sanaydigan handler. Klass — `pest --parallel` da xavfsiz (autoloader bir marta yuklaydi). */
final class KitCountingHandler implements EventHandler
{
    public int $calls = 0;

    /** @var list<string> */
    public array $seenSubs = [];

    public function __construct(
        private readonly string $listensTo = EventNames::UserDeleted,
        public bool $broken = false,
    ) {}

    public function handles(string $eventName): bool
    {
        return $eventName === $this->listensTo;
    }

    public function handle(OutboxMessage $message): void
    {
        if ($this->broken) {
            throw new RuntimeException('handler yiqildi');
        }

        $this->calls++;
        $sub = $message->payload['sub'] ?? null;

        if (is_string($sub)) {
            $this->seenSubs[] = $sub;
        }
    }
}

$message = static fn (string $name = EventNames::UserDeleted): OutboxMessage => new OutboxMessage(
    id: PublicId::generate()->value,
    name: $name,
    payload: ['sub' => PublicId::generate()->value],
    occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
);

it('hodisani qayta ishlaydi', function () use ($message): void {
    $kit = EventKit::make();
    $handler = new KitCountingHandler;

    expect($kit->consumer([$handler])->consume($message()))->toBe(ConsumeOutcome::Processed)
        ->and($handler->calls)->toBe(1);
});

/**
 * ⚠️ At-least-once yetkazishda bitta xabar bir necha marta keladi — bu
 * NORMAL holat. Ikki marta o'chirish yoki ikki marta ball berish esa xato.
 */
it('BIR XIL xabar ikki marta kelsa bir marta ishlanadi', function () use ($message): void {
    $kit = EventKit::make();
    $handler = new KitCountingHandler;
    $consumer = $kit->consumer([$handler]);
    $event = $message();

    $first = $consumer->consume($event);
    $second = $consumer->consume($event);

    expect($first)->toBe(ConsumeOutcome::Processed)
        ->and($second)->toBe(ConsumeOutcome::Duplicate)
        ->and($handler->calls)->toBe(1);
});

it('turli xabarlar alohida ishlanadi', function () use ($message): void {
    $kit = EventKit::make();
    $handler = new KitCountingHandler;
    $consumer = $kit->consumer([$handler]);

    $consumer->consume($message());
    $consumer->consume($message());

    expect($handler->calls)->toBe(2);
});

it('handler yo\'q bo\'lsa e\'tiborsiz qoldiradi va BELGILAMAYDI', function () use ($message): void {
    // Belgilanmasligi muhim: modulga keyinroq handler qo'shilsa, xabarni
    // qayta o'ynatish imkoni qoladi.
    $kit = EventKit::make();

    $outcome = $kit->consumer([new KitCountingHandler(listensTo: 'boshqa.hodisa')])
        ->consume($message());

    expect($outcome)->toBe(ConsumeOutcome::Ignored)
        ->and($kit->consumed->seen)->toBe([]);
});

it('bir nechta handler ketma-ket chaqiriladi', function () use ($message): void {
    $kit = EventKit::make();
    $a = new KitCountingHandler;
    $b = new KitCountingHandler;

    $kit->consumer([$a, $b])->consume($message());

    expect($a->calls)->toBe(1)->and($b->calls)->toBe(1);
});

it('xato bo\'lsa DLQ\'ga yozadi', function () use ($message): void {
    $kit = EventKit::make();

    $outcome = $kit->consumer([new KitCountingHandler(broken: true)])->consume($message());

    expect($outcome)->toBe(ConsumeOutcome::Failed)
        ->and($kit->deadLetters->count())->toBe(1)
        ->and($kit->deadLetters->records[0]['error'])->toContain('handler yiqildi');
});

/**
 * ⚠️ ENG NOZIK QOIDA. Xato bo'lsa tranzaksiya qaytariladi va
 * `consumed_events` da IZ QOLMAYDI — aks holda xabar «ko'rilgan» bo'lib
 * qolib, hech qachon qayta ishlanmasdi. `user_deleted` uchun bu ma'lumot
 * o'chirilmay qolishi demakdir.
 */
it('xato bo\'lsa tranzaksiya QAYTARILADI — xabar qayta ishlanishi mumkin', function () use ($message): void {
    $kit = EventKit::make();
    $handler = new KitCountingHandler(broken: true);
    $event = $message();

    $kit->consumer([$handler])->consume($event);

    expect($kit->transaction->rollbacks)->toBe(1)
        ->and($kit->consumed->seen)->toBe([]);

    // Nosozlik tuzaldi — xabar qaytadan yetkazildi va endi ishlanadi.
    $handler->broken = false;

    expect($kit->consumer([$handler])->consume($event))->toBe(ConsumeOutcome::Processed)
        ->and($handler->calls)->toBe(1);
});

it('handler xabar payload\'ini oladi', function () use ($message): void {
    $kit = EventKit::make();
    $handler = new KitCountingHandler;
    $event = $message();

    $kit->consumer([$handler])->consume($event);

    expect($handler->seenSubs)->toBe([$event->payload['sub']]);
});
