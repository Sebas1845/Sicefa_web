# Flujo funcional del módulo PTVENTA

Este documento resume cómo está organizado el módulo **PTVENTA** dentro de la plataforma Laravel y el recorrido que siguen las funcionalidades principales. Está pensado como guía de verificación rápida para desarrolladores y QA.

## 1. Arranque del módulo
- El archivo `module.json` registra el alias `ptventa` y enlaza el proveedor `PTVENTAServiceProvider`, que es el punto de entrada del módulo.【F:Modules/PTVENTA/module.json†L1-L13】
- `PTVENTAServiceProvider` publica/mezcla configuración, carga vistas y traducciones, y registra el `RouteServiceProvider`, que a su vez publica los archivos de rutas `web.php` y `api.php`.【F:Modules/PTVENTA/Providers/PTVENTAServiceProvider.php†L7-L76】【F:Modules/PTVENTA/Providers/RouteServiceProvider.php†L9-L45】

## 2. Ruteo principal
- Todas las rutas web están protegidas por el middleware `lang` y se agrupan bajo el prefijo `/ptventa`. Desde allí se organizan subgrupos por controlador para vistas públicas, administración, cajero, inventario, ventas, caja y movimientos.【F:Modules/PTVENTA/Routes/web.php†L16-L103】
- Las rutas API están inactivas salvo el placeholder autenticado estándar para `/api/ptventa`.【F:Modules/PTVENTA/Routes/api.php†L1-L17】

## 3. Controladores y responsabilidades
- **PTVENTAController**: entrega las vistas públicas (inicio, desarrolladores, información), la pantalla de configuración y los tableros de administrador/cajero. El método `admin()` prepara métricas de ventas y KPIs usando movimientos de tipo “Venta” y la unidad productiva-bodega asociada al módulo.【F:Modules/PTVENTA/Http/Controllers/PTVENTAController.php†L17-L132】
- **InventoryController**: lista el inventario agrupado por elemento, controla vistas de creación/bajas/estado, genera reportes (HTML y PDF) y expone formularios para consultar entradas y ventas por rango de fechas.【F:Modules/PTVENTA/Http/Controllers/InventoryController.php†L16-L347】
- **SaleController**: administra el panel de ventas por sesión de caja, el formulario de registro (validando que exista una caja abierta), el detalle de ventas y la generación de reportes/PDF de ventas agrupadas por producto en rangos temporales.【F:Modules/PTVENTA/Http/Controllers/SaleController.php†L17-L200】
- **ElementController**: CRUD básico de productos vendidos (Elementos) incluyendo validaciones, carga de imágenes y rutas de retorno diferenciadas por rol.【F:Modules/PTVENTA/Http/Controllers/ElementController.php†L16-L138】
- **CashController**: gestiona apertura/cierre de cajas (`CashCount`), valida exclusividad de caja abierta y sincroniza montos finales mediante transacciones de base de datos.【F:Modules/PTVENTA/Http/Controllers/CashController.php†L16-L93】
- **MovementController**: expone el histórico de movimientos filtrado por fechas/actor, aprovechando relaciones de `Movement` y `Person`.【F:Modules/PTVENTA/Http/Controllers/MovementController.php†L14-L56】
- **PUW helper**: resuelve la unidad productiva y bodega “Punto de venta”, reutilizada en todo el módulo para aislar el inventario/caja que le pertenece.【F:Modules/PTVENTA/Http/Controllers/PUW.php†L10-L25】

## 4. Componentes Livewire
- `Sale\GenerateSale` controla toda la lógica interactiva del proceso de venta: consulta inventario disponible, arma el carrito, calcula totales/cambio y persiste movimientos autorizados, integrándose con `PUW`, `Inventory`, `Movement` y `CashCount`.【F:Modules/PTVENTA/Http/Livewire/Sale/GenerateSale.php†L25-L200】
- `Inventory\RegisterEntry` (y su contraparte `RegisterLow`) gestionan el ingreso/egreso de productos al inventario del punto de venta, con selección de bodegas, lotes y responsables. (Ver archivos en `Modules/PTVENTA/Http/Livewire/Inventory/`).
- `Element\ShowImages` soporta la previsualización de imágenes asociadas a elementos desde las vistas administrativas.

