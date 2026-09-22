<?php

namespace App\Enums;

enum CatalogSource: string
{
    case Categories = 'categories';
    case Brands = 'brands';
    case Products = 'products';

    public function variableName(): string
    {
        return match ($this) {
            self::Categories => 'category_id',
            self::Brands => 'brand_id',
            self::Products => 'product_id',
        };
    }
}
