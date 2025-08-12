<?php

namespace App\Http\Controllers;

use App\Support\Kanban;                 // определяет владельца: User или Shop
use App\Models\KanbanOrderCard;         // локальные карточки (column/position/note)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KanbanOrderCardController extends Controller
{
    private const POSITION_STEP = 10;

    /** Страница канбана (грузим из Shopify, раскладываем по нашим колонкам) */
    public function index(Request $request)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        // 0) Единый код для системной колонки
        $systemCode = 'new';

        // 1) Гарантируем существование системной колонки с code = 'new'
        $system = $owner->kanbanColumns()->where('code', $systemCode)->first();

        if (!$system) {
            // если есть любая системная — переименуем её в 'new'
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

        // 2) Колонки владельца (отсортированы по position)
        $cols = $owner->kanbanColumns()
            ->orderBy('position')
            ->get(['code','name','desc','hex','is_system','position']);

        // 3) Заказы
        $orders = $this->fetchShopifyOrders($owner);

        // 4) Карточки
        $cards = KanbanOrderCard::forOwner($owner)
            ->whereIn('shop_order_id', $orders->pluck('id'))
            ->get()
            ->keyBy('shop_order_id');

        // 5) Группируем по колонкам с дефолтом на системную
        $existingCodes = $cols->pluck('code')->all();
        $byColumn = collect();

        foreach ($orders as $o) {
            $card = $cards->get($o['id']);
            $code = $card->column_code ?? $systemCode;
            if (!in_array($code, $existingCodes, true)) {
                $code = $systemCode;
            }
            $pos  = $card->position ?? 0;

            $byColumn[$code] ??= collect();
            $byColumn[$code]->push([
                'id'       => $o['id'],
                'name'     => $o['name'] ?? ('#'.$o['id']),
                'business' => $o['customer_name'] ?? '',
                'pos'      => $pos,
            ]);
        }

        // сортировка задач внутри колонок по pos
        foreach ($byColumn as $code => $items) {
            $byColumn[$code] = $items->sortBy('pos')->values()->map(fn($t) => [
                'id'       => $t['id'],
                'name'     => $t['name'],
                'business' => $t['business'],
            ]);
        }

        // 6) Формируем массив для фронта (tasks -> array)
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

        $data = $request->validate([
            'column'            => ['required','string','max:64'],
            'ordered_ids'       => ['array'],
            'ordered_ids.*'     => ['string'], // Shopify id может быть большим → держим строкой
            'new_index'         => ['nullable','integer','min:0'],
            'shop_order_number' => ['nullable','string','max:64'],
        ]);

        // проверим, что колонка реально есть у владельца
        $exists = $owner->kanbanColumns()->where('code', $data['column'])->exists();
        abort_unless($exists, 422, 'Column does not exist.');

        DB::transaction(function () use ($owner, $data, $orderId) {
            // upsert карточки
            $card = KanbanOrderCard::updateOrCreate(
                [
                    'ownerable_type' => get_class($owner),
                    'ownerable_id'   => $owner->getKey(),
                    'shop_order_id'  => $orderId,
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
     * Загрузка заказов из Shopify REST API (osiset/laravel-shopify).
     * Возвращает коллекцию: [['id','name','customer_name'], ...]
     */
    private function fetchShopifyOrders($shop)
    {
        if (!method_exists($shop, 'api')) {
            return collect();
        }

        $resp = $shop->api()->rest('GET', '/admin/api/2024-10/orders.json', [
            'status' => 'any',
            'limit'  => 100,
            'fields' => 'id,name,customer',
        ]);

        // osiset может вернуть body как массив/объект — нормализуем
        $body = $resp['body'] ?? [];
        $orders = is_array($body) ? ($body['orders'] ?? []) : ($body->container['orders'] ?? []);

        return collect($orders)->map(function ($o) {
            $id = $this->extractShopifyId($o['id'] ?? ($o->id ?? null)); // numeric id или GID
            $name = $o['name'] ?? ($o->name ?? null);

            $customer = $o['customer'] ?? ($o->customer ?? null);
            $first = is_array($customer) ? ($customer['first_name'] ?? null) : ($customer->first_name ?? null);
            $last  = is_array($customer) ? ($customer['last_name']  ?? null) : ($customer->last_name  ?? null);
            $email = is_array($customer) ? ($customer['email']      ?? null) : ($customer->email      ?? null);

            $customerName = trim(($first ?? '').' '.($last ?? '')) ?: ($email ?? '');

            return [
                'id'            => (string) $id, // держим строкой
                'name'          => $name,
                'customer_name' => $customerName,
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
}
