<?php

declare(strict_types=1);

return [
    /*
     * IdP — `raso-core`. Token `iss` claim'i shunga AYNAN teng bo'lishi shart.
     */
    'issuer' => env('RASO_ISSUER', 'https://api.raso.uz'),

    /*
     * Token `aud` i shunga teng bo'lmasa rad etiladi — ya'ni chat uchun
     * berilgan token calendar'da ISHLAMAYDI.
     *
     * ⚠️ HOZIRCHA bu — shu modulning **OAuth klient identifikatori**
     * (`oauth_clients.id`), `modules.code` EMAS. Sabab: core (MP-07)
     * `aud` ga league standarti bo'yicha klient id'sini qo'yadi.
     * Barqaror `modules.code` MP-10 (modul katalogi) bilan keladi va
     * o'shanda bu yerga ham o'zgartirish kerak bo'ladi.
     */
    'audience' => env('RASO_MODULE_KEY'),

    'jwks_uri' => env('RASO_JWKS_URI', 'https://api.raso.uz/oauth/jwks'),

    /* Kalitlar keshi. 24 soat — IdP'ga har so'rovda borilmasin. */
    'jwks_ttl' => (int) env('RASO_JWKS_TTL', 86400),

    /*
     * `kid` topilmaganda majburiy yangilash orasidagi eng qisqa vaqt.
     * Qalbaki `kid` bilan yuborilgan so'rovlar oqimi IdP'ga amplifikatsiya
     * bo'lib qaytmasligi uchun.
     */
    'jwks_refresh_cooldown' => (int) env('RASO_JWKS_REFRESH_COOLDOWN', 300),

    /* JWKS so'rovi timeout'i (sekund). Qisqa: fail-closed'da uzoq kutish zarar. */
    'jwks_timeout' => (int) env('RASO_JWKS_TIMEOUT', 3),

    /* Soat farqi uchun bag'rikenglik (sekund). */
    'leeway' => (int) env('RASO_JWT_LEEWAY', 30),

    'events' => [
        /*
         * Servislararo umumiy Redis Stream. Hamma modul shu stream'ni o'qiydi,
         * har biri O'Z consumer group'i bilan — shuning uchun bitta modul
         * xabarni «yeb qo'ymaydi».
         */
        'stream' => env('RASO_EVENT_STREAM', 'raso.events'),

        'redis_connection' => env('RASO_EVENT_REDIS', 'default'),

        /*
         * ⚠️ Consumer group — HAR MODULDA BOSHQACHA bo'lishi SHART.
         *
         * Ikki modul bir xil group bilan o'qisa, xabar ular ORASIDA
         * bo'linadi: chat oladi — calendar olmaydi. `identity.user_deleted`
         * uchun bu «bir modul o'chirdi, ikkinchisi bilmadi» degani.
         */
        'group' => env('RASO_EVENT_GROUP', 'raso-module'),

        /*
         * Stream cheksiz o'smasin. Uzoq muddatli manba — outbox jadvali,
         * stream emas: xabar yetkazilgach unga ehtiyoj qolmaydi.
         */
        'max_length' => (int) env('RASO_EVENT_STREAM_MAXLEN', 100_000),

        'relay_batch' => (int) env('RASO_RELAY_BATCH', 100),

        'relay_max_attempts' => (int) env('RASO_RELAY_MAX_ATTEMPTS', 5),
    ],
];
