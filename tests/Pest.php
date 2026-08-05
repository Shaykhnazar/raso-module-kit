<?php

declare(strict_types=1);

/*
 * Pest bootstrap.
 *
 * Bu paketda Laravel YO'Q — hamma test sof PHP unit testi. Shuning uchun
 * `pest()->extend(TestCase::class)` chaqirilmaydi va testlar millisekundda o'tadi.
 *
 * ⚠️ Test faylida `function` E'LON QILMANG. `pest --parallel` ikki faylni bitta
 * jarayonga yuklasa `Cannot redeclare` bilan yiqiladi va bu xato ketma-ket
 * ishlatilganda KO'RINMAYDI. Umumiy yordamchi kerak bo'lsa — `src/Testing/`
 * ga, u composer `autoload.files` orqali hamma joyda mavjud.
 */
