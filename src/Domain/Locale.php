<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Domain;

/**
 * Interfeys tili.
 *
 * `uz_Cyrl` — alohida til EMAS, o'zbek tilining kirill YOZUVI. Shuning uchun
 * `language()` ikkalasi uchun ham `uz` qaytaradi: tarjima kaliti bitta bo'lishi,
 * transliteratsiya esa yozuv darajasida hal qilinishi kerak.
 *
 * Sof domen: HTTP sarlavhasini o'qish Infrastructure ishi, bu yerda faqat
 * BCP-47 tegini tanish mantiqi.
 */
enum Locale: string
{
    case Uz = 'uz';
    case UzCyrl = 'uz_Cyrl';
    case Ru = 'ru';
    case En = 'en';

    /**
     * BCP-47 tegidan (`uz-Cyrl-UZ`, `ru-RU`, `en-US`) tilni aniqlaydi.
     * Tanilmasa — o'zbekcha (asosiy auditoriya tili).
     */
    public static function fromTag(string $tag): self
    {
        $normalized = strtolower(trim($tag));

        // Yozuv tekshiruvi TILDAN OLDIN: `uz-Cyrl` ni `uz` yutib yubormasin.
        if (str_contains($normalized, 'cyrl')) {
            return self::UzCyrl;
        }

        return match (true) {
            str_starts_with($normalized, 'ru') => self::Ru,
            str_starts_with($normalized, 'en') => self::En,
            default => self::Uz,
        };
    }

    /** Yozuvdan qat'i nazar til kodi — tarjima fayllari shu bo'yicha tanlanadi. */
    public function language(): string
    {
        return match ($this) {
            self::Uz, self::UzCyrl => 'uz',
            self::Ru => 'ru',
            self::En => 'en',
        };
    }

    public function isCyrillicScript(): bool
    {
        return $this === self::UzCyrl || $this === self::Ru;
    }
}
