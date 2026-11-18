SELECT
    COL_LENGTH('dbo.inventory_movements', 'customer_id') AS customer_id,
    COL_LENGTH('dbo.inventory_movements', 'requested_by') AS requested_by,
    COL_LENGTH('dbo.inventory_movements', 'customer_name') AS customer_name,
    COL_LENGTH('dbo.inventory_movements', 'customer_address') AS customer_address;

SELECT migration, batch
FROM dbo.migrations
WHERE migration = '2026_11_18_000206_add_exit_order_data_to_inventory_movements';

SELECT TOP (20)
    m.id,
    m.reference AS numero_orden,
    m.document_date,
    m.requested_by AS solicitado_por,
    m.customer_name AS cliente,
    m.customer_address AS domicilio,
    COUNT(l.id) AS productos
FROM dbo.inventory_movements AS m
LEFT JOIN dbo.inventory_movement_lines AS l ON l.movement_id = m.id
WHERE m.direction = 'outbound'
GROUP BY m.id, m.reference, m.document_date, m.requested_by, m.customer_name, m.customer_address
ORDER BY m.id DESC;
