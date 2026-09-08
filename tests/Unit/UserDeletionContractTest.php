<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Events\UserDeletedListener;
use Raso\ModuleKit\Testing\UserDeletionContract;

/**
 * Modulning tipik listener'i — bu yerda «jadval» oddiy massiv.
 * Har modul o'z DB'si bilan xuddi shu shaklda yozadi.
 */
final class KitFakeTableListener extends UserDeletedListener
{
    /** @var array<string, int> user_ref → qatorlar soni */
    public array $rows = [];

    /**
     * IKKINCHI «jadval» — chinakam modulda ular bir nechta bo'ladi va
     * `remaining` ularni QO'SHIB beradi. Aynan shu yig'indi bir paytlar
     * juda keng o'chirishni niqoblab qo'ygan edi.
     *
     * @var array<string, int>
     */
    public array $notes = [];

    public function __construct(
        public bool $purgesEverything = false,
        public bool $purgesNothing = false,
        public bool $purgesEveryonesNotes = false,
    ) {}

    public function seed(PublicId $userRef): void
    {
        $this->rows[$userRef->value] = ($this->rows[$userRef->value] ?? 0) + 3;
        $this->notes[$userRef->value] = ($this->notes[$userRef->value] ?? 0) + 2;
    }

    public function remaining(PublicId $userRef): int
    {
        return ($this->rows[$userRef->value] ?? 0) + ($this->notes[$userRef->value] ?? 0);
    }

    protected function purge(PublicId $userRef): int
    {
        if ($this->purgesNothing) {
            return 0;
        }

        if ($this->purgesEverything) {
            $count = array_sum($this->rows) + array_sum($this->notes);
            $this->rows = [];
            $this->notes = [];

            return $count;
        }

        $count = ($this->rows[$userRef->value] ?? 0) + ($this->notes[$userRef->value] ?? 0);
        unset($this->rows[$userRef->value], $this->notes[$userRef->value]);

        if ($this->purgesEveryonesNotes) {
            // ⚠️ `WHERE user_ref` FAQAT bitta jadvalda tushib qolgan —
            // eng ko'p uchraydigan xato shakli, va eng qiyin ko'rinadigani:
            // o'chirilgan odam uchun hammasi to'g'ri ishlagandek ko'rinadi.
            $count += array_sum($this->notes);
            $this->notes = [];
        }

        return $count;
    }
}

it('to\'g\'ri yozilgan listener kontraktdan o\'tadi', function (): void {
    $listener = new KitFakeTableListener;

    UserDeletionContract::assertPurges(
        listener: $listener,
        seed: fn (PublicId $u) => $listener->seed($u),
        remaining: fn (PublicId $u): int => $listener->remaining($u),
    );
})->throwsNoExceptions();

/**
 * ⚠️ Kontraktning butun mohiyati shu: o'chirmaydigan modul CI'da USHLANADI,
 * prod'da emas. Aks holda «meni unut» tugmasi yolg'onchi bo'lardi.
 */
it('o\'chirmaydigan listener KONTRAKTNI YIQITADI', function (): void {
    $listener = new KitFakeTableListener(purgesNothing: true);

    UserDeletionContract::assertPurges(
        listener: $listener,
        seed: fn (PublicId $u) => $listener->seed($u),
        remaining: fn (PublicId $u): int => $listener->remaining($u),
    );
})->throws(RuntimeException::class, 'Meni unut');

it('JUDA KENG o\'chiradigan listener ham yiqitadi', function (): void {
    // «Hammasini o'chir» — boshqa odamlarning ma'lumotini ham yo'q qiladi.
    $listener = new KitFakeTableListener(purgesEverything: true);

    UserDeletionContract::assertPurges(
        listener: $listener,
        seed: fn (PublicId $u) => $listener->seed($u),
        remaining: fn (PublicId $u): int => $listener->remaining($u),
    );
})->throws(RuntimeException::class, 'JUDA KENG');

it('`seed` hech narsa yaratmasa kontrakt o\'zini ishonchsiz deb e\'lon qiladi', function (): void {
    // Aks holda test «yashil» bo'lib, aslida hech narsa sinalmagan bo'lardi.
    $listener = new KitFakeTableListener;

    UserDeletionContract::assertPurges(
        listener: $listener,
        seed: fn (PublicId $u) => null,
        remaining: fn (PublicId $u): int => 0,
    );
})->throws(RuntimeException::class, 'ishonchsiz');

it('faqat `identity.user_deleted` ni eshitadi', function (): void {
    $listener = new KitFakeTableListener;

    expect($listener->handles(EventNames::UserDeleted))->toBeTrue()
        ->and($listener->handles(EventNames::UserProfileUpdated))->toBeFalse();
});

it('`sub` yaroqsiz bo\'lsa o\'chirishni BAJARMAYDI', function (string $sub): void {
    // Buzuq xabar bilan «hammasini o'chirib yuborish» eng yomon stsenariy.
    (new KitFakeTableListener)->handle(new OutboxMessage(
        id: PublicId::generate()->value,
        name: EventNames::UserDeleted,
        payload: ['sub' => $sub],
        occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ));
})->with(['', '42', 'axlat'])->throws(InvalidArgumentException::class);

it('`sub` umuman yo\'q bo\'lsa ham rad etadi', function (): void {
    (new KitFakeTableListener)->handle(new OutboxMessage(
        id: PublicId::generate()->value,
        name: EventNames::UserDeleted,
        payload: [],
        occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ));
})->throws(InvalidArgumentException::class);

/**
 * ⚠️ SHU TEST BIR PAYTLAR YO'Q EDI va kontrakt aynan shu holatni
 * O'TKAZIB YUBORARDI. `remaining` yig'indi qaytargani uchun begonaning
 * omon qolgan `rows` jadvali uning `notes` jadvali butunlay yo'q
 * qilinganini yashirardi: eski tekshiruv «noldan katta» edi, endi
 * O'CHIRISHDAN OLDINGI SON bilan solishtiriladi.
 *
 * Haqiqiy modulda topilgan (preline-crm, ticket 15): bitta jadvaldan
 * `WHERE user_ref` tushib qolganda kit indamay o'taverardi.
 */
it('begonaning FAQAT BITTA jadvalini yo\'q qilgan listener ham yiqitadi', function (): void {
    $listener = new KitFakeTableListener(purgesEveryonesNotes: true);

    UserDeletionContract::assertPurges(
        listener: $listener,
        seed: fn (PublicId $u) => $listener->seed($u),
        remaining: fn (PublicId $u): int => $listener->remaining($u),
    );
})->throws(RuntimeException::class, 'JUDA KENG');
