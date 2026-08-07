<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Raso\ModuleKit\Events\OutboxRelay;

/**
 * Modul outbox'ini Redis Stream'ga chiqaradi.
 *
 * ⚠️ Tranzaksiyadan TASHQARIDA ishlaydi — outbox naqshining mohiyati:
 * biznes amali outbox qatorini yozib commit qiladi va tugaydi; yetkazish
 * nosozligi foydalanuvchi amalini yiqitmaydi.
 */
final class RelayOutboxCommand extends Command
{
    protected $signature = 'raso:module:relay
        {--limit= : Bir o\'tishda nechta xabar}
        {--daemon : Uzluksiz ishlash}
        {--max-seconds=0 : Demon rejimida ish vaqti (0 — cheksiz)}';

    protected $description = 'Modul outbox xabarlarini stream ga chiqaradi';

    public function handle(OutboxRelay $relay): int
    {
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');
        $maxSeconds = (int) $this->option('max-seconds');
        $startedAt = time();

        do {
            $report = $relay->flush($limit);

            if ($report->needsAttention()) {
                $this->warn("Chiqarilmadi: {$report->failed} qayta uriniladi, {$report->exhausted} chetga surildi.");
            }

            if ($report->published > 0) {
                $this->info("Chiqarildi: {$report->published}");
            }

            if ($maxSeconds > 0 && time() - $startedAt >= $maxSeconds) {
                return self::SUCCESS;
            }

            if ($this->option('daemon') && $report->total() === 0) {
                // Navbat bo'sh bo'lsa DB'ni tinimsiz so'roqqa tutmaymiz.
                sleep(2);
            }
        } while ($this->option('daemon'));

        return self::SUCCESS;
    }
}
