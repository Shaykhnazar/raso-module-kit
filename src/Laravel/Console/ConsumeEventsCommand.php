<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Raso\ModuleKit\Events\ConsumeOutcome;
use Raso\ModuleKit\Events\EventConsumer;
use Raso\ModuleKit\Laravel\RedisStreamReader;

/**
 * Core hodisalarini iste'mol qiladi.
 *
 * Ikki rejim:
 *   – bir o'tish (cron uchun zaxira yo'l);
 *   – `--daemon` (systemd) — hodisa bir necha soniyada yetib boradi.
 *
 * ⚠️ HAR O'TISHDA avval PENDING xabarlar o'qiladi. Ular yiqilgan
 * worker izi: `read()` faqat yangilarini beradi va eskilari hech kim
 * ko'rmaydigan bo'lib qolardi.
 */
final class ConsumeEventsCommand extends Command
{
    protected $signature = 'raso:module:consume
        {--limit=50 : Bir o\'tishda nechta xabar}
        {--daemon : Uzluksiz ishlash}
        {--max-seconds=0 : Demon rejimida ish vaqti (0 — cheksiz)}';

    protected $description = 'Core hodisalarini Redis Stream dan iste\'mol qiladi';

    public function handle(RedisStreamReader $reader, EventConsumer $consumer): int
    {
        $reader->ensureGroup();

        $limit = (int) $this->option('limit');
        $maxSeconds = (int) $this->option('max-seconds');
        $startedAt = time();

        do {
            $this->drain($reader, $consumer, $limit);

            if ($maxSeconds > 0 && time() - $startedAt >= $maxSeconds) {
                return self::SUCCESS;
            }
        } while ($this->option('daemon'));

        return self::SUCCESS;
    }

    private function drain(RedisStreamReader $reader, EventConsumer $consumer, int $limit): void
    {
        $batches = [
            // Avval yiqilgan worker qoldirgani, keyin yangilari.
            $reader->readPending($limit),
            $reader->read($limit, $this->option('daemon') ? 5000 : 0),
        ];

        foreach ($batches as $entries) {
            foreach ($entries as $entry) {
                $outcome = $consumer->consume($entry['message']);

                /*
                 * ⚠️ ACK faqat qayta ishlangan yoki e'tiborsiz qoldirilgan
                 * xabarga. `Failed` bo'lsa ACK QILINMAYDI — xabar pending
                 * ro'yxatda qoladi va qayta uriniladi. Aks holda yiqilgan
                 * `identity.user_deleted` butunlay yo'qolardi.
                 */
                if ($outcome !== ConsumeOutcome::Failed) {
                    $reader->acknowledge($entry['stream_id']);
                }

                if ($outcome === ConsumeOutcome::Failed) {
                    $this->warn("Xabar ishlanmadi: {$entry['message']->name} ({$entry['message']->id})");
                }
            }
        }
    }
}
