<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Application\Ports\ProductCatalogLookupInterface;
use App\Modules\Inventory\Domain\Ports\ProductRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentProductCatalogLookup;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentProductRepository;
use App\Modules\Inventory\Application\Ports\ProductCatalogQueryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Eloquent\Repositories\EloquentProductCatalogQuery;
use App\Modules\Inventory\Application\Ports\RegisterInventoryEntryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Services\SqlRegisterInventoryEntry;
use Illuminate\Support\ServiceProvider;
use App\Modules\Inventory\Application\Ports\InventoryEntryOptionsInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Services\SqlInventoryEntryOptions;
use App\Modules\Inventory\Application\Ports\InventoryDashboardQueryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Queries\SqlInventoryDashboardQuery;

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
        $this->app->bind(
            ProductCatalogQueryInterface::class,
            EloquentProductCatalogQuery::class,
        );
        $this->app->bind(
            RegisterInventoryEntryInterface::class,
            SqlRegisterInventoryEntry::class,
        );
        $this->app->bind(
            InventoryEntryOptionsInterface::class,
            SqlInventoryEntryOptions::class,
        );
        $this->app->bind(
            InventoryDashboardQueryInterface::class,
            SqlInventoryDashboardQuery::class,
        );
    }
}
