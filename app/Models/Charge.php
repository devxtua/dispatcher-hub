<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'charge_id',
        'test',
        'status',
        'name',
        'terms',
        'type',
        'price',
        'capped_amount',
        'trial_days',
        'billing_on',
        'activated_on',
        'trial_ends_on',
        'cancelled_on',
        'expires_on',
        'plan_id',
        'description',
        'reference_charge',
        'shop_id',
    ];

    protected $casts = [
        'test'          => 'boolean',
        'price'         => 'decimal:2',
        'capped_amount' => 'decimal:2',
        'billing_on'    => 'datetime',
        'activated_on'  => 'datetime',
        'trial_ends_on' => 'datetime',
        'cancelled_on'  => 'datetime',
        'expires_on'    => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
