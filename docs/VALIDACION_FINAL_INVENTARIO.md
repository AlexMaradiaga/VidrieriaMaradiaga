# Validación final del módulo de inventario

Este documento separa la comprobación técnica de la aceptación operativa. El README permanece dedicado exclusivamente a describir el programa.

## Comprobación técnica automatizada

Ejecutar desde la raíz del proyecto:

```powershell
npm ci
npm run typecheck
npm run build
npm audit
php artisan optimize:clear
php artisan test
php artisan route:list --path=inventory
```

La prueba `InventoryOperationsIntegrationTest` verifica contra una base SQL Server cuyo nombre termine en `_Test`:

- entrada e idempotencia;
- costo promedio ponderado;
- salida e impedimento de saldos negativos;
- traslado atómico entre ubicaciones;
- conteo físico y movimiento de ajuste.

## Aceptación operativa

Realizar estas pruebas con datos de demostración y un usuario Administrador:

- crear, editar, desactivar y reactivar un producto;
- registrar una entrada y comprobar existencias, costo promedio, historial y Kardex;
- registrar una salida y confirmar que disminuye la ubicación correcta;
- trasladar existencias y comprobar ambos saldos;
- ejecutar un conteo físico y verificar el ajuste generado;
- revisar alertas de mínimos y reposición;
- crear y cambiar el estado de un retazo;
- crear un kit y revisar su disponibilidad;
- usar la calculadora de cortes;
- crear registros de catálogos y conversiones;
- cambiar idioma, tema y color desde Configuración;
- instalar la aplicación desde Configuración en un navegador compatible.

## Criterio de cierre

El módulo se acepta cuando todos los comandos terminan sin errores y cada escenario operativo conserva saldos, costos, permisos y trazabilidad coherentes.
