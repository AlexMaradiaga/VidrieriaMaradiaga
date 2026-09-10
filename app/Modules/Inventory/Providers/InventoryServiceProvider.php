<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Application\Ports\ProductCatalogLookupInterface;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentProductCatalogLookup;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepositoryInterface::class,
            EloquentProductRepository::class,
        );

        $this->app->bind(
            ProductCatalogLookupInterface::class,
            EloquentProductCatalogLookup::class,
        );
    }
}
