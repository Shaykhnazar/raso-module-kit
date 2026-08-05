<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as Cache;
use Raso\ModuleKit\Auth\Exception\JwksUnavailable;

/**
 * JWKS ni keshdan beradi (`Modul platformasi/01` §5).
 *
 * Ikki himoya:
 *  1. **24 soat TTL** — har so'rovda IdP'ga borilmaydi.
 *  2. **Rate-limit** — `kid` topilmaganda majburiy yangilash 5 daqiqada bir
 *     martadan ko'p bo'lmaydi. Aks holda noto'g'ri `kid` bilan yuborilgan
 *     tokenlar oqimi IdP'ga DDoS bo'lib qaytardi (amplifikatsiya).
 */
final class CachedJwksProvider implements JwksProvider
{
    private const string KEYS_CACHE = 'raso:jwks:keys';

    private const string REFRESH_LOCK = 'raso:jwks:refresh_lock';

    /**
     * So'rov ichidagi memoizatsiya — bitta so'rovda kesh drayveriga bir necha
     * marta borilmasin. Octane'da worker tirik qolgani uchun bu ATAYLAB
     * so'rovdan keyin saqlanmaydi: provider har so'rovda yangi quriladi.
     *
     * @var array<string, Key>|null
     */
    private ?array $memo = null;

    public function __construct(
        private readonly Cache $cache,
        private readonly JwksFetcher $fetcher,
        private readonly string $jwksUri,
        private readonly int $ttlSeconds = 86400,
        private readonly int $refreshCooldownSeconds = 300,
    ) {}

    public function keys(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = self::parse($this->cachedOrFetch());
    }

    public function refresh(): array
    {
        // Sovish davri ichida bo'lsak — IdP'ga bormaymiz va bor kalitni
        // qaytaramiz. Chaqiruvchi (TokenVerifier) `kid` topilmasa tokenni
        // rad etadi; bu to'g'ri, chunki qalbaki `kid` aynan shunday ko'rinadi.
        if ($this->cache->get(self::REFRESH_LOCK) !== null) {
            return $this->memo ?? self::parse($this->cachedOrFetch());
        }

        $this->cache->put(self::REFRESH_LOCK, true, $this->refreshCooldownSeconds);

        return $this->memo = self::parse($this->fetchAndStore());
    }

    /**
     * Keshdagi hujjat SHAKLI tekshiriladi: kesh drayveri (Redis) tashqi
     * tizim — u qaytargan narsa kutilgan shaklda deb ishonilmaydi.
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    private function cachedOrFetch(): array
    {
        /** @var mixed $cached */
        $cached = $this->cache->get(self::KEYS_CACHE);

        if (is_array($cached) && isset($cached['keys']) && is_array($cached['keys'])) {
            /** @var array{keys: list<array<string, mixed>>} $cached */
            return $cached;
        }

        return $this->fetchAndStore();
    }

    /** @return array{keys: list<array<string, mixed>>} */
    private function fetchAndStore(): array
    {
        $document = $this->fetcher->fetch($this->jwksUri);

        $this->cache->put(self::KEYS_CACHE, $document, $this->ttlSeconds);

        return $document;
    }

    /**
     * @param  array{keys: list<array<string, mixed>>}  $document
     * @return array<string, Key>
     */
    private static function parse(array $document): array
    {
        try {
            /** @var array<string, Key> $keys */
            $keys = JWK::parseKeySet($document, 'RS256');
        } catch (\Throwable $e) {
            throw JwksUnavailable::malformed('(keshdagi hujjat)');
        }

        return $keys;
    }
}
