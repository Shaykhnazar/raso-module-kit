<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platformaning `Bearer` sxemasi: token so'rovdan QANDAY o'qiladi va
 * autentifikatsiyasiz so'rovga QANDAY javob beriladi.
 *
 * NEGA alohida klass, `AuthenticateMiddleware` ning private metodlari emas:
 * modul ikkinchi auth drayverini yozganida (preline-crm mustaqil rejimi —
 * bazadagi opaque token, JWT emas) shu ikki metod NUSXA KO'CHIRILDI, harfma-harf.
 * Ya'ni «401 qanday ko'rinadi» degan javob uch joyda turdi: ikkala middleware
 * va modulning o'z drayveri. Bittasi o'zgarsa — masalan `WWW-Authenticate`
 * ga `realm` qo'shilsa yoki xabar matni almashsa — modullar klient uchun
 * bir-biridan farq qila boshlaydi va buni hech qaysi test ushlamaydi.
 *
 * ⚠️ Bu yerda TEKSHIRUV yo'q va bo'lmaydi ham. Tokenni qanday tekshirish —
 * DRAYVERNING ishi: `raso` rejimida offline JWT (`TokenVerifier`), mustaqil
 * rejimda bazadagi opaque token. Shu sabab bu klass `TokenVerifier` ni
 * BILMAYDI: bilsa, JWT'siz drayver undan foydalana olmasdi va nusxa
 * ko'chirish qaytadan boshlanardi.
 */
final class BearerScheme
{
    /**
     * RFC 6750 §2.1 — sxema nomi va uni tokendan ajratuvchi bo'sh joy.
     *
     * ⚠️ Solishtirish REGISTRGA SEZGIR (`bearer ...` rad etiladi). RFC bo'yicha
     * sxema nomi registrga sezgir emas, lekin buni yumshatish HAMMA modulda
     * bir vaqtda qabul qilinadigan sarlavhalar to'plamini kengaytiradi —
     * ya'ni fail-open yo'nalishidagi o'zgarish. Kerak bo'lsa alohida qaror
     * bilan, alohida testlar bilan qilinsin.
     */
    private const string PREFIX = 'Bearer ';

    /**
     * ⚠️ Sabab klientga HECH QACHON aytilmaydi. «Token yo'q», «imzo noto'g'ri»
     * va «muddati o'tdi» ni ajratish hujumchiga tizim haqida ma'lumot beradi
     * (mavjud hisoblarni sanash shu yerdan boshlanadi). Sabab — logda.
     */
    private const string MESSAGE = 'Autentifikatsiya talab qilinadi.';

    /**
     * `Authorization: Bearer <token>` dan tokenni oladi.
     *
     * @return string|null sarlavha yo'q, sxema boshqa yoki token bo'sh bo'lsa `null`
     */
    public static function token(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! str_starts_with($header, self::PREFIX)) {
            return null;
        }

        $token = trim(substr($header, strlen(self::PREFIX)));

        return $token === '' ? null : $token;
    }

    /**
     * Autentifikatsiyasiz so'rovga platformaning YAGONA javobi.
     *
     * `WWW-Authenticate: Bearer` — RFC 7235 talabi: 401 javob klientga qaysi
     * sxema kutilayotganini aytishi shart. Modul BFF'lari aynan shunga qarab
     * qayta kirishga yo'naltiradi.
     */
    public static function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            ['message' => self::MESSAGE],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }
}
