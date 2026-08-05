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
    final public function handles(string $eventName): bool
    {
        return $eventName === EventNames::UserDeleted;
    }

    final public function handle(OutboxMessage $message): void
    {
        $this->purge($this->userRefFrom($message));
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
