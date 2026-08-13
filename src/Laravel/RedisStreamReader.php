<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Connection;
use Raso\ModuleKit\Events\OutboxMessage;
use Throwable;

/**
 * Redis Stream'dan hodisalarni o'qiydi (consumer group).
 *
 * ⚠️ HAR MODUL O'Z GROUP'I bilan o'qiydi. Shu sabab bitta xabarni hamma
 * modul oladi va biri ikkinchisining xabarini «yeb qo'ymaydi».
 *
 * ⚠️ ACK faqat MUVAFFAQIYATLI qayta ishlashdan keyin. Xabar ishlanmasa
 * u pending ro'yxatda qoladi va keyingi o'tishda (yoki boshqa worker
 * tomonidan) qayta olinadi. Darhol ACK qilsak, yiqilgan xabar butunlay
 * yo'qolardi — `identity.user_deleted` uchun bu qonun buzilishi.
 */
final readonly class RedisStreamReader
{
    public function __construct(
        private Redis $redis,
        private string $stream,
        private string $group,
        private string $consumer,
        private string $connection = 'default',
    ) {}

    /**
     * Group mavjudligini kafolatlaydi.
     *
     * `MKSTREAM` — stream hali yo'q bo'lsa yaratadi: modul core'dan
     * OLDIN ishga tushishi mumkin va bu xato bo'lmasligi kerak.
     */
    public function ensureGroup(): void
    {
        try {
            // phpredis imzosi: xGroup(amal, kalit, group, id, mkStream).
            $this->connection()->command('xgroup', ['CREATE', $this->stream, $this->group, '0', true]);
        } catch (Throwable $e) {
            // `BUSYGROUP` — group allaqachon bor, bu NORMAL holat.
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Yangi xabarlarni o'qiydi.
     *
     * @return list<array{stream_id: string, message: OutboxMessage}>
     */
    public function read(int $limit = 50, int $blockMilliseconds = 5000): array
    {
        /*
         * ⚠️ phpredis imzosi: xReadGroup(group, consumer, [stream => id],
         * count, block). Redis protokoli shaklida (`GROUP ... STREAMS ...`)
         * yozsak, phpredis argumentlarni boshqacha o'qib xato beradi.
         *
         * ⚠️ `block <= 0` da BLOCK argumenti UMUMAN berilmaydi.
         *
         * Redis'da `BLOCK 0` — «cheksiz kut», «kutma» EMAS. Bir martalik
         * rejimda 0 uzatilganda buyruq abadiy bloklanib, soket o'qish
         * timeout'iga urilardi va `RedisException: read error on
         * connection` bilan yiqilardi — bo'sh navbatda HAR SAFAR. Navbatda
         * xabar turgan paytda bu ko'rinmasdi, shuning uchun jonli sinovda
         * topildi.
         */
        $arguments = [$this->group, $this->consumer, [$this->stream => '>'], $limit];

        if ($blockMilliseconds > 0) {
            $arguments[] = $blockMilliseconds;
        }

        /** @var mixed $raw */
        $raw = $this->connection()->command('xreadgroup', $arguments);

        return $this->decode($raw);
    }

    /**
     * Yetkazilgan, lekin ACK qilinmagan xabarlar (yiqilgan worker izi).
     *
     * ⚠️ Busiz xabarlar abadiy pending ro'yxatda qolib ketardi: `read()`
     * faqat YANGI xabarlarni beradi (`>`), eskilari esa ko'rinmas bo'lib
     * qolardi va hech kim ularni qayta ishlamasdi.
     *
     * @return list<array{stream_id: string, message: OutboxMessage}>
     */
    public function readPending(int $limit = 50): array
    {
        /** @var mixed $raw */
        // `0` — shu consumer'ga yetkazilgan, ACK qilinmagan xabarlar.
        $raw = $this->connection()->command('xreadgroup', [
            $this->group,
            $this->consumer,
            [$this->stream => '0'],
            $limit,
        ]);

        return $this->decode($raw);
    }

    public function acknowledge(string $streamId): void
    {
        // ⚠️ phpredis'da id'lar MASSIV bo'lishi shart, satr emas.
        $this->connection()->command('xack', [$this->stream, $this->group, [$streamId]]);
    }

    /**
     * @return list<array{stream_id: string, message: OutboxMessage}>
     */
    private function decode(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $entries = [];

        // Javob shakli drayverga qarab farq qiladi (phpredis va predis).
        foreach ($raw as $streamKey => $messages) {
            if (! is_array($messages)) {
                continue;
            }

            foreach ($messages as $streamId => $fields) {
                if (! is_array($fields)) {
                    continue;
                }

                $decoded = $this->toMessage($fields);

                if ($decoded !== null) {
                    $entries[] = ['stream_id' => (string) $streamId, 'message' => $decoded];
                }
            }
        }

        return $entries;
    }

    /** @param array<mixed> $fields */
    private function toMessage(array $fields): ?OutboxMessage
    {
        $payload = json_decode((string) ($fields['payload'] ?? '[]'), true);

        try {
            return OutboxMessage::fromArray([
                'id' => (string) ($fields['id'] ?? ''),
                'name' => (string) ($fields['name'] ?? ''),
                'payload' => is_array($payload) ? $payload : [],
                'occurred_at' => (string) ($fields['occurred_at'] ?? ''),
            ]);
        } catch (Throwable) {
            /*
             * ⚠️ Buzuq xabar butun oqimni to'xtatmaydi. `null` qaytariladi
             * va u ACK ham qilinmaydi — pending ro'yxatda qolib, admin
             * ko'rishi uchun ko'rinib turadi.
             */
            return null;
        }
    }

    private function connection(): Connection
    {
        return $this->redis->connection($this->connection);
    }
}
