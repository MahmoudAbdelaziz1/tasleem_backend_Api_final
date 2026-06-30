<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'order_id';

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

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'total_price'   => 'decimal:2',
        'tasleem_fee'   => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

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
                $product = $order->product;
                
                if ($product) {
                  
                    $product->increment('quantity', $order->quantity);
                    
                  
                    if ($product->quantity > 0 && $product->status === '0') {
                        $product->update(['status' => '1']);
                    }
                }
            }
        });
    }
}