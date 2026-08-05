<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Raso\ModuleKit\Events\TransactionRunner;
use Throwable;

/**
 * Tranzaksiyani TAQLID qiladi.
 *
 * Testda haqiqiy DB yo'q, shuning uchun rollback'ni `$onRollback` klosurasi
 * bajaradi. Bu «xato bo'lsa `consumed_events` da iz qolmasin» qoidasini
 * HAQIQATAN tekshirish imkonini beradi — shunchaki ishonib qo'ya qolish emas.
 */
final class FakeTransactionRunner implements TransactionRunner
{
    public int $rollbacks = 0;

    /** @var (callable(): void)|null */
    private $onRollback;

    /** @param (callable(): void)|null $onRollback */
    public function __construct(?callable $onRollback = null)
    {
        $this->onRollback = $onRollback;
    }

    public function run(callable $work): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            $this->rollbacks++;

            if ($this->onRollback !== null) {
                ($this->onRollback)();
            }

            throw $e;
        }
    }
}
