<?php

declare(strict_types=1);

namespace Raso\ModuleKit\Auth\Http;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Raso\ModuleKit\Auth\RasoUserIdentity;
use Symfony\Component\HttpFoundation\Response;

/**
 * `raso.scope:chat:write` — token kerakli scope'ga egaligini tekshiradi.
 *
 * Bir nechta scope berilsa — HAMMASI kerak (VA, YOKI emas). Sabab: «yoki»
 * semantikasi ruxsatni kutilganidan keng qiladi va buni route'ga qarab
 * payqash qiyin.
 *
 * ⚠️ `raso.auth` dan KEYIN turishi shart. Foydalanuvchi bo'lmasa 401
 * qaytaradi (403 emas) — «kim ekanligingni bilmayapman» bilan «senga
 * ruxsat yo'q» boshqa-boshqa javob.
 */
final readonly class ScopeMiddleware
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        if (! $user instanceof RasoUserIdentity) {
            // 401 javob `raso.auth` niki bilan AYNAN bir xil bo'lishi shart:
            // klient «token yubormadim» va «scope yetmadi» ni farqlay olishi
            // uchun shakl emas, STATUS gapiradi (401 va 403).
            return BearerScheme::unauthorized();
        }

        if (! $user->rasoUser->scopes->hasAll(array_values($scopes))) {
            return new JsonResponse(
                [
                    'message' => "Bu amal uchun ruxsat yo'q.",
                    'required_scope' => implode(' ', $scopes),
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
