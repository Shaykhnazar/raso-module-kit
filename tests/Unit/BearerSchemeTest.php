<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Raso\ModuleKit\Auth\Http\AuthenticateMiddleware;
use Raso\ModuleKit\Auth\Http\BearerScheme;
use Raso\ModuleKit\Auth\Http\ScopeMiddleware;
use Raso\ModuleKit\Auth\RasoUserIdentity;
use Raso\ModuleKit\Domain\RasoUser;
use Raso\ModuleKit\Testing\AuthKit;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Modulning IKKINCHI auth drayveri — JWT'siz. `preline-crm` mustaqil rejimi
 * aynan shunday: token bazadagi opaque satr, tekshiruv `SELECT`.
 *
 * ⚠️ Shu klass — kontrakt testining o'zi: `BearerScheme` JWT'ni bilmasligi
 * SHART, aks holda mana bunday drayver undan foydalana olmasdi va sarlavha
 * o'qish bilan 401 javobini yana nusxa ko'chirardi.
 */
final readonly class KitFakeOpaqueTokenMiddleware
{
    /** @param array<string, RasoUser> $accounts token → foydalanuvchi */
    public function __construct(private array $accounts) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $token = BearerScheme::token($request);

        if ($token === null) {
            return BearerScheme::unauthorized();
        }

        $user = $this->accounts[$token] ?? null;

        if ($user === null) {
            return BearerScheme::unauthorized();
        }

        $identity = new RasoUserIdentity($user);
        $request->setUserResolver(static fn (): RasoUserIdentity => $identity);

        return $next($request);
    }
}

it('`Authorization: Bearer <token>` dan tokenni oladi', function (): void {
    $request = Request::create('/threads');
    $request->headers->set('Authorization', 'Bearer abc.def.ghi');

    expect(BearerScheme::token($request))->toBe('abc.def.ghi');
});

it('token atrofidagi bo\'sh joyni tashlaydi', function (): void {
    $request = Request::create('/threads');
    $request->headers->set('Authorization', 'Bearer   abc   ');

    expect(BearerScheme::token($request))->toBe('abc');
});

it('sarlavha yaroqsiz bo\'lsa `null` qaytaradi', function (string $header): void {
    $request = Request::create('/threads');
    $request->headers->set('Authorization', $header);

    expect(BearerScheme::token($request))->toBeNull();
})->with([
    'Basic aGk6aGk=',
    'Bearer',
    'Bearer   ',
    'token abc',
    // ⚠️ Registr AHAMIYATLI: `bearer` rad etiladi. RFC bo'yicha sxema nomi
    // registrga sezgir emas, lekin buni yumshatish qabul qilinadigan
    // sarlavhalar to'plamini HAMMA modulda bir vaqtda kengaytiradi — bu
    // alohida qaror, nusxa ko'chirishni yig'ish paytida qilinadigan ish emas.
    'bearer abc',
]);

it('sarlavha umuman bo\'lmasa `null` qaytaradi', function (): void {
    expect(BearerScheme::token(Request::create('/threads')))->toBeNull();
});

it('401 javobi `WWW-Authenticate: Bearer` bilan keladi', function (): void {
    $response = BearerScheme::unauthorized();

    expect($response->getStatusCode())->toBe(401)
        ->and($response->headers->get('WWW-Authenticate'))->toBe('Bearer');
});

it('401 javobi rad etish sababini OSHKOR QILMAYDI', function (): void {
    // Tanada faqat `message` — «token yo'q», «muddati o'tdi» va «imzo
    // noto'g'ri» ni ajratish hisoblarni sanashga yo'l ochadi.
    /** @var array<string, mixed> $body */
    $body = json_decode((string) BearerScheme::unauthorized()->getContent(), associative: true);

    expect(array_keys($body))->toBe(['message']);
});

/**
 * ⚠️ Nusxa ko'chirish aynan shu yerda qaytib kelardi.
 *
 * `raso.auth` va `raso.scope` autentifikatsiyasiz so'rovga BIR XIL javob
 * berishi shart. Ilgari 401 tanasi uch joyda alohida yozilgan edi (ikkala
 * middleware va modulning lokal drayveri) — bittasi o'zgarsa klient uchun
 * javob endpointga qarab farq qilardi.
 */
it('`raso.auth` va `raso.scope` bir xil 401 javob beradi', function (): void {
    $kit = AuthKit::make();

    $fromAuth = (new AuthenticateMiddleware($kit->verifier))->handle(
        Request::create('/threads'),
        fn (): Response => new Response('ok'),
    );

    $fromScope = (new ScopeMiddleware)->handle(
        Request::create('/threads'),
        fn (): Response => new Response('ok'),
        'chat:read',
    );

    expect($fromScope->getStatusCode())->toBe($fromAuth->getStatusCode())
        ->and($fromScope->getContent())->toBe($fromAuth->getContent())
        ->and($fromScope->headers->get('WWW-Authenticate'))->toBe($fromAuth->headers->get('WWW-Authenticate'))
        ->and($fromScope->headers->get('Content-Type'))->toBe($fromAuth->headers->get('Content-Type'));
});

it('JWT\'siz drayver ham AYNAN shu 401 ni qaytaradi', function (): void {
    $middleware = new KitFakeOpaqueTokenMiddleware(['maxfiy-token' => fakeRasoUser(['scope' => 'crm:read'])]);

    $fromLocal = $middleware->handle(
        Request::create('/customers'),
        fn (): Response => new Response('ok'),
    );

    $fromKit = (new AuthenticateMiddleware(AuthKit::make()->verifier))->handle(
        Request::create('/threads'),
        fn (): Response => new Response('ok'),
    );

    expect($fromLocal->getStatusCode())->toBe($fromKit->getStatusCode())
        ->and($fromLocal->getContent())->toBe($fromKit->getContent())
        ->and($fromLocal->headers->get('WWW-Authenticate'))->toBe($fromKit->headers->get('WWW-Authenticate'));
});

it('JWT\'siz drayver yaroqli token bilan foydalanuvchini bog\'laydi', function (): void {
    $middleware = new KitFakeOpaqueTokenMiddleware(['maxfiy-token' => fakeRasoUser(['scope' => 'crm:read'])]);

    $request = Request::create('/customers');
    $request->headers->set('Authorization', 'Bearer maxfiy-token');

    $captured = null;
    $response = $middleware->handle($request, function (Request $r) use (&$captured): Response {
        $captured = $r->user();

        return new Response('ok');
    });

    expect($response->getStatusCode())->toBe(200)
        ->and($captured)->toBeInstanceOf(RasoUserIdentity::class)
        ->and($captured?->rasoUser->can('crm:read'))->toBeTrue();
});
