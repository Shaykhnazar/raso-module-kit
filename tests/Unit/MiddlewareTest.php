<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Raso\ModuleKit\Auth\Http\AuthenticateMiddleware;
use Raso\ModuleKit\Auth\Http\ScopeMiddleware;
use Raso\ModuleKit\Auth\RasoUserIdentity;
use Raso\ModuleKit\Testing\AuthKit;
use Raso\ModuleKit\Testing\RasoUserFactory;

/*
 * `next` — o'tib ketganini bildiruvchi eng sodda javob. Klosura sifatida
 * har testda qayta yasaladi (test faylida `function` e'lon qilinmaydi).
 */

it('yaroqli token bilan o\'tkazadi va `$request->user()` ni bog\'laydi', function (): void {
    $kit = AuthKit::make();
    $request = Request::create('/threads');
    $request->headers->set('Authorization', 'Bearer '.$kit->issuer->issue(['scope' => 'chat:read']));

    $captured = null;
    $response = (new AuthenticateMiddleware($kit->verifier))->handle(
        $request,
        function (Request $r) use (&$captured): Response {
            $captured = $r->user();

            return new Response('ok');
        },
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($captured)->toBeInstanceOf(RasoUserIdentity::class)
        ->and($captured?->rasoUser->can('chat:read'))->toBeTrue();
});

it('Authorization sarlavhasi yo\'q bo\'lsa 401', function (): void {
    $kit = AuthKit::make();

    $response = (new AuthenticateMiddleware($kit->verifier))->handle(
        Request::create('/threads'),
        fn (): Response => new Response('ok'),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and($response->headers->get('WWW-Authenticate'))->toBe('Bearer');
});

it('`Bearer` bo\'lmagan sxemani rad etadi', function (string $header): void {
    $kit = AuthKit::make();
    $request = Request::create('/threads');
    $request->headers->set('Authorization', $header);

    $response = (new AuthenticateMiddleware($kit->verifier))->handle(
        $request,
        fn (): Response => new Response('ok'),
    );

    expect($response->getStatusCode())->toBe(401);
})->with(['Basic aGk6aGk=', 'Bearer', 'Bearer   ', 'token abc']);

/**
 * ⚠️ FAIL-CLOSED, uchidan-uchiga: IdP yiqilgan bo'lsa ham javob **401**.
 *
 * 500 qaytarish auth muammosini infratuzilma xatosi kabi ko'rsatadi va
 * monitoringda noto'g'ri joyga qaraladi.
 */
it('IdP yiqilgan bo\'lsa 401 qaytaradi, 500 EMAS', function (): void {
    $kit = AuthKit::make();
    $token = $kit->issuer->issue();
    $kit->issuer->unavailable = true;

    $request = Request::create('/threads');
    $request->headers->set('Authorization', "Bearer {$token}");

    $response = (new AuthenticateMiddleware($kit->verifier))->handle(
        $request,
        fn (): Response => new Response('ok'),
    );

    expect($response->getStatusCode())->toBe(401);
});

it('rad etish sababi klientga OSHKOR QILINMAYDI', function (): void {
    $kit = AuthKit::make();
    $request = Request::create('/threads');
    $request->headers->set('Authorization', 'Bearer '.$kit->issuer->issueSignedByStranger());

    $response = (new AuthenticateMiddleware($kit->verifier))->handle(
        $request,
        fn (): Response => new Response('ok'),
    );

    $body = (string) $response->getContent();

    expect($body)->not->toContain('imzo')
        ->and($body)->not->toContain('kid')
        ->and($body)->not->toContain('kalit');
});

it('scope yetarli bo\'lsa o\'tkazadi', function (): void {
    $request = Request::create('/threads');
    $identity = new RasoUserIdentity(RasoUserFactory::make(['scope' => 'chat:read chat:write']));
    $request->setUserResolver(static fn (): RasoUserIdentity => $identity);

    $response = (new ScopeMiddleware)->handle(
        $request,
        fn (): Response => new Response('ok'),
        'chat:write',
    );

    expect($response->getStatusCode())->toBe(200);
});

it('scope yetmasa 403', function (): void {
    $request = Request::create('/threads');
    $identity = new RasoUserIdentity(RasoUserFactory::make(['scope' => 'chat:read']));
    $request->setUserResolver(static fn (): RasoUserIdentity => $identity);

    $response = (new ScopeMiddleware)->handle(
        $request,
        fn (): Response => new Response('ok'),
        'chat:write',
    );

    expect($response->getStatusCode())->toBe(403);
});

it('bir nechta scope berilsa HAMMASI kerak (VA, YOKI emas)', function (): void {
    $request = Request::create('/threads');
    $identity = new RasoUserIdentity(RasoUserFactory::make(['scope' => 'chat:read']));
    $request->setUserResolver(static fn (): RasoUserIdentity => $identity);

    $response = (new ScopeMiddleware)->handle(
        $request,
        fn (): Response => new Response('ok'),
        'chat:read',
        'chat:write',
    );

    expect($response->getStatusCode())->toBe(403);
});

it('foydalanuvchi umuman bo\'lmasa 403 EMAS, 401 qaytaradi', function (): void {
    // «Kim ekanligingni bilmayapman» bilan «senga ruxsat yo'q» boshqa-boshqa javob.
    $response = (new ScopeMiddleware)->handle(
        Request::create('/threads'),
        fn (): Response => new Response('ok'),
        'chat:read',
    );

    expect($response->getStatusCode())->toBe(401);
});
