<?php

declare(strict_types=1);

use Raso\ModuleKit\Laravel\EventConnection;

/*
 * Modullararo stream qaysi Redis ULANISHIDA va qaysi BO'LIMDA yashaydi.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ⚠️ NEGA BU TEST BOR: XATO SHAKLI «XATOSIZ YIQILISH».
 * ═══════════════════════════════════════════════════════════════════════
 *
 * Stream ikki tomonda boshqa joyda bo'lib qolsa, `XREADGROUP` BO'SH
 * javob qaytaradi — xuddi yangi xabar yo'qdek. Na istisno, na jurnal
 * yozuvi, na `raso:module:doctor` belgisi.
 *
 * Bu bir marta PREFIKS bilan sodir bo'ldi (core
 * `muvaffaqiyat-os-database-raso.events` ga yozib, modul boshqa
 * kalitni o'qidi) va IKKINCHI marta BO'LIM RAQAMI bilan takrorlandi:
 * ulanish `default` dan nusxa olingani uchun modulga o'z `REDIS_DB`
 * ini berish stream'ni ham o'sha bo'limga ko'chirardi.
 *
 * `identity.user_deleted` uchun bu «core o'chirdi, modul eshitmadi»
 * degani — ya'ni «meni unut» kontrakti jimgina bajarilmay qoladi.
 *
 * ⚠️ Test faylida `function` E'LON QILINMAYDI.
 */

/** Prefiksli va o'z bo'limiga qo'yilgan `default` ulanishi. */
$default = [
    'host' => '10.0.0.5',
    'port' => '6380',
    'password' => 'secret',
    'database' => '7',
    'options' => ['prefix' => 'raso-kalendar-database-'],
];

it('`default` dan HOST va PORT nusxa olinadi', function () use ($default): void {
    // ⚠️ Modul faqat oddiy `REDIS_*` o'zgaruvchilarini to'ldirishi
    // kifoya — stream uchun ikkinchi to'plam sozlash talab
    // qilinmaydi.
    $connection = EventConnection::from($default, '0');

    expect($connection['host'])->toBe('10.0.0.5')
        ->and($connection['port'])->toBe('6380')
        ->and($connection['password'])->toBe('secret');
});

it('PREFIKS TASHLANADI — stream nomi hamma servisda AYNAN bir xil', function () use ($default): void {
    expect(EventConnection::from($default, '0')['options'])->toBe(['prefix' => '']);
});

it('BO\'LIM `default` DAN OLINMAYDI — `REDIS_DB` stream\'ni KO\'CHIRMAYDI', function () use ($default): void {
    /*
     * ⚠️ SHU TESTNING BUTUN MAZMUNI. `default` da `database => '7'`
     * turibdi; stream esa berilgan bo'limda qolishi SHART.
     */
    expect(EventConnection::from($default, '0')['database'])->toBe('0');
});

it('stream BO\'LIMI O\'Z sozlamasi bilan ko\'chiriladi', function () use ($default): void {
    // ⚠️ Core tomonda bu `REDIS_EVENT_DB`; ikkala tomon ham uni AYNAN
    // bir xil qiymatga qo'yishi shart.
    expect(EventConnection::from($default, '3')['database'])->toBe('3');
});

it('`read_timeout` NOL — cheksiz', function () use ($default): void {
    /*
     * ⚠️ Demon rejimida `XREADGROUP` `BLOCK` bilan chaqiriladi va
     * soket timeout'i undan qisqa bo'lsa, ulanish «read error» bilan
     * uzilardi: iste'molchi har bo'sh tsiklda yiqilardi.
     */
    expect(EventConnection::from($default, '0')['read_timeout'])->toBe(0);
});

it('`default` da bo\'lmagan kalit qo\'shilmaydi, BOR kalit esa yo\'qolmaydi', function (): void {
    // ⚠️ Nusxa TO'LIQ: `url`, `username`, `scheme` kabi kalitlar
    // stream ulanishida ham kerak bo'ladi va ularni sanab yozish
    // ro'yxatni bir kuni eskirtirardi.
    $connection = EventConnection::from(['url' => 'tcp://redis:6379', 'username' => 'app'], '0');

    expect($connection['url'])->toBe('tcp://redis:6379')
        ->and($connection['username'])->toBe('app')
        ->and($connection)->not->toHaveKey('host');
});
