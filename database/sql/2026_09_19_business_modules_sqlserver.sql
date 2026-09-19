/* Vidriería Maradiaga ERP - Acceso, empleados, ventas y contabilidad.
   SQL Server 2019+. Ejecutar sobre una base que ya contiene el módulo de inventario. */
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF COL_LENGTH('dbo.users', 'active') IS NULL ALTER TABLE dbo.users ADD active bit NOT NULL CONSTRAINT DF_users_active DEFAULT 1;
IF COL_LENGTH('dbo.users', 'last_login_at') IS NULL ALTER TABLE dbo.users ADD last_login_at datetime2 NULL;

IF OBJECT_ID('dbo.employees','U') IS NULL
BEGIN
 CREATE TABLE dbo.employees(
  id bigint IDENTITY PRIMARY KEY, employee_code nvarchar(30) NOT NULL, national_id nvarchar(30) NULL,
  first_name nvarchar(80) NOT NULL, last_name nvarchar(80) NOT NULL, email nvarchar(150) NULL,
  phone nvarchar(30) NULL, address nvarchar(500) NULL, hire_date date NULL, department nvarchar(80) NULL,
  job_title nvarchar(100) NULL, monthly_salary decimal(18,2) NOT NULL CONSTRAINT DF_employees_salary DEFAULT 0,
  user_id bigint NULL, active bit NOT NULL CONSTRAINT DF_employees_active DEFAULT 1,
  created_at datetime2 NULL, updated_at datetime2 NULL, deleted_at datetime2 NULL,
  CONSTRAINT UQ_employees_code UNIQUE(employee_code), CONSTRAINT FK_employees_user FOREIGN KEY(user_id) REFERENCES dbo.users(id)
 );
 CREATE UNIQUE INDEX UQ_employees_national_id ON dbo.employees(national_id) WHERE national_id IS NOT NULL;
 CREATE UNIQUE INDEX UQ_employees_user_id ON dbo.employees(user_id) WHERE user_id IS NOT NULL;
END;

IF OBJECT_ID('dbo.accounting_accounts','U') IS NULL
BEGIN
 CREATE TABLE dbo.accounting_accounts(
  id bigint IDENTITY PRIMARY KEY, code nvarchar(30) NOT NULL UNIQUE, name nvarchar(150) NOT NULL,
  type nvarchar(20) NOT NULL, parent_id bigint NULL, accepts_entries bit NOT NULL DEFAULT 1,
  active bit NOT NULL DEFAULT 1, created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT FK_accounts_parent FOREIGN KEY(parent_id) REFERENCES dbo.accounting_accounts(id),
  CONSTRAINT CK_accounts_type CHECK(type IN('asset','liability','equity','revenue','expense'))
 );
 CREATE TABLE dbo.accounting_periods(
  id bigint IDENTITY PRIMARY KEY, year smallint NOT NULL, month tinyint NOT NULL, starts_on date NOT NULL,
  ends_on date NOT NULL, status nvarchar(10) NOT NULL DEFAULT 'open', closed_by bigint NULL,
  closed_at datetime2 NULL, created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT UQ_accounting_period UNIQUE(year,month), CONSTRAINT FK_period_closed_by FOREIGN KEY(closed_by) REFERENCES dbo.users(id),
  CONSTRAINT CK_period_month CHECK(month BETWEEN 1 AND 12), CONSTRAINT CK_period_status CHECK(status IN('open','closed'))
 );
 CREATE TABLE dbo.accounting_journal_entries(
  id bigint IDENTITY PRIMARY KEY, number nvarchar(30) NOT NULL UNIQUE, entry_date date NOT NULL,
  period_id bigint NOT NULL, source_type nvarchar(30) NOT NULL DEFAULT 'manual', source_id bigint NULL,
  description nvarchar(250) NOT NULL, reference nvarchar(100) NULL, status nvarchar(10) NOT NULL DEFAULT 'posted',
  created_by bigint NOT NULL, posted_by bigint NOT NULL, posted_at datetime2 NOT NULL,
  created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT FK_journal_period FOREIGN KEY(period_id) REFERENCES dbo.accounting_periods(id),
  CONSTRAINT FK_journal_created_by FOREIGN KEY(created_by) REFERENCES dbo.users(id),
  CONSTRAINT FK_journal_posted_by FOREIGN KEY(posted_by) REFERENCES dbo.users(id)
 );
 CREATE INDEX IX_journal_date ON dbo.accounting_journal_entries(entry_date,status);
 CREATE TABLE dbo.accounting_journal_lines(
  id bigint IDENTITY PRIMARY KEY, entry_id bigint NOT NULL, line_number int NOT NULL, account_id bigint NOT NULL,
  description nvarchar(250) NULL, debit decimal(18,2) NOT NULL DEFAULT 0, credit decimal(18,2) NOT NULL DEFAULT 0,
  created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT UQ_journal_line UNIQUE(entry_id,line_number),
  CONSTRAINT FK_journal_line_entry FOREIGN KEY(entry_id) REFERENCES dbo.accounting_journal_entries(id),
  CONSTRAINT FK_journal_line_account FOREIGN KEY(account_id) REFERENCES dbo.accounting_accounts(id),
  CONSTRAINT CK_journal_line_amount CHECK(debit >= 0 AND credit >= 0 AND ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)))
 );
 CREATE TABLE dbo.accounting_settings(
  id bigint IDENTITY PRIMARY KEY, [key] nvarchar(50) NOT NULL UNIQUE, account_id bigint NOT NULL,
  created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT FK_accounting_setting_account FOREIGN KEY(account_id) REFERENCES dbo.accounting_accounts(id)
 );
