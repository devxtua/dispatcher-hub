<?php

namespace App\Http\Controllers;

use App\Support\Kanban;                 // определяет владельца: User или Shop
use App\Models\KanbanOrderCard;         // локальные карточки (column/position/note)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class KanbanOrderCardController extends Controller
{
    private const POSITION_STEP = 10;

    /** Страница канбана (грузим из Shopify, раскладываем по нашим колонкам) */

    public function index(Request $request)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        // 0) Единый код системной колонки
        $systemCode = 'new';

        // 1) Гарантируем существование системной колонки
        $system = $owner->kanbanColumns()->where('code', $systemCode)->first();
        if (!$system) {
            $legacy = $owner->kanbanColumns()->where('is_system', true)->first();
            if ($legacy) {
                $legacy->update([
                    'code'      => $systemCode,
                    'name'      => $legacy->name ?: 'New Orders',
                    'desc'      => $legacy->desc,
                    'hex'       => $legacy->hex ?: '#0284C7',
                    'position'  => 1,
                    'is_system' => true,
                ]);
            } else {
                $owner->kanbanColumns()->firstOrCreate(
                    ['code' => $systemCode],
                    [
                        'name'      => 'New Orders',
                        'desc'      => 'System column',
                        'hex'       => '#0284C7',
                        'position'  => 1,
                        'is_system' => true,
                    ]
                );
            }
        }

        // 2) Колонки владельца
        $cols = $owner->kanbanColumns()
            ->orderBy('position')
            ->get(['code','name','desc','hex','is_system','position']);

        $existingCodes = $cols->pluck('code')->all();

        // 3) Заказы из Shopify
        $orders = $this->fetchShopifyOrders($owner);

        // ⬇️ добавь — создаст недостающие карточки и заполнит номера
        $this->syncOrdersWithCards($owner, $orders, $systemCode);

        // 4) Локальные карточки по найденным заказам (после синка!)
        $orderIds = $orders->pluck('id')->all();
        $cards = KanbanOrderCard::forOwner($owner)
            ->when($orderIds, fn($q) => $q->whereIn('shop_order_id', $orderIds))
            ->get()
            ->keyBy('shop_order_id');


        // (Фолбэк) если Shopify ничего не вернул (нет прав/ошибка),
        // покажем хотя бы карточки из БД как задачи без "order".
        if ($orders->isEmpty()) {
            $cardsOnly = KanbanOrderCard::forOwner($owner)->get();
            $byColumn = collect();
            foreach ($cardsOnly as $card) {
                $code = in_array($card->column_code, $existingCodes, true) ? $card->column_code : $systemCode;
                $byColumn[$code] ??= collect();
                $byColumn[$code]->push([
                    'id'        => (string) $card->shop_order_id,
                    'name'      => $card->shop_order_number ?: ('#'.$card->shop_order_id),
                    'business'  => '',
                    'note'      => $card->note,
                    'order'     => null, // нет данных из Shopify
                    'pos'       => $card->position ?? 0,
                ]);
            }

            foreach ($byColumn as $code => $items) {
                $byColumn[$code] = $items->sortBy('pos')->values()->map(fn($t) => [
                    'id'       => $t['id'],
                    'name'     => $t['name'],
                    'business' => $t['business'],
                    'note'     => $t['note'],
                    'order'    => $t['order'],
                ]);
            }

            $columns = $cols->map(fn ($c) => [
                'id'    => $c->code,
                'name'  => $c->name,
                'desc'  => $c->desc,
                'hex'   => $c->hex,
                'tasks' => ($byColumn[$c->code] ?? collect())->values()->all(),
            ])->values()->all();

            return Inertia::render('Shopify/Kanban', ['columns' => $columns]);
        }

        // 5) Группировка заказов по колонкам, добавляем полные данные заказа
        $byColumn = collect();

        foreach ($orders as $o) {
            $card = $cards->get($o['id']);
            $code = $card->column_code ?? $systemCode;
            if (!in_array($code, $existingCodes, true)) {
                $code = $systemCode;
            }
            $pos = $card->position ?? 0;

            $byColumn[$code] ??= collect();
            $byColumn[$code]->push([
                'id'        => $o['id'],
                'name'      => $o['name'] ?? ('#'.$o['id']),
                'business'  => $o['customer_name'] ?? '',
                'note'      => $card->note ?? null,
                'order'     => $o['order'], // <-- полный объект заказа
                'pos'       => $pos,
            ]);
        }

        // 6) Сортировка задач внутри колонок
        foreach ($byColumn as $code => $items) {
            $byColumn[$code] = $items->sortBy('pos')->values()->map(fn($t) => [
                'id'       => $t['id'],
                'name'     => $t['name'],
                'business' => $t['business'],
                'note'     => $t['note'],
                'order'    => $t['order'],
            ]);
        }

        // 7) Формируем массив для фронта
        $columns = $cols->map(fn ($c) => [
            'id'    => $c->code,
            'name'  => $c->name,
            'desc'  => $c->desc,
            'hex'   => $c->hex,
            'tasks' => ($byColumn[$c->code] ?? collect())->values()->all(),
        ])->values()->all();

        return Inertia::render('Shopify/Kanban', [
            'columns' => $columns,
        ]);
    }

 

    /**
     * Перенос одной карточки.
     * PUT /kanban/cards/{orderId}/move
     * body:
     *  - column: целевая колонка (code)
     *  - ordered_ids[]: текущий порядок id в целевой колонке (сверху вниз) — предпочтительно
     *  - new_index?: номер позиции, если не присылаем ordered_ids
     *  - shop_order_number?: string (опционально)
     */
    public function move(Request $request, string $orderId)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $validator = Validator::make($request->all(), [
            'column'            => ['required','string','max:64'],
            'ordered_ids'       => ['array'],
            'ordered_ids.*'     => ['string'],
            'new_index'         => ['nullable','integer','min:0'],
            'shop_order_number' => ['nullable','string','max:64'],
        ]);

        if ($validator->fails()) {
            // Log::warning('kanban.move: validation failed', [
            //     'payload' => $request->all(),
            //     'errors'  => $validator->errors()->toArray(),
            // ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        // колонка должна быть у владельца
        if (!$owner->kanbanColumns()->where('code', $data['column'])->exists()) {
            // Log::warning('kanban.move: column not found for owner', [
            //     'column' => $data['column'],
            //     'owner'  => ['type' => get_class($owner), 'id' => (string)$owner->getKey()],
            //     'owner_columns' => $owner->kanbanColumns()->pluck('code'),
            // ]);
            return response()->json(['errors' => ['column' => ['Column does not exist.']]], 422);
        }

        try {
            DB::transaction(function () use ($owner, $data, $orderId) {
                $card = KanbanOrderCard::updateOrCreate(
                    [
                        'ownerable_type' => get_class($owner),
                        'ownerable_id'   => (string) $owner->getKey(),
                        'shop_order_id'  => (string) $orderId,
                    ],
                    [
                        'column_code'       => $data['column'],
                        'shop_order_number' => $data['shop_order_number'] ?? null,
                    ]
                );

                if (isset($data['new_index'])) {
                    $card->position = $data['new_index'] * self::POSITION_STEP;
                    $card->save();
                }

                if (!empty($data['ordered_ids'])) {
                    $this->reindexColumn($owner, $data['column'], $data['ordered_ids']);
                }
            });

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('kanban.move: fail', ['msg' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine()]);
            return response()->json(['ok' => false], 500);
        }
    }

    /**
     * Массовая пересортировка колонки.
     * PUT /kanban/cards/reorder
     * body:
     *  - column: string
     *  - ordered_ids: string[] (id заказов сверху вниз)
     */
    public function reorder(Request $request)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $data = $request->validate([
            'column'        => ['required','string','max:64'],
            'ordered_ids'   => ['required','array','min:1'],
            'ordered_ids.*' => ['string'],
        ]);

        // колонка должна существовать у владельца
        $exists = $owner->kanbanColumns()->where('code', $data['column'])->exists();
        abort_unless($exists, 422, 'Column does not exist.');

        $this->reindexColumn($owner, $data['column'], $data['ordered_ids']);

        return response()->json(['ok' => true]);
    }

    /**
     * Сохранить примечание.
     * PUT /kanban/cards/{orderId}/note
     * body: { note?: string|null }
     */
    public function note(Request $request, string $orderId)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $data = $request->validate([
            'note' => ['nullable','string'],
        ]);

        KanbanOrderCard::updateOrCreate(
            [
                'ownerable_type' => get_class($owner),
                'ownerable_id'   => $owner->getKey(),
                'shop_order_id'  => $orderId,
            ],
            [
                'note' => $data['note'],
            ]
        );

        return response()->noContent();
    }

    /** Плотная переиндексация позиций 0,10,20… внутри заданной колонки */
    private function reindexColumn($owner, string $columnCode, array $orderedIds): void
    {
        // бережно чистим, не отбрасывая "0" (хотя для Shopify это неактуально)
        $ids = array_values(array_unique(array_filter($orderedIds, fn($v) => $v !== null && $v !== '')));
        if (!$ids) return;

        DB::transaction(function () use ($owner, $columnCode, $ids) {
            foreach ($ids as $i => $orderId) {
                KanbanOrderCard::updateOrCreate(
                    [
                        'ownerable_type' => get_class($owner),
                        'ownerable_id'   => $owner->getKey(),
                        'shop_order_id'  => $orderId,
                    ],
                    [
                        'column_code' => $columnCode,
                        'position'    => $i * self::POSITION_STEP,
                    ]
                );
            }
        });
    }

   /**
     * Загрузка заказов из Shopify REST API (полные данные для фронта).
     * Требует права read_orders.
     * Возвращает коллекцию: [
     *   ['id','name','customer_name','order' => <ARRAY ПОЛНОГО ЗАКАЗА>], ...
     * ]
     */
    private function fetchShopifyOrders($shop)
    {
        if (!method_exists($shop, 'api')) {
            return collect();
        }

        // Подтянем побольше полей (в т.ч. line_items, адреса и т.п.)
        // При желании сузьте список.
        $fields = implode(',', [
            'id','name','order_number','email','phone',
            'created_at','updated_at','currency',
            'subtotal_price','total_price','total_tax','total_discounts',
            'financial_status','fulfillment_status',
            'customer','billing_address','shipping_address',
            'shipping_lines','discount_codes','line_items'
        ]);

        $resp = $shop->api()->rest('GET', '/admin/api/2025-07/orders.json', [
            'status' => 'any',
            'limit'  => 100,
            'fields' => $fields,
        ]);

        // osiset: body может быть массив/объект — нормализуем
        $body = $resp['body'] ?? [];
        $orders = is_array($body) ? ($body['orders'] ?? []) : ($body->container['orders'] ?? []);

        return collect($orders)->map(function ($src) {
            // приведение к массиву
            $raw = is_array($src) ? $src : (array) json_decode(json_encode($src), true);

            // безопасно извлечём id (число или GID)
            $idRaw = $raw['id'] ?? null;
            if (!$idRaw && isset($raw['admin_graphql_api_id'])) {
                $idRaw = $raw['admin_graphql_api_id'];
            }
            $id = $this->extractShopifyId($idRaw);

            // имя клиента
            $cust = $raw['customer'] ?? [];
            $first = is_array($cust) ? ($cust['first_name'] ?? '') : '';
            $last  = is_array($cust) ? ($cust['last_name']  ?? '') : '';
            $email = is_array($cust) ? ($cust['email']      ?? '') : '';
            $customerName = trim($first.' '.$last) ?: $email;

            return [
                'id'            => (string) $id,
                'name'          => $raw['name'] ?? ('#'.$id),
                'customer_name' => $customerName,
                'order'         => $raw, // <-- отдадим весь объект заказа во фронт
            ];
        });
    }

    /** Парсит Shopify id: число или GID вида gid://shopify/Order/12345 */
    private function extractShopifyId($value): ?string
    {
        if (is_null($value)) return null;
        if (is_numeric($value)) return (string) $value;
        if (is_string($value) && preg_match('~/Order/(\d+)$~', $value, $m)) {
            return $m[1];
        }
        return (string) $value;
    }

    /**
     * Создаёт карточки для заказов, которых ещё нет в БД (в колонке $systemCode),
     * и проставляет shop_order_number там, где он NULL.
     *
     * @param \Illuminate\Support\Collection $orders  // элементы вида ['id','name','order'=>[...]]
     */
    private function syncOrdersWithCards($owner, $orders, string $systemCode = 'new'): void
    {
        if (!$orders || $orders->isEmpty()) return;

        // список id и карта id => номер
        $ids = $orders->pluck('id')->filter()->map(fn($v) => (string)$v)->values();

        $numbers = [];
        foreach ($orders as $o) {
            $num = $o['order']['order_number'] ?? (isset($o['name']) ? ltrim((string)$o['name'], '#') : null);
            if ($num) $numbers[(string)$o['id']] = (string)$num;
        }

        // какие уже есть
        $existingIds = KanbanOrderCard::forOwner($owner)
            ->whereIn('shop_order_id', $ids)
            ->pluck('shop_order_id')
            ->map(fn($v) => (string)$v)
            ->all();

        $existsSet  = array_flip($existingIds);
        $missingIds = $ids->filter(fn($id) => !isset($existsSet[(string)$id]))->values();

        DB::transaction(function () use ($owner, $systemCode, $missingIds, $numbers) {
            // позиция для новых — в конец системной колонки
            $startPos = (int) KanbanOrderCard::forOwner($owner)
                ->where('column_code', $systemCode)
                ->max('position');

            $pos = $startPos;
            foreach ($missingIds as $id) {
                $pos += self::POSITION_STEP;
                KanbanOrderCard::create([
                    'ownerable_type'     => get_class($owner),
                    'ownerable_id'       => (string) $owner->getKey(),
                    'shop_order_id'      => (string) $id,
                    'shop_order_number'  => $numbers[(string)$id] ?? null,
                    'column_code'        => $systemCode,
                    'position'           => $pos,
                ]);
            }

            // доза-полняем номер там, где он NULL
            if (!empty($numbers)) {
                KanbanOrderCard::forOwner($owner)
                    ->whereIn('shop_order_id', array_keys($numbers))
                    ->whereNull('shop_order_number')
                    ->chunkById(200, function ($rows) use ($numbers) {
                        foreach ($rows as $row) {
                            $id = (string) $row->shop_order_id;
                            if (isset($numbers[$id])) {
                                $row->update(['shop_order_number' => $numbers[$id]]);
                            }
                        }
                    });
            }
        });
    }

}
