/* Verificación de solo lectura. No modifica datos. */

SELECT migration
FROM migrations
WHERE migration = '2026_11_14_000205_add_inventory_client_adjustments';

SELECT code, name, is_default, active
FROM inventory_warehouses
WHERE code IN ('BOD-ADH-BAR', 'BOD-VID-ESP', 'BOD-PERF-PVC', 'BOD-PERF-ALU')
ORDER BY code;

SELECT
    categories.code AS category_code,
    categories.name AS category_name,
    warehouses.code AS warehouse_code,
    warehouses.name AS warehouse_name
FROM inventory_categories AS categories
LEFT JOIN inventory_warehouses AS warehouses
    ON warehouses.id = categories.default_warehouse_id
WHERE categories.code IN ('ACCESSORIES', 'HARDWARE', 'SEALANTS', 'GLASS', 'PVC', 'ALUMINUM')
ORDER BY categories.sort_order, categories.name;

SELECT
    COL_LENGTH('inventory_transfers', 'responsible_name') AS transfer_responsible_column,
    COL_LENGTH('inventory_counts', 'responsible_name') AS count_responsible_column;

SELECT
    CASE WHEN OBJECT_ID('inventory_tools', 'U') IS NULL THEN 0 ELSE 1 END AS tools_table_exists,
    CASE WHEN OBJECT_ID('inventory_tool_assignments', 'U') IS NULL THEN 0 ELSE 1 END AS assignments_table_exists;

SELECT name
FROM permissions
WHERE name IN ('inventory.tools.view', 'inventory.tools.manage', 'inventory.tools.assign')
ORDER BY name;
