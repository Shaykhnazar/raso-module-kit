<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Raso\ModuleKit\Events\ConsumedEvents;
use Raso\ModuleKit\Events\DeadLetters;
use Raso\ModuleKit\Events\DeletionConfirmer;
use Raso\ModuleKit\Events\UserDeletedListener;
use ReflectionProperty;
use Throwable;

/**
 * Modul kontraktga mos sozlanganini tekshiradi. **CI'da ishlatilsin.**
 *
 * Bu buyruq — «meni unut» kafolatining texnik majburlagichi: modul
 * `UserDeletedListener` ni bog'lamagan bo'lsa CI qizil bo'ladi, prod'da
 * jimgina buzilib turmaydi.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'raso:module:doctor';

    protected $description = 'Modul platformasi kontraktini tekshiradi (CI uchun)';

    public function handle(): int
    {
        $problems = [];

        foreach ($this->requiredBindings() as $abstract => $why) {
            try {
                $this->laravel->make($abstract);
            } catch (Throwable) {
                $problems[] = "{$abstract} bog'lanmagan — {$why}";
            }
        }

        $problems = [...$problems, ...$this->deletionConfirmationProblems()];

        /** @var Config $config */
        $config = $this->laravel->make(Config::class);

        foreach ($this->requiredConfig() as $key) {
            $value = $config->get($key);

            if (! is_string($value) || trim($value) === '') {
                $problems[] = "config `{$key}` bo'sh";
            }
        }

        if ($problems !== []) {
            $this->error('Modul kontrakti buzilgan:');

            foreach ($problems as $problem) {
                $this->line("  ✗ {$problem}");
            }

            return self::FAILURE;
        }

        $this->info('✓ Modul kontrakti bajarilgan.');

        return self::SUCCESS;
    }

    /**
     * O'chirish TASDIQI haqiqatan ulanganini tekshiradi.
     *
     * ⚠️ Bog'lanish borligi yetarli emas: modul `UserDeletedListener` ni
     * kengaytirib `parent::__construct()` ni chaqirmasa, tasdiqlovchi
     * `null` bo'lib qoladi va modul ma'lumotni o'chirsa ham core buni
     * bilmasdi — tilxat admin hisobotida abadiy «kutilmoqda» bo'lardi.
     * Bu bitta unutilgan qator, shuning uchun uni odam emas, CI ushlaydi.
     *
     * @return list<string>
     */
    private function deletionConfirmationProblems(): array
    {
        try {
            $listener = $this->laravel->make(UserDeletedListener::class);
        } catch (Throwable) {
            // Bog'lanish yo'qligi yuqorida allaqachon qayd etilgan.
            return [];
        }

        $confirmer = (new ReflectionProperty(UserDeletedListener::class, 'confirmer'))
            ->getValue($listener);

        if ($confirmer instanceof DeletionConfirmer) {
            return [];
        }

        return [
            sprintf(
                "%s tasdiqlovchisiz qurilgan — o'chirish tilxati core'ga YETMAYDI ".
                '(konstruktorda `parent::__construct($confirmer)` chaqirilganini tekshiring)',
                $listener::class,
            ),
        ];
    }

    /** @return array<class-string, string> */
    private function requiredBindings(): array
    {
        return [
            UserDeletedListener::class => "«meni unut» ishlamaydi (O'zR qonuni talabi)",
            ConsumedEvents::class => 'hodisalar takror qayta ishlanadi',
            DeadLetters::class => "qayta ishlanmagan xabar KO'RINMAY qoladi",
        ];
    }

    /** @return list<string> */
    private function requiredConfig(): array
    {
        return [
            'module-kit.issuer',
            'module-kit.audience',
            'module-kit.jwks_uri',
        ];
    }
}
