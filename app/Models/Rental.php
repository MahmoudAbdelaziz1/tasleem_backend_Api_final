<?php
// app/Models/Rental.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class Rental extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rentals';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'rental_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'renter_id',
        'start_date',
        'end_date',
        'daily_price',
        'total_days',
        'total_price',
        'payment_method',      
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
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     *  Relationships
     */


    public function product()
    {
        return $this->belongsTo(Product::class);
    }


    public function renter()
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

  
    public function owner()
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
        return $this->hasOne(Payment::class, 'rental_id', 'rental_id');
    }

    /**
     * Accessors & Mutators
     */

    
    public function setEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $value;
        
        if (isset($this->attributes['start_date'])) {
            $start = Carbon::parse($this->attributes['start_date']);
            $end = Carbon::parse($value);
            $this->attributes['total_days'] = $start->diffInDays($end) + 1;
        }
    }

   
    public function setTotalDaysAttribute()
    {
        
    }

    public function getTotalPriceAttribute()
    {
        return $this->daily_price * $this->total_days;
    }

  
    public function isActive()
    {
        return $this->status === 'active' && 
               Carbon::now()->between($this->start_date, $this->end_date);
    }

  
    public function isCompleted()
    {
        return $this->status === 'completed' || 
               ($this->status === 'active' && Carbon::now()->gt($this->end_date));
    }

    /**
     * Scopes
     */

  
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->whereDate('start_date', '<=', Carbon::now())
                     ->whereDate('end_date', '>=', Carbon::now());
    }

   
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed')
                     ->orWhere(function($q) {
                         $q->where('status', 'active')
                           ->whereDate('end_date', '<', Carbon::now());
                     });
    }

   
    public function scopeForRenter($query, $userId)
    {
        return $query->where('renter_id', $userId);
    }


    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

  
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('start_date', '<=', $date)
                     ->whereDate('end_date', '>=', $date);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rental) {
            
            $start = Carbon::parse($rental->start_date);
            $end = Carbon::parse($rental->end_date);
            
            if ($end <= $start) {
                throw ValidationException::withMessages([
                    'end_date' => 'The End Date Must Be After Start Date'
                ]);
            }

          
            $rental->total_days = $start->diffInDays($end) + 1;
            
          
            $rental->total_price = $rental->daily_price * $rental->total_days;

        
            $conflictingRentals = Rental::where('product_id', $rental->product_id)
                ->where('status', '!=', 'cancelled')
                ->where(function($query) use ($start, $end) {
                    $query->whereBetween('start_date', [$start, $end])
                          ->orWhereBetween('end_date', [$start, $end])
                          ->orWhere(function($q) use ($start, $end) {
                              $q->where('start_date', '<=', $start)
                                ->where('end_date', '>=', $end);
                          });
                })
                ->exists();

            if ($conflictingRentals) {
                throw ValidationException::withMessages([
                    'dates' => 'The product Is Not Avilable Now'
                ]);
            }
        });
    }
}