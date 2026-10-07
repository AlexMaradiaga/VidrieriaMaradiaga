SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID(N'dbo.accounting_treasury_accounts', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.accounting_treasury_accounts (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_accounting_treasury_accounts PRIMARY KEY,
            code NVARCHAR(30) NOT NULL CONSTRAINT UQ_accounting_treasury_accounts_code UNIQUE,
            name NVARCHAR(150) NOT NULL,
            type NVARCHAR(20) NOT NULL CONSTRAINT DF_accounting_treasury_type DEFAULT N'cash',
            accounting_account_id BIGINT NOT NULL CONSTRAINT UQ_accounting_treasury_ledger UNIQUE,
            bank_name NVARCHAR(120) NULL,
            account_number NVARCHAR(80) NULL,
            active BIT NOT NULL CONSTRAINT DF_accounting_treasury_active DEFAULT 1,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_accounting_treasury_ledger FOREIGN KEY (accounting_account_id) REFERENCES dbo.accounting_accounts(id)
        );
    END;

    IF COL_LENGTH('dbo.sales_documents', 'treasury_account_id') IS NULL
    BEGIN
        ALTER TABLE dbo.sales_documents ADD treasury_account_id BIGINT NULL;
        ALTER TABLE dbo.sales_documents ADD CONSTRAINT FK_sales_documents_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id);
    END;

    IF COL_LENGTH('dbo.sales_payments', 'treasury_account_id') IS NULL
    BEGIN
        ALTER TABLE dbo.sales_payments ADD treasury_account_id BIGINT NULL;
        ALTER TABLE dbo.sales_payments ADD CONSTRAINT FK_sales_payments_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id);
    END;

    IF OBJECT_ID(N'dbo.purchasing_documents', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.purchasing_documents (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_purchasing_documents PRIMARY KEY,
            operation_key UNIQUEIDENTIFIER NOT NULL CONSTRAINT UQ_purchasing_documents_operation UNIQUE,
            number NVARCHAR(30) NOT NULL CONSTRAINT UQ_purchasing_documents_number UNIQUE,
            supplier_document NVARCHAR(100) NULL,
            document_date DATE NOT NULL,
            due_date DATE NULL,
            supplier_id BIGINT NOT NULL,
            location_id BIGINT NOT NULL,
            status NVARCHAR(15) NOT NULL CONSTRAINT DF_purchasing_documents_status DEFAULT N'posted',
            payment_type NVARCHAR(10) NOT NULL CONSTRAINT DF_purchasing_documents_payment DEFAULT N'credit',
            treasury_account_id BIGINT NULL,
            subtotal DECIMAL(18,2) NOT NULL,
            discount DECIMAL(18,2) NOT NULL CONSTRAINT DF_purchasing_documents_discount DEFAULT 0,
            tax DECIMAL(18,2) NOT NULL CONSTRAINT DF_purchasing_documents_tax DEFAULT 0,
            total DECIMAL(18,2) NOT NULL,
            paid_amount DECIMAL(18,2) NOT NULL CONSTRAINT DF_purchasing_documents_paid DEFAULT 0,
            notes NVARCHAR(1000) NULL,
            journal_entry_id BIGINT NULL,
            created_by BIGINT NOT NULL,
            posted_at DATETIME2(0) NOT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_purchasing_documents_supplier FOREIGN KEY (supplier_id) REFERENCES dbo.inventory_suppliers(id),
            CONSTRAINT FK_purchasing_documents_location FOREIGN KEY (location_id) REFERENCES dbo.inventory_locations(id),
            CONSTRAINT FK_purchasing_documents_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_purchasing_documents_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id),
            CONSTRAINT FK_purchasing_documents_user FOREIGN KEY (created_by) REFERENCES dbo.users(id)
        );
        CREATE INDEX IX_purchasing_documents_date ON dbo.purchasing_documents(document_date, status);
        CREATE INDEX IX_purchasing_documents_due ON dbo.purchasing_documents(due_date, status);
    END;

    IF OBJECT_ID(N'dbo.purchasing_lines', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.purchasing_lines (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_purchasing_lines PRIMARY KEY,
            purchase_id BIGINT NOT NULL,
            line_number INT NOT NULL,
            product_id BIGINT NOT NULL,
            unit_id BIGINT NOT NULL,
            quantity DECIMAL(18,4) NOT NULL,
            unit_cost DECIMAL(18,4) NOT NULL,
            discount DECIMAL(18,2) NOT NULL CONSTRAINT DF_purchasing_lines_discount DEFAULT 0,
            tax_rate DECIMAL(7,4) NOT NULL CONSTRAINT DF_purchasing_lines_tax_rate DEFAULT 0,
            subtotal DECIMAL(18,2) NOT NULL,
            tax_amount DECIMAL(18,2) NOT NULL CONSTRAINT DF_purchasing_lines_tax DEFAULT 0,
            total DECIMAL(18,2) NOT NULL,
            inventory_movement_id BIGINT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT UQ_purchasing_lines_number UNIQUE (purchase_id, line_number),
            CONSTRAINT FK_purchasing_lines_purchase FOREIGN KEY (purchase_id) REFERENCES dbo.purchasing_documents(id),
            CONSTRAINT FK_purchasing_lines_product FOREIGN KEY (product_id) REFERENCES dbo.inventory_products(id),
            CONSTRAINT FK_purchasing_lines_unit FOREIGN KEY (unit_id) REFERENCES dbo.inventory_units(id),
            CONSTRAINT FK_purchasing_lines_movement FOREIGN KEY (inventory_movement_id) REFERENCES dbo.inventory_movements(id)
        );
    END;

    IF OBJECT_ID(N'dbo.purchasing_payments', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.purchasing_payments (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_purchasing_payments PRIMARY KEY,
            purchase_id BIGINT NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            treasury_account_id BIGINT NOT NULL,
            method NVARCHAR(30) NOT NULL,
            reference NVARCHAR(100) NULL,
            notes NVARCHAR(500) NULL,
            paid_by BIGINT NOT NULL,
            journal_entry_id BIGINT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_purchasing_payments_purchase FOREIGN KEY (purchase_id) REFERENCES dbo.purchasing_documents(id),
            CONSTRAINT FK_purchasing_payments_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_purchasing_payments_user FOREIGN KEY (paid_by) REFERENCES dbo.users(id),
            CONSTRAINT FK_purchasing_payments_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id)
        );
    END;

    IF OBJECT_ID(N'dbo.accounting_expenses', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.accounting_expenses (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_accounting_expenses PRIMARY KEY,
            operation_key UNIQUEIDENTIFIER NOT NULL CONSTRAINT UQ_accounting_expenses_operation UNIQUE,
            number NVARCHAR(30) NOT NULL CONSTRAINT UQ_accounting_expenses_number UNIQUE,
            expense_date DATE NOT NULL,
            due_date DATE NULL,
            payee NVARCHAR(150) NOT NULL,
            category NVARCHAR(30) NOT NULL,
            expense_account_id BIGINT NOT NULL,
            document_number NVARCHAR(100) NULL,
            payment_type NVARCHAR(10) NOT NULL,
            treasury_account_id BIGINT NULL,
            subtotal DECIMAL(18,2) NOT NULL,
            tax DECIMAL(18,2) NOT NULL CONSTRAINT DF_accounting_expenses_tax DEFAULT 0,
            total DECIMAL(18,2) NOT NULL,
            paid_amount DECIMAL(18,2) NOT NULL CONSTRAINT DF_accounting_expenses_paid DEFAULT 0,
            notes NVARCHAR(1000) NULL,
            status NVARCHAR(15) NOT NULL CONSTRAINT DF_accounting_expenses_status DEFAULT N'posted',
            journal_entry_id BIGINT NULL,
            created_by BIGINT NOT NULL,
            posted_at DATETIME2(0) NOT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_accounting_expenses_account FOREIGN KEY (expense_account_id) REFERENCES dbo.accounting_accounts(id),
            CONSTRAINT FK_accounting_expenses_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_accounting_expenses_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id),
            CONSTRAINT FK_accounting_expenses_user FOREIGN KEY (created_by) REFERENCES dbo.users(id)
        );
        CREATE INDEX IX_accounting_expenses_date ON dbo.accounting_expenses(expense_date, status);
        CREATE INDEX IX_accounting_expenses_due ON dbo.accounting_expenses(due_date, status);
    END;

    IF OBJECT_ID(N'dbo.accounting_expense_payments', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.accounting_expense_payments (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_accounting_expense_payments PRIMARY KEY,
            expense_id BIGINT NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            treasury_account_id BIGINT NOT NULL,
            method NVARCHAR(30) NOT NULL,
            reference NVARCHAR(100) NULL,
            paid_by BIGINT NOT NULL,
            journal_entry_id BIGINT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_accounting_expense_payments_expense FOREIGN KEY (expense_id) REFERENCES dbo.accounting_expenses(id),
            CONSTRAINT FK_accounting_expense_payments_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_accounting_expense_payments_user FOREIGN KEY (paid_by) REFERENCES dbo.users(id),
            CONSTRAINT FK_accounting_expense_payments_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id)
        );
    END;

    IF OBJECT_ID(N'dbo.accounting_loans', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.accounting_loans (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_accounting_loans PRIMARY KEY,
            number NVARCHAR(30) NOT NULL CONSTRAINT UQ_accounting_loans_number UNIQUE,
            creditor NVARCHAR(150) NOT NULL,
            description NVARCHAR(250) NOT NULL,
            reference NVARCHAR(100) NULL,
            start_date DATE NOT NULL,
            principal DECIMAL(18,2) NOT NULL,
            outstanding_principal DECIMAL(18,2) NOT NULL,
            annual_interest_rate DECIMAL(7,4) NOT NULL CONSTRAINT DF_accounting_loans_rate DEFAULT 0,
            installments INT NOT NULL CONSTRAINT DF_accounting_loans_installments DEFAULT 1,
            liability_account_id BIGINT NOT NULL,
            interest_expense_account_id BIGINT NOT NULL,
            treasury_account_id BIGINT NOT NULL,
            status NVARCHAR(15) NOT NULL CONSTRAINT DF_accounting_loans_status DEFAULT N'active',
            journal_entry_id BIGINT NULL,
            created_by BIGINT NOT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_accounting_loans_liability FOREIGN KEY (liability_account_id) REFERENCES dbo.accounting_accounts(id),
            CONSTRAINT FK_accounting_loans_interest FOREIGN KEY (interest_expense_account_id) REFERENCES dbo.accounting_accounts(id),
            CONSTRAINT FK_accounting_loans_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_accounting_loans_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id),
            CONSTRAINT FK_accounting_loans_user FOREIGN KEY (created_by) REFERENCES dbo.users(id)
        );
    END;

    IF OBJECT_ID(N'dbo.accounting_loan_payments', N'U') IS NULL
    BEGIN
        CREATE TABLE dbo.accounting_loan_payments (
            id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_accounting_loan_payments PRIMARY KEY,
            loan_id BIGINT NOT NULL,
            payment_date DATE NOT NULL,
            principal_amount DECIMAL(18,2) NOT NULL,
            interest_amount DECIMAL(18,2) NOT NULL CONSTRAINT DF_accounting_loan_payments_interest DEFAULT 0,
            late_fee DECIMAL(18,2) NOT NULL CONSTRAINT DF_accounting_loan_payments_late DEFAULT 0,
            total DECIMAL(18,2) NOT NULL,
            treasury_account_id BIGINT NOT NULL,
            reference NVARCHAR(100) NULL,
            paid_by BIGINT NOT NULL,
            journal_entry_id BIGINT NULL,
            created_at DATETIME2(0) NULL,
            updated_at DATETIME2(0) NULL,
            CONSTRAINT FK_accounting_loan_payments_loan FOREIGN KEY (loan_id) REFERENCES dbo.accounting_loans(id),
            CONSTRAINT FK_accounting_loan_payments_treasury FOREIGN KEY (treasury_account_id) REFERENCES dbo.accounting_treasury_accounts(id),
            CONSTRAINT FK_accounting_loan_payments_user FOREIGN KEY (paid_by) REFERENCES dbo.users(id),
            CONSTRAINT FK_accounting_loan_payments_journal FOREIGN KEY (journal_entry_id) REFERENCES dbo.accounting_journal_entries(id)
        );
    END;

    COMMIT TRANSACTION;
    PRINT N'Contabilidad operativa, compras, gastos, tesorería y préstamos instalados correctamente.';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
