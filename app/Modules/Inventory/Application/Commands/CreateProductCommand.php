<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Commands;

final readonly class CreateProductCommand
{
    public function __construct(
        public string $sku,
        public string $name,
        public int $categoryId,
        public int $baseUnitId,
        public string $productType = 'material',
        public ?string $barcode = null,
        public ?string $description = null,
        public string $minimumStock = '0',
        public ?string $maximumStock = null,
        public string $reorderPoint = '0',
        public string $salePrice = '0',
        public bool $trackStock = true,
        public bool $trackLots = false,
        public bool $trackRemnants = false,
        public bool $allowNegativeStock = false,
    ) {
    }
}