END;

IF OBJECT_ID('dbo.sales_customers','U') IS NULL
BEGIN
 CREATE TABLE dbo.sales_customers(
  id bigint IDENTITY PRIMARY KEY, code nvarchar(30) NOT NULL UNIQUE, name nvarchar(150) NOT NULL,
  legal_name nvarchar(150) NULL, tax_id nvarchar(30) NULL, email nvarchar(150) NULL, phone nvarchar(30) NULL,
  address nvarchar(500) NULL, credit_limit decimal(18,2) NOT NULL DEFAULT 0, payment_terms_days int NOT NULL DEFAULT 0,
  active bit NOT NULL DEFAULT 1, created_at datetime2 NULL, updated_at datetime2 NULL, deleted_at datetime2 NULL
 );
 CREATE INDEX IX_sales_customers_tax_id ON dbo.sales_customers(tax_id);
 CREATE TABLE dbo.sales_documents(
  id bigint IDENTITY PRIMARY KEY, operation_key uniqueidentifier NOT NULL UNIQUE, number nvarchar(30) NOT NULL UNIQUE,
  document_date date NOT NULL, customer_id bigint NOT NULL, location_id bigint NOT NULL,
  status nvarchar(15) NOT NULL DEFAULT 'posted', payment_type nvarchar(10) NOT NULL DEFAULT 'cash',
  subtotal decimal(18,2) NOT NULL, discount decimal(18,2) NOT NULL DEFAULT 0, tax decimal(18,2) NOT NULL DEFAULT 0,
  total decimal(18,2) NOT NULL, paid_amount decimal(18,2) NOT NULL DEFAULT 0, notes nvarchar(1000) NULL,
  journal_entry_id bigint NULL, created_by bigint NOT NULL, posted_by bigint NOT NULL, posted_at datetime2 NOT NULL,
  created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT FK_sales_customer FOREIGN KEY(customer_id) REFERENCES dbo.sales_customers(id),
  CONSTRAINT FK_sales_location FOREIGN KEY(location_id) REFERENCES dbo.inventory_locations(id),
  CONSTRAINT FK_sales_journal FOREIGN KEY(journal_entry_id) REFERENCES dbo.accounting_journal_entries(id),
  CONSTRAINT FK_sales_created_by FOREIGN KEY(created_by) REFERENCES dbo.users(id),
  CONSTRAINT FK_sales_posted_by FOREIGN KEY(posted_by) REFERENCES dbo.users(id),
  CONSTRAINT CK_sales_status CHECK(status IN('posted','cancelled')),
  CONSTRAINT CK_sales_payment_type CHECK(payment_type IN('cash','credit')),
  CONSTRAINT CK_sales_amounts CHECK(subtotal >= 0 AND discount >= 0 AND tax >= 0 AND total >= 0 AND paid_amount >= 0 AND paid_amount <= total)
 );
 CREATE INDEX IX_sales_date ON dbo.sales_documents(document_date,status);
 CREATE TABLE dbo.sales_lines(
  id bigint IDENTITY PRIMARY KEY, sale_id bigint NOT NULL, line_number int NOT NULL, product_id bigint NOT NULL,
  unit_id bigint NOT NULL, quantity decimal(18,4) NOT NULL, unit_price decimal(18,4) NOT NULL,
  discount decimal(18,2) NOT NULL DEFAULT 0, tax_rate decimal(7,4) NOT NULL DEFAULT 0,
  subtotal decimal(18,2) NOT NULL, tax_amount decimal(18,2) NOT NULL DEFAULT 0, total decimal(18,2) NOT NULL,
  unit_cost decimal(18,4) NOT NULL DEFAULT 0, inventory_movement_id bigint NULL, created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT UQ_sales_line UNIQUE(sale_id,line_number), CONSTRAINT FK_sales_line_sale FOREIGN KEY(sale_id) REFERENCES dbo.sales_documents(id),
  CONSTRAINT FK_sales_line_product FOREIGN KEY(product_id) REFERENCES dbo.inventory_products(id),
  CONSTRAINT FK_sales_line_unit FOREIGN KEY(unit_id) REFERENCES dbo.inventory_units(id),
  CONSTRAINT FK_sales_line_movement FOREIGN KEY(inventory_movement_id) REFERENCES dbo.inventory_movements(id),
  CONSTRAINT CK_sales_line_amounts CHECK(quantity > 0 AND unit_price >= 0 AND discount >= 0 AND tax_rate >= 0 AND subtotal >= 0 AND tax_amount >= 0 AND total >= 0)
 );
 CREATE TABLE dbo.sales_payments(
  id bigint IDENTITY PRIMARY KEY, sale_id bigint NOT NULL, payment_date date NOT NULL, amount decimal(18,2) NOT NULL,
  method nvarchar(30) NOT NULL, reference nvarchar(100) NULL, notes nvarchar(500) NULL, received_by bigint NOT NULL,
  journal_entry_id bigint NULL, created_at datetime2 NULL, updated_at datetime2 NULL,
  CONSTRAINT FK_payment_sale FOREIGN KEY(sale_id) REFERENCES dbo.sales_documents(id),
  CONSTRAINT FK_payment_user FOREIGN KEY(received_by) REFERENCES dbo.users(id),
  CONSTRAINT FK_payment_journal FOREIGN KEY(journal_entry_id) REFERENCES dbo.accounting_journal_entries(id),
  CONSTRAINT CK_payment_amount CHECK(amount > 0)
 );
