<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
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
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('raso.auth', AuthenticateMiddleware::class);
        $router->aliasMiddleware('raso.scope', ScopeMiddleware::class);

        $this->publishes([
            __DIR__.'/../../config/module-kit.php' => $this->app->configPath('module-kit.php'),
        ], 'module-kit-config');
    }

    private function config(): Config
    {
        return $this->app->make(Config::class);
    }
}
