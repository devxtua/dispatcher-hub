<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Base;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Base
{
    protected $except = ['magic/*'];

    // ВАЖНО: сигнатура без тип-хинтов, чтобы совпасть с базовой
    public function handle($request, Closure $next)
    {
        // Поддержим оба варианта: bearerToken() и прямой заголовок
        $authHeader      = $request->header('Authorization', '');
        $hasBearerHeader = is_string($authHeader) && stripos($authHeader, 'Bearer ') === 0;
        $hasBearer       = $hasBearerHeader || (bool) $request->bearerToken();
        $isWrite         = in_array($request->method(), ['POST','PUT','PATCH','DELETE'], true);

        // Shopify-поток: write + Bearer → полностью обходим базовый CSRF
        if ($isWrite && $hasBearer) {
            return $next($request);
        }

        // Обычный веб-поток (куки/CSRF) — как в Laravel по умолчанию
        return parent::handle($request, $next);
    }
}