END;

DECLARE @now datetime2=SYSDATETIME();
DECLARE @accounts TABLE(code nvarchar(30),name nvarchar(150),type nvarchar(20),setting nvarchar(50));
INSERT INTO @accounts VALUES
('1101','Caja y bancos','asset','cash'),('1102','Cuentas por cobrar','asset','accounts_receivable'),
('1103','Inventarios','asset','inventory'),('2101','Impuesto sobre ventas por pagar','liability','sales_tax_payable'),
('3101','Capital','equity',NULL),('4101','Ingresos por ventas','revenue','sales_revenue'),
('5101','Costo de ventas','expense','cost_of_goods_sold'),('5201','Gastos operativos','expense',NULL);
INSERT INTO dbo.accounting_accounts(code,name,type,parent_id,accepts_entries,active,created_at,updated_at)
SELECT a.code,a.name,a.type,NULL,1,1,@now,@now FROM @accounts a WHERE NOT EXISTS(SELECT 1 FROM dbo.accounting_accounts x WHERE x.code=a.code);
INSERT INTO dbo.accounting_settings([key],account_id,created_at,updated_at)
SELECT a.setting,x.id,@now,@now FROM @accounts a JOIN dbo.accounting_accounts x ON x.code=a.code
WHERE a.setting IS NOT NULL AND NOT EXISTS(SELECT 1 FROM dbo.accounting_settings s WHERE s.[key]=a.setting);
IF NOT EXISTS(SELECT 1 FROM dbo.accounting_periods WHERE year=YEAR(GETDATE()) AND month=MONTH(GETDATE()))
 INSERT INTO dbo.accounting_periods(year,month,starts_on,ends_on,status,created_at,updated_at)
 VALUES(YEAR(GETDATE()),MONTH(GETDATE()),DATEFROMPARTS(YEAR(GETDATE()),MONTH(GETDATE()),1),EOMONTH(GETDATE()),'open',@now,@now);

