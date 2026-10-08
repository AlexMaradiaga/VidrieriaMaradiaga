<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

final class ClientProductCatalogDataTest extends TestCase
{
    public function test_client_catalog_is_complete_and_consistent(): void
    {
        $path = dirname(__DIR__, 3).'/database/data/vidrieria_maradiaga_products.json';
        $products = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertCount(301, $products);
        $this->assertCount(301, array_unique(array_column($products, 'sku')));
        $this->assertSame('VM-0001', $products[0]['sku']);
        $this->assertSame('VM-0301', $products[300]['sku']);
        $this->assertSame(288.7, $products[154]['sale_price']);
        $this->assertSame(332, $products[154]['source_price_with_tax']);
        $this->assertSame('ALUMINUM', $products[25]['category_code']);
        $this->assertSame('HARDWARE', $products[284]['category_code']);
        $this->assertSame('GLASS', $products[286]['category_code']);

        foreach ($products as $product) {
            $this->assertNotSame('', trim($product['name']));
            $this->assertLessThanOrEqual(180, mb_strlen($product['name']));
            $this->assertGreaterThanOrEqual(0, $product['sale_price']);
            $this->assertFalse($product['track_remnants']);
            $this->assertContains($product['category_code'], [
                'ALUMINUM',
                'PVC',
                'GLASS',
                'ACCESSORIES',
                'HARDWARE',
                'SEALANTS',
            ]);
            $this->assertContains($product['unit_code'], ['UNIT', 'METER', 'BAR', 'SHEET', 'TUBE']);
        }
    }
}
