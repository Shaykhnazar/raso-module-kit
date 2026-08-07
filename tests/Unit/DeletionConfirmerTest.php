<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as Http;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\DeletionConfirmer;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Events\UserDeletedListener;
use Raso\ModuleKit\Laravel\HttpDeletionConfirmer;

/*
 * MP-11 — «meni unut» tilxatining TASDIQLANISHI.
 *
 * Busiz zanjir yarim ochiq qolardi: modul ma'lumotni o'chirsa ham, core
 * buni bilmasdi va admin hisobotida tilxat abadiy «kutilmoqda» bo'lardi.
 * Lokal E2E aynan shuni topdi.
 */

final class SpyConfirmer implements DeletionConfirmer
{
    /** @var list<array{sub: string, rows: int}> */
    public array $calls = [];

    public function __construct(private readonly ?Throwable $fails = null) {}

    public function confirm(PublicId $userRef, int $purgedRows): void
    {
        $this->calls[] = ['sub' => $userRef->value, 'rows' => $purgedRows];

        if ($this->fails !== null) {
            throw $this->fails;
        }
    }
}

final class ConfirmingListener extends UserDeletedListener
{
    public int $purged = 0;

    public function __construct(?DeletionConfirmer $confirmer, private readonly int $rows = 7)
    {
        parent::__construct($confirmer);
    }

    protected function purge(PublicId $userRef): int
    {
        $this->purged++;

        return $this->rows;
    }
}

/**
 * ⚠️ Klass, `function` EMAS. Pest parallel ishlaydi va ikki test faylida
 * bir xil nomdagi funksiya `Cannot redeclare` bilan yiqitadi (CLAUDE.md).
 */
final class DeletedMessage
{
    public static function of(string $sub): OutboxMessage
    {
        return OutboxMessage::fromArray([
            'id' => '1',
            'name' => EventNames::UserDeleted,
            'payload' => ['sub' => $sub],
            'occurred_at' => '2026-08-07T06:00:00+00:00',
        ]);
    }
}

it('o\'chirgandan keyin tilxatni tasdiqlaydi', function (): void {
    $spy = new SpyConfirmer;
    $sub = (string) PublicId::generate();

    (new ConfirmingListener($spy))->handle(DeletedMessage::of($sub));

    expect($spy->calls)->toBe([['sub' => $sub, 'rows' => 7]]);
});

/**
 * ⚠️ Tartib MUHIM: avval o'chirish, keyin tasdiq. Teskarisi bo'lsa,
 * o'chirish yiqilganda core'da «o'chirildi» deb yozilib qolardi.
 */
it('tasdiq O\'CHIRISHDAN KEYIN yuboriladi', function (): void {
    $listener = new ConfirmingListener(new SpyConfirmer(new RuntimeException('core javob bermadi')));

    try {
        $listener->handle(DeletedMessage::of((string) PublicId::generate()));
    } catch (RuntimeException) {
        // Kutilgan.
    }

    expect($listener->purged)->toBe(1);
});

/**
 * ⚠️ Xato YUTILMAYDI. Iste'molchi tranzaksiyasi qaytarilib xabar qayta
 * yetkazilishi uchun istisno yuqoriga chiqishi shart — aks holda tilxat
 * tasdiqlanmay qolardi va buni hech kim sezmasdi.
 */
it('tasdiq yiqilsa istisno YUQORIGA chiqadi', function (): void {
    $listener = new ConfirmingListener(new SpyConfirmer(new RuntimeException('core javob bermadi')));

    expect(fn () => $listener->handle(DeletedMessage::of((string) PublicId::generate())))
        ->toThrow(RuntimeException::class, 'core javob bermadi');
});

/**
 * ⚠️ `parent::__construct()` ni chaqirmagan avlod klass ham YIQILMASLIGI
 * kerak: property promoted bo'lsa u initsializatsiya qilinmay qolib
 * fatal xato berardi. Bunday modulni `raso:module:doctor` ushlaydi.
 */
it('tasdiqlovchisiz listener yiqilmaydi', function (): void {
    $listener = new class extends UserDeletedListener
    {
        public function __construct() {}

        protected function purge(PublicId $userRef): int
        {
            return 0;
        }
    };

    $listener->handle(DeletedMessage::of((string) PublicId::generate()));
})->throwsNoExceptions();

it('core endpointiga `sub` va qatorlar sonini yuboradi', function (): void {
    $http = new Http;
    $http->fake(['*' => $http->response(['message' => 'Tasdiqlandi.'])]);
    $sub = PublicId::generate();

    (new HttpDeletionConfirmer($http, 'http://core.test', 'klient', 'sir'))->confirm($sub, 4);

    $http->assertSent(function ($request) use ($sub): bool {
        return $request->url() === 'http://core.test/api/v1/modules/deletion-receipts'
            && $request->data() === ['sub' => $sub->value, 'purged_rows' => 4]
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('klient:sir'));
    });
});

/**
 * ⚠️ 404 — XATO EMAS. Core «kutilayotgan tilxat yo'q» deydi, ya'ni u
 * allaqachon tasdiqlangan. Xabar `at-least-once` yetkaziladi, shuning
 * uchun qayta kelishi NORMAL; xato deb hisoblasak, xabar abadiy qayta
 * urinilib DLQ ni to'ldirardi.
 */
it('404 ni TAKROR tasdiq deb qabul qiladi', function (): void {
    $http = new Http;
    $http->fake(['*' => $http->response(['message' => 'topilmadi'], 404)]);

    (new HttpDeletionConfirmer($http, 'http://core.test', 'klient', 'sir'))
        ->confirm(PublicId::generate(), 0);
})->throwsNoExceptions();

it('boshqa xato javobda istisno tashlaydi', function (): void {
    $http = new Http;
    $http->fake(['*' => $http->response(['message' => 'server'], 500)]);

    expect(fn () => (new HttpDeletionConfirmer($http, 'http://core.test', 'klient', 'sir'))
        ->confirm(PublicId::generate(), 0))
        ->toThrow(RuntimeException::class);
});

it('issuer oxiridagi slash ikkilanmaydi', function (): void {
    $http = new Http;
    $http->fake(['*' => $http->response([])]);

    (new HttpDeletionConfirmer($http, 'http://core.test/', 'klient', 'sir'))
        ->confirm(PublicId::generate(), 0);

    $http->assertSent(fn ($request): bool => $request->url() === 'http://core.test/api/v1/modules/deletion-receipts');
});
