<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Events;

use Throwable;

/**
 * Kelgan hodisani idempotent qayta ishlaydi.
 *
 * Tartib MUHIM va ataylab shunday:
 *
 *   TRANZAKSIYA {
 *       markConsumed()  →  false bo'lsa darhol chiqamiz (dublikat)
 *       handler(lar)ni ishga tushiramiz
 *   }
 *
 * Belgilash va qayta ishlash bitta tranzaksiyada. Jarayon o'rtada yiqilsa
 * ikkalasi ham qaytariladi va xabar qaytadan yetkaziladi. Aks holda xabar
 * «ko'rilgan» bo'lib qolib, hech qachon qayta ishlanmasdi — `user_deleted`
 * uchun bu ma'lumot o'chirilmay qolishi demakdir.
 */
final readonly class EventConsumer
{
    /** @param list<EventHandler> $handlers */
    public function __construct(
        private ConsumedEvents $consumed,
        private DeadLetters $deadLetters,
        private TransactionRunner $transaction,
        private array $handlers,
    ) {}

    public function consume(OutboxMessage $message): ConsumeOutcome
    {
        $handlers = array_values(array_filter(
            $this->handlers,
            static fn (EventHandler $h): bool => $h->handles($message->name),
        ));

        if ($handlers === []) {
            // Handler yo'q — belgilamaymiz ham. Modulga keyinroq handler
            // qo'shilsa, xabarni qayta o'ynatish (replay) mumkin bo'lib qoladi.
            return ConsumeOutcome::Ignored;
        }

        try {
            return $this->transaction->run(function () use ($message, $handlers): ConsumeOutcome {
                if (! $this->consumed->markConsumed($message->id)) {
                    return ConsumeOutcome::Duplicate;
                }

                foreach ($handlers as $handler) {
                    $handler->handle($message);
                }

                return ConsumeOutcome::Processed;
            });
        } catch (Throwable $e) {
            // Tranzaksiya qaytarildi → `consumed_events` da iz qolmadi →
            // stream qayta yetkazsa yana urinib ko'riladi. DLQ esa ko'rinadigan
            // signal: bu xabar hozircha ishlanmadi.
            $this->deadLetters->record($message, $e->getMessage());

            return ConsumeOutcome::Failed;
        }
    }
}
