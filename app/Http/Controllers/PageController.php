<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Personalisation;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PageController extends Controller
{
    public function home()
    {
        $settings = Personalisation::first();

        return Inertia::render('Home', [
            'appName' => $settings?->app_name ?? 'Shopify App',
            'appLogo' => $settings?->app_logo
                ? asset('storage/' . $settings->app_logo)
                : null,
        ]);
    }

    
    public function terms()
    {
        return Inertia::render('Terms');
    }


    // установка приложения Shopify
    public function install(Request $request)
    {
        $settings = Personalisation::first();

        // нормализуем shop из query
        $shop = (string) str($request->string('shop')->trim())
            ->when(fn ($s) => $s !== '' && !str($s)->contains('.'), fn ($s) => $s->append('.myshopify.com'))
            ->value();
        if ($shop && !preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i', $shop)) {
            $shop = '';
        }

        // === БЕРЁМ REDIRECT И SCOPES ИЗ КОНФИГА / .ENV ===
        // .env:
        // SHOPIFY_OAUTH_REDIRECT=https://tandooria.com/authenticate
        // SHOPIFY_API_SCOPES=read_orders,write_orders
        // SHOPIFY_PER_USER=true
        $redirect = config('services.shopify.redirect', env('SHOPIFY_OAUTH_REDIRECT', url('/authenticate')));
        // если редирект относительный — делаем абсолютным
        if (!Str::startsWith($redirect, ['http://','https://'])) {
            $redirect = url($redirect);
        }

        $scopes = collect(
            explode(',', (string) config('services.shopify.scopes', env('SHOPIFY_API_SCOPES', 'read_orders')))
        )
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        $perUser = (bool) config('services.shopify.per_user', env('SHOPIFY_PER_USER', true));

        // state для защиты OAuth (и кладём в сессию)
        $state = Str::random(40);
        $request->session()->put('shopify_oauth_state', $state);

        return Inertia::render('Shopify/Install', [
            'shop'     => $shop,
            'appName'  => $settings?->app_name ?? config('app.name'),
            'appLogo'  => $settings?->app_logo ? asset('storage/' . ltrim($settings->app_logo, '/')) : null,
            'apiKey'   => config('shopify-app.api_key'),   // как у вас и было
            'redirect' => $redirect,                       // <-- из .env/конфига
            'scopes'   => $scopes,                         // <-- из .env/конфига
            'perUser'  => $perUser,                        // опционально
            'state'    => $state,                          // state из сессии
        ]);
    }
}
