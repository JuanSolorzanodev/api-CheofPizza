<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'description',
    ];

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'category_size_prices')
            ->withPivot(['price'])
            ->withTimestamps();
    }

    public function categorySizePrices(): HasMany
    {
        return $this->hasMany(CategorySizePrice::class, 'category_id');
    }

    public function pizzas(): HasMany
    {
        return $this->hasMany(Pizza::class, 'category_id');
    }

    public function promotionDetails(): HasMany
    {
        return $this->hasMany(PromotionDetail::class, 'category_id');
    }

    public function saleByCategories(): HasMany
    {
        return $this->hasMany(SaleByCategory::class, 'category_id');
    }
}
