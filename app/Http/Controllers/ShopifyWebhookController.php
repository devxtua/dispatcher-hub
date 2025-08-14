<?php

namespace App\Http\Controllers;

use App\Models\KanbanOrderCard;
use Illuminate\Http\Request;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Storage\Queries\Shop as ShopQuery;
use Illuminate\Support\Str;

class ShopifyWebhookController extends Controller
{
    public function ordersCreate(Request $request)
    {
        // \Log::info('ordersCreate payload', $request->all());
        
        // $data = $request->all();
        // $pretty = json_encode(
        //     $data,
        //     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        // );
        // \Log::info("ordersCreate payload:\n{$pretty}");



        $shop = $this->resolveShop($request);
        if (!$shop) return response()->noContent();

        $p   = $request->all();
        $id  = $this->extractShopifyId($p['id'] ?? null);
        $num = $p['name'] ?? null;

        if ($id) {
            // кладём в системную колонку new
            KanbanOrderCard::updateOrCreate(
                [
                    'ownerable_type' => get_class($shop),
                    'ownerable_id'   => $shop->getKey(),
                    'shop_order_id'  => (string)$id,
                ],
                [
                    'shop_order_number' => $num,
                    'column_code'       => 'new',
                ]
            );
        }

        return response()->noContent(); // 204
    }

    public function ordersUpdated(Request $request)
    {
        $shop = $this->resolveShop($request);
        if (!$shop) return response()->noContent();

        $p   = $request->all();
        $id  = $this->extractShopifyId($p['id'] ?? null);
        $num = $p['name'] ?? null;

        if ($id && $num) {
            KanbanOrderCard::where([
                'ownerable_type' => get_class($shop),
                'ownerable_id'   => $shop->getKey(),
                'shop_order_id'  => (string)$id,
            ])->update(['shop_order_number' => $num]);
        }

        return response()->noContent();
    }

    private function resolveShop(Request $request)
    {
        $domain = (string)$request->header('X-Shopify-Shop-Domain', '');
        if ($domain === '') return null;

        return app(ShopQuery::class)->getByDomain(new ShopDomain($domain));
    }

    private function extractShopifyId($v): ?string
    {
        if ($v === null) return null;
        if (is_numeric($v)) return (string)$v;
        if (is_string($v) && preg_match('~/Order/(\d+)$~', $v, $m)) return $m[1];
        return (string)$v;
    }
}
