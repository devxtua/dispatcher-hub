<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Personalisation;
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

        // нормализуем shop
        $shop = (string) str($request->string('shop')->trim())
            ->when(fn ($s) => $s !== '' && !str($s)->contains('.'), fn ($s) => $s->append('.myshopify.com'))
            ->value();
        if ($shop && !preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i', $shop)) {
            $shop = '';
        }

        return Inertia::render('Shopify/Install', [
            'shop'    => $shop,
            'appName' => $settings?->app_name ?? config('app.name'),
            'appLogo' => $settings?->app_logo
                ? asset('storage/' . ltrim($settings->app_logo, '/'))
                : null, 
            'apiKey'  => config('shopify-app.api_key'),
        ]);
    }
}
