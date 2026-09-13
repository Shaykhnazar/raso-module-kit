<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Tests\Support;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Support\Arr;

/**
 * Konfiguratsiya reestrining eng sodda ko'rinishi.
 *
 * ⚠️ TEST FAYLIDAN CHIQARILDI: u `ModuleWiringContractTest` ning
 * ichida yashardi va ikkinchi spec (`EventConnectionTest`) unga
 * suyangan kuni fayllar TARTIBIGA bog'liq bo'lib qoldi —
 * `pest tests/Unit/EventConnectionTest.php` yolg'iz chaqirilganda
 * «Class not found» beradi, to'liq to'plamda esa yashil. Shunday
 * yashiringan bog'liqlik CI'da emas, lokal bitta faylni
 * yugurtirgan odamda portlaydi.
 *
 * NEGA `Illuminate\Config\Repository` emas: bu paketda Laravel ilovasi YO'Q
 * va `illuminate/config` o'rnatilmagan. Kontrakt esa faqat interfeysga
 * suyanadi — modul haqiqiy reestrni beradi.
 */
final class KitFakeConfig implements ConfigContract
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = []) {}

    /** @param string $key */
    public function has($key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * @param  array<mixed>|string  $key
     * @param  mixed  $default
     */
    public function get($key, $default = null): mixed
    {
        if (! is_string($key)) {
            return $default;
        }

        return Arr::get($this->items, $key, $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param  array<string, mixed>|string  $key
     * @param  mixed  $value
     */
    public function set($key, $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $one => $single) {
            Arr::set($this->items, $one, $single);
        }
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function prepend($key, $value): void
    {
        $items = $this->listAt($key);
        array_unshift($items, $value);
        $this->set($key, $items);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function push($key, $value): void
    {
        $items = $this->listAt($key);
        $items[] = $value;
        $this->set($key, $items);
    }

    /**
     * @param  string  $key
     * @return array<mixed>
     */
    private function listAt($key): array
    {
        $items = $this->get($key, []);

        return is_array($items) ? $items : [];
    }
}
