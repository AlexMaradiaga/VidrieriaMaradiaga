SELECT DB_NAME() AS DatabaseName;

SELECT COUNT(*) AS TotalMigrations
FROM migrations;

SELECT
    COUNT(*) AS TotalProducts,
    MIN(id) AS FirstProductId,
    MAX(id) AS LastProductId
FROM inventory_products
WHERE deleted_at IS NULL;

SELECT COUNT(*) AS TotalUsers
FROM users;

SELECT COUNT(*) AS TotalRoles
FROM roles;

SELECT COUNT(*) AS TotalPermissions
FROM permissions;

SELECT COUNT(*) AS TotalAccountingAccounts
FROM accounting_accounts;

SELECT TOP 10
    id,
    sku,
    name,
    sale_price
FROM inventory_products
WHERE deleted_at IS NULL
ORDER BY id;
