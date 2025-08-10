<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'price',
        'capped_amount',
        'terms',
        'trial_days',
        'test',
        'on_install',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'capped_amount' => 'decimal:2',
        'test'          => 'boolean',
        'on_install'    => 'boolean',
    ];

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }
}

