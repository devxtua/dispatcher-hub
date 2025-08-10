<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

class Shop extends Authenticatable implements IShopModel
{
    use HasFactory, SoftDeletes, ShopModel;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'password',
        'plan_id',
        'shopify_grandfathered',
        'shopify_namespace',
        'shopify_freemium',
        'theme_support_level',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'shopify_grandfathered' => 'boolean',
        'shopify_freemium'      => 'boolean',
        'plan_id'               => 'integer',
        'deleted_at'            => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class, 'shop_id');
    }

    // App\Models\Shop
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
