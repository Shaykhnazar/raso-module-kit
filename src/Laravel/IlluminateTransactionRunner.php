<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Database\ConnectionResolverInterface;
use Raso\ModuleKit\Events\TransactionRunner;

final readonly class IlluminateTransactionRunner implements TransactionRunner
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function run(callable $work): mixed
    {
        // `transaction()` `Closure` kutadi — `callable` ni o'raymiz.
        return $this->connections->connection()->transaction(
            static fn (): mixed => $work(),
        );
    }
}
