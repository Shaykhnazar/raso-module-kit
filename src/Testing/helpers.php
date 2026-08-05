<?php

declare(strict_types=1);

use Raso\ModuleKit\Domain\RasoUser;
use Raso\ModuleKit\Testing\RasoUserFactory;

/*
 * Global test yordamchilari.
 *
 * NEGA composer `autoload.files` orqali, har repodagi `tests/Helpers/` da EMAS:
 * `pest --parallel` ikki test faylini bitta jarayonga yuklaganda bir xil nomli
 * funksiya `Cannot redeclare` bilan yiqiladi — va bu xato ketma-ket ishlatilganda
 * KO'RINMAYDI. Bitta manba + `function_exists` qo'riqchisi bu tuzoqni butunlay
 * yopadi.
 *
 * ⚠️ `actingAsRasoUser()` bu yerda YO'Q — u Laravel guard'iga tayanadi va
 * MP-02 (JwtGuard) bilan birga keladi. Mavjud bo'lmagan narsani oldindan
 * yozib qo'yish — ishlaydi deb o'ylanadigan o'lik kod.
 */

if (! function_exists('fakeRasoUser')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function fakeRasoUser(array $overrides = []): RasoUser
    {
        return RasoUserFactory::make($overrides);
    }
}
