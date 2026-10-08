<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

interface ProductCatalogQueryInterface
{
    public function search(
        string $search,
        ?int $categoryId,
        string $status,
        string $sort,
        int $perPage,
        int $page,
    ): array;

    public function formOptions(): array;
}
