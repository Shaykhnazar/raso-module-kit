<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/**
 * Iste'mol qilishni tranzaksiyaga o'raydi.
 *
 * ⚠️ NEGA BU KERAK — eng nozik joy. «Ko'rilgan» deb belgilash va hodisani
 * qayta ishlash **bitta tranzaksiyada** bo'lishi shart:
 *
 *  - Avval belgilab, keyin qayta ishlasak va jarayon yiqilsa — xabar
 *    «ko'rilgan» bo'lib qoladi va **hech qachon qayta ishlanmaydi**.
 *    `identity.user_deleted` uchun bu — ma'lumot o'chirilmay qolishi.
 *  - Bitta tranzaksiyada bo'lsa, yiqilish rollback qiladi va xabar
 *    qaytadan yetkaziladi.
 */
interface TransactionRunner
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;
}