## 5. Vistas y recursos
- Las vistas Blade viven en `Modules/PTVENTA/Resources/views` y se cargan automáticamente por el service provider. Se dividen en páginas públicas (`index`, `developers`, `information`), tableros (`admin-index`, `cashier-index`) y secciones funcionales (`inventory`, `sale`, `cash`, `movements`, `reports`, etc.).
- Los componentes Livewire renderizan plantillas ubicadas en `Resources/views/livewire/...` para formularios dinámicos.

## 6. Flujos funcionales principales
1. **Operación diaria del administrador**
   - Ingresa a `/ptventa/admin`, donde el dashboard muestra KPIs de ventas, inventario y cajas cerradas usando los datos agregados del `PTVENTAController@admin`.【F:Modules/PTVENTA/Http/Controllers/PTVENTAController.php†L41-L125】
   - Gestiona productos desde `/ptventa/admin/element/*` con el `ElementController` y puede registrar entradas/bajas usando los componentes Livewire conectados a `InventoryController`.
   - Abre caja en `/ptventa/admin/cash/index`; `CashController@store` garantiza que solo exista una caja abierta por bodega y asigna el responsable actual.【F:Modules/PTVENTA/Http/Controllers/CashController.php†L29-L50】
   - Registra ventas en `/ptventa/admin/sale/register`, donde `SaleController@register` verifica la caja abierta y el componente `GenerateSale` gestiona el flujo transaccional.【F:Modules/PTVENTA/Http/Controllers/SaleController.php†L77-L177】【F:Modules/PTVENTA/Http/Livewire/Sale/GenerateSale.php†L53-L200】
   - Consulta reportes y exporta PDF desde las rutas de `InventoryController` y `SaleController` (inventario, entradas, ventas por rango).【F:Modules/PTVENTA/Routes/web.php†L30-L66】
   - Cierra la caja con `CashController@close`, que valida montos y guarda los totales finales.【F:Modules/PTVENTA/Http/Controllers/CashController.php†L52-L93】

2. **Flujo del cajero**
   - Accede a `/ptventa/cashier` para su tablero principal. Las rutas replican las del administrador, pero con prefijos `cashier/*` y los mismos controladores actúan según el rol resuelto en los helpers de rutas.【F:Modules/PTVENTA/Routes/web.php†L24-L103】
   - El cajero comparte la lógica de inventario, ventas y caja, manteniendo consistencia gracias al uso de `getRoleRouteName` en redirecciones y a la reutilización de `PUW` para aislar la bodega del punto de venta.【F:Modules/PTVENTA/Http/Controllers/SaleController.php†L84-L177】【F:Modules/PTVENTA/Http/Controllers/CashController.php†L29-L50】【F:Modules/PTVENTA/Http/Controllers/PUW.php†L12-L22】

3. **Consulta de movimientos**
   - Tanto administrador como cajero pueden consultar el histórico en `/ptventa/{rol}/movement/index` y filtrar por fecha o documento. `MovementController` centraliza esta lógica y reutiliza el helper `PUW` para limitar los resultados a la bodega correspondiente.【F:Modules/PTVENTA/Routes/web.php†L97-L103】【F:Modules/PTVENTA/Http/Controllers/MovementController.php†L14-L56】

## 7. Dependencias externas
El módulo depende fuertemente de entidades definidas en el módulo **SICA** (`CashCount`, `Movement`, `Inventory`, `Element`, etc.), lo cual garantiza que toda la información transaccional y de catálogo se mantenga sincronizada entre módulos.【F:Modules/PTVENTA/Http/Controllers/PTVENTAController.php†L7-L12】【F:Modules/PTVENTA/Http/Controllers/SaleController.php†L8-L13】【F:Modules/PTVENTA/Http/Livewire/Sale/GenerateSale.php†L10-L23】

Con esta vista general se puede verificar rápidamente que el flujo end-to-end del punto de venta (productos → inventario → caja → venta → reportes) está cohesionado y respaldado por los controladores y componentes descritos.