DECLARE @permissions TABLE(name nvarchar(255));
INSERT INTO @permissions VALUES
('access.users.view'),('access.users.manage'),('access.roles.manage'),('access.employees.manage'),
('suppliers.view'),('suppliers.manage'),('sales.view'),('sales.create'),('sales.post'),
('sales.payments.manage'),('sales.customers.manage'),('accounting.accounts.view'),
('accounting.accounts.manage'),('accounting.periods.manage'),('accounting.entries.view'),
('accounting.entries.create'),('accounting.reports.view');
INSERT INTO dbo.permissions(name,guard_name,created_at,updated_at)
SELECT p.name,'web',@now,@now FROM @permissions p WHERE NOT EXISTS(SELECT 1 FROM dbo.permissions x WHERE x.name=p.name AND x.guard_name='web');
IF NOT EXISTS(SELECT 1 FROM dbo.roles WHERE name='Contabilidad' AND guard_name='web') INSERT INTO dbo.roles(name,guard_name,created_at,updated_at) VALUES('Contabilidad','web',@now,@now);
IF NOT EXISTS(SELECT 1 FROM dbo.roles WHERE name='Recursos Humanos' AND guard_name='web') INSERT INTO dbo.roles(name,guard_name,created_at,updated_at) VALUES('Recursos Humanos','web',@now,@now);
DECLARE @admin bigint=(SELECT TOP 1 id FROM dbo.roles WHERE name='Administrador' AND guard_name='web');
IF @admin IS NOT NULL INSERT INTO dbo.role_has_permissions(permission_id,role_id) SELECT p.id,@admin FROM dbo.permissions p WHERE NOT EXISTS(SELECT 1 FROM dbo.role_has_permissions rp WHERE rp.permission_id=p.id AND rp.role_id=@admin);
DECLARE @accountingRole bigint=(SELECT TOP 1 id FROM dbo.roles WHERE name='Contabilidad' AND guard_name='web');
IF @accountingRole IS NOT NULL
 INSERT INTO dbo.role_has_permissions(permission_id,role_id)
 SELECT p.id,@accountingRole FROM dbo.permissions p
 WHERE p.name IN('sales.view','suppliers.view','accounting.accounts.view','accounting.accounts.manage','accounting.periods.manage','accounting.entries.view','accounting.entries.create','accounting.reports.view')
 AND NOT EXISTS(SELECT 1 FROM dbo.role_has_permissions rp WHERE rp.permission_id=p.id AND rp.role_id=@accountingRole);
DECLARE @hrRole bigint=(SELECT TOP 1 id FROM dbo.roles WHERE name='Recursos Humanos' AND guard_name='web');
IF @hrRole IS NOT NULL
 INSERT INTO dbo.role_has_permissions(permission_id,role_id)
 SELECT p.id,@hrRole FROM dbo.permissions p
 WHERE p.name IN('access.users.view','access.employees.manage')
 AND NOT EXISTS(SELECT 1 FROM dbo.role_has_permissions rp WHERE rp.permission_id=p.id AND rp.role_id=@hrRole);
DECLARE @salesRole bigint=(SELECT TOP 1 id FROM dbo.roles WHERE name='Ventas' AND guard_name='web');
IF @salesRole IS NOT NULL
 INSERT INTO dbo.role_has_permissions(permission_id,role_id)
 SELECT p.id,@salesRole FROM dbo.permissions p
 WHERE p.name IN('sales.view','sales.create','sales.post','sales.payments.manage','sales.customers.manage')
 AND NOT EXISTS(SELECT 1 FROM dbo.role_has_permissions rp WHERE rp.permission_id=p.id AND rp.role_id=@salesRole);

COMMIT TRANSACTION;
PRINT 'Módulos empresariales instalados correctamente.';
