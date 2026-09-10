<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Inventory\Domain\Enums\ProductType;
use App\Modules\Inventory\Domain\Exceptions\InvalidProductException;
use App\Modules\Inventory\Domain\ValueObjects\Sku;

final class Product
{
    private ?int $id;
    private int $categoryId;
    private int $baseUnitId;
    private Sku $sku;
    private ?string $barcode;
    private string $name;
    private ?string $description;
    private ProductType $productType;
    private string $minimumStock;
    private ?string $maximumStock;
    private string $reorderPoint;
    private string $averageCost;
    private string $lastPurchaseCost;
    private string $salePrice;
    private bool $trackStock;
    private bool $trackLots;
    private bool $trackRemnants;
    private bool $allowNegativeStock;
    private bool $active;

    private function __construct(
        ?int $id,
        int $categoryId,
        int $baseUnitId,
        Sku $sku,
        ?string $barcode,
        string $name,
        ?string $description,
        ProductType $productType,
        string $minimumStock,
        ?string $maximumStock,
        string $reorderPoint,
        string $averageCost,
        string $lastPurchaseCost,
        string $salePrice,
        bool $trackStock,
        bool $trackLots,
        bool $trackRemnants,
        bool $allowNegativeStock,
        bool $active,
    ) {
        if ($id !== null && $id <= 0) {
            throw InvalidProductException::invalidIdentifier('id');
        }

        $this->assertIdentifier($categoryId, 'category_id');
        $this->assertIdentifier($baseUnitId, 'base_unit_id');

        $this->id = $id;
        $this->categoryId = $categoryId;
        $this->baseUnitId = $baseUnitId;
        $this->sku = $sku;
        $this->barcode = self::normalizeBarcode($barcode);
        $this->name = self::normalizeName($name);
        $this->description = self::normalizeDescription($description);
        $this->productType = $productType;

        $this->configureInventory(
            minimumStock: $minimumStock,
            maximumStock: $maximumStock,
            reorderPoint: $reorderPoint,
            trackStock: $trackStock,
            trackLots: $trackLots,
            trackRemnants: $trackRemnants,
            allowNegativeStock: $allowNegativeStock,
        );

        $this->changeCommercialValues(
            averageCost: $averageCost,
            lastPurchaseCost: $lastPurchaseCost,
            salePrice: $salePrice,
        );

        $this->active = $active;
    }

    public static function create(
        Sku $sku,
        string $name,
        int $categoryId,
        int $baseUnitId,
        ProductType $productType = ProductType::MATERIAL,
        ?string $barcode = null,
        ?string $description = null,
        string $minimumStock = '0',
        ?string $maximumStock = null,
        string $reorderPoint = '0',
        string $averageCost = '0',
        string $lastPurchaseCost = '0',
        string $salePrice = '0',
        bool $trackStock = true,
        bool $trackLots = false,
        bool $trackRemnants = false,
        bool $allowNegativeStock = false,
    ): self {
        return new self(
            id: null,
            categoryId: $categoryId,
            baseUnitId: $baseUnitId,
            sku: $sku,
            barcode: $barcode,
            name: $name,
            description: $description,
            productType: $productType,
            minimumStock: $minimumStock,
            maximumStock: $maximumStock,
            reorderPoint: $reorderPoint,
            averageCost: $averageCost,
            lastPurchaseCost: $lastPurchaseCost,
            salePrice: $salePrice,
            trackStock: $trackStock,
            trackLots: $trackLots,
            trackRemnants: $trackRemnants,
            allowNegativeStock: $allowNegativeStock,
            active: true,
        );
    }

    public static function reconstitute(
        int $id,
        int $categoryId,
        int $baseUnitId,
        Sku $sku,
        ?string $barcode,
        string $name,
        ?string $description,
        ProductType $productType,
        string $minimumStock,
        ?string $maximumStock,
        string $reorderPoint,
        string $averageCost,
        string $lastPurchaseCost,
        string $salePrice,
        bool $trackStock,
        bool $trackLots,
        bool $trackRemnants,
        bool $allowNegativeStock,
        bool $active,
    ): self {
        return new self(
            id: $id,
            categoryId: $categoryId,
            baseUnitId: $baseUnitId,
            sku: $sku,
            barcode: $barcode,
            name: $name,
            description: $description,
            productType: $productType,
            minimumStock: $minimumStock,
            maximumStock: $maximumStock,
            reorderPoint: $reorderPoint,
            averageCost: $averageCost,
            lastPurchaseCost: $lastPurchaseCost,
            salePrice: $salePrice,
            trackStock: $trackStock,
            trackLots: $trackLots,
            trackRemnants: $trackRemnants,
            allowNegativeStock: $allowNegativeStock,
            active: $active,
        );
    }

