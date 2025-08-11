<?php

// app/Http/Middleware/EitherUserOrShop.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Osiset\ShopifyApp\Http\Middleware\VerifyShopify;

class EitherUserOrShop
{
    public function __construct(
        private VerifyShopify $verifyShopify,
        private AuthenticateSession $authenticateSession
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // 1) Shopify SPA (App Bridge) — всегда с Bearer
        if ($request->bearerToken()) {
            try {
                return $this->verifyShopify->handle($request, $next);
            } catch (\Throwable $e) {
                // Токен невалиден/протух → фронту надо обновить токен
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
        }

        // 2) Обычный веб-пользователь по сессии
        if (auth('web')->check()) {
            return $this->authenticateSession->handle($request, $next);
        }

        // 3) Неавторизованы:
        //    - если это не Inertia, а чистый API → 401 JSON
        if ($request->expectsJson() && !$request->hasHeader('X-Inertia')) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        //    - Inertia/браузер → редирект на логин (Inertia обработает корректно)
        return redirect()->guest(route('login'));
    }
}
