<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB; // <-- важно
use App\Support\Kanban;
use App\Models\KanbanOrderColumn;
use Illuminate\Http\Request;

class KanbanOrderColumnController extends Controller
{
    protected function findByCodeOrFail($owner, string $code)
    {
        return $owner->kanbanColumns()->where('code', $code)->firstOrFail();
    }

    // POST /kanban/columns
    public function store(Request $request)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $table = (new KanbanOrderColumn)->getTable(); // 'kanban_order_columns'

        $data = $request->validate([
            'code' => [
                'required','string','max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                Rule::unique($table, 'code')->where(fn ($q) => $q
                    ->where('ownerable_type', get_class($owner))
                    ->where('ownerable_id', $owner->getKey())
                    ->whereNull('deleted_at')
                ),
                function ($attr, $value, $fail) {
                    if (strtolower($value) === 'new') {
                        $fail('The code "new" is reserved.');
                    }
                },
            ],
            'name' => 'required|string|max:100',
            'desc' => 'nullable|string',
            'hex'  => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Нормализация
        $data['code'] = trim($data['code']);
        $data['name'] = trim($data['name']);
        $data['desc'] = isset($data['desc']) ? trim($data['desc']) : null;
        $data['hex']  = strtoupper($data['hex']);

        // следующая позиция (после всех существующих)
        $pos = (int) $owner->kanbanColumns()->max('position') + 1;

        $col = $owner->kanbanColumns()->create([
            ...$data,
            'position'  => $pos,
            'is_system' => false,
        ]);

        return response()->json([
            'column' => [
                'id'   => $col->code,
                'name' => $col->name,
                'desc' => $col->desc,
                'hex'  => $col->hex,
            ],
        ], 201);
    }

    // PUT /kanban/columns/{code}
    public function update(Request $request, string $code)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $col = $this->findByCodeOrFail($owner, $code);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'desc' => 'nullable|string',
            'hex'  => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $data['name'] = trim($data['name']);
        $data['desc'] = isset($data['desc']) ? trim($data['desc']) : null;
        $data['hex']  = strtoupper($data['hex']);

        $col->update($data);

        return response()->noContent();
    }

    // DELETE /kanban/columns/{code}
    public function destroy(string $code)
    {
        $owner = Kanban::owner();
        abort_unless($owner, 403);

        $col = $this->findByCodeOrFail($owner, $code);
        abort_if($col->is_system, 403, 'System column cannot be deleted.');

        $col->delete();

        return response()->noContent();
    }

    /**
     * PUT /kanban/columns/reorder
     * body: { codes: ["in-progress","done", ...] } — только пользовательские, без системных
     */
public function reorder(Request $request)
{
    $owner = Kanban::owner();
    abort_unless($owner, 403);

    $codes = $request->validate([
        'codes'   => 'required|array|min:1',
        'codes.*' => 'string',
    ])['codes'];

    $base = (int) $owner->kanbanColumns()
        ->where('is_system', true)
        ->max('position');   // позиция последней системной, например 1

    $start = $base + 1;

    DB::transaction(function () use ($owner, $codes, $start) {
        // 1) присланные — вперёд, в том порядке
        foreach ($codes as $i => $code) {
            $owner->kanbanColumns()
                ->where('code', $code)
                ->update(['position' => $start + $i]);
        }

        // 2) остальные пользовательские — следом, сохраняя их текущий относительный порядок
        $rest = $owner->kanbanColumns()
            ->where('is_system', false)
            ->whereNotIn('code', $codes)
            ->orderBy('position')
            ->pluck('code');

        $offset = $start + count($codes);
        foreach ($rest as $j => $code) {
            $owner->kanbanColumns()
                ->where('code', $code)
                ->update(['position' => $offset + $j]);
        }
    });

    return response()->noContent();
}

}
