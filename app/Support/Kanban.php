<?php
// app/Support/Kanban.php
namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class Kanban
{
    /**
     * Возвращает текущего владельца канбана: Shop или User (в указанном порядке).
     * Порядок берётся из config('kanban.guards', ['shop','web']).
     */
    public static function owner(): ?Authenticatable
    {
        $guards = config('kanban.guards', ['shop', 'web']);

        foreach ($guards as $guard) {
            $auth = Auth::guard($guard);
            if ($auth->check()) {
                return $auth->user();
            }
        }

        // на всякий случай fallback на дефолтный guard
        return Auth::user();
    }

    /** То же, но с 401, если не найден владелец. */
    public static function ownerOrFail(): Authenticatable
    {
        $owner = self::owner();
        abort_if(!$owner, 401, 'Unauthenticated');
        return $owner;
    }
}
