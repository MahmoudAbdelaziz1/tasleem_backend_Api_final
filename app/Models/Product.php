<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'owner_id',
        'quantity',
        'view_count',
        'rate',
        'pay_count',
        'addingToCart_count',
        'status',
        'type',
        'is_boosted',         
        'boost_expires_at',    
    
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'rate' => 'decimal:2',
        'is_boosted' => 'boolean',          
        'boost_expires_at' => 'datetime',    
    ];

    /**
     *Relationships
     */


    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

 
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }


    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }


    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

 
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

 
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

   
    public function wishlistedBy()
    {
        return $this->belongsToMany(User::class, 'wishlist', 'product_id', 'user_id')
                    ->withTimestamps();
    }

  
    public function recommendations()
    {
        return $this->hasMany(AiRecommendation::class);
    }






        
    public function isActivelyBoosted()
    {
        return $this->is_boosted 
            && $this->boost_expires_at 
            && $this->boost_expires_at->isFuture();
    }

   
    public function scopeBoostedFirst($query)
    {
        return $query->orderByRaw("CASE WHEN is_boosted=1 AND boost_expires_at>NOW() THEN 0 ELSE 1 END ASC");
    }

    /**
     * Accessors & Mutators
     */

    
    public function getPrimaryImageAttribute()
    {
        $primary = $this->images()->first();
        return $primary ? $primary->image_url : asset('images/default-product.png');
    }

  
    public function isAvailable()
    {
        return $this->status === '1' && $this->quantity > 0;
    }


    public function isForSale()
    {
        return in_array($this->type, ['sale']);
    }

  
    public function isForRent()
    {
        return in_array($this->type, ['rental']);
    }

    /**
     * Scopes
     */

    
    public function scopeAvailable($query)
    {
        return $query->where('status', '1');
    }

  
    public function scopeForSale($query)
    {
        return $query->whereIn('type', ['sale']);
    }

    
    public function scopeForRent($query)
    {
        return $query->whereIn('type', ['rental']);
    }

 
    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }


    public function scopeOwnedBy($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    
    public function scopeTopRated($query)
    {
        return $query->orderBy('rate', 'desc');
    }

   
    public function scopeMostViewed($query)
    {
        return $query->orderBy('view_count', 'desc');
    }

  
    public function scopeBestSelling($query)
    {
        return $query->orderBy('pay_count', 'desc');
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        
        static::retrieved(function ($product) {
            $product->increment('view_count');
        });
    }
}