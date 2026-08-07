<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use InvalidArgumentException;
use Raso\ModuleKit\Domain\PublicId;

/**
 * «Meni unut» — har modul UCHUN MAJBURIY.
 *
 * ⚠️ Bu abstrakt klass ataylab shunday qurilgan: modul uni **kengaytirmasa
 * va konteynerga bog'lamasa**, `raso:module:doctor` buyrug'i yiqiladi va CI
 * qizil bo'ladi. Sabab — O'zR «Shaxsiy ma'lumotlar to'g'risida»gi qonuni:
 * foydalanuvchi o'chirilganda uning ma'lumoti HAMMA modulda o'chishi shart.
 * Aks holda profil sahifasidagi «meni unut» tugmasi yolg'onchi bo'ladi va
 * buni hech kim sezmaydi.
 *
 * Test uchun: `Raso\ModuleKit\Testing\UserDeletionContract::assertPurges()`.
 */
abstract class UserDeletedListener implements EventHandler
{
    /**
     * ⚠️ ATAYLAB promoted EMAS va `readonly` EMAS.
     *
     * Promoted bo'lsa, `parent::__construct()` ni chaqirmagan avlod
     * klassda property INITSIALIZATSIYA QILINMAY qolib, `handle()` fatal
     * xato berardi — ya'ni bitta unutilgan qator butun «meni unut»
     * zanjirini yiqitardi. Shu shaklda esa u sukut bo'yicha `null`.
     *
     * `null` — tasdiq YUBORILMAYDI. Ishlab turgan modulda bu xato holat,
     * shuning uchun `raso:module:doctor` uni ushlaydi.
     */
    protected ?DeletionConfirmer $confirmer = null;

    public function __construct(?DeletionConfirmer $confirmer = null)
    {
        $this->confirmer = $confirmer;
    }

    final public function handles(string $eventName): bool
    {
        return $eventName === EventNames::UserDeleted;
    }

    final public function handle(OutboxMessage $message): void
    {
        $userRef = $this->userRefFrom($message);

        $purged = $this->purge($userRef);

        /*
         * ⚠️ Tasdiq O'CHIRISHDAN KEYIN va SHU tranzaksiya ichida.
         *
         * Tashqariga chiqarsak, o'chirish commit bo'lib tasdiq yiqilishi
         * mumkin edi va bu holat hech qayerda ko'rinmasdi. Ichida bo'lgani
         * uchun ikkalasi birga muvaffaqiyatli bo'ladi yoki birga
         * qaytariladi va xabar qayta yetkaziladi (o'chirish idempotent).
         *
         * HTTP chaqiruvi tranzaksiyani ushlab turadi — buni bila turib
         * qabul qildik: hisob o'chirish kuniga bir necha marta bo'ladigan
         * amal, issiq yo'l emas, va `HttpDeletionConfirmer` da 3 soniyalik
         * timeout bor.
         */
        $this->confirmer?->confirm($userRef, $purged);
    }

    /**
     * Shu `user_ref` ga tegishli **hamma** ma'lumotni o'chiradi.
     *
     * ⚠️ Arxivlash, «soft delete» yoki anonimlashtirish YETARLI EMAS —
     * qator jismonan o'chirilishi kerak. Yagona istisno: qonun talab
     * qiladigan audit yozuvlari (ular PII saqlamaydi).
     *
     * @return int o'chirilgan qatorlar soni (log va `raso:module:purge-user` uchun)
     */
    abstract protected function purge(PublicId $userRef): int;

    private function userRefFrom(OutboxMessage $message): PublicId
    {
        $sub = $message->payload['sub'] ?? null;

        if (! is_string($sub) || ! PublicId::isValid($sub)) {
            throw new InvalidArgumentException(
                "`{$message->name}` xabarida yaroqli `sub` yo'q — o'chirish bajarilmadi.",
            );
        }

        return PublicId::fromString($sub);
    }
}
