<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'order_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'tasleem_fee',     
        'delivery_fee',     
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tasleem_fee'   => 'decimal:2',   
        'delivery_fee'  => 'decimal:2',     
    ];

    /**
     * Relationships
     */


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // البائع (من خلال المنتج)
    public function seller()
    {
        return $this->hasOneThrough(
            User::class,
            Product::class,
            'id',
            'id',
            'product_id',
            'owner_id'
        );
    }


    public function payment()
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id');
    }


    public function review()
    {
        return $this->hasOne(Review::class, 'order_id', 'order_id');
    }

    /**
     * Accessors & Mutators
     */


    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = $value;
        if (isset($this->attributes['unit_price'])) {
            $this->attributes['total_price'] = $value * $this->attributes['unit_price'];
        }
    }

  
    public function isShippable()
    {
        return $this->status === 'confirmed';
    }


    public function isCompleted()
    {
        return $this->status === 'delivered';
    }

    /**
     * Scopes
     */


    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }


    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }


    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
          
            $order->total_price = $order->quantity * $order->unit_price;
        });

        static::created(function ($order) {
          
            $order->product->increment('pay_count', $order->quantity);
        });

        static::updating(function ($order) {
         
            if ($order->isDirty('status') && $order->status === 'cancelled') {
         
                $order->product->increment('quantity', $order->quantity);
            }
        });
    }
}