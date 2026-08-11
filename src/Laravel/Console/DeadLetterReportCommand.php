<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Raso\ModuleKit\Events\DeadLetterAlarm;
use Raso\ModuleKit\Events\DeadLetters;

/**
 * DLQ hisoboti — MP-31. **Cron'da ishlatilsin.**
 *
 * ⚠️ CHIQISH KODI monitoring uchun: `0` sog'lom · `1` xabar qolgan ·
 * `2` «meni unut» bajarilmagan. Odam log o'qishiga tayanib bo'lmaydi —
 * DLQ oylar davomida hech kim qaramaydigan joy bo'lib qolishi mumkin.
 */
final class DeadLetterReportCommand extends Command
{
    protected $signature = 'raso:module:dlq';

    protected $description = 'Qayta ishlanmagan hodisalar hisoboti (monitoring uchun)';

    public function handle(DeadLetters $letters): int
    {
        $alarm = DeadLetterAlarm::from($letters);

        if ($alarm->isHealthy()) {
            $this->info('✓ DLQ bo\'sh.');

            return self::SUCCESS;
        }

        $this->error("DLQ'da {$alarm->total} ta qayta ishlanmagan xabar bor:");

        foreach ($alarm->byName as $name => $count) {
            $this->line("  {$name}: {$count}");
        }

        if ($alarm->hasUnfulfilledErasure()) {
            /*
             * ⚠️ Bu oddiy nosozlik EMAS. Foydalanuvchi «meni unut»
             * tugmasini bosgan, tizim «bajarildi» degan, lekin ma'lumot
             * o'chirilmagan — O'zR qonuni buzilishi.
             *
             * `raso:module:purge-user {sub}` bilan qo'lda bajarish
             * mumkin; sabab esa `dead_letters.error` da.
             */
            $this->error(
                '🛑 «MENI UNUT» BAJARILMAGAN. Bu qonuniy talab — '.
                "`raso:module:purge-user {sub}` bilan qo'lda bajaring va sababini tekshiring."
            );

            return 2;
        }

        return self::FAILURE;
    }
}
