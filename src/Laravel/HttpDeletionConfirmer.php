<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\DeletionConfirmer;
use RuntimeException;
use Throwable;

/**
 * Tilxatni core'ning `POST /api/v1/modules/deletion-receipts` endpointiga
 * yuboradi (MP-11).
 *
 * ⚠️ Autentifikatsiya — modulning O'Z klient credentials'i (HTTP Basic),
 * foydalanuvchi tokeni EMAS: bu paytda foydalanuvchi allaqachon o'chirilgan
 * va uning tokeni yo'q. Chaqiruvchi — servisning o'zi.
 */
final readonly class HttpDeletionConfirmer implements DeletionConfirmer
{
    public function __construct(
        private Http $http,
        /** Core API'sining manzili — OIDC `issuer` identifikatori EMAS. */
        private string $coreUrl,
        private string $clientId,
        private string $clientSecret,
        private int $timeout = 3,
        private ?LoggerInterface $logger = null,
    ) {}

    public function confirm(PublicId $userRef, int $purgedRows): void
    {
        $url = rtrim($this->coreUrl, '/').'/api/v1/modules/deletion-receipts';

        try {
            $response = $this->http->asJson()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout($this->timeout)
                ->post($url, ['sub' => (string) $userRef, 'purged_rows' => $purgedRows]);
        } catch (Throwable $e) {
            throw new RuntimeException("O'chirish tilxati yuborilmadi: {$url}", 0, $e);
        }

        if ($response->successful()) {
            return;
        }

        /*
         * ⚠️ 404 — XATO EMAS. Core «kutilayotgan tilxat topilmadi» deydi,
         * ya'ni u allaqachon tasdiqlangan. Xabar `at-least-once` yetkaziladi
         * va shu sabab qayta kelishi NORMAL; 404 ni xato deb hisoblasak,
         * xabar abadiy qayta urinilib DLQ ni to'ldirardi.
         */
        if ($response->status() === 404) {
            /*
             * ⚠️ OGOHLANTIRISH YOZILADI. 404 haqiqatan «allaqachon
             * tasdiqlangan» bo'lishi mumkin, lekin manzil noto'g'ri
             * sozlanganda begona server ham 404 qaytaradi — va tasdiq
             * jimgina yo'qolardi. Log busiz yagona iz admin hisobotidagi
             * 24 soatlik kechikish bo'lardi.
             */
            $this->logger?->warning("O'chirish tilxati topilmadi (404) — manzil to'g'rimi?", [
                'url' => $url,
                'sub' => (string) $userRef,
            ]);

            return;
        }

        throw new RuntimeException(
            "O'chirish tilxati qabul qilinmadi ({$response->status()}): {$url}",
        );
    }
}
