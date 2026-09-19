<?php

declare(strict_types=1);

namespace App\Modules\Business\Providers;

use App\Modules\Accounting\Application\Ports\AccountingPostingInterface;
use App\Modules\Accounting\Infrastructure\Persistence\Services\SqlAccountingPosting;
use App\Modules\Sales\Application\Ports\SalesServiceInterface;
use App\Modules\Sales\Infrastructure\Persistence\Services\SqlSalesService;
use Illuminate\Support\ServiceProvider;

final class BusinessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountingPostingInterface::class, SqlAccountingPosting::class);
        $this->app->bind(SalesServiceInterface::class, SqlSalesService::class);
    }
}
