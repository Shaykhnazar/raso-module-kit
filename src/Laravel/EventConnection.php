<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

/**
 * Modullararo stream yashaydigan Redis ULANISHINING shakli.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ⚠️ NEGA ALOHIDA KLASS: QAROR SINALADIGAN BO'LISHI UCHUN.
 * ═══════════════════════════════════════════════════════════════════════
 *
 * Bu mantiq `ModuleKitServiceProvider::registerEventConnection()` ning
 * ichida edi va HECH QANDAY test unga yetib bora olmasdi: uni sinash
 * `register()` ni chaqirishni, u esa `env()` ni talab qiladi — bu
 * paketda esa `vlucas/phpdotenv` yo'q (`PhpOption\Option not found`).
 * Ya'ni qoida kodda bor edi, lekin uni hech kim qadab turmagan —
 * va u aynan shu holatda IKKI MARTA buzildi (prefiks, keyin bo'lim).
 *
 * Endi qaror sof funksiya: kirish — `default` ulanishi va bo'lim
 * raqami, chiqish — ulanish massivi. Provayder faqat konfiguratsiyani
 * ulaydi.
 */
final class EventConnection
{
    /**
     * @param  array<string, mixed>  $default  `database.redis.default`
     * @return array<string, mixed>
     */
    public static function from(array $default, string $database): array
    {
        return [
            ...$default,
            /*
             * ⚠️ PREFIKS BO'SH. Laravel `default` ulanishida kalitlarga
             * `APP_NAME` dan olingan prefiks qo'yadi, ya'ni core
             * `muvaffaqiyat-os-database-raso.events` ga yozib, modul
             * `raso-kalendar-database-raso.events` ni o'qirdi —
             * ikkalasi HECH QACHON uchrashmasdi va hech qanday xato
             * ham chiqmasdi (lokal E2E'da topilgan).
             */
            'options' => ['prefix' => ''],
            /*
             * ⚠️ BO'LIM `default` DAN OLINMAYDI — va bu o'sha
             * nuqsonning IKKINCHI ko'rinishi. Modulga o'z `REDIS_DB`
             * ini berish (kesh va navbat kalitlari boshqa modulniki
             * bilan aralashmasin degan TO'G'RI sabab bilan) stream'ni
             * ham o'sha bo'limga ko'chirardi, core esa uni
             * `REDIS_EVENT_DB` bo'limiga yozadi.
             *
             * Natijasi bir xil: `XREADGROUP` bo'sh javob qaytaradi,
             * xuddi yangi xabar yo'qdek. `identity.user_deleted` uchun
             * bu «core o'chirdi, modul eshitmadi» — «meni unut»
             * kontrakti jimgina bajarilmay qoladi.
             */
            'database' => $database,
            /*
             * ⚠️ `read_timeout` = 0 — cheksiz. Demon rejimida
             * `XREADGROUP` `BLOCK` bilan chaqiriladi va soket timeout'i
             * undan qisqa bo'lsa, ulanish «read error» bilan uzilardi:
             * iste'molchi har bo'sh tsiklda yiqilardi.
             */
            'read_timeout' => 0,
        ];
    }
}