    public function updateInformation(
        Sku $sku,
        string $name,
        int $categoryId,
        int $baseUnitId,
        ProductType $productType,
        ?string $barcode,
        ?string $description,
    ): void {
        $this->assertIdentifier($categoryId, 'category_id');
        $this->assertIdentifier($baseUnitId, 'base_unit_id');

        $this->sku = $sku;
        $this->name = self::normalizeName($name);
        $this->categoryId = $categoryId;
        $this->baseUnitId = $baseUnitId;
        $this->productType = $productType;
        $this->barcode = self::normalizeBarcode($barcode);
        $this->description = self::normalizeDescription($description);
    }

    public function configureInventory(
        string $minimumStock,
        ?string $maximumStock,
        string $reorderPoint,
        bool $trackStock,
        bool $trackLots,
        bool $trackRemnants,
        bool $allowNegativeStock,
    ): void {
        $normalizedMinimum = self::normalizeDecimal(
            $minimumStock,
            'stock mínimo',
        );

        $normalizedMaximum = $maximumStock === null
            ? null
            : self::normalizeDecimal($maximumStock, 'stock máximo');

        $normalizedReorderPoint = self::normalizeDecimal(
            $reorderPoint,
            'punto de reorden',
        );

        if (
            $normalizedMaximum !== null
            && self::toScaledInteger($normalizedMaximum)
                < self::toScaledInteger($normalizedMinimum)
        ) {
            throw InvalidProductException::maximumStockLowerThanMinimum();
        }

        $this->minimumStock = $normalizedMinimum;
        $this->maximumStock = $normalizedMaximum;
        $this->reorderPoint = $normalizedReorderPoint;
        $this->trackStock = $trackStock;
        $this->trackLots = $trackLots;
        $this->trackRemnants = $trackRemnants;
        $this->allowNegativeStock = $allowNegativeStock;
    }

    public function changeCommercialValues(
        string $averageCost,
        string $lastPurchaseCost,
        string $salePrice,
    ): void {
        $this->averageCost = self::normalizeDecimal(
            $averageCost,
            'costo promedio',
        );

        $this->lastPurchaseCost = self::normalizeDecimal(
            $lastPurchaseCost,
            'último costo de compra',
        );

        $this->salePrice = self::normalizeDecimal(
            $salePrice,
            'precio de venta',
        );
    }

    public function assignId(int $id): void
    {
        if ($id <= 0) {
            throw InvalidProductException::invalidIdentifier('id');
        }

        if ($this->id !== null) {
            throw InvalidProductException::identityAlreadyAssigned();
        }

        $this->id = $id;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    private function assertIdentifier(int $id, string $field): void
    {
        if ($id <= 0) {
            throw InvalidProductException::invalidIdentifier($field);
        }
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidProductException::emptyName();
        }

        if (mb_strlen($name) > 180) {
            throw InvalidProductException::nameTooLong();
        }

        return $name;
    }

    private static function normalizeBarcode(?string $barcode): ?string
    {
        if ($barcode === null || trim($barcode) === '') {
            return null;
        }

        $barcode = trim($barcode);

        if (mb_strlen($barcode) > 80) {
            throw InvalidProductException::barcodeTooLong();
        }

        return $barcode;
    }

    private static function normalizeDescription(
        ?string $description,
    ): ?string {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $description = trim($description);

        if (mb_strlen($description) > 1000) {
            throw InvalidProductException::descriptionTooLong();
        }

        return $description;
    }

    private static function normalizeDecimal(
        string $value,
        string $field,
    ): string {
        $value = trim($value);

        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $value)) {
            throw InvalidProductException::invalidDecimal($field);
        }

        [$integerPart, $decimalPart] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        $integerPart = ltrim($integerPart, '0');

        if ($integerPart === '') {
            $integerPart = '0';
        }

        return $integerPart.'.'.str_pad($decimalPart, 4, '0');
    }

    private static function toScaledInteger(string $value): int
    {
        [$integerPart, $decimalPart] = explode('.', $value, 2);

        return (int) ($integerPart.$decimalPart);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function categoryId(): int
    {
        return $this->categoryId;
    }

    public function baseUnitId(): int
    {
        return $this->baseUnitId;
    }

    public function sku(): Sku
    {
        return $this->sku;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function productType(): ProductType
    {
        return $this->productType;
    }

    public function minimumStock(): string
    {
        return $this->minimumStock;
    }

    public function maximumStock(): ?string
    {
        return $this->maximumStock;
    }

    public function reorderPoint(): string
    {
        return $this->reorderPoint;
    }

    public function averageCost(): string
    {
        return $this->averageCost;
    }

    public function lastPurchaseCost(): string
    {
        return $this->lastPurchaseCost;
    }

    public function salePrice(): string
    {
        return $this->salePrice;
    }

    public function tracksStock(): bool
    {
        return $this->trackStock;
    }

    public function tracksLots(): bool
    {
        return $this->trackLots;
    }

    public function tracksRemnants(): bool
    {
        return $this->trackRemnants;
    }

    public function allowsNegativeStock(): bool
    {
        return $this->allowNegativeStock;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
