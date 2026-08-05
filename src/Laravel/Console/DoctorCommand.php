<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Raso\ModuleKit\Events\ConsumedEvents;
use Raso\ModuleKit\Events\DeadLetters;
use Raso\ModuleKit\Events\UserDeletedListener;
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
