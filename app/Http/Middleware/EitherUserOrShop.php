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
        // logger()->info('shopify headers', $request->headers->all());

        // 1) Если пришёл SPA-токен, пробуем как Shopify СНАЧАЛА
        if ($request->bearerToken()) {
            try {
                return $this->verifyShopify->handle($request, $next);
            } catch (\Throwable $e) {
                // токен битый/протух — идём дальше, вдруг это обычный пользователь
            }
        }

        // 2) Обычный пользователь (web guard + живая сессия)
        if (auth('web')->check()) {
            return $this->authenticateSession->handle($request, $next);
        }

        // 3) Ни того, ни другого → для Shopify (SPA) вернём 401,
        // App Bridge сам обновит токен и повторит запрос;
        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        // для браузера — редирект на логин
        return redirect()->route('login');
    }
}
