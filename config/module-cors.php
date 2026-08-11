<?php

declare(strict_types=1);

/*
 * Modul-servis uchun CORS — MP-29a.
 *
 * ⚠️ NEGA MODULGA CORS KERAK. Shell (`raso.uz`) dashboard'da modul
 * widget'ini BRAUZERDAN o'qiydi (core modul backendiga bormaydi —
 * bo'lmasa bitta sekin modul butun dashboardni sekinlashtirardi).
 * Bu cross-origin so'rov, ya'ni modul ruxsat berishi kerak.
 *
 * ⚠️ `raso.uz` va `chat.raso.uz` — cross-ORIGIN, lekin SAME-SITE
 * (registrable domain bitta). Shuning uchun modul sessiya cookie'si
 * `SameSite=Lax` bo'lib qolaveradi va u shu so'rovda YUBORILADI.
 * `None` kerak emas — u CSRF yuzasini bekorga kengaytirardi.
 *
 * ⚠️ Bu fayl modul `config/cors.php` ini ALMASHTIRMAYDI. Modulda o'z
 * fayli bo'lsa u ustun turadi; bu — sukut bo'yicha xavfsiz holat.
 */

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * ⚠️ ANIQ RO'YXAT, `*` EMAS: `*` va credentials BIRGA ishlamaydi
     * (brauzer rad etadi). `*` qo'yilsa sozlama «ruxsat berilgan»
     * ko'rinadi-yu, amalda hamma credentials'li so'rov yiqiladi.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('RASO_SHELL_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    /*
     * ⚠️ Busiz brauzer modul cookie'sini YUBORMAYDI va widget doim
     * «kirilmagan» holatida qolardi — buni faqat brauzer konsolida
     * ko'rish mumkin edi.
     */
    'supports_credentials' => true,
];
