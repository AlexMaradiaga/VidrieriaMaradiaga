<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InventoryProductModel extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_products';

    protected $fillable = [
        'category_id',
        'base_unit_id',
        'sku',
        'barcode',
        'name',
        'description',
        'product_type',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'average_cost',
        'last_purchase_cost',
        'sale_price',
        'track_stock',
        'track_lots',
        'track_remnants',
        'allow_negative_stock',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category_id' => 'integer',
            'base_unit_id' => 'integer',
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'average_cost' => 'decimal:4',
            'last_purchase_cost' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'track_stock' => 'boolean',
            'track_lots' => 'boolean',
            'track_remnants' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
