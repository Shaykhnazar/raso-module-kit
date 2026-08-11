<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Http;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\JsonResponse;
use Raso\ModuleKit\Events\DeadLetterAlarm;
use Raso\ModuleKit\Events\DeadLetters;
use Throwable;

/**
 * `/health` — MP-31. Uptime tekshiruvi va deploy'dan keyingi nazorat.
 *
 * ⚠️ «Ilova javob beryaptimi» YETARLI EMAS. Modul HTTP'da tirik bo'lib,
 * ayni paytda hodisalarni umuman qayta ishlamayotgan bo'lishi mumkin:
 * consumer o'lgan, DB yiqilgan, DLQ to'lib borayapti. Bunday holat
 * oddiy ping bilan KO'RINMAYDI — shuning uchun bu yerda DB va DLQ ham
 * tekshiriladi.
 *
 * ⚠️ Status kodi: sog'lom `200`, aks holda `503`. Monitoring tizimlari
 * tananing ichini o'qimaydi, faqat kodga qaraydi — ya'ni JSON qanchalik
 * batafsil bo'lmasin, `200` qaytarsak hech kim xabar topmasdi.
 *
 * ⚠️ Bu endpoint AUTH'SIZ va shunday bo'lishi kerak: uptime tekshiruvi
 * token bilan kelmaydi. Shu sabab javobda foydalanuvchi ma'lumoti YO'Q
 * — faqat hodisa NOMLARI va sonlar.
 */
final readonly class HealthController
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private DeadLetters $letters,
    ) {}

    public function __invoke(): JsonResponse
    {
        $database = $this->databaseOk();

        /*
         * ⚠️ DB yiqilgan bo'lsa DLQ ni SO'RAMAYMIZ: u ham o'sha
         * ulanishga tayanadi va istisno tashlab, health endpointining
         * O'ZINI 500 qilardi — ya'ni «nima buzilgan» degan savolga
         * javob beradigan yagona joy ham yo'qolardi.
         */
        $alarm = $database ? DeadLetterAlarm::from($this->letters) : null;

        $severity = $database ? ($alarm?->severity() ?? 'ok') : 'critical';

        return new JsonResponse([
            'status' => $severity === 'ok' ? 'ok' : 'degraded',
            'severity' => $severity,
            'database' => $database,
            'dead_letters' => [
                'total' => $alarm?->total,
                'by_name' => $alarm?->byName,
                'unfulfilled_erasure' => $alarm?->hasUnfulfilledErasure(),
            ],
        ], $severity === 'ok' ? 200 : 503);
    }

    private function databaseOk(): bool
    {
        try {
            $this->connections->connection()->select('select 1');

            return true;
        } catch (Throwable) {
            // ⚠️ Istisno YUTILADI: health endpointi hech qachon yiqilmasligi
            // kerak, u aynan yiqilgan tizimni tasvirlash uchun bor.
            return false;
        }
    }
}
