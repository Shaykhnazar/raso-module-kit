<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Raso\ModuleKit\Auth\Http\AuthenticateMiddleware;
use Raso\ModuleKit\Auth\Http\BearerScheme;
use Raso\ModuleKit\Auth\Http\ScopeMiddleware;
use Raso\ModuleKit\Auth\JwksProvider;
use Raso\ModuleKit\Auth\TokenVerifier;
use Raso\ModuleKit\Events\EventPublisher;
use Raso\ModuleKit\Events\TransactionRunner;
use Raso\ModuleKit\Testing\AuthKit;
use Raso\ModuleKit\Testing\FakeTransactionRunner;
use Raso\ModuleKit\Testing\ModuleWiringContract;
use Raso\ModuleKit\Testing\RecordingPublisher;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konfiguratsiya reestrining eng sodda ko'rinishi.
 *
 * NEGA `Illuminate\Config\Repository` emas: bu paketda Laravel ilovasi YO'Q
 * va `illuminate/config` o'rnatilmagan. Kontrakt esa faqat interfeysga
 * suyanadi — modul haqiqiy reestrni beradi.
 */
final class KitFakeConfig implements ConfigContract
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = []) {}

    /** @param string $key */
    public function has($key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * @param  array<mixed>|string  $key
     * @param  mixed  $default
     */
    public function get($key, $default = null): mixed
    {
        if (! is_string($key)) {
            return $default;
        }

        return Arr::get($this->items, $key, $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param  array<string, mixed>|string  $key
     * @param  mixed  $value
     */
    public function set($key, $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $one => $single) {
            Arr::set($this->items, $one, $single);
        }
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function prepend($key, $value): void
    {
        $items = $this->listAt($key);
        array_unshift($items, $value);
        $this->set($key, $items);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function push($key, $value): void
    {
        $items = $this->listAt($key);
        $items[] = $value;
        $this->set($key, $items);
    }

    /**
     * @param  string  $key
     * @return array<mixed>
     */
    private function listAt($key): array
    {
        $items = $this->get($key, []);

        return is_array($items) ? $items : [];
    }
}

/**
 * Modulning MUSTAQIL rejimdagi drayveri o'rnida turadi: `raso.auth`
 * taxallusi kit'nikidan boshqa klassga ulanadi. Kontrakt buni ta'qiqlamasligi
 * kerak — aks holda ikki drayverli modul kontraktdan foydalana olmasdi.
 */
final readonly class KitFakeLocalDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        return BearerScheme::token($request) === null
            ? BearerScheme::unauthorized()
            : $next($request);
    }
}

/**
 * To'g'ri ulangan modul ilovasining eng kichik nusxasi: kit bog'laydigan
 * portlar, ikki middleware taxallusi, `module-kit` konfigi va `/health`.
 */
final class KitWiringHarness
{
    /** @param class-string $authDriver */
    public static function app(
        bool $withHealthRoute = true,
        bool $healthBehindAuth = false,
        string $authDriver = AuthenticateMiddleware::class,
        string $consumerGroup = 'chat',
    ): Container {
        $app = new Container;
        $kit = AuthKit::make();

        $app->instance(TokenVerifier::class, $kit->verifier);
        $app->instance(JwksProvider::class, $kit->jwks);
        $app->instance(EventPublisher::class, new RecordingPublisher);
        $app->instance(TransactionRunner::class, new FakeTransactionRunner);

        $app->instance(ConfigContract::class, new KitFakeConfig([
            'module-kit' => [
                'issuer' => 'https://api.raso.uz',
                'audience' => '019fd400-0000-7000-8000-0000000000c4',
                'jwks_uri' => 'https://api.raso.uz/oauth/jwks',
                'events' => ['group' => $consumerGroup],
            ],
        ]));

        $router = new Router(new Dispatcher($app), $app);
        $router->aliasMiddleware('raso.auth', $authDriver);
        $router->aliasMiddleware('raso.scope', ScopeMiddleware::class);

        if ($withHealthRoute) {
            $route = $router->get('/health', static fn (): string => 'ok');

            if ($healthBehindAuth) {
                $route->middleware('raso.auth');
            }
        }

        $app->instance(Router::class, $router);

        return $app;
    }

    public static function config(Container $app): KitFakeConfig
    {
        $config = $app->make(ConfigContract::class);

        return $config instanceof KitFakeConfig ? $config : new KitFakeConfig;
    }

    public static function router(Container $app): Router
    {
        return $app->make(Router::class);
    }
}

it('to\'g\'ri ulangan modul kontraktdan o\'tadi', function (): void {
    ModuleWiringContract::assertWired(
        app: KitWiringHarness::app(),
        scope: 'chat:read',
        consumerGroup: 'chat',
    );
})->throwsNoExceptions();

it('mustaqil rejimdagi O\'Z auth drayveri ham qabul qilinadi', function (): void {
    // preline-crm ikki rejimda ishlaydi: `raso.auth` ortida kit'ning
    // middleware'i ham, modulning lokal drayveri ham turishi mumkin.
    ModuleWiringContract::assertWired(
        app: KitWiringHarness::app(authDriver: KitFakeLocalDriver::class, consumerGroup: 'preline-crm'),
        scope: 'preline-crm:read',
        consumerGroup: 'preline-crm',
        authenticateMiddleware: KitFakeLocalDriver::class,
    );
})->throwsNoExceptions();

/**
 * ⚠️ Kontraktning butun mohiyati shu: sim-ulash uzilsa CI QIZIL bo'ladi,
 * prod'dagi birinchi so'rov emas.
 */
it('bog\'lanmagan port kontraktni YIQITADI', function (): void {
    $app = KitWiringHarness::app();
    $app->forgetInstance(EventPublisher::class);

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'Sim-ulash UZILGAN');

it('`raso.auth` boshqa klassga ulangan bo\'lsa yiqiladi', function (): void {
    // Endpoint `raso.auth` bilan himoyalangandek ko'rinib, aslida
    // tekshirilmasdi.
    $app = KitWiringHarness::app(authDriver: ScopeMiddleware::class);

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'raso.auth');

it('`raso.scope` taxallusi yo\'q bo\'lsa yiqiladi', function (): void {
    $app = KitWiringHarness::app();
    KitWiringHarness::router($app)->aliasMiddleware('raso.scope', KitFakeLocalDriver::class);

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'raso.scope');

it('issuer boshqa bo\'lsa yiqiladi', function (): void {
    // Sukut `.env` bilan ishga tushgan modul HAMMA tokenni rad etardi va
    // sabab «imzo noto'g'ri» kabi ko'rinardi.
    $app = KitWiringHarness::app();
    KitWiringHarness::config($app)->set('module-kit.issuer', 'https://localhost');

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'issuer');

it('`aud` bo\'sh bo\'lsa yiqiladi', function (): void {
    $app = KitWiringHarness::app();
    KitWiringHarness::config($app)->set('module-kit.audience', '');

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'audience');

it('kutilgan `aud` berilsa u ham solishtiriladi', function (): void {
    ModuleWiringContract::assertWired(
        app: KitWiringHarness::app(),
        scope: 'chat:read',
        consumerGroup: 'chat',
        audience: 'boshqa-modul-kaliti',
    );
})->throws(RuntimeException::class, 'audience');

it('JWKS manzili bo\'sh bo\'lsa yiqiladi', function (): void {
    $app = KitWiringHarness::app();
    KitWiringHarness::config($app)->set('module-kit.jwks_uri', '');

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'jwks_uri');

/**
 * ⚠️ Eng qimmat tuzoq: ikki modul bitta consumer group bilan o'qisa, xabar
 * ular ORASIDA bo'linadi va `identity.user_deleted` bir modulda bajarilib,
 * ikkinchisida umuman ko'rilmaydi. Hech qanday xato chiqmaydi.
 */
it('consumer group `.env` da qolib ketgan bo\'lsa yiqiladi', function (): void {
    $app = KitWiringHarness::app();
    KitWiringHarness::config($app)->set('module-kit.events.group', 'raso-module');

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'events.group');

it('modul kit sukutini o\'z group\'i deb bersa ham yiqiladi', function (): void {
    // Aks holda test «yashil» bo'lardi va ikki modul baribir to'qnashardi.
    $app = KitWiringHarness::app();
    KitWiringHarness::config($app)->set('module-kit.events.group', 'raso-module');

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'raso-module');
})->throws(RuntimeException::class, 'kit sukutini');

it('`/health` ro\'yxatga qo\'yilmagan bo\'lsa yiqiladi', function (): void {
    $app = KitWiringHarness::app(withHealthRoute: false);

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, '/health');

it('`/health` auth ortiga yashirilgan bo\'lsa yiqiladi', function (): void {
    // Monitoring tokensiz keladi: himoyalangan `/health` — «modul o'lik» degani.
    $app = KitWiringHarness::app(healthBehindAuth: true);

    ModuleWiringContract::assertWired(app: $app, scope: 'chat:read', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'raso.auth');

it('modul scope\'ini bermasa kontrakt o\'zini ishonchsiz deb e\'lon qiladi', function (): void {
    // Aks holda test hech narsa sinamasdan o'tib ketardi.
    ModuleWiringContract::assertWired(app: KitWiringHarness::app(), scope: '', consumerGroup: 'chat');
})->throws(RuntimeException::class, 'ishonchsiz');
