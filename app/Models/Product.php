<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'facebook_page_id',
    'product_category_id',
    'product_brand_id',
    'name',
    'sku',
    'price',
    'currency',
    'description',
    'image_url',
    'product_url',
    'in_stock',
    'is_active',
    'sort_order',
])]
class Product extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'in_stock' => true,
        'sort_order' => 0,
        'currency' => 'LYD',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function facebookPage(): BelongsTo
    {
        return $this->belongsTo(FacebookPage::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ProductPost::class);
    }

    public function formattedPrice(): string
    {
        return $this->price === null ? '' : rtrim(rtrim(number_format((float) $this->price, 2, '.', ''), '0'), '.');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
