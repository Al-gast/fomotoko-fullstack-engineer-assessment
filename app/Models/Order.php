<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_price',
        'status',
    ];

    protected $casts = [
        'total_price' => 'integer',
    ];

    // Satu order berisi satu atau lebih item.
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}