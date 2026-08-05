<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth\Http;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Raso\ModuleKit\Auth\Exception\TokenRejected;
use Raso\ModuleKit\Auth\RasoUserIdentity;
use Raso\ModuleKit\Auth\TokenVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * `raso.auth` — Bearer token'ni tekshiradi va foydalanuvchini so'rovga bog'laydi.
 *
 * NEGA Laravel guard emas, middleware: modul-servis stateless API. Guard
 * varianti har modulda `config/auth.php` ga yozuv talab qiladi — bu
 * unutiladigan va sozlanmasa **jimgina ochiq qoladigan** qadam. Middleware
 * esa route'da ko'rinib turadi va sukut bo'yicha yopiq.
 *
 * Muvaffaqiyatdan keyin `$request->user()` → `RasoUserIdentity` qaytaradi.
 */
final readonly class AuthenticateMiddleware
{
    public function __construct(private TokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return $this->unauthorized();
        }

        try {
            $user = $this->verifier->verify($token);
        } catch (TokenRejected) {
            // ⚠️ Sabab klientga AYTILMAYDI: «imzo noto'g'ri» bilan «kalit
            // topilmadi» ni ajratish hujumchiga tizim haqida ma'lumot beradi.
            // Sabab `TokenRejected::$reason` da — log uchun.
            return $this->unauthorized();
        }

        $identity = new RasoUserIdentity($user);
        $request->setUserResolver(static fn (): RasoUserIdentity => $identity);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Autentifikatsiya talab qilinadi.'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }
}
