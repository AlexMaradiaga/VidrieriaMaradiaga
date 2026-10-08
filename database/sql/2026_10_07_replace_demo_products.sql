/*
 Catálogo oficial de Vidriería Maradiaga — 301 productos.
 Fuente: PRODUCTOS.xlsx. Los precios insertados son valores sin ISV.
 ADVERTENCIA: este script sustituye únicamente una base con 0 o 2 productos de demostración.
 Ejecute este script O el seeder ClientProductCatalogSeeder; no ambos.
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @ExistingProducts INT = (SELECT COUNT(*) FROM dbo.inventory_products);

    IF @ExistingProducts NOT IN (0, 2)
        THROW 51000, 'Importación cancelada: se esperaban 0 o 2 productos de demostración.', 1;

    IF EXISTS (
        SELECT 1 FROM dbo.sales_lines AS sl
        INNER JOIN dbo.inventory_products AS p ON p.id = sl.product_id
    )
        THROW 51001, 'Importación cancelada: existen ventas asociadas a los productos actuales.', 1;

    IF EXISTS (
        SELECT 1 FROM dbo.purchasing_lines AS pl
        INNER JOIN dbo.inventory_products AS p ON p.id = pl.product_id
    )
        THROW 51002, 'Importación cancelada: existen compras asociadas a los productos actuales.', 1;

    DECLARE @Products TABLE (
        sku NVARCHAR(50) NOT NULL,
        name NVARCHAR(180) NOT NULL,
        category_code NVARCHAR(30) NOT NULL,
        unit_code NVARCHAR(30) NOT NULL,
        sale_price DECIMAL(18, 4) NOT NULL,
        track_remnants BIT NOT NULL
    );

    INSERT INTO @Products (sku, name, category_code, unit_code, sale_price, track_remnants)
    VALUES
    (N'VM-0001', N'Acrílico “Black Ice” de 40', N'GLASS',     N'SHEET', 497.8300, 1),
    (N'VM-0002', N'Acrílico “Cañaveral” bronce de 36', N'GLASS',     N'SHEET', 447.8300, 1),
    (N'VM-0003', N'Acrílico “Cuadricula” humo de 40', N'GLASS',     N'SHEET', 497.3900, 1),
    (N'VM-0004', N'Acrílico “Delfin Celeste” de 24', N'GLASS',     N'SHEET', 331.3000, 1),
    (N'VM-0005', N'Acrílico “Estrella Bronce” de 36', N'GLASS',     N'SHEET', 495.6500, 1),
    (N'VM-0006', N'Acrílico “Lluvia Celeste” de 30', N'GLASS',     N'SHEET', 413.0400, 1),
    (N'VM-0007', N'Acrílico “Nevado Neutro” de 40', N'GLASS',     N'SHEET', 331.3000, 1),
    (N'VM-0008', N'Acrílico “Transparente” 3mm de 4X8', N'GLASS',     N'SHEET', 1608.7000, 1),
    (N'VM-0009', N'Angulo de 1 ½ X 1/8', N'ALUMINUM',     N'BAR', 608.7000, 0),
    (N'VM-0010', N'Angulo de 1 x 1 x 0.62 Natural', N'ALUMINUM',     N'BAR', 212.9500, 0),
    (N'VM-0011', N'Angulo de 1/2X3/8 - BR', N'ALUMINUM',     N'BAR', 86.9600, 0),
    (N'VM-0012', N'Angulo galvanizado de 1 ¼ X 1 ¼ 1 ¼ X 10 para tablilla', N'PVC',     N'BAR', 20.0000, 0),
    (N'VM-0013', N'Barra de empuje PTA abatible - AL', N'HARDWARE',     N'UNIT', 217.3900, 0),
    (N'VM-0014', N'Barra decorativa 9x15 PVC - BL', N'PVC',     N'BAR', 52.1700, 0),
    (N'VM-0015', N'Barra decorativa de 9X5 madera', N'ACCESSORIES',     N'UNIT', 68.7000, 0),
    (N'VM-0016', N'Batiente BK sencillo P/V Abat. C/Sello', N'ALUMINUM',     N'BAR', 132.0600, 0),
    (N'VM-0017', N'Batiente BL sencillo P/V Abat. C/Sello', N'ALUMINUM',     N'BAR', 118.4400, 0),
    (N'VM-0018', N'Batiente cristal Black sencillo C/Sello', N'ALUMINUM',     N'BAR', 46.4900, 0),
    (N'VM-0019', N'Batiente cristal sencillo 88 BK C/Sello', N'ALUMINUM',     N'BAR', 105.2900, 0),
    (N'VM-0020', N'Batiente de presion - BL', N'ALUMINUM',     N'BAR', 100.0000, 0),
    (N'VM-0021', N'Batiente de presion - Nogal', N'ALUMINUM',     N'BAR', 139.1300, 0),
    (N'VM-0022', N'Batiente para abatible - Negro', N'ALUMINUM',     N'BAR', 117.3900, 0),
    (N'VM-0023', N'Batiente para abatible PVC - BL', N'PVC',     N'BAR', 134.7800, 0),
    (N'VM-0024', N'Batiente para corrediza PVC - BL', N'PVC',     N'BAR', 60.0000, 0),
    (N'VM-0025', N'Batiente para corrediza PVC - N', N'PVC',     N'BAR', 93.9100, 0),
    (N'VM-0026', N'Batiente vidrio ventana proyectable bro', N'GLASS',     N'SHEET', 342.3600, 1),
    (N'VM-0027', N'Bisagra de friccion 10pulg', N'HARDWARE',     N'UNIT', 117.7900, 0),
    (N'VM-0028', N'Bisagra de piano', N'HARDWARE',     N'UNIT', 260.8700, 0),
    (N'VM-0029', N'Bisagra para proyectable de 12', N'HARDWARE',     N'UNIT', 103.4800, 0),
    (N'VM-0030', N'Cabezal C/M XLT C/Canal - BL', N'ALUMINUM',     N'BAR', 482.6100, 0),
    (N'VM-0031', N'Cabezal C/M XLT C/Canal - BR', N'ALUMINUM',     N'BAR', 433.0400, 0),
    (N'VM-0032', N'Cabezal C/M XLT C/Canal - Nogal', N'ALUMINUM',     N'BAR', 533.0400, 0),
    (N'VM-0033', N'Cabezal celosia - MF', N'ALUMINUM',     N'BAR', 260.8700, 0),
    (N'VM-0034', N'Cabezal hoja PTA corrediza clasica - BL', N'ALUMINUM',     N'BAR', 439.3200, 0),
    (N'VM-0035', N'Cabezal hoja PTA corrediza clasica - BR', N'ALUMINUM',     N'BAR', 448.1600, 0),
    (N'VM-0036', N'Cabezal hoja XLT - BL', N'ALUMINUM',     N'BAR', 420.8700, 0),
    (N'VM-0037', N'Cabezal hoja XLT - BR', N'ALUMINUM',     N'BAR', 443.4800, 0),
    (N'VM-0038', N'Cabezal hoja XLT - Nogal', N'ALUMINUM',     N'BAR', 484.3500, 0),
    (N'VM-0039', N'Caja de madera P/Vidrio en "M"', N'GLASS',     N'SHEET', 592.5000, 1),
    (N'VM-0040', N'Canal de 1/2x1/2 blanco', N'ALUMINUM',     N'BAR', 111.6600, 0),
    (N'VM-0041', N'Canal de 1/2X1/2 liviano - AL', N'ALUMINUM',     N'BAR', 91.3000, 0),
    (N'VM-0042', N'Canal de 1/2X1/2 liviano - BR', N'ALUMINUM',     N'BAR', 106.9600, 0),
    (N'VM-0043', N'Canal de 1/2X1/2 liviano - Nogal', N'ALUMINUM',     N'BAR', 121.7400, 0),
    (N'VM-0044', N'Canal de 1/2X1/2 pesado - AL', N'ALUMINUM',     N'BAR', 130.4300, 0),
    (N'VM-0045', N'Canal de 1/2X1/2 pesado - BR', N'ALUMINUM',     N'BAR', 147.8300, 0),
    (N'VM-0046', N'Canal de carga', N'ALUMINUM',     N'BAR', 54.7800, 0),
    (N'VM-0047', N'Canal de furring', N'ALUMINUM',     N'BAR', 33.4800, 0),
    (N'VM-0048', N'Canal H zapato - AL', N'ALUMINUM',     N'BAR', 191.3000, 0),
    (N'VM-0049', N'Carrete felpa V/C clasica y PVC 400mts', N'PVC',     N'UNIT', 684.5200, 0),
    (N'VM-0050', N'Cerradura “ DIP ” negra P/Vent. Corrediza', N'HARDWARE',     N'UNIT', 47.8300, 0),
    (N'VM-0051', N'Cerradura “ DIP ” P/PTA Corrediza - BR', N'HARDWARE',     N'UNIT', 65.2200, 0),
    (N'VM-0052', N'Cerradura automática P/Vent. Económico - AL', N'HARDWARE',     N'UNIT', 47.8300, 0),
    (N'VM-0053', N'Cerradura automática P/Vent. Económico - BL', N'HARDWARE',     N'UNIT', 56.5200, 0),
    (N'VM-0054', N'Cerradura completa P/PTA abatible “ DIP ” - AL', N'HARDWARE',     N'UNIT', 317.3900, 0),
    (N'VM-0055', N'Cerradura media luna blanca con recibidor', N'HARDWARE',     N'UNIT', 39.1300, 0),
    (N'VM-0056', N'Cerradura P/Vent. Guillotina - BR', N'HARDWARE',     N'UNIT', 30.4300, 0),
    (N'VM-0057', N'Cerradura resilencia “ DIP ” (Blanca)', N'HARDWARE',     N'UNIT', 404.3500, 0),
    (N'VM-0058', N'Cierrapuertas “ DIPROC ” pesado natural', N'HARDWARE',     N'UNIT', 413.0400, 0),
    (N'VM-0059', N'Cierrapuertas “ OLIMPIA ” pesado - BR', N'HARDWARE',     N'UNIT', 730.4300, 0),
    (N'VM-0060', N'Cierre automático derecho P/ PVC - N', N'PVC',     N'UNIT', 34.7800, 0),
    (N'VM-0061', N'Cierre automático derecho para PVC - BL', N'PVC',     N'UNIT', 43.4700, 0),
    (N'VM-0062', N'Cierre automático izquierdo P/ PVC - N', N'PVC',     N'UNIT', 34.7800, 0),
    (N'VM-0063', N'Cierre automático izquierdo para PVC - BL', N'PVC',     N'UNIT', 43.4700, 0),
    (N'VM-0064', N'Cierre para ventana Guillotina WH', N'HARDWARE',     N'UNIT', 21.4500, 0),
    (N'VM-0065', N'Cilindro P/Cerradura completa “ DIP ” - AL', N'HARDWARE',     N'UNIT', 152.1700, 0),
    (N'VM-0066', N'Cilindro P/Cerradura completa “ DIP ” - BL', N'HARDWARE',     N'UNIT', 173.9100, 0),
    (N'VM-0067', N'Clavo de acero de 1', N'HARDWARE',     N'UNIT', 56.5200, 0),
    (N'VM-0068', N'Contramarco 2 carriles PVC - BL', N'PVC',     N'BAR', 221.7400, 0),
    (N'VM-0069', N'Contramarco 3 carriles Liviano PVC - BL', N'PVC',     N'BAR', 304.3500, 0),
    (N'VM-0070', N'Contramarco 3 carriles para corrediza PVC - BL', N'PVC',     N'BAR', 307.8300, 0),
    (N'VM-0071', N'Contramarco Black P/V fijo', N'ALUMINUM',     N'BAR', 447.9800, 0),
    (N'VM-0072', N'Contramarco blanco P/V fija con sello', N'ALUMINUM',     N'BAR', 319.6500, 0),
    (N'VM-0073', N'Contramarco de 2 carriles PVC - N', N'PVC',     N'BAR', 355.6500, 0),
    (N'VM-0074', N'Contramarco de 3 carriles P/ PVC - N', N'PVC',     N'BAR', 487.8300, 0),
    (N'VM-0075', N'Contramarco fijo 60 negro', N'ALUMINUM',     N'BAR', 364.3500, 0),
    (N'VM-0076', N'Contramarco fijo 60 PVC - BL', N'PVC',     N'BAR', 343.0000, 0),
    (N'VM-0077', N'Contramarco ventana Proyectable - BR', N'ALUMINUM',     N'BAR', 808.9500, 0),
    (N'VM-0078', N'Cornisa PVC - BL', N'PVC',     N'BAR', 86.9600, 0),
    (N'VM-0079', N'Cornisa PVC Madera Cerezo C-L001', N'PVC',     N'BAR', 95.6500, 0),
    (N'VM-0080', N'Corte especial', N'ACCESSORIES',     N'UNIT', 86.9600, 0),
    (N'VM-0081', N'Cremona para corrediza de 1600MM - PVC', N'PVC',     N'UNIT', 143.4800, 0),
    (N'VM-0082', N'Cremona para corrediza de 800MM - PVC', N'PVC',     N'UNIT', 89.5700, 0),
    (N'VM-0083', N'Division vent. y ventanilla Proyectable - BR', N'ALUMINUM',     N'BAR', 770.3800, 0),
    (N'VM-0084', N'Doble canal para vitrina - AL', N'ALUMINUM',     N'BAR', 189.5700, 0),
    (N'VM-0085', N'Empaque 730 TYPSA P/Vidrio de 5mm', N'GLASS',     N'SHEET', 1226.0800, 1),
    (N'VM-0086', N'Empaque 734 TYPSA 1m', N'ACCESSORIES',     N'METER', 10.4300, 0),
    (N'VM-0087', N'Empaque grande P/Malla PVC R/L 12.5', N'PVC',     N'UNIT', 591.3000, 0),
    (N'VM-0088', N'Empaque para malla de aluminio H-111 (48/35)', N'ACCESSORIES',     N'UNIT', 613.0400, 0),
    (N'VM-0089', N'Empaque para malla PVC', N'PVC',     N'UNIT', 121.7400, 0),
    (N'VM-0090', N'Encuentro central color Black S80', N'ALUMINUM',     N'BAR', 107.6300, 0),
    (N'VM-0091', N'Encuentro central color blanco S80', N'ALUMINUM',     N'BAR', 97.0000, 0),
    (N'VM-0092', N'Enmallador', N'ACCESSORIES',     N'UNIT', 171.9100, 0),
    (N'VM-0093', N'Escuadra metalica P/Malla', N'HARDWARE',     N'UNIT', 147.8300, 0),
    (N'VM-0094', N'Escuadra plastica - BL', N'HARDWARE',     N'UNIT', 3.0400, 0),
    (N'VM-0095', N'Escuadra plastica - BR', N'HARDWARE',     N'UNIT', 2.3500, 0),
    (N'VM-0096', N'Espejo 3mm (1/8) - 1.83X2.44', N'GLASS',     N'SHEET', 782.6100, 1),
    (N'VM-0097', N'Espejo biselado miralite 5mm en metros', N'GLASS',     N'METER', 1700.0000, 0),
    (N'VM-0098', N'Esquinera para malla color blanco', N'ACCESSORIES',     N'UNIT', 2.9000, 0),
    (N'VM-0099', N'Esquinera para malla color natural', N'ACCESSORIES',     N'UNIT', 1.8700, 0),
    (N'VM-0100', N'Felpa P/Corrediza " DIP " rollo', N'ACCESSORIES',     N'UNIT', 782.6100, 0),
    (N'VM-0101', N'Felpa P/Económico - BR 1M', N'ACCESSORIES',     N'METER', 2.6100, 0),
    (N'VM-0102', N'Felpa P/PVC Metros', N'PVC',     N'UNIT', 2.6100, 0),
    (N'VM-0103', N'Felpa P/PVC Rollo', N'PVC',     N'UNIT', 739.1300, 0),
    (N'VM-0104', N'Fiberglass negra de 42 rollo de 30.5m', N'ACCESSORIES',     N'UNIT', 556.5200, 0),
    (N'VM-0105', N'Fiberglass negra de 48', N'ACCESSORIES',     N'UNIT', 634.7800, 0),
    (N'VM-0106', N'Guias para corrediza blanca', N'HARDWARE',     N'UNIT', 2.6100, 0),
    (N'VM-0107', N'Guias para corrediza negras', N'HARDWARE',     N'UNIT', 2.6100, 0),
    (N'VM-0108', N'Haladera de concha – AL', N'HARDWARE',     N'UNIT', 47.8300, 0),
    (N'VM-0109', N'Haladera de concha blanco', N'HARDWARE',     N'UNIT', 115.9800, 0),
    (N'VM-0110', N'Haladera de lujo “ DIP ” - BR', N'HARDWARE',     N'UNIT', 373.9100, 0),
    (N'VM-0111', N'Haladera de lujo turbular “ OLIMPIA ” - AL', N'HARDWARE',     N'UNIT', 485.2200, 0),
    (N'VM-0112', N'Haladera doblada negra 1" 9 (10/Caja)', N'HARDWARE',     N'UNIT', 385.2500, 0),
    (N'VM-0113', N'Haladera para malla corrediza negra', N'HARDWARE',     N'UNIT', 6.9600, 0),
    (N'VM-0114', N'Haladera para PVC - BL', N'PVC',     N'UNIT', 56.5200, 0),
    (N'VM-0115', N'Haladera para PVC - N', N'PVC',     N'UNIT', 56.5200, 0),
    (N'VM-0116', N'Haladera recta cromada de 1.20mts', N'HARDWARE',     N'UNIT', 956.5200, 0),
    (N'VM-0117', N'Haladera recta cromada de 60cm', N'HARDWARE',     N'UNIT', 482.6100, 0),
    (N'VM-0118', N'Haladera recta cromada de 90cm', N'HARDWARE',     N'UNIT', 680.0000, 0),
    (N'VM-0119', N'Hoja de puerta abatible para apertura extra PVC - BL', N'PVC',     N'BAR', 639.1300, 0),
    (N'VM-0120', N'Hoja de puerta corrediza PVC – BL', N'PVC',     N'BAR', 300.0000, 0),
    (N'VM-0121', N'Hoja de ventana corrediza PVC - BL', N'PVC',     N'BAR', 229.5600, 0),
    (N'VM-0122', N'Hoja de ventana corrediza PVC - N', N'PVC',     N'BAR', 362.6100, 0),
    (N'VM-0123', N'Hoja de vidrio Dark Blue 1.65X2.14 5mm', N'GLASS',     N'SHEET', 1109.0100, 1),
    (N'VM-0124', N'Hoja de vidrio Reflec Bronce 1.65X2.14 5mm', N'GLASS',     N'SHEET', 758.4500, 1),
    (N'VM-0125', N'Hoja externa de ventana abatible negro', N'ACCESSORIES',     N'UNIT', 516.5200, 0),
    (N'VM-0126', N'Hoja mosquitera para corrediza PVC - BL', N'PVC',     N'UNIT', 112.1700, 0),
    (N'VM-0127', N'Hoja mosquitera V2 PVC - BL', N'PVC',     N'UNIT', 132.1700, 0),
    (N'VM-0128', N'Hoja mosquitero negro', N'ACCESSORIES',     N'UNIT', 209.5700, 0),
    (N'VM-0129', N'Hoja Vid Ref Dark Blue 1.65X2.14 5mm', N'ACCESSORIES',     N'UNIT', 824.4000, 0),
    (N'VM-0130', N'Hoja vidrio claro 1.65X2.14 5mm', N'GLASS',     N'SHEET', 700.2400, 1),
    (N'VM-0131', N'Hoja vidrio gris oscuro 1.65X2.14 6mm', N'GLASS',     N'SHEET', 1609.2800, 1),
    (N'VM-0132', N'Hoja vidrio laminado bronce 1.65X2.14 6mm', N'GLASS',     N'SHEET', 1057.4900, 1),
    (N'VM-0133', N'Jamba C/M PTA corrediza clasica 21# - BL', N'ALUMINUM',     N'BAR', 527.7200, 0),
    (N'VM-0134', N'Jamba C/M PTA corrediza clasica 21# - BR', N'ALUMINUM',     N'BAR', 545.6500, 0),
    (N'VM-0135', N'Jamba C/M XLT - BL', N'ALUMINUM',     N'BAR', 594.7800, 0),
    (N'VM-0136', N'Jamba C/M XLT - BR', N'ALUMINUM',     N'BAR', 626.0900, 0),
    (N'VM-0137', N'Jamba C/M XLT - Nogal', N'ALUMINUM',     N'BAR', 690.4300, 0),
    (N'VM-0138', N'Jamba doble enclipada 5', N'ALUMINUM',     N'BAR', 86.7800, 0),
    (N'VM-0139', N'Jamba hoja Económico - BR', N'ALUMINUM',     N'BAR', 188.7000, 0),
    (N'VM-0140', N'Jamba hoja PTA de baño - BL', N'ALUMINUM',     N'BAR', 126.9600, 0),
    (N'VM-0141', N'Jamba hoja puerta de baño - AL', N'ALUMINUM',     N'BAR', 127.8300, 0),
    (N'VM-0142', N'Jamba llavín Económico - AL', N'ALUMINUM',     N'BAR', 246.0900, 0),
    (N'VM-0143', N'Jamba llavín Económico - BL', N'ALUMINUM',     N'BAR', 300.0000, 0),
    (N'VM-0144', N'Jamba llavín Económico - BR', N'ALUMINUM',     N'BAR', 313.0400, 0),
    (N'VM-0145', N'Jamba llavín Económico - Nogal', N'ALUMINUM',     N'BAR', 333.9100, 0),
    (N'VM-0146', N'Jamba llavin PTA abatible - BR', N'ALUMINUM',     N'BAR', 853.9100, 0),
    (N'VM-0147', N'Jamba llavín XLT - BL', N'ALUMINUM',     N'BAR', 475.6500, 0),
    (N'VM-0148', N'Jamba llavín XLT - BR', N'ALUMINUM',     N'BAR', 500.0000, 0),
    (N'VM-0149', N'Jamba llavín XLT - Nogal', N'ALUMINUM',     N'BAR', 586.9600, 0),
    (N'VM-0150', N'Jamba traslape XLT - BL', N'ALUMINUM',     N'BAR', 451.3000, 0),
    (N'VM-0151', N'Jamba traslape XLT - BR', N'ALUMINUM',     N'BAR', 474.7800, 0),
    (N'VM-0152', N'Jamba traslape XLT - Nogal', N'ALUMINUM',     N'BAR', 526.0900, 0),
    (N'VM-0153', N'Jamba y cabezal C/M Económico si canal - BL', N'ALUMINUM',     N'BAR', 384.3400, 0),
    (N'VM-0154', N'Jamba y cabezal C/M Económico sin canal - AL', N'ALUMINUM',     N'BAR', 326.9600, 0),
    (N'VM-0155', N'Jamba y cabezal C/M Económico sin canal - BR', N'ALUMINUM',     N'BAR', 288.7000, 0),
    (N'VM-0156', N'Jamba y cabezal C/M Económico sin canal - Nogal', N'ALUMINUM',     N'BAR', 426.0900, 0),
    (N'VM-0157', N'Kit de varilla roscada', N'ACCESSORIES',     N'UNIT', 97.3900, 0),
    (N'VM-0158', N'Lamina de bronce nevado 1.83X2.44 5mm', N'GLASS',     N'SHEET', 1611.3200, 1),
    (N'VM-0159', N'Lamina de espejo 2.44X1.83 3mm', N'GLASS',     N'SHEET', 685.4200, 1),
    (N'VM-0160', N'Lamina doble capa claro 3.30X2.14 6.38mm', N'GLASS',     N'SHEET', 2601.3600, 1),
    (N'VM-0161', N'Lamina Vid claro 3.30X2.44 5+5 (10.38mm)', N'GLASS',     N'SHEET', 4117.4900, 1),
    (N'VM-0162', N'Lamina Vid laminado bronce 3.30X2.14 6.38mm', N'GLASS',     N'SHEET', 3197.5800, 1),
    (N'VM-0163', N'Lamina Vid. Laminado bronce 10mm - 3.30X2.44', N'GLASS',     N'SHEET', 5183.8200, 1),
    (N'VM-0164', N'Lamina vidrio bronce 3.3X2.44 5mm', N'GLASS',     N'SHEET', 2011.8500, 1),
    (N'VM-0165', N'Lamina vidrio gris oscuro 1.83X2.44 5mm', N'GLASS',     N'SHEET', 1180.4300, 1),
    (N'VM-0166', N'Llavin P/Vitrina', N'ACCESSORIES',     N'UNIT', 60.8700, 0),
    (N'VM-0167', N'Manecilla doble con llave para PVC - N', N'PVC',     N'UNIT', 200.0000, 0),
    (N'VM-0168', N'Manecilla doble con llaves para PVC - BL', N'PVC',     N'UNIT', 200.0000, 0),
    (N'VM-0169', N'Manecilla sencilla sin llave para PVC - BL', N'PVC',     N'UNIT', 73.9100, 0),
    (N'VM-0170', N'Maquina soldadora de PVC portátil', N'PVC',     N'UNIT', 5739.1300, 0),
    (N'VM-0171', N'Marco mosquitero americano - BL', N'ALUMINUM',     N'BAR', 318.2600, 0),
    (N'VM-0172', N'Marco mosquitero americano - BR', N'ALUMINUM',     N'BAR', 278.2600, 0),
    (N'VM-0173', N'Marco mosquitero americano - Nogal', N'ALUMINUM',     N'BAR', 330.4300, 0),
    (N'VM-0174', N'Masilla blanca', N'SEALANTS',     N'UNIT', 56.5200, 0),
    (N'VM-0175', N'Masilla negra', N'SEALANTS',     N'UNIT', 56.5200, 0),
    (N'VM-0176', N'Media lamina gris intermed 1.65X2.14 5mm', N'GLASS',     N'SHEET', 973.6100, 1),
    (N'VM-0177', N'Media lamina Vid bronce 1.65X2.14 5mm', N'GLASS',     N'SHEET', 707.5200, 1),
    (N'VM-0178', N'Media lamina vidrio bronce 1.65X2.44 5mm', N'GLASS',     N'SHEET', 867.4900, 1),
    (N'VM-0179', N'Moldura clásica - BR', N'ALUMINUM',     N'BAR', 117.3900, 0),
    (N'VM-0180', N'Moldura colonial - BL', N'ALUMINUM',     N'BAR', 93.1200, 0),
    (N'VM-0181', N'Moldura colonial - Madera', N'ALUMINUM',     N'BAR', 143.4800, 0),
    (N'VM-0182', N'Moldura externa PVC Madera Cerezo', N'PVC',     N'BAR', 78.2600, 0),
    (N'VM-0183', N'Moldura para malla blanca', N'ALUMINUM',     N'BAR', 64.6100, 0),
    (N'VM-0184', N'Moldura para malla natural', N'ALUMINUM',     N'BAR', 65.8900, 0),
    (N'VM-0185', N'Moldura PVC externa blanca', N'PVC',     N'BAR', 86.9600, 0),
    (N'VM-0186', N'Moldura trapezoidal - BR', N'ALUMINUM',     N'BAR', 114.7800, 0),
    (N'VM-0187', N'Moldura trapezoidal - Nogal', N'ALUMINUM',     N'BAR', 128.7000, 0),
    (N'VM-0188', N'Operador P/Celosia', N'HARDWARE',     N'UNIT', 60.8700, 0),
    (N'VM-0189', N'Papel Teflón para maquina PVC', N'PVC',     N'UNIT', 260.8700, 0),
    (N'VM-0190', N'Pata P/Puerta', N'HARDWARE',     N'UNIT', 92.1700, 0),
    (N'VM-0191', N'Perfil malla - BL', N'ALUMINUM',     N'BAR', 56.5200, 0),
    (N'VM-0192', N'Perfil malla - BR', N'ALUMINUM',     N'BAR', 60.8700, 0),
    (N'VM-0193', N'Perfil malla - MF', N'ALUMINUM',     N'BAR', 50.5300, 0),
    (N'VM-0194', N'Perfil malla - Nogal', N'ALUMINUM',     N'BAR', 69.5700, 0),
    (N'VM-0195', N'Picaporte " DIP " - BR', N'HARDWARE',     N'UNIT', 78.2600, 0),
    (N'VM-0196', N'Pivote “ DIP ” - AL', N'HARDWARE',     N'UNIT', 147.8300, 0),
    (N'VM-0197', N'Pivote “ DIP ” - BR', N'HARDWARE',     N'UNIT', 147.8300, 0),
    (N'VM-0198', N'Pivote “ OLIMPIA ” - AL', N'HARDWARE',     N'UNIT', 189.5700, 0),
    (N'VM-0199', N'Planchuela 1m', N'ALUMINUM',     N'BAR', 173.9100, 0),
    (N'VM-0200', N'Refuerzo para hoja mosquitero', N'ACCESSORIES',     N'UNIT', 93.9100, 0),
    (N'VM-0201', N'Remaches 5/32X1/2', N'HARDWARE',     N'UNIT', 0.4100, 0),
    (N'VM-0202', N'Resorte Guillotina 24', N'HARDWARE',     N'UNIT', 194.6400, 0),
    (N'VM-0203', N'Resorte P/Guillotina de 22', N'HARDWARE',     N'UNIT', 126.0900, 0),
    (N'VM-0204', N'Resorte P/Guillotina de 28', N'HARDWARE',     N'UNIT', 142.6100, 0),
    (N'VM-0205', N'Rodo de acero de 1 ¼', N'HARDWARE',     N'UNIT', 20.0000, 0),
    (N'VM-0206', N'Rodo de acero de 1 ½', N'HARDWARE',     N'UNIT', 22.6100, 0),
    (N'VM-0207', N'Rodo de acero para malla corrediza', N'HARDWARE',     N'UNIT', 14.7800, 0),
    (N'VM-0208', N'Rodo doble para PVC', N'PVC',     N'UNIT', 9.5700, 0),
    (N'VM-0209', N'Rodo P/PTA de baño', N'HARDWARE',     N'UNIT', 6.0900, 0),
    (N'VM-0210', N'Rodo P/Vent. Económico', N'HARDWARE',     N'UNIT', 10.4300, 0),
    (N'VM-0211', N'Rodo para mosquitero PVC', N'PVC',     N'UNIT', 4.3400, 0),
    (N'VM-0212', N'Rodo sencillo para PVC', N'PVC',     N'UNIT', 6.0800, 0),
    (N'VM-0213', N'S62 rodo sencillo de ventana', N'HARDWARE',     N'UNIT', 4.1300, 0),
    (N'VM-0214', N'Silicon Contrupega', N'SEALANTS',     N'TUBE', 73.0400, 0),
    (N'VM-0215', N'Silicon transparente " ABRO "', N'SEALANTS',     N'TUBE', 86.0900, 0),
    (N'VM-0216', N'SOP. Pasamanos muro para tubo de 42.4mm', N'ALUMINUM',     N'BAR', 346.4800, 0),
    (N'VM-0217', N'Soporte de barra (sapo) - AL', N'HARDWARE',     N'UNIT', 21.7400, 0),
    (N'VM-0218', N'Soporte de barra de empuje blanco', N'HARDWARE',     N'UNIT', 92.2100, 0),
    (N'VM-0219', N'Sujetador tela metalica nat.', N'HARDWARE',     N'UNIT', 0.8800, 0),
    (N'VM-0220', N'Tablilla PVC - BL brillante de 25CM/P-009', N'PVC',     N'BAR', 171.3000, 0),
    (N'VM-0221', N'Tablilla PVC - BL estampado de 25CM/P-003', N'PVC',     N'BAR', 171.3000, 0),
    (N'VM-0222', N'Tablilla PVC - BL huno de 25CM/P-005', N'PVC',     N'BAR', 156.5200, 0),
    (N'VM-0223', N'Tablilla PVC - BL Matte de 25CM/P-007', N'PVC',     N'BAR', 140.8700, 0),
    (N'VM-0224', N'Tablilla PVC machimbrada Madera Cerezo de 25CM', N'PVC',     N'BAR', 173.0400, 0),
    (N'VM-0225', N'Taco fischer S6', N'HARDWARE',     N'UNIT', 0.3900, 0),
    (N'VM-0226', N'Tapa decorativa', N'HARDWARE',     N'UNIT', 0.2100, 0),
    (N'VM-0227', N'Tapa decorativa para PVC - BL', N'PVC',     N'UNIT', 1.7400, 0),
    (N'VM-0228', N'Tapa drenaje PVC - BL', N'PVC',     N'UNIT', 2.7400, 0),
    (N'VM-0229', N'Teflon 1# x 3# pie de 0.16mm', N'ACCESSORIES',     N'UNIT', 188.5500, 0),
    (N'VM-0230', N'Tornillo de (7X7/16) punta broca para vitrina', N'HARDWARE',     N'UNIT', 0.1700, 0),
    (N'VM-0231', N'Tornillo de (7X7/16) punta fina', N'HARDWARE',     N'UNIT', 0.1700, 0),
    (N'VM-0232', N'Tornillo de armar goloso (10X3/4)', N'HARDWARE',     N'UNIT', 0.6100, 0),
    (N'VM-0233', N'Tornillo de económica (10X3)', N'HARDWARE',     N'UNIT', 1.4900, 0),
    (N'VM-0234', N'Tornillo de instalar avellanado (10X1 ½)', N'HARDWARE',     N'UNIT', 0.8300, 0),
    (N'VM-0235', N'Tornillo de instalar goloso (10X1 ½)', N'HARDWARE',     N'UNIT', 0.8300, 0),
    (N'VM-0236', N'Tornillo P/Abatible (14X3) avellanado', N'HARDWARE',     N'UNIT', 1.9100, 0),
    (N'VM-0237', N'Tornillo P/Haladera recta negro', N'HARDWARE',     N'UNIT', 13.0400, 0),
    (N'VM-0238', N'Tornillo P/PTA de baño (8X1)', N'HARDWARE',     N'UNIT', 0.4800, 0),
    (N'VM-0239', N'Tornillo punta broca P/Tapicería (8X1/2)', N'HARDWARE',     N'UNIT', 0.3100, 0),
    (N'VM-0240', N'Tornillo punta fina P/Tapicería (8X1/2)', N'HARDWARE',     N'UNIT', 0.2800, 0),
    (N'VM-0241', N'Trancador de proyectable blanco', N'HARDWARE',     N'UNIT', 80.0000, 0),
    (N'VM-0242', N'Trancador para Proyectable - BR', N'HARDWARE',     N'UNIT', 38.9600, 0),
    (N'VM-0243', N'Traslape 4 hojas color black', N'ALUMINUM',     N'BAR', 107.0900, 0),
    (N'VM-0244', N'Traslape 4 hojas color blanco', N'ALUMINUM',     N'BAR', 95.0200, 0),
    (N'VM-0245', N'Traslape black', N'ALUMINUM',     N'BAR', 94.8600, 0),
    (N'VM-0246', N'Traslape black 4 hojas 88', N'ALUMINUM',     N'BAR', 104.3000, 0),
    (N'VM-0247', N'Travesaño fijo 60 negro', N'ALUMINUM',     N'BAR', 394.7800, 0),
    (N'VM-0248', N'Tubo 1 3/4 x 1 3/4 1Pestaña blanco', N'ALUMINUM',     N'BAR', 705.5000, 0),
    (N'VM-0249', N'Tubo 1 3/4 x 4 - BL', N'ALUMINUM',     N'BAR', 895.4700, 0),
    (N'VM-0250', N'Tubo 1 3/4 x 4 - BR', N'ALUMINUM',     N'BAR', 857.5600, 0),
    (N'VM-0251', N'Tubo acero Inox 42.4mm x 1 5mm x 6000mm', N'ALUMINUM',     N'BAR', 1376.6200, 0),
    (N'VM-0252', N'Tubo de 1 ¾ X 1 - AL', N'ALUMINUM',     N'BAR', 434.4900, 0),
    (N'VM-0253', N'Tubo de 1 ¾ X 1 - BL', N'ALUMINUM',     N'BAR', 417.3900, 0),
    (N'VM-0254', N'Tubo de 1 ¾ X 1 ¾ con 1 pestaña - AL', N'ALUMINUM',     N'BAR', 622.6100, 0),
    (N'VM-0255', N'Tubo de 1 ¾ X 1 ¾ con 1 pestaña - BL', N'ALUMINUM',     N'BAR', 582.6100, 0),
    (N'VM-0256', N'Tubo de 1 ¾ X 1 ¾ con 1 pestaña - Nogal', N'ALUMINUM',     N'BAR', 622.6100, 0),
    (N'VM-0257', N'Tubo de 1 ¾ X 4 con 1 pestana - AL', N'ALUMINUM',     N'BAR', 1066.9500, 0),
    (N'VM-0258', N'Tubo de 1 ¾ X 4 con 1 pestaña - BR', N'ALUMINUM',     N'BAR', 1076.5200, 0),
    (N'VM-0259', N'Tubo de 1 ¾ X 4 con 1 pestaña - Nogal', N'ALUMINUM',     N'BAR', 1061.7400, 0),
    (N'VM-0260', N'Tubo de 1 ¾ X 4 con 2 pestaña - BR', N'ALUMINUM',     N'BAR', 1100.0000, 0),
    (N'VM-0261', N'Tubo de 1 ¾ X 4 con 2 pestaña - Nogal', N'ALUMINUM',     N'BAR', 1330.4300, 0),
    (N'VM-0262', N'Tubo de 1 ¾ X 4 liso - AL', N'ALUMINUM',     N'BAR', 992.1700, 0),
    (N'VM-0263', N'Tubo de 1 ¾ X 4 liso - BR', N'ALUMINUM',     N'BAR', 1022.6100, 0),
    (N'VM-0264', N'Tubo de 1X1 - AL', N'ALUMINUM',     N'BAR', 267.8300, 0),
    (N'VM-0265', N'Tubo de 1X1 con 2 canales incorporado tipo “ L ”', N'ALUMINUM',     N'BAR', 395.6500, 0),
    (N'VM-0266', N'Tubo de 1X1 con 2 canales incorporado tipo “ T ”', N'ALUMINUM',     N'BAR', 395.6500, 0),
    (N'VM-0267', N'Tubo de 1X1 con canal incorporado', N'ALUMINUM',     N'BAR', 330.4300, 0),
    (N'VM-0268', N'Umbral C/M Económico sin canal - AL', N'ALUMINUM',     N'BAR', 199.1300, 0),
    (N'VM-0269', N'Umbral C/M Económico sin canal - BL', N'ALUMINUM',     N'BAR', 217.3900, 0),
    (N'VM-0270', N'Umbral C/M Económico sin canal - Nogal', N'ALUMINUM',     N'BAR', 260.0000, 0),
    (N'VM-0271', N'Umbral C/M XLT C/Canal - BL', N'ALUMINUM',     N'BAR', 402.6100, 0),
    (N'VM-0272', N'Umbral C/M XLT C/Canal - BR', N'ALUMINUM',     N'BAR', 361.7400, 0),
    (N'VM-0273', N'Umbral C/M XLT C/Canal - Nogal', N'ALUMINUM',     N'BAR', 445.2200, 0),
    (N'VM-0274', N'Umbral celosia - MF', N'ALUMINUM',     N'BAR', 260.8700, 0),
    (N'VM-0275', N'Umbral hoja PTA corrediza clasica - BR', N'ALUMINUM',     N'BAR', 376.6000, 0),
    (N'VM-0276', N'Umbral hoja PTA corrediza clasica - BL', N'ALUMINUM',     N'BAR', 346.2000, 0),
    (N'VM-0277', N'Umbral hoja XLT - BL', N'ALUMINUM',     N'BAR', 355.6500, 0),
    (N'VM-0278', N'Umbral hoja XLT - BR', N'ALUMINUM',     N'BAR', 321.7400, 0),
    (N'VM-0279', N'Umbral hoja XLT - Nogal', N'ALUMINUM',     N'BAR', 408.7000, 0),
    (N'VM-0280', N'Umbral y cabezal hoja de Económico - AL', N'ALUMINUM',     N'BAR', 200.0000, 0),
    (N'VM-0281', N'Umbral y cabezal hoja de Económico - BL', N'ALUMINUM',     N'BAR', 220.8700, 0),
    (N'VM-0282', N'Umbral y cabezal hoja de Económico - BR', N'ALUMINUM',     N'BAR', 256.5200, 0),
    (N'VM-0283', N'Umbral y cabezal hoja de Económico - Nogal', N'ALUMINUM',     N'BAR', 263.4800, 0),
    (N'VM-0284', N'Unión PVC - BL', N'PVC',     N'BAR', 86.9600, 0),
    (N'VM-0285', N'Uñas de 1/8 P/Espejo', N'GLASS',     N'SHEET', 6.9600, 1),
    (N'VM-0286', N'Uñas de 3/16 P/Espejo', N'GLASS',     N'SHEET', 7.8300, 1),
    (N'VM-0287', N'Vidrio claro 4mm - 2.44X1.83', N'GLASS',     N'SHEET', 747.8300, 1),
    (N'VM-0288', N'Vidrio claro 5mm SPH - 1.605X2', N'GLASS',     N'SHEET', 678.2600, 1),
    (N'VM-0289', N'Vidrio flotado bronce 5mm (3/16) - 1.65X2.14', N'GLASS',     N'SHEET', 826.0900, 1),
    (N'VM-0290', N'Vidrio flotado bronce 5mm (3/16) - 3.30X2.14', N'GLASS',     N'SHEET', 1652.1700, 1),
    (N'VM-0291', N'Vidrio flotado claro 6mm - 1.65X2.14', N'GLASS',     N'SHEET', 915.6500, 1),
    (N'VM-0292', N'Vidrio laminado 3+3 claro 1.65X2.14 5mm', N'GLASS',     N'SHEET', 1123.8000, 1),
    (N'VM-0293', N'Vidrio laminado bronce 6mm (1/4) - 1.65X2.14', N'GLASS',     N'SHEET', 1652.1700, 1),
    (N'VM-0294', N'Vidrio laminado claro 6mm (1/4) - 1.65X2.14', N'GLASS',     N'SHEET', 1556.5200, 1),
    (N'VM-0295', N'Vidrio reflectivo azul 5mm - 1.65X2.14', N'GLASS',     N'SHEET', 963.0400, 1),
    (N'VM-0296', N'Vidrio reflectivo bronce 5mm (3/16) - 1.65X2.14', N'GLASS',     N'SHEET', 826.0800, 1),
    (N'VM-0297', N'Vidrio super gris 5mm - 1.83X2.14', N'GLASS',     N'SHEET', 1224.3500, 1),
    (N'VM-0298', N'Vidrio super gris 5mm - 1.83X2.44', N'GLASS',     N'SHEET', 1352.1700, 1),
    (N'VM-0299', N'Vidrio super gris 5mm (3/16) - 1.65X2.14', N'GLASS',     N'SHEET', 1069.5600, 1),
    (N'VM-0300', N'Vidrio super gris 5mm X G - 1.65X2.44', N'GLASS',     N'SHEET', 1173.9100, 1),
    (N'VM-0301', N'Vidrio tapiz bronce 5mm - 1.83X2.44', N'GLASS',     N'SHEET', 1256.5200, 1);

    IF (SELECT COUNT(*) FROM @Products) <> 301
        THROW 51003, 'El catálogo preparado no contiene 301 productos.', 1;

    IF EXISTS (
        SELECT 1 FROM @Products AS source
        LEFT JOIN dbo.inventory_categories AS category ON category.code = source.category_code
        LEFT JOIN dbo.inventory_units AS unit_measure ON unit_measure.code = source.unit_code
        WHERE category.id IS NULL OR unit_measure.id IS NULL
    )
        THROW 51004, 'Faltan categorías o unidades. Ejecute primero InventoryCatalogSeeder.', 1;

    DELETE transfer
    FROM dbo.inventory_transfers AS transfer
    INNER JOIN dbo.inventory_products AS product ON product.id = transfer.product_id;

    DELETE count_line
    FROM dbo.inventory_count_lines AS count_line
    INNER JOIN dbo.inventory_products AS product ON product.id = count_line.product_id;

    DELETE FROM dbo.inventory_counts
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.inventory_count_lines AS count_line
        WHERE count_line.count_id = inventory_counts.id
    );

    DELETE remnant
    FROM dbo.inventory_remnants AS remnant
    INNER JOIN dbo.inventory_products AS product ON product.id = remnant.product_id;

    DELETE kit_item
    FROM dbo.inventory_kit_items AS kit_item
    INNER JOIN dbo.inventory_products AS product ON product.id = kit_item.product_id;

    DELETE FROM dbo.inventory_kits
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.inventory_kit_items AS kit_item
        WHERE kit_item.kit_id = inventory_kits.id
    );

    DELETE supplier_product
    FROM dbo.inventory_supplier_products AS supplier_product
    INNER JOIN dbo.inventory_products AS product ON product.id = supplier_product.product_id;

    DELETE product_unit
    FROM dbo.inventory_product_units AS product_unit
    INNER JOIN dbo.inventory_products AS product ON product.id = product_unit.product_id;

    DELETE product_attribute
    FROM dbo.inventory_product_attributes AS product_attribute
    INNER JOIN dbo.inventory_products AS product ON product.id = product_attribute.product_id;

    DELETE balance
    FROM dbo.inventory_stock_balances AS balance
    INNER JOIN dbo.inventory_products AS product ON product.id = balance.product_id;

    DELETE movement_line
    FROM dbo.inventory_movement_lines AS movement_line
    INNER JOIN dbo.inventory_products AS product ON product.id = movement_line.product_id;

    DELETE FROM dbo.inventory_movements
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.inventory_movement_lines AS movement_line
        WHERE movement_line.movement_id = inventory_movements.id
    );

    DELETE FROM dbo.inventory_products;
    DBCC CHECKIDENT ('dbo.inventory_products', RESEED, 0) WITH NO_INFOMSGS;

    INSERT INTO dbo.inventory_products (
        category_id,
        base_unit_id,
        sku,
        barcode,
        name,
        description,
        product_type,
        minimum_stock,
        maximum_stock,
        reorder_point,
        average_cost,
        last_purchase_cost,
        sale_price,
        track_stock,
        track_lots,
        track_remnants,
        allow_negative_stock,
        active,
        created_at,
        updated_at,
        deleted_at
    )
    SELECT
        category.id,
        unit_measure.id,
        source.sku,
        NULL,
        source.name,
        NULL,
        N'material',
        0,
        NULL,
        0,
        0,
        0,
        source.sale_price,
        1,
        0,
        source.track_remnants,
        0,
        1,
        SYSDATETIME(),
        SYSDATETIME(),
        NULL
    FROM @Products AS source
    INNER JOIN dbo.inventory_categories AS category ON category.code = source.category_code
    INNER JOIN dbo.inventory_units AS unit_measure ON unit_measure.code = source.unit_code
    ORDER BY source.sku;

    IF (SELECT COUNT(*) FROM dbo.inventory_products) <> 301
        THROW 51005, 'La cantidad insertada no coincide con los 301 productos esperados.', 1;

    COMMIT TRANSACTION;
    PRINT 'Catálogo instalado correctamente: 301 productos, IDs del 1 al 301.';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
