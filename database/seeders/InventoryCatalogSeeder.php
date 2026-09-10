<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $units = [
            ['code' => 'UNIT', 'name' => 'Unidad', 'symbol' => 'ud', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'METER', 'name' => 'Metro', 'symbol' => 'm', 'dimension' => 'length', 'decimal_places' => 4],
            ['code' => 'SQUARE_METER', 'name' => 'Metro cuadrado', 'symbol' => 'm²', 'dimension' => 'area', 'decimal_places' => 4],
            ['code' => 'MILLIMETER', 'name' => 'Milímetro', 'symbol' => 'mm', 'dimension' => 'length', 'decimal_places' => 2],
            ['code' => 'BAR', 'name' => 'Barra', 'symbol' => 'barra', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'SHEET', 'name' => 'Hoja', 'symbol' => 'hoja', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'PAIR', 'name' => 'Par', 'symbol' => 'par', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'SET', 'name' => 'Juego', 'symbol' => 'juego', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'TUBE', 'name' => 'Tubo', 'symbol' => 'tubo', 'dimension' => 'quantity', 'decimal_places' => 0],
            ['code' => 'KILOGRAM', 'name' => 'Kilogramo', 'symbol' => 'kg', 'dimension' => 'weight', 'decimal_places' => 4],
        ];

        foreach ($units as $unit) {
            DB::table('inventory_units')->updateOrInsert(
                ['code' => $unit['code']],
                [...$unit, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $categories = [
            ['code' => 'ALUMINUM', 'name' => 'Aluminio', 'sort_order' => 10],
            ['code' => 'PVC', 'name' => 'PVC', 'sort_order' => 20],
            ['code' => 'GLASS', 'name' => 'Vidrios', 'sort_order' => 30],
            ['code' => 'ACCESSORIES', 'name' => 'Accesorios', 'sort_order' => 40],
            ['code' => 'HARDWARE', 'name' => 'Herrajes', 'sort_order' => 50],
            ['code' => 'SEALANTS', 'name' => 'Selladores', 'sort_order' => 60],
        ];

        foreach ($categories as $category) {
            DB::table('inventory_categories')->updateOrInsert(
                ['code' => $category['code']],
                [...$category, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        DB::table('inventory_warehouses')->updateOrInsert(
            ['code' => 'MAIN'],
            [
                'name' => 'Bodega principal',
                'is_default' => true,
                'active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $warehouseId = DB::table('inventory_warehouses')
            ->where('code', 'MAIN')
            ->value('id');

        foreach ([
            ['code' => 'GENERAL', 'name' => 'Almacenamiento general'],
            ['code' => 'PROFILES', 'name' => 'Perfiles y barras'],
            ['code' => 'GLASS-RACK', 'name' => 'Caballete de vidrios'],
            ['code' => 'REMNANTS', 'name' => 'Retazos reutilizables'],
        ] as $location) {
            DB::table('inventory_locations')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'code' => $location['code']],
                [...$location, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $millimeterId = DB::table('inventory_units')->where('code', 'MILLIMETER')->value('id');

        $attributes = [
            'ALUMINUM' => [
                ['code' => 'LENGTH', 'name' => 'Longitud', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => true],
                ['code' => 'GAUGE', 'name' => 'Calibre', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
                ['code' => 'COLOR', 'name' => 'Color', 'data_type' => 'text', 'unit_id' => null, 'required' => true],
                ['code' => 'SERIES', 'name' => 'Serie', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
            ],
            'PVC' => [
                ['code' => 'LENGTH', 'name' => 'Longitud', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => true],
                ['code' => 'THICKNESS', 'name' => 'Espesor', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => false],
                ['code' => 'COLOR', 'name' => 'Color', 'data_type' => 'text', 'unit_id' => null, 'required' => true],
            ],
            'GLASS' => [
                ['code' => 'GLASS_TYPE', 'name' => 'Tipo de vidrio', 'data_type' => 'select', 'unit_id' => null, 'required' => true],
                ['code' => 'THICKNESS', 'name' => 'Espesor', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => true],
                ['code' => 'WIDTH', 'name' => 'Ancho', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => true],
                ['code' => 'HEIGHT', 'name' => 'Alto', 'data_type' => 'decimal', 'unit_id' => $millimeterId, 'required' => true],
                ['code' => 'COLOR', 'name' => 'Color', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
            ],
            'ACCESSORIES' => [
                ['code' => 'SIZE', 'name' => 'Medida', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
                ['code' => 'MATERIAL', 'name' => 'Material', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
            ],
            'HARDWARE' => [
                ['code' => 'SIZE', 'name' => 'Medida', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
                ['code' => 'FINISH', 'name' => 'Acabado', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
            ],
            'SEALANTS' => [
                ['code' => 'COLOR', 'name' => 'Color', 'data_type' => 'text', 'unit_id' => null, 'required' => false],
                ['code' => 'CONTENT', 'name' => 'Contenido', 'data_type' => 'text', 'unit_id' => null, 'required' => true],
            ],
        ];

        foreach ($attributes as $categoryCode => $items) {
            $categoryId = DB::table('inventory_categories')
                ->where('code', $categoryCode)
                ->value('id');

            foreach ($items as $sortOrder => $attribute) {
                DB::table('inventory_category_attributes')->updateOrInsert(
                    ['category_id' => $categoryId, 'code' => $attribute['code']],
                    [
                        'unit_id' => $attribute['unit_id'],
                        'name' => $attribute['name'],
                        'data_type' => $attribute['data_type'],
                        'is_required' => $attribute['required'],
                        'is_filterable' => true,
                        'sort_order' => $sortOrder + 1,
                        'active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        $glassTypeId = DB::table('inventory_category_attributes')
            ->join('inventory_categories', 'inventory_categories.id', '=', 'inventory_category_attributes.category_id')
            ->where('inventory_categories.code', 'GLASS')
            ->where('inventory_category_attributes.code', 'GLASS_TYPE')
            ->value('inventory_category_attributes.id');

        foreach (['TEMPERED' => 'Templado', 'LAMINATED' => 'Laminado', 'FLOAT' => 'Flotado', 'REFLECTIVE' => 'Reflectivo'] as $value => $label) {
            DB::table('inventory_attribute_options')->updateOrInsert(
                ['category_attribute_id' => $glassTypeId, 'value' => $value],
                ['label' => $label, 'active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
