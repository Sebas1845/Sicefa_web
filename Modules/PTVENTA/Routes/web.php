<?php

use Illuminate\Support\Facades\Route;

// Todas las rutas están agrupadas bajo el middleware 'lang' para manejar la localización de idiomas y tienen el prefijo 'ptventa' para identificar el módulo de punto de venta.
Route::middleware(['lang'])->group(function () {
    Route::prefix('ptventa')->group(function () {

        // --- Rutas de PTVENTAController ---
        // Estas rutas gestionan la navegación general y las vistas de los paneles principales del sistema de punto de venta.
        Route::controller('PTVENTAController')->group(function () {
            Route::get('index', 'index')->name('cefa.ptventa.index'); // Página principal del módulo PTVENTA, sirve como punto de entrada para que los usuarios accedan al sistema.
            Route::get('developers', 'devs')->name('cefa.ptventa.devs'); // Muestra una página con información sobre los desarrolladores, como créditos, detalles de versión o documentación técnica.
            Route::get('information', 'info')->name('cefa.ptventa.info'); // Presenta una página informativa sobre el módulo PTVENTA, que puede incluir el propósito del sistema o el contexto organizacional.
            Route::get('admin', 'admin')->name('ptventa.admin.index'); // Muestra el panel de administración, que incluye datos de ventas de los últimos dos meses, métricas clave (como unidades productivas, bodegas, conteos de caja cerrados) y elementos de inventario recientemente añadidos.
            Route::get('cashier', 'cashier')->name('ptventa.cashier.index'); // Muestra el panel de cajero, diseñado para que los cajeros gestionen operaciones diarias, como ventas y conteos de caja.
            Route::get('admin/configuration', 'configuration')->name('ptventa.admin.configuration.index'); // Muestra la página de configuración para administradores, permitiendo ajustar parámetros del sistema específicos para su rol, como configuraciones del punto de venta.
            Route::get('cashier/configuration', 'configuration')->name('ptventa.cashier.configuration.index'); // Muestra la página de configuración para cajeros, permitiendo ajustar parámetros relevantes para su flujo de trabajo, como preferencias de visualización.
        });

        // --- Rutas de InventoryController ---
        // Estas rutas gestionan las operaciones de inventario y los reportes, incluyendo visualización, creación y generación de reportes de inventario y ventas, tanto para administradores como para cajeros.
        Route::controller('InventoryController')->group(function () {
            Route::get('admin/inventory/index', 'index')->name('ptventa.admin.inventory.index'); // Muestra el inventario actual para administradores, listando los elementos agrupados por producto con cantidades no nulas, ordenados por última actualización.
            Route::get('cashier/inventory/index', 'index')->name('ptventa.cashier.inventory.index'); // Muestra el inventario actual para cajeros, similar a la vista de administrador pero adaptado a sus permisos.
            Route::get('admin/inventory/create', 'create')->name('ptventa.admin.inventory.create'); // Presenta un formulario para que los administradores añadan nuevos elementos al inventario, como productos recibidos de proveedores.
            Route::get('cashier/inventory/create', 'create')->name('ptventa.cashier.inventory.create'); // Presenta un formulario para que los cajeros añadan nuevos elementos al inventario, generalmente para actualizaciones menores dentro de sus permisos.
            Route::get('admin/inventory/status', 'status')->name('ptventa.admin.inventory.status'); // Muestra el estado del inventario para administradores, destacando productos vencidos y aquellos que vencerán en los próximos tres días.
            Route::get('cashier/inventory/status', 'status')->name('ptventa.cashier.inventory.status'); // Muestra el estado del inventario para cajeros, similar a la vista de administrador, mostrando productos vencidos y próximos a vencer.
            Route::get('admin/inventory/low', 'low_create')->name('ptventa.admin.inventory.low'); // Muestra un formulario para que los administradores reporten niveles bajos de inventario o soliciten reabastecimiento.
            Route::get('cashier/inventory/low', 'low_create')->name('ptventa.cashier.inventory.low'); // Muestra un formulario para que los cajeros reporten niveles bajos de inventario, adaptado a sus permisos.
            Route::get('admin/reports/index', 'reports')->name('ptventa.admin.reports.index'); // Muestra el panel de reportes para administradores, proporcionando acceso a diferentes tipos de reportes de inventario y ventas.
            Route::get('cashier/reports/index', 'reports')->name('ptventa.cashier.reports.index'); // Muestra el panel de reportes para cajeros, similar al de administradores pero con acceso restringido según su rol.
            Route::post('admin/reports/inventory/pdf', 'generateInventoryPDF')->name('ptventa.admin.reports.inventory.generate.pdf'); // Genera un PDF del inventario actual para administradores, mostrando detalles como producto, lote, fechas de producción/vencimiento, cantidades y precios.
            Route::post('cashier/reports/inventory/pdf', 'generateInventoryPDF')->name('ptventa.cashier.reports.inventory.generate.pdf'); // Genera un PDF del inventario actual para cajeros, con la misma funcionalidad que la ruta de administrador pero adaptada a su rol.
            Route::get('admin/reports/inventory/entries', 'showInventoryEntriesForm')->name('ptventa.admin.reports.inventory.entries'); // Muestra un formulario para que los administradores consulten entradas de inventario por fechas.
            Route::post('admin/reports/inventory/entries/generate', 'generateInventoryEntries')->name('ptventa.admin.reports.inventory.entries.generate'); // Genera un reporte de entradas de inventario para administradores, mostrando movimientos aprobados dentro de un rango de fechas.
            Route::post('admin/reports/inventory/entries/pdf', 'generateInventoryEntriesPDF')->name('ptventa.admin.reports.inventory.entries.pdf'); // Genera un PDF de las entradas de inventario para administradores, con detalles como número de comprobante, responsable, producto, cantidad y precios.
            Route::get('cashier/reports/inventory/entries', 'showInventoryEntriesForm')->name('ptventa.cashier.reports.inventory.entries'); // Muestra un formulario para que los cajeros consulten entradas de inventario por fechas, adaptado a sus permisos.
            Route::post('cashier/reports/inventory/entries/generate', 'generateInventoryEntries')->name('ptventa.cashier.reports.inventory.entries.generate'); // Genera un reporte de entradas de inventario para cajeros, mostrando movimientos aprobados dentro de un rango de fechas.
            Route::post('cashier/reports/inventory/entries/pdf', 'generateInventoryEntriesPDF')->name('ptventa.cashier.reports.inventory.entries.pdf'); // Genera un PDF de las entradas de inventario para cajeros, con detalles similares a los de la ruta de administrador.
            Route::get('admin/reports/sales', 'showSalesForm')->name('ptventa.admin.reports.sales'); // Muestra un formulario para que los administradores consulten ventas por fechas.
            Route::post('admin/reports/sales/generate', 'generateSales')->name('ptventa.admin.reports.generate.sales'); // Genera un reporte de ventas para administradores, mostrando movimientos aprobados de tipo "Venta" dentro de un rango de fechas.
            Route::post('admin/reports/sales/pdf', 'generateSalesPDF')->name('ptventa.admin.reports.generate.sales.pdf'); // Genera un PDF de las ventas para administradores, con detalles como número de comprobante, cliente, producto, cantidad, precios y totales.
            Route::post('admin/reports/sales/products/pdf', 'generateSalesProductsPDF')->name('ptventa.admin.reports.generate.products.pdf'); // Genera un PDF de productos vendidos para administradores, agrupando productos por nombre y referencia, con cantidades, precios y subtotales.
            Route::get('cashier/reports/sales', 'showSalesForm')->name('ptventa.cashier.reports.sales'); // Muestra un formulario para que los cajeros consulten ventas por fechas, adaptado a sus permisos.
            Route::post('cashier/reports/sales/generate', 'generateSales')->name('ptventa.cashier.reports.generate.sales'); // Genera un reporte de ventas para cajeros, mostrando movimientos aprobados de tipo "Venta" dentro de un rango de fechas.
            Route::post('cashier/reports/sales/pdf', 'generateSalesPDF')->name('ptventa.cashier.reports.generate.sales.pdf'); // Genera un PDF de las ventas para cajeros, con detalles similares a los de la ruta de administrador.
            Route::post('cashier/reports/sales/products/pdf', 'generateSalesProductsPDF')->name('ptventa.cashier.reports.generate.products.pdf'); // Genera un PDF de productos vendidos para cajeros, agrupando productos por nombre y referencia, con cantidades, precios y subtotales.
            Route::post('reports/sales/excel','exportSalesExcel')->name('reports.generate.sales.excel');
        });

        // --- Rutas de SaleController ---
        // Estas rutas gestionan las operaciones de ventas, incluyendo la visualización, registro y consulta de ventas.
        Route::controller('SaleController')->group(function () {
            Route::get('admin/sale/index', 'index')->name('ptventa.admin.sale.index'); // Muestra el listado de ventas para administradores, con detalles de productos vendidos agrupados y el estado de la caja activa.
            Route::get('cashier/sale/index', 'index')->name('ptventa.cashier.sale.index'); // Muestra el listado de ventas para cajeros, similar a la vista de administrador pero adaptado a sus permisos.
            Route::get('admin/sale/register', 'register')->name('ptventa.admin.sale.register'); // Muestra un formulario para que los administradores registren una nueva venta, requiere una caja abierta.
            Route::get('cashier/sale/register', 'register')->name('ptventa.cashier.sale.register'); // Muestra un formulario para que los cajeros registren una nueva venta, requiere una caja abierta.
            Route::get('admin/sale/show/{movement}', 'show')->name('ptventa.admin.movements.sale.show'); // Muestra los detalles de un movimiento de venta específico para administradores, incluyendo información del tipo de movimiento, responsables y productos.
            Route::get('cashier/sale/show/{movement}', 'show')->name('ptventa.cashier.movements.sale.show'); // Muestra los detalles de un movimiento de venta específico para cajeros, similar a la vista de administrador.
        });

        // --- Rutas de ElementController ---
        // Estas rutas gestionan las operaciones de los elementos (productos) en el sistema, como creación, edición y actualización, exclusivas para administradores.
        Route::controller('ElementController')->group(function () {
            Route::get('admin/element/index', 'index')->name('ptventa.admin.element.index'); // Muestra el listado de elementos (productos) disponibles en el sistema para administradores.
            Route::get('admin/element/edit/{element}', 'edit')->name('ptventa.admin.element.edit'); // Muestra un formulario para que los administradores editen un elemento existente, cargando sus detalles.
            Route::put('admin/element/update/{element}', 'update')->name('ptventa.admin.element.update'); // Actualiza los datos de un elemento existente en el sistema, con validaciones para nombre, precio, imagen y otros campos.
            Route::get('admin/element/create', 'create')->name('ptventa.admin.element.create'); // Muestra un formulario para que los administradores creen un nuevo elemento (producto) en el sistema.
            Route::post('admin/element/store', 'store')->name('ptventa.admin.element.store'); // Almacena un nuevo elemento en el sistema, con validaciones para nombre, precio, imagen y otros campos.
        });

        // --- Rutas de CashController ---
        // Estas rutas gestionan las operaciones de caja, como apertura, cierre y visualización del estado de la caja.
        Route::controller('CashController')->group(function () {
            Route::get('admin/cash/index', 'index')->name('ptventa.admin.cash.index'); // Muestra el estado de la caja para administradores, incluyendo la caja activa y el historial de conteos de caja.
            Route::get('cashier/cash/index', 'index')->name('ptventa.cashier.cash.index'); // Muestra el estado de la caja para cajeros, similar a la vista de administrador pero adaptado a sus permisos.
            Route::post('admin/cash/store', 'store')->name('ptventa.admin.cash.store'); // Abre una nueva caja para administradores, registrando la fecha de apertura y el saldo inicial.
            Route::post('cashier/cash/store', 'store')->name('ptventa.cashier.cash.store'); // Abre una nueva caja para cajeros, con las mismas funcionalidades que la ruta de administrador.
            Route::post('admin/cash/close', 'close')->name('ptventa.admin.cash.close'); // Cierra una caja activa para administradores, registrando el saldo final y la fecha de cierre.
            Route::post('cashier/cash/close', 'close')->name('ptventa.cashier.cash.close'); // Cierra una caja activa para cajeros, con las mismas funcionalidades que la ruta de administrador.
        });

        // --- Rutas de MovementController ---
        // Estas rutas gestionan la visualización y consulta de movimientos (como ventas o entradas de inventario) en el sistema.
        Route::controller('MovementController')->group(function () {
            Route::get('admin/movement/index', 'index')->name('ptventa.admin.movements.index'); // Muestra el historial de movimientos para administradores, filtrado por la fecha actual y la unidad productiva/bodega.
            Route::get('cashier/movement/index', 'index')->name('ptventa.cashier.movements.index'); // Muestra el historial de movimientos para cajeros, similar a la vista de administrador pero adaptado a sus permisos.
            Route::post('admin/movement/consult', 'consult')->name('ptventa.admin.movements.consult'); // Permite a los administradores consultar movimientos por rango de fechas y opcionalmente por número de documento de una persona (cliente, registro o entrega).
            Route::post('cashier/movement/consult', 'consult')->name('ptventa.cashier.movements.consult'); // Permite a los cajeros consultar movimientos por rango de fechas y opcionalmente por número de documento, adaptado a sus permisos.
        });
    });
});