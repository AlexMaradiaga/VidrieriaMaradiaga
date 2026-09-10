<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Modules\Inventory\Providers\InventoryServiceProvider::class,
];
