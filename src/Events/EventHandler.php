<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

/** Hodisaga javob beruvchi. Modul o'zinikini yozadi va konteynerga bog'laydi. */
interface EventHandler
{
    public function handles(string $eventName): bool;

    /**
     * ⚠️ Tranzaksiya ICHIDA chaqiriladi. Uzoq davom etadigan ish (HTTP so'rov,
     * LLM chaqiruvi) bu yerda QILINMASIN — navbatga qo'yilsin.
     */
    public function handle(OutboxMessage $message): void;
}
