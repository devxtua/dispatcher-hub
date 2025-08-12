<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanOrderCard extends Model
{
    protected $fillable = [
        'ownerable_type',
        'ownerable_id',
        'shop_order_id',
        'shop_order_number',
        'column_code',
        'position',
        'note',
    ];

    public function scopeForOwner($q, $owner)
    {
        return $q->where('ownerable_type', get_class($owner))
                 ->where('ownerable_id',   $owner->getKey());
    }
}

