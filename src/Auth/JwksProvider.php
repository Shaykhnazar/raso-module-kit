<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Firebase\JWT\Key;
use Raso\ModuleKit\Auth\Exception\JwksUnavailable;

/**
 * IdP public kalitlarini beradi (`kid` → `Key`).
 *
 * Keshlangan bo'lishi kutiladi: har so'rovda IdP'ga borish — core yiqilsa
 * hamma modul yiqilishi demak (`Modul platformasi/01` §5).
 */
interface JwksProvider
{
    /**
     * @return array<string, Key>
     *
     * @throws JwksUnavailable
     */
    public function keys(): array;

    /**
     * Keshni chetlab o'tib majburiy yangilaydi — `kid` topilmaganda (kalit
     * rotatsiyasi) chaqiriladi. Rate-limit implementatsiya zimmasida.
     *
     * @return array<string, Key>
     *
     * @throws JwksUnavailable
     */
    public function refresh(): array;
}
