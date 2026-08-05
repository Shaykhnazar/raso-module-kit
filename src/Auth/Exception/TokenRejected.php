<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth\Exception;

use RuntimeException;
use Throwable;

/**
 * Token qabul qilinmadi. HAR DOIM 401 ga aylanadi — 500 ga EMAS.
 *
 * ⚠️ `reason` faqat log va test uchun. Klientga qaytariladigan javobda
 * tafsilot berilmaydi: «imzo noto'g'ri» bilan «kalit topilmadi» ni ajratish
 * hujumchiga tizim haqida ma'lumot beradi.
 */
final class TokenRejected extends RuntimeException
{
    private function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("Token rad etildi: {$reason}", 0, $previous);
    }

    public static function missing(): self
    {
        return new self('bearer_yoq');
    }

    public static function malformed(?Throwable $previous = null): self
    {
        return new self('shakli_buzuq', $previous);
    }

    public static function unsupportedAlgorithm(string $alg): self
    {
        return new self("algoritm_qollanmaydi:{$alg}");
    }

    public static function unknownKey(string $kid): self
    {
        return new self("kalit_topilmadi:{$kid}");
    }

    public static function invalidSignature(?Throwable $previous = null): self
    {
        return new self('imzo_notogri', $previous);
    }

    public static function expired(?Throwable $previous = null): self
    {
        return new self('muddati_otgan', $previous);
    }

    public static function wrongIssuer(): self
    {
        return new self('notogri_issuer');
    }

    public static function wrongAudience(): self
    {
        return new self('notogri_audience');
    }

    public static function invalidClaims(?Throwable $previous = null): self
    {
        return new self('claim_notogri', $previous);
    }

    /**
     * JWKS olinmadi — tizim nosozligi, lekin javob baribir **401**.
     *
     * Fail-closed: kalitni tekshira olmayapmiz, demak tokenga ISHONMAYMIZ.
     * 500 qaytarish tekshiruvni «vaqtincha» chetlab o'tish taassurotini
     * beradi va monitoringda auth muammosi sifatida ko'rinmaydi.
     */
    public static function keysUnavailable(JwksUnavailable $previous): self
    {
        return new self('kalitlar_olinmadi', $previous);
    }
}
