<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Testing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Raso\ModuleKit\Auth\Http\AuthenticateMiddleware;
use Raso\ModuleKit\Auth\Http\ScopeMiddleware;
use Raso\ModuleKit\Auth\JwksProvider;
use Raso\ModuleKit\Auth\TokenVerifier;
use Raso\ModuleKit\Events\EventPublisher;
use Raso\ModuleKit\Events\TransactionRunner;
use RuntimeException;
use Throwable;

/**
 * ⚠️ HAR MODUL UCHUN MAJBURIY KONTRAKT TESTI — «modul kit'ga to'g'ri ulanganmi».
 *
 * Modul o'z test to'plamida shuni chaqiradi:
 *
 * ```php
 * it('modul kit ga to\'g\'ri ulangan', function (): void {
 *     ModuleWiringContract::assertWired(
 *         app: $this->app,
 *         scope: 'chat:read',
 *         consumerGroup: 'chat',
 *     );
 * });
 * ```
 *
 * NEGA kit'da: bu tekshiruvlar har modulga HARFMA-HARF nusxa ko'chirilgan edi
 * (chat, calendar, preline-crm — uchta nusxa, farqi faqat scope satrida).
 * Ya'ni kit bog'lanishini yoki middleware taxallusini o'zgartirish uchta
 * repoda tahrir talab qilardi, va yangilashni unutgan modul YASHIL bo'lib
 * turaverardi — sim-ulash esa buzilgan. Endi kutilma kit'da: bu yerni
 * o'zgartirish hamma modulning kutilmasini bir vaqtda yangilaydi.
 *
 * NEGA abstrakt Pest testi emas, static metod: `UserDeletionContract` bilan
 * bir naqsh. Pest'da meros olinadigan test fayli parallel rejimda kutilmagan
 * holatlarga olib keladi, va modul o'z scope'ini faqat o'zi biladi.
 *
 * ⚠️ Bu «bo'sh» smoke test emas. Bog'lanish sim-ulash xatosi tufayli uzilib
 * qolsa, modul ishga tushadi, marshrutlar javob beradi — va faqat birinchi
 * HAQIQIY so'rovda 500 chiqadi.
 */
final class ModuleWiringContract
{
    /**
     * Kit'ning O'ZI bog'laydigan portlar. Modul bularni bog'lamaydi —
     * ular buzilgan bo'lsa, aybdor kit yoki modulning provider ro'yxati.
     *
     * ⚠️ Ro'yxat SHU YERDA o'sadi. Kit yangi bog'lanish qo'shsa, uni bu
     * yerga qo'shish kifoya: hamma modul keyingi `composer update` da
     * yangi kutilmani avtomatik oladi.
     *
     * @var list<class-string>
     */
    private const array BINDINGS = [
        TokenVerifier::class,
        JwksProvider::class,
        EventPublisher::class,
        TransactionRunner::class,
    ];

    /** Kit `boot()` da ro'yxatga qo'yadigan salomatlik manzili (MP-31). */
    private const string HEALTH_URI = 'health';

    /**
     * `config/module-kit.php` dagi SUKUT group nomi. Modul uni almashtirmasa,
     * ikki modul bitta group bilan o'qiydi.
     */
    private const string DEFAULT_CONSUMER_GROUP = 'raso-module';

    /**
     * @param  Container  $app  modulning konteyneri (Pest'da `$this->app`)
     * @param  string  $scope  modulning O'Z scope'i, masalan `chat:read`
     * @param  string  $consumerGroup  modulning O'Z consumer group'i — har modulda BOSHQACHA
     * @param  string  $issuer  IdP identifikatori; token `iss` i shunga aynan teng bo'lishi shart
     * @param  string|null  $audience  modulning `aud` i; `null` — faqat «bo'sh emas» tekshiriladi
     * @param  class-string  $authenticateMiddleware  `raso.auth` taxallusi ostida kutilgan klass
     *                                                (mustaqil rejimdagi modul o'z drayverini beradi)
     *
     * @throws RuntimeException sim-ulashda nosozlik bo'lsa
     */
    public static function assertWired(
        Container $app,
        string $scope,
        string $consumerGroup,
        string $issuer = 'https://api.raso.uz',
        ?string $audience = null,
        string $authenticateMiddleware = AuthenticateMiddleware::class,
    ): void {
        $router = $app->make(Router::class);

        self::assertBindings($app);
        self::assertMiddlewareAliases($router, $authenticateMiddleware);
        self::assertConfig($app->make(Config::class), $issuer, $audience, $consumerGroup);
        self::assertHealthRouteIsOpen($router);
        self::assertVerifiesTokensOffline($scope);
    }

