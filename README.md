# Vidriería Maradiaga ERP

Aplicación Laravel 12 + Inertia + Vue 3 + SQL Server. El módulo de inventario incluye:

- catálogo de productos, categorías, unidades, proveedores, bodegas y ubicaciones;
- conversiones de unidades de compra, venta y salida;
- entradas por compra o saldo inicial con costo promedio ponderado;
- salidas por venta, orden de trabajo, producción, consumo, merma o ajuste;
- traslados atómicos entre ubicaciones;
- conteos físicos y ajustes documentados automáticamente en el Kardex;
- existencias por ubicación, historial de movimientos y Kardex;
- alertas por punto de reposición;
- control de retazos por medidas y estado;
- kits con disponibilidad calculada según sus componentes;
- calculadora de cortes rectangulares;
- permisos por rol con Spatie Permission.

## Puesta en marcha después de copiar el proyecto

Conserva tu archivo `.env` actual y ejecuta en PowerShell, desde la raíz del proyecto:

```powershell
composer install
npm ci
php artisan migrate
php artisan db:seed --class=InventoryCatalogSeeder
php artisan db:seed --class=AccessRolesSeeder
php artisan permission:cache-reset
php artisan optimize:clear
npm run typecheck
npm run build
php artisan test
```

Luego vuelve a iniciar sesión para que el navegador reciba los permisos actualizados.

## Reglas operativas importantes

- Crear o editar un producto configura sus niveles; no crea existencia.
- Las existencias cambian únicamente por entradas, salidas, traslados o conteos.
- Las salidas nunca permiten dejar saldo negativo.
- Los traslados descuentan y reciben dentro de una sola transacción.
- Los conteos sustituyen el saldo de la ubicación y generan movimientos de ajuste por cada diferencia.
- Las solicitudes de entradas, salidas, traslados y conteos tienen una clave idempotente para impedir duplicados accidentales.
- Una conversión indica cuántas unidades base contiene la unidad alterna. Ejemplo: una caja de 12 unidades tiene factor `12`.

## Comandos de comprobación

```powershell
php artisan route:list --path=inventory
npm run typecheck
npm run build
php artisan test
```

## Mensaje de commit sugerido

```text
feat(inventario): completar operaciones, conteos, retazos, kits y catálogos
```
