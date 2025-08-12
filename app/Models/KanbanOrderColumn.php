<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Casts\Attribute;

class KanbanOrderColumn extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $table = 'kanban_order_columns';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ownerable_type','ownerable_id',
        'code','name','desc','hex','position','meta','is_system',
    ];

    protected $casts = [
        'position'  => 'integer',
        'meta'      => 'array',
        'is_system' => 'boolean',
    ];

    public function ownerable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ownerable_type', 'ownerable_id');
    }

    /** Удобный скоуп под владельца */
    public function scopeForOwner($q, $owner)
    {
        return $q->where('ownerable_type', get_class($owner))
                 ->where('ownerable_id', $owner->getKey());
    }

    /** Сортировка по позиции */
    public function scopeOrdered($q)
    {
        return $q->orderBy('position');
    }

    /** Нормализация */
    protected function hex(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? strtoupper($v) : $v
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? trim($v) : $v
        );
    }

    protected function desc(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? trim($v) : $v
        );
    }
}