    private static function assertBindings(Container $app): void
    {
        foreach (self::BINDINGS as $abstract) {
            try {
                $app->make($abstract);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Sim-ulash UZILGAN: `{$abstract}` konteynerdan olinmadi ({$e->getMessage()}). ".
                    "Modul `ModuleKitServiceProvider` ni yuklaganini va `.env` to'ldirilganini tekshiring.",
                    previous: $e,
                );
            }
        }
    }

    /**
     * @param  class-string  $authenticateMiddleware
     */
    private static function assertMiddlewareAliases(Router $router, string $authenticateMiddleware): void
    {
        /** @var array<string, mixed> $aliases */
        $aliases = $router->getMiddleware();

        $expected = [
            'raso.auth' => $authenticateMiddleware,
            'raso.scope' => ScopeMiddleware::class,
        ];

        foreach ($expected as $alias => $class) {
            if (($aliases[$alias] ?? null) !== $class) {
                throw new RuntimeException(
                    "`{$alias}` taxallusi `{$class}` ga ulanmagan. ".
                    "Marshrutda `->middleware(['raso.auth'])` yozilgani bilan HECH NARSA ".
                    "tekshirilmasdi — ya'ni endpoint jimgina OCHIQ qolardi.",
                );
            }
        }
    }

    private static function assertConfig(
        Config $config,
        string $issuer,
        ?string $audience,
        string $consumerGroup,
    ): void {
        // Bo'sh `issuer` yoki `audience` — token HAR DOIM rad etiladi va sabab
        // «imzo noto'g'ri» kabi ko'rinadi, ya'ni xato butunlay boshqa joyda
        // izlanadi.
        if ($config->get('module-kit.issuer') !== $issuer) {
            throw new RuntimeException(
                "`module-kit.issuer` `{$issuer}` emas. Token `iss` i bilan AYNAN solishtiriladi.",
            );
        }

        $actualAudience = $config->get('module-kit.audience');

        if ($audience !== null && $actualAudience !== $audience) {
            throw new RuntimeException("`module-kit.audience` `{$audience}` emas.");
        }

        if ($audience === null && (! is_string($actualAudience) || $actualAudience === '')) {
            throw new RuntimeException(
                '`module-kit.audience` bo\'sh (`RASO_MODULE_KEY`). Bo\'sh `aud` — HAMMA token rad etiladi.',
            );
        }

        $jwksUri = $config->get('module-kit.jwks_uri');

        if (! is_string($jwksUri) || $jwksUri === '') {
            throw new RuntimeException('`module-kit.jwks_uri` bo\'sh — kalitlar hech qachon olinmaydi.');
        }

        self::assertConsumerGroup($config, $consumerGroup);
    }

    /**
     * ⚠️ Bu tekshiruvning butun mohiyati shu.
     *
     * Hamma modul bitta `raso.events` stream'ini o'qiydi. Group nomi bir xil
     * bo'lsa, Redis xabarni ular ORASIDA BO'LADI: bittasi oladi, ikkinchisi
     * UMUMAN ko'rmaydi. `identity.user_deleted` uchun bu «bir modul o'chirdi,
     * ikkinchisi saqlab qoldi» degani — ya'ni «meni unut» yolg'onchi bo'lardi
     * va HECH QANDAY XATO CHIQMASDI.
     *
     * `.env` nusxa ko'chirilganda aynan shu ustun unutiladi.
     */
    private static function assertConsumerGroup(Config $config, string $expected): void
    {
        if ($expected === self::DEFAULT_CONSUMER_GROUP || $expected === '') {
            throw new RuntimeException(
                'Consumer group sifatida kit sukutini (`'.self::DEFAULT_CONSUMER_GROUP.'`) bermang: '.
                'u har modulda BOSHQACHA bo\'lishi shart, aks holda modullar bir-birining xabarini yeydi.',
            );
        }

        $actual = $config->get('module-kit.events.group');

        if ($actual !== $expected) {
            throw new RuntimeException(
                "`module-kit.events.group` `{$expected}` emas (`RASO_EVENT_GROUP`). ".
                'Ikki modul bir xil group bilan o\'qisa, xabar ular ORASIDA bo\'linadi.',
            );
        }
    }

    /**
     * `/health` — platforma modulni shu manzil orqali kuzatadi (`module.json`
     * dagi `health_url`). U yo'q bo'lsa yoki auth ortiga yashirinsa, uptime
     * tekshiruvi tokensiz keladi va SOG'LOM modul ham «o'lik» ko'rinadi.
     */
    private static function assertHealthRouteIsOpen(Router $router): void
    {
        $health = null;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->uri() === self::HEALTH_URI && in_array('GET', $route->methods(), true)) {
                $health = $route;

                break;
            }
        }

        if (! $health instanceof Route) {
            throw new RuntimeException(
                '`GET /health` marshruti ro\'yxatda yo\'q. Kit uni o\'zi qo\'yadi — demak provider '.
                'yuklanmagan yoki modul marshrutni ustidan yozgan.',
            );
        }

        if (in_array('raso.auth', $health->middleware(), true)) {
            throw new RuntimeException(
                '`GET /health` `raso.auth` ortida turibdi. Uptime tekshiruvi token bilan kelmaydi: '.
                'monitoring modulni doim «o\'lik» deb ko\'radi.',
            );
        }
    }

    /**
     * Modul testlari core ISHLAMAYOTGANDA ham o'tishi kerak: `AuthKit` soxta
     * IdP beradi va tarmoqqa umuman chiqilmaydi.
     *
     * Shu yerda scope qoidasi ham qayd etiladi: token FAQAT o'zida yozilgan
     * scope'ni ochadi — prefiks, wildcard va ierarxiya YO'Q.
     */
    private static function assertVerifiesTokensOffline(string $scope): void
    {
        if ($scope === '') {
            throw new RuntimeException('Kontrakt testi ishonchsiz: modul o\'z scope\'ini bermadi.');
        }

        $kit = AuthKit::make();
        $user = $kit->verifier->verify($kit->issuer->issue(['scope' => $scope]));

        if (! $user->can($scope)) {
            throw new RuntimeException(
                "Token `{$scope}` bilan berildi, lekin `can('{$scope}')` `false` qaytardi. ".
                'Kit\'ning scope o\'qishi buzilgan.',
            );
        }

        $prefix = strstr($scope, ':', before_needle: true);

        if ($prefix === false) {
            return;
        }

        foreach ([$prefix, "{$prefix}:*"] as $wider) {
            if ($user->can($wider)) {
                throw new RuntimeException(
                    "SCOPE JIMGINA KENGAYDI: `{$scope}` tokeni `{$wider}` ni ham ochdi. ".
                    'Prefiks/wildcard mos kelishi — ruxsatni sezilmasdan kengaytiradigan teshik.',
                );
            }
        }
    }
}
