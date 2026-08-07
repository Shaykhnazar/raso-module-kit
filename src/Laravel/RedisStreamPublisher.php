<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Contracts\Redis\Factory as Redis;
use JsonException;
use Raso\ModuleKit\Events\EventPublisher;
use Raso\ModuleKit\Events\Exception\PublishFailed;
use Raso\ModuleKit\Events\OutboxMessage;
use Throwable;

/**
 * Xabarni Redis Stream'ga yozadi (`XADD`).
 *
 * NEGA Redis Streams, RabbitMQ/Kafka emas: Redis allaqachon bor (Horizon
 * uchun), consumer group va at-least-once semantikasi bor, yangi servis
 * saqlash kerak emas. Yuk oshsa almashtiriladi — `EventPublisher` porti
 * shu uchun.
 *
 * `MAXLEN ~` — stream cheksiz o'smasin. Xabarlar yetkazilgach kerak emas;
 * uzoq muddatli manba — outbox jadvali, stream emas.
 */
final readonly class RedisStreamPublisher implements EventPublisher
{
    public function __construct(
        private Redis $redis,
        private string $stream = 'raso.events',
        private string $connection = 'default',
        private int $maxLength = 100_000,
    ) {}

    public function publish(OutboxMessage $message): void
    {
        try {
            $payload = json_encode($message->payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw PublishFailed::forStream($this->stream, $e);
        }

        try {
            /*
             * ⚠️ Argument tartibi phpredis'ning `xAdd` IMZOSI bo'yicha:
             * (kalit, id, maydonlar, maxlen, taqribiymi). Redis protokoli
             * shaklida (`MAXLEN ~ N *`) yozsak, phpredis uni maydon deb
             * qabul qilib xato beradi — soxta publisher'li unit testda bu
             * KO'RINMAYDI, faqat jonli Redis'da chiqadi.
             */
            $this->redis->connection($this->connection)->command('xadd', [
                $this->stream,
                '*',
                [
                    'id' => $message->id,
                    'name' => $message->name,
                    'payload' => $payload,
                    'occurred_at' => $message->occurredAt->format(DATE_ATOM),
                ],
                $this->maxLength,
                // Taqribiy kesish — aniq MAXLEN har XADD'da stream'ni
                // to'liq kesib, yozishni sekinlashtirardi.
                true,
            ]);
        } catch (Throwable $e) {
            throw PublishFailed::forStream($this->stream, $e);
        }
    }
}
