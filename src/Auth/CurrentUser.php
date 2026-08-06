<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Illuminate\Http\Request;
use Raso\ModuleKit\Domain\RasoUser;

/**
 * So'rovdagi foydalanuvchini TOZA tur bilan beradi.
 *
 * ⚠️ NEGA KERAK — `$request->user()` ning turi ishonchsiz.
 *
 * Larastan uni `config/auth.php` dagi provider modeliga qarab aniqlaydi.
 * Modul-servisda esa `App\Models\User` YO'Q (foydalanuvchi core'da),
 * lekin Laravel framework'ning standart konfigi baribir shu klassni
 * ko'rsatib turadi. Natijada har modulda `instanceof` bilan toraytirish
 * ishlamay, `class.notFound` xatosi chiqardi.
 *
 * Shu bitta joyda hal qilinadi — har modulda emas.
 */
final class CurrentUser
{
    /** Autentifikatsiyadan o'tgan foydalanuvchi yoki `null`. */
    public static function of(Request $request): ?RasoUser
    {
        $identity = $request->user();

        return $identity instanceof RasoUserIdentity ? $identity->rasoUser : null;
    }

    /**
     * Foydalanuvchining `sub` i (uuid) yoki `null`.
     *
     * Modul jadvallarida `user_ref` sifatida aynan shu saqlanadi.
     */
    public static function refOf(Request $request): ?string
    {
        return self::of($request)?->sub->value;
    }
}
