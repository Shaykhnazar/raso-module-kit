<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use DateTimeImmutable;
use DateTimeZone;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Events\UserDeletedListener;
use RuntimeException;

/**
 * ⚠️ HAR MODUL UCHUN MAJBURIY KONTRAKT TESTI.
 *
 * Modul o'z test to'plamida shuni chaqiradi:
 *
 * ```php
 * it('foydalanuvchi o\'chirilganda modul ma\'lumoti qolmaydi', function () {
 *     UserDeletionContract::assertPurges(
 *         listener: app(ChatUserDeletedListener::class),
 *         seed: fn (PublicId $u) => seedThreadsAndMessagesFor($u),
 *         remaining: fn (PublicId $u) => DB::table('chat_threads')->where('user_ref', $u->value)->count()
 *                                      + DB::table('chat_messages')->where(...)->count(),
 *     );
 * });
 * ```
 *
 * NEGA abstrakt Pest testi emas, static metod: Pest'da meros olinadigan test
 * fayli parallel rejimda kutilmagan holatlarga olib keladi, va modul o'z
 * jadvallarini faqat o'zi biladi. Bu shakl esa oddiy va aniq: modul nima
 * ekishini va nimani sanashni aytadi, qolganini kontrakt bajaradi.
 */
final class UserDeletionContract
{
    /**
     * @param  callable(PublicId): void  $seed  shu foydalanuvchi uchun ma'lumot yaratadi
     * @param  callable(PublicId): int  $remaining  shu foydalanuvchiga tegishli qolgan qatorlar soni
     */
    public static function assertPurges(
        UserDeletedListener $listener,
        callable $seed,
        callable $remaining,
    ): void {
        $userRef = PublicId::generate();
        $stranger = PublicId::generate();

        $seed($userRef);
        $seed($stranger);

        // ⚠️ BEGONANING SANOG'I O'CHIRISHDAN OLDIN OLINADI. Pastda u
        // «noldan katta» emas, AYNAN SHU SON bilan solishtiriladi —
        // sababi `remaining` YIG'INDI qaytaradi: modul bir nechta
        // jadvalini qo'shib beradi. «Noldan katta» tekshiruvi juda keng
        // o'chirishni NIQOBLAB qo'yardi — begonaning bitta omon qolgan
        // jadvali qolgan hammasi yo'q qilinganini yashirardi va kontrakt
        // yashil bo'lib turaverardi. Bu haqiqiy modulda topildi
        // (preline-crm, ticket 15): `WHERE user_ref` tushib qolganda
        // kitning o'z tekshiruvi ISHLAMADI.
        $strangerBefore = self::countFor($remaining, $stranger);

        if (self::countFor($remaining, $userRef) === 0) {
            throw new RuntimeException(
                'Kontrakt testi ishonchsiz: `seed` hech narsa yaratmadi yoki `remaining` 0 qaytardi. '.
                "O'chirish sinalmagan bo'ladi.",
            );
        }

        $listener->handle(self::message($userRef));

        $left = self::countFor($remaining, $userRef);

        if ($left !== 0) {
            throw new RuntimeException(
                "«Meni unut» BUZILGAN: o'chirishdan keyin {$left} qator qoldi. ".
                'Modul `identity.user_deleted` ni to\'liq bajarmayapti.',
            );
        }

        // Boshqa odamning ma'lumoti tegilmaganini ham tekshiramiz —
        // «hammasini o'chir» ham xato, va u faqat shu yerda ushlanadi.
        $strangerAfter = self::countFor($remaining, $stranger);

        if ($strangerAfter !== $strangerBefore) {
            $lost = $strangerBefore - $strangerAfter;

            throw new RuntimeException(
                "O'chirish JUDA KENG: begona foydalanuvchining {$lost} qatori ham o'chirildi ".
                "({$strangerBefore} edi, {$strangerAfter} qoldi).",
            );
        }
    }

    /**
     * `@phpstan-impure` — natija o'zgaruvchan holatga (DB/massiv) bog'liq:
     * o'chirishdan OLDIN va KEYIN chaqirilganda boshqa qiymat qaytaradi.
     * Bu shu kontraktning butun mohiyati.
     *
     * @param  callable(PublicId): int  $remaining
     *
     * @phpstan-impure
     */
    private static function countFor(callable $remaining, PublicId $userRef): int
    {
        return $remaining($userRef);
    }

    private static function message(PublicId $userRef): OutboxMessage
    {
        return new OutboxMessage(
            id: PublicId::generate()->value,
            name: EventNames::UserDeleted,
            payload: ['sub' => $userRef->value],
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }
}
