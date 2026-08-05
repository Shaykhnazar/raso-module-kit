<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\AggregateRoot;
use Raso\ModuleKit\Domain\DomainEvent;

/**
 * Test uchun eng kichik aggregate va event. `tests/Helpers/` ga chiqarilmadi:
 * ular FAQAT shu faylda ishlatiladi va klass (funksiya emas) — `pest --parallel`
 * dagi `Cannot redeclare` tuzog'i klasslarga tegishli emas, chunki
 * autoloader ularni bir marta yuklaydi.
 */
final class KitTestEvent implements DomainEvent
{
    public function __construct(private readonly string $name = 'kit.something_happened') {}

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-05 10:00:00', new DateTimeZone('UTC'));
    }

    public function eventName(): string
    {
        return $this->name;
    }

    public function toPayload(): array
    {
        return ['name' => $this->name];
    }
}

final class KitTestAggregate extends AggregateRoot
{
    public function doSomething(string $name = 'kit.something_happened'): void
    {
        $this->recordEvent(new KitTestEvent($name));
    }
}

it('yangi aggregate\'da hodisa yo\'q', function (): void {
    expect((new KitTestAggregate)->pendingEvents())->toBe([]);
});

it('hodisalarni tartib bilan to\'playdi', function (): void {
    $a = new KitTestAggregate;
    $a->doSomething('birinchi');
    $a->doSomething('ikkinchi');

    expect(array_map(fn (DomainEvent $e): string => $e->eventName(), $a->pendingEvents()))
        ->toBe(['birinchi', 'ikkinchi']);
});

it('`pendingEvents()` TOZALAMAYDI — rollback\'da hodisa yo\'qolmasin', function (): void {
    $a = new KitTestAggregate;
    $a->doSomething();

    expect($a->pendingEvents())->toHaveCount(1)
        ->and($a->pendingEvents())->toHaveCount(1);
});

it('`releaseEvents()` chiqaradi va tozalaydi', function (): void {
    $a = new KitTestAggregate;
    $a->doSomething();

    expect($a->releaseEvents())->toHaveCount(1)
        ->and($a->pendingEvents())->toBe([]);
});

it('hodisa vaqti UTC da', function (): void {
    expect((new KitTestEvent)->occurredAt()->getTimezone()->getName())->toBe('UTC');
});
