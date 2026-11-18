SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF COL_LENGTH('dbo.inventory_movements', 'customer_id') IS NULL
    ALTER TABLE dbo.inventory_movements ADD customer_id BIGINT NULL;

IF COL_LENGTH('dbo.inventory_movements', 'requested_by') IS NULL
    ALTER TABLE dbo.inventory_movements ADD requested_by NVARCHAR(180) NULL;

IF COL_LENGTH('dbo.inventory_movements', 'customer_name') IS NULL
    ALTER TABLE dbo.inventory_movements ADD customer_name NVARCHAR(180) NULL;

IF COL_LENGTH('dbo.inventory_movements', 'customer_address') IS NULL
    ALTER TABLE dbo.inventory_movements ADD customer_address NVARCHAR(500) NULL;

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'fk_inv_movements_customer')
    ALTER TABLE dbo.inventory_movements WITH CHECK
    ADD CONSTRAINT fk_inv_movements_customer
    FOREIGN KEY (customer_id) REFERENCES dbo.sales_customers(id);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.inventory_movements') AND name = 'ix_inv_movements_order_reference')
    CREATE INDEX ix_inv_movements_order_reference ON dbo.inventory_movements(direction, reference);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.inventory_movements') AND name = 'ix_inv_movements_customer')
    CREATE INDEX ix_inv_movements_customer ON dbo.inventory_movements(customer_id);

IF NOT EXISTS (SELECT 1 FROM dbo.migrations WHERE migration = '2026_11_18_000206_add_exit_order_data_to_inventory_movements')
BEGIN
    DECLARE @batch INT = ISNULL((SELECT MAX(batch) FROM dbo.migrations), 0) + 1;
    INSERT INTO dbo.migrations (migration, batch)
    VALUES ('2026_11_18_000206_add_exit_order_data_to_inventory_movements', @batch);
END;

COMMIT TRANSACTION;
PRINT 'Órdenes de salida instaladas correctamente.';
