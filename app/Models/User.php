<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'address',
        'city',
        'phone',
        'national_id',
        'user_photo',
        'role',
        'status',
        'post_code',
        'wallet_balance',
        'free_sales_remaining' 

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
         'wallet_balance' => 'decimal:2',
    ];

    /**
     * Relationships
     */


    public function products()
    {
        return $this->hasMany(Product::class, 'owner_id');
    }


    public function rentals()
    {
        return $this->hasMany(Rental::class, 'renter_id');
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


    public function wishlist()
    {
        return $this->belongsToMany(Product::class, 'wishlist', 'user_id', 'product_id')
                    ->withTimestamps();
    }

 
    public function logs()
    {
        return $this->hasMany(Log::class);
    }


    public function recommendations()
    {
        return $this->hasMany(AiRecommendation::class);
    }

  
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }



        // العلاقات الجديدة للمحفظة والإشعارات
    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Accessors & Mutators
     */

   
    public function getUserPhotoAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

   
    public function isAdmin()
    {
        return $this->role === 'admin';
    }



  
    public function isSeller()
    {
        return $this->role === 'seller';
    }

   
    public function isActive()
    {
        return $this->status === '1';
    }

    /**
     * Scopes
     */


    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }


    public function scopeSellers($query)
    {
        return $query->where('role', 'user');
    }

   
    public function scopeInCity($query, $city)
    {
        return $query->where('city', $city);
    }
}