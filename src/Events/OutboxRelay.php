<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use Raso\ModuleKit\Events\Exception\PublishFailed;

/**
 * Outbox'dagi xabarlarni stream'ga chiqaradi.
 *
 * ⚠️ Tranzaksiyadan **TASHQARIDA** ishlaydi — bu naqshning butun mohiyati.
 * Biznes amali outbox qatorini yozib commit qiladi va tugaydi; yetkazish
 * nosozligi foydalanuvchi amalini YIQITMAYDI. Core'da bu allaqachon shunday
 * (`OutboxRelay` → `TelegramNotifier`), shu naqsh modullarga ko'chirildi.
 */
final readonly class OutboxRelay
{
    public function __construct(
        private OutboxStore $store,
        private EventPublisher $publisher,
        private int $batchSize = 100,
        private int $maxAttempts = 5,
    ) {}

    /**
     * @param  int|null  $limit  bir o'tishdagi chegara (sukut — konstruktordagi)
     */
    public function flush(?int $limit = null): RelayReport
    {
        $limit ??= $this->batchSize;

        $published = 0;
        $failed = 0;
        $exhausted = 0;

        foreach ($this->store->pending($limit) as $message) {
            try {
                $this->publisher->publish($message);
                $this->store->markPublished($message->id);
                $published++;
            } catch (PublishFailed $e) {
                // Urinishlar tugagan bo'lsa — qayta urinmaymiz, qo'lda
                // ko'riladi. Aks holda buzuq xabar navbatni abadiy band qiladi.
                if ($message->attempts + 1 >= $this->maxAttempts) {
                    $this->store->markExhausted($message->id, $e->getMessage());
                    $exhausted++;

                    continue;
                }

                $this->store->markFailed($message->id, $e->getMessage());
                $failed++;
            }
        }

        return new RelayReport($published, $failed, $exhausted);
    }
}
