<?php
// app/Models/Review.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Review extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reviews';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'review_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'comment',
    ];

    /**
     *  Relationships
     */


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessors & Mutators
     */

  
    public function getStarRatingAttribute()
    {
        $fullStars = floor($this->rating);
        $halfStar = ($this->rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
        
        return [
            'full' => $fullStars,
            'half' => $halfStar,
            'empty' => $emptyStars,
        ];
    }

    /**
     * Scopes
     */

  
    public function scopeHighRating($query, $min = 4)
    {
        return $query->where('rating', '>=', $min);
    }

   
    public function scopeLowRating($query, $max = 2)
    {
        return $query->where('rating', '<=', $max);
    }

    
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

   
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($review) {
           
            if ($review->rating < 1 || $review->rating > 5) {
                throw ValidationException::withMessages([
                    'rating' =>'The rating should be between 1 and 5'
                ]);
            }
        });

        static::saved(function ($review) {
          
            $product = $review->product;
            $average = $product->reviews()->avg('rating');
            $product->rate = $average;
            $product->save();
        });

        static::deleted(function ($review) {
           
            $product = $review->product;
            $average = $product->reviews()->avg('rating') ?? 0;
            $product->rate = $average;
            $product->save();
        });
    }
}