<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Raso\ModuleKit\Auth\CachedJwksProvider;
use Raso\ModuleKit\Auth\Http\AuthenticateMiddleware;
use Raso\ModuleKit\Auth\Http\ScopeMiddleware;
use Raso\ModuleKit\Auth\HttpJwksFetcher;
use Raso\ModuleKit\Auth\JwksFetcher;
use Raso\ModuleKit\Auth\JwksProvider;
use Raso\ModuleKit\Auth\TokenVerifier;
use Raso\ModuleKit\Events\EventPublisher;
use Raso\ModuleKit\Events\TransactionRunner;
use Raso\ModuleKit\Laravel\Console\ConsumeEventsCommand;
use Raso\ModuleKit\Laravel\Console\DoctorCommand;
use Raso\ModuleKit\Laravel\Console\PurgeUserCommand;
use Raso\ModuleKit\Laravel\Console\RelayOutboxCommand;

/**
 * Modul-servisga auth'ni ulaydi. Modul faqat `.env` ni to'ldiradi:
 *
 *   RASO_ISSUER=https://api.raso.uz
 *   RASO_MODULE_KEY=chat            # token `aud` i
 *   RASO_JWKS_URI=https://api.raso.uz/oauth/jwks
 *
 * Middleware alias'lari: `raso.auth` va `raso.scope`.
 */
final class ModuleKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/module-kit.php', 'module-kit');

        $this->app->singleton(JwksFetcher::class, fn ($app): HttpJwksFetcher => new HttpJwksFetcher(
            $app->make(Http::class),
            (int) $this->config()->get('module-kit.jwks_timeout', 3),
        ));

        // ⚠️ `singleton` EMAS — `bind`: JWKS memoizatsiyasi so'rov ichida
        // qolsin, Octane'da worker umri bo'yi EMAS. Aks holda kalit
        // rotatsiyasidan keyin worker qayta ishga tushmaguncha eski kalit
        // ishlatilardi.
        $this->app->bind(JwksProvider::class, fn ($app): CachedJwksProvider => new CachedJwksProvider(
            $app->make(CacheFactory::class)->store(),
            $app->make(JwksFetcher::class),
            (string) $this->config()->get('module-kit.jwks_uri'),
            (int) $this->config()->get('module-kit.jwks_ttl', 86400),
            (int) $this->config()->get('module-kit.jwks_refresh_cooldown', 300),
        ));

        $this->app->bind(TokenVerifier::class, fn ($app): TokenVerifier => new TokenVerifier(
            $app->make(JwksProvider::class),
            (string) $this->config()->get('module-kit.issuer'),
            (string) $this->config()->get('module-kit.audience'),
            (int) $this->config()->get('module-kit.leeway', 30),
        ));

        $this->app->bind(EventPublisher::class, fn ($app): RedisStreamPublisher => new RedisStreamPublisher(
            $app->make(Redis::class),
            (string) $this->config()->get('module-kit.events.stream', 'raso.events'),
            (string) $this->config()->get('module-kit.events.redis_connection', 'default'),
            (int) $this->config()->get('module-kit.events.max_length', 100_000),
        ));

        /*
         * Stream o'quvchisi. `consumer` nomi HOST nomidan olinadi —
         * bir necha worker bir vaqtda ishlaganda ular bir-birining
         * xabarini tortib olmasin va pending ro'yxati aralashmasin.
         */
        $this->app->bind(RedisStreamReader::class, fn ($app): RedisStreamReader => new RedisStreamReader(
            $app->make(Redis::class),
            (string) $this->config()->get('module-kit.events.stream', 'raso.events'),
            // Group — MODUL nomi: har modul o'z nusxasini oladi.
            (string) $this->config()->get('module-kit.events.group', 'raso-module'),
            (string) (gethostname() ?: 'worker'),
            (string) $this->config()->get('module-kit.events.redis_connection', 'default'),
        ));

        $this->app->bind(TransactionRunner::class, fn ($app): IlluminateTransactionRunner => new IlluminateTransactionRunner(
            $app->make(ConnectionResolverInterface::class),
        ));

        /*
         * ⚠️ `ConsumedEvents`, `DeadLetters`, `OutboxStore` va
         * `UserDeletedListener` ATAYLAB bog'lanmagan — ular modulning O'Z
         * jadvallariga tayanadi. Modul ularni o'zi bog'laydi; bog'lamasa
         * `raso:module:doctor` yiqiladi va CI qizil bo'ladi.
         */
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('raso.auth', AuthenticateMiddleware::class);
        $router->aliasMiddleware('raso.scope', ScopeMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeUserCommand::class,
                DoctorCommand::class,
                ConsumeEventsCommand::class,
                RelayOutboxCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../../config/module-kit.php' => $this->app->configPath('module-kit.php'),
        ], 'module-kit-config');
    }

    private function config(): Config
    {
        return $this->app->make(Config::class);
    }
}
