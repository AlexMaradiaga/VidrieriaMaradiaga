# Vidriería Maradiaga ERP

Vidriería Maradiaga ERP es una plataforma web modular diseñada para centralizar y optimizar la administración operativa de una empresa dedicada a la comercialización y transformación de vidrio y materiales relacionados.

El sistema proporciona una base escalable para integrar las distintas áreas de la empresa. Su primera etapa funcional está orientada al control de inventario y establece la estructura necesaria para incorporar posteriormente los módulos de personal, roles administrativos, contabilidad, ventas y otras operaciones del negocio.

## Módulo de inventario

El módulo de inventario permite administrar productos, materiales y existencias desde su ingreso hasta su consumo, traslado o ajuste. Incluye las siguientes funciones:

- panel principal con indicadores reales del inventario;
- catálogo de productos con códigos únicos, categorías, unidades de control y niveles de existencia;
- gestión de categorías, unidades, proveedores, bodegas y ubicaciones;
- conversiones entre unidades de compra, almacenamiento, venta y salida;
- registro de entradas por compra, devolución, regularización o saldo inicial;
- cálculo del costo promedio ponderado de las existencias;
- registro de salidas por venta, orden de trabajo, producción, consumo interno, merma o ajuste;
- traslados de productos entre ubicaciones;
- conteos físicos con generación de ajustes por diferencias;
- consulta de existencias por producto y ubicación;
- historial de movimientos y Kardex;
- alertas para productos que alcanzan su nivel mínimo o punto de reposición;
- registro y seguimiento de retazos reutilizables;
- creación de kits y cálculo de disponibilidad según sus componentes;
- calculadora de cortes rectangulares para el aprovechamiento de materiales;
- activación y desactivación controlada de productos y registros maestros.

## Seguridad y acceso

La plataforma dispone de autenticación de usuarios y control de acceso basado en roles y permisos. Cada usuario puede visualizar o ejecutar únicamente las acciones autorizadas para su perfil dentro del sistema.

Las operaciones que modifican existencias se procesan de forma transaccional para mantener la consistencia de los saldos y evitar movimientos incompletos. También se aplican controles para impedir solicitudes duplicadas y existencias negativas.

## Experiencia de usuario

La interfaz está desarrollada para funcionar en computadoras, tabletas y dispositivos móviles. Cuenta con navegación centralizada, mensajes de confirmación, alertas visuales y opciones de personalización que permiten seleccionar el idioma, el tema de la interfaz y el color principal del sistema.

## Arquitectura

Vidriería Maradiaga ERP utiliza una arquitectura modular inspirada en los principios de arquitectura hexagonal y diseño dirigido por el dominio. Esta organización separa las reglas del negocio, los casos de uso, la persistencia de datos y la interfaz de usuario, facilitando el mantenimiento y la incorporación progresiva de nuevos módulos.

La solución está construida con Laravel, Inertia, Vue, TypeScript y SQL Server.
