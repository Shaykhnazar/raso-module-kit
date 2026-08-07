<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use Raso\ModuleKit\Domain\PublicId;

/**
 * O'chirish TILXATINI core'ga tasdiqlaydi (MP-11).
 *
 * ⚠️ Busiz «meni unut» zanjiri YARIM OCHIQ qolardi: modul ma'lumotni
 * o'chirsa ham, core buni bilmasdi va admin hisobotida tilxat abadiy
 * «kutilmoqda» bo'lib turardi. Huquqiy talab — o'chirish FAKTI qayd
 * etilishi (O'zR «Shaxsiy ma'lumotlar to'g'risida»gi qonuni).
 *
 * Xato bo'lsa ISTISNO tashlaydi: iste'molchi tranzaksiyasi qaytariladi va
 * xabar qayta yetkaziladi. Jimgina yutib yuborsak, tilxat tasdiqlanmay
 * qolardi va buni hech kim sezmasdi.
 */
interface DeletionConfirmer
{
    /**
     * @param  int  $purgedRows  o'chirilgan qatorlar soni (hisobot uchun)
     *
     * @throws \RuntimeException tasdiqlanmasa
     */
    public function confirm(PublicId $userRef, int $purgedRows): void;
}
