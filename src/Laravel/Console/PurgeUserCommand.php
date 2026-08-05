<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel\Console;

use Illuminate\Console\Command;
use Raso\ModuleKit\Domain\PublicId;
use Raso\ModuleKit\Events\EventNames;
use Raso\ModuleKit\Events\OutboxMessage;
use Raso\ModuleKit\Events\UserDeletedListener;
use Throwable;

/**
 * Foydalanuvchi ma'lumotini QO'LDA o'chiradi.
 *
 * Nima uchun kerak: `identity.user_deleted` xabari yo'qolsa yoki DLQ'ga
 * tushsa, admin o'chirishni qo'lda yakunlashi kerak — aks holda «meni unut»
 * to'liq bajarilmagan bo'lib qoladi (MP-11 hisoboti shuni ko'rsatadi).
 */
final class PurgeUserCommand extends Command
{
    protected $signature = 'raso:module:purge-user {sub : Foydalanuvchi uuid (public_id)} {--force : Tasdiqlashsiz}';

    protected $description = "Berilgan foydalanuvchining shu moduldagi hamma ma'lumotini o'chiradi";

    public function handle(UserDeletedListener $listener): int
    {
        $sub = $this->argument('sub');

        if (! is_string($sub)) {
            $this->error('`sub` argumenti satr bo\'lishi kerak.');

            return self::FAILURE;
        }

        if (! PublicId::isValid($sub)) {
            $this->error("Yaroqsiz uuid: {$sub}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("«{$sub}» ma'lumoti butunlay o'chiriladi. Davom etamizmi?")) {
            $this->info('Bekor qilindi.');

            return self::SUCCESS;
        }

        try {
            $listener->handle(new OutboxMessage(
                id: PublicId::generate()->value,
                name: EventNames::UserDeleted,
                payload: ['sub' => $sub],
                occurredAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            ));
        } catch (Throwable $e) {
            $this->error("O'chirish bajarilmadi: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Bajarildi: {$sub}");

        return self::SUCCESS;
    }
}
