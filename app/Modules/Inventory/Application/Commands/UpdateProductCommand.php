<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Commands;

final readonly class UpdateProductCommand
{
    public function __construct(
        public int $productId,
        public string $name,
        public ?string $barcode,
        public ?string $description,
        public string $minimumStock,
        public ?string $maximumStock,
        public string $reorderPoint,
        public string $salePrice,
    ) {
    }
}
