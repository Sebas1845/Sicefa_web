<?php

namespace Modules\PTVENTA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\SICA\Entities\Inventory;
use Modules\SICA\Entities\Movement;
use Modules\SICA\Entities\MovementType;
use Modules\SICA\Entities\ProductiveUnitWarehouse as PUW;
use TCPDF;
use Maatwebsite\Excel\Facades\Excel;
use Modules\PTVENTA\Exports\SalesReportExport;

class InventoryController extends Controller
{
    // Listado del inventario actual
    public function index()
    {
        $inventories = Inventory::with(['element']) // evitar N+1
            ->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
            ->where('amount', '<>', 0)
            ->orderBy('updated_at', 'DESC')
            ->get();

        // AGRUPAR POR PRODUCTO (key correcto)
        $grouped = $inventories->groupBy('element_id');

        // Convertir a colección de grupos (para $loop->iteration en Blade)
        $groupedInventories = collect();
        foreach ($grouped as $grp) {
            $groupedInventories->push($grp);
        }

        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_index_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_index_title_view')
        ];

        return view('ptventa::inventory.index', compact('view', 'groupedInventories'));
    }

    public function create()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_create_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_create_title_view')
        ];
        return view('ptventa::inventory.create', compact('view'));
    }

    public function status(Request $request)
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_status_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_status_title_view')
        ];

        $productosVencidos = Inventory::where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
            ->where('state', 'Disponible')
            ->where('expiration_date', '<', now())
            ->orderBy('expiration_date')
            ->get();

        $productosPorVencer = Inventory::where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
            ->where('state', 'Disponible')
            ->where('expiration_date', '>', now())
            ->where('expiration_date', '<=', now()->addDays(3))
            ->orderBy('expiration_date')
            ->get();

        return view('ptventa::inventory.status', compact('view', 'productosVencidos', 'productosPorVencer'));
    }

    public function low_create()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_low_create_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_low_create_title_view')
        ];
        return view('ptventa::inventory.low', compact('view'));
    }

    public function show_entry(Movement $movement)
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_show_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_show_title_view')
        ];
        return view('ptventa::inventory.show-entry', compact('view', 'movement'));
    }

    public function showLow(Movement $movement)
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_show_low_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_show_low_title_view')
        ];
        return view('ptventa::inventory.show-low', compact('view', 'movement'));
    }

    public function reports()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_reports_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_reports_title_view')
        ];

        return view('ptventa::reports.index', compact('view'));
    }

    // PDF: Inventario actual (fix de índices y nombres)
    public function generateInventoryPDF(Request $request)
    {
        $inventories = Inventory::with(['element'])
            ->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
            ->where('amount', '<>', 0)
            ->orderBy('updated_at', 'DESC')
            ->get();

        $puw = PUW::getAppPuw();

        $groups = $inventories->groupBy('element_id')->values(); // colección de grupos

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $title = 'Reporte de Inventario - ' . date('Y-m-d');
        $pdf->SetTitle($title);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();
        $pdf->SetY(15);
        $header = 'Centro de Formación Agroindustrial "La Angostura" | Campoalegre - Huila';
        $pdf->Cell(0, 0, $header, 0, 1, 'C');

        $html = '<h4 style="text-align: center;"><strong>Bodega:</strong> ' . $puw->warehouse->name . ' - <strong>Unidad Productiva:</strong> ' . $puw->productive_unit->name . '</h4>';
        $html .= '<h3 style="text-align: center;">' . $title . '</h3>';
        $html .= '<table style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead style="background-color: #f2f2f2;">';
        $html .= '<tr>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:10px;width:25px;"><b>#</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:130px;"><b>Producto</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:45px;"><b>N° Lote</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:62px;"><b>Fecha Producción</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:62px;"><b>Fecha Vencimiento</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;"><b>Cantidad</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;"><b>Precio Entrada</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;"><b>Precio Venta</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:62px;"><b>Existencias</b></th>';
        $html .= '</tr></thead><tbody>';

        foreach ($groups as $idx => $group) {
            $firstRecord = $group->first();
            $rowspan = $group->count();
            $html .= '<tr>';
            $html .= '<td rowspan="' . $rowspan . '" style="border:1px solid #ddd;text-align:center;padding:8px;width:25px;">' . ($idx + 1) . '</td>';
            $html .= '<td rowspan="' . $rowspan . '" style="border:1px solid #ddd;text-align:left;padding:8px;width:130px;">' . $firstRecord->element->name . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;width:45px;">' . $firstRecord->lot_number . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;width:62px;">' . $firstRecord->production_date . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;width:62px;">' . $firstRecord->expiration_date . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;">' . $firstRecord->amount . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;">' . priceFormat($firstRecord->price) . '</td>';
            $html .= '<td rowspan="' . $rowspan . '" style="border:1px solid #ddd;text-align:center;padding:8px;width:50px;">' . priceFormat($firstRecord->element->price) . '</td>';
            $html .= '<td rowspan="' . $rowspan . '" style="border:1px solid #ddd;text-align:center;padding:8px;width:62px;">' . $group->sum('amount') . '</td>';
            $html .= '</tr>';

            foreach ($group->slice(1) as $record) {
                $html .= '<tr>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $record->lot_number . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;">' . $record->production_date . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;">' . $record->expiration_date . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $record->amount . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . priceFormat($record->price) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = 'reporte_inventarios_' . date('Ymd') . '.pdf';
        $pdf->Output($filename, 'I');
    }

    public function showInventoryEntriesForm()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_inventory_show_entries_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_inventory_show_entries_title_view')
        ];
        $start_date = request()->input('start_date', now()->format('Y-m-d'));
        $end_date = request()->input('end_date', now()->format('Y-m-d'));

        return view('ptventa::reports.inventory-entries-form', compact('view', 'start_date', 'end_date'));
    }

    public function generateInventoryEntries(Request $request)
    {
        $startDateInput = Carbon::parse($request->input('start_date'))->format('Y-m-d');
        $endDateInput = Carbon::parse($request->input('end_date'))->format('Y-m-d');
        $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $endDateInput)->endOfDay();

        $movement_type = MovementType::where('name', 'Movimiento Interno')->firstOrFail();
        $movements = Movement::whereHas('warehouse_movements', function ($query) {
            $query->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
                ->where('role', 'Recibe');
        })
            ->where('movement_type_id', $movement_type->id)
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->orderBy('registration_date', 'ASC')
            ->get();

        return $this->showInventoryEntriesForm()->with('movements', $movements);
    }

    public function generateInventoryEntriesPDF(Request $request)
    {
        $startDateInput = Carbon::parse($request->input('start_date'))->format('Y-m-d');
        $endDateInput = Carbon::parse($request->input('end_date'))->format('Y-m-d');
        $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $endDateInput)->endOfDay();

        $movement_type = MovementType::where('name', 'Movimiento Interno')->firstOrFail();
        $movements = Movement::whereHas('warehouse_movements', function ($query) {
            $query->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
                ->where('role', 'Recibe');
        })
            ->where('movement_type_id', $movement_type->id)
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->orderBy('registration_date', 'ASC')
            ->get();

        $puw = PUW::getAppPuw();
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $title = 'Reporte de Entradas de Inventario - ' . $startDateInput . ' al ' . $endDateInput;
        $pdf->SetTitle($title);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();
        $pdf->SetY(15);
        $header = 'Centro de Formación Agroindustrial "La Angostura" | Campoalegre - Huila';
        $pdf->Cell(0, 0, $header, 0, 1, 'C');

        $html = '<h4 style="text-align: center;"><strong>Bodega:</strong> ' . $puw->warehouse->name . ' - <strong>Unidad Productiva:</strong> ' . $puw->productive_unit->name . '</h4>';
        $html .= '<h3 style="text-align: center;">' . $title . '</h3>';
        $html .= '<table style="border-collapse: collapse; width: 100%;">';
        $html .= '<thead style="background-color: #f2f2f2;"><tr>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:25px;"><b>#</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:52px;"><b>N° de Voucher</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:72px;"><b>Responsable que entrega</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;"><b>Fecha de ingreso</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;width:90px;"><b>Producto</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;"><b>Cantidad</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;"><b>Precio</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;"><b>Subtotal</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;"><b>Total</b></th>';
        $html .= '</tr></thead><tbody>';

        foreach ($movements as $key => $movement) {
            foreach ($movement->movement_details as $index => $movement_detail) {
                $html .= '<tr>';
                if ($index === 0) {
                    $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;width:25px;" rowspan="' . count($movement->movement_details) . '">' . ($key + 1) . '</td>';
                    $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;width:52px;" rowspan="' . count($movement->movement_details) . '">' . $movement->voucher_number . '</td>';
                    $entrega = optional($movement->movement_responsibilities->where('role', 'ENTREGA')->first())->person->full_name ?? '';
                    $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;width:72px;" rowspan="' . count($movement->movement_details) . '">' . $entrega . '</td>';
                    $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;" rowspan="' . count($movement->movement_details) . '">' . $movement->registration_date . '</td>';
                }
                $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;width:90px;">' . $movement_detail->inventory->element->name . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $movement_detail->amount . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . priceFormat($movement_detail->price) . '</td>';
                $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . priceFormat($movement_detail->amount * $movement_detail->price) . '</td>';
                if ($index === 0) {
                    $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;" rowspan="' . count($movement->movement_details) . '">' . priceFormat($movement->price) . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = 'Reporte_entradas_inventario_' . $startDateInput . '_al_' . $endDateInput . '.pdf';
        $pdf->Output($filename, 'I');
    }

    public function showSalesForm()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sales_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sales_title_view')
        ];
        $start_date = request()->input('start_date', now()->format('Y-m-d'));
        $end_date = request()->input('end_date', now()->format('Y-m-d'));

        return view('ptventa::reports.sales-form', compact('view', 'start_date', 'end_date'));
    }

    public function generateSales(Request $request)
    {
        $startDateInput = Carbon::parse($request->input('start_date'))->format('Y-m-d');
        $endDateInput   = Carbon::parse($request->input('end_date'))->format('Y-m-d');
        $startDate = Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay();
        $endDate   = Carbon::createFromFormat('Y-m-d', $endDateInput)->endOfDay();

        // 1. Tipo de movimiento (puedes ajustar el name según cómo esté en tu BD)
        $movement_type = MovementType::where('name', 'Venta')->first(); // SIN fail por si acaso

        $query = Movement::query()
            ->with(['movement_details.inventory.element', 'movement_responsibilities.person'])
            ->whereBetween('registration_date', [$startDate, $endDate]);

        // 2. Si encontró tipo "Venta", filtramos; si no, por ahora no filtramos por tipo
        if ($movement_type) {
            $query->where('movement_type_id', $movement_type->id);
        }

        // 3. Estado: prueba primero sin filtrar, para ver si aparece algo
        //    Luego puedes activar según veas qué estados tienes de verdad.
        $query->where('state', 'Aprobado');

        // 4. Bodega / role: igual, probemos SIN esto primero:

        $query->whereHas('warehouse_movements', function ($q) {
            $q->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
                ->where('role', 'Entrega');
        });


        $movements = $query->orderBy('registration_date', 'ASC')->get();

        // === Agrupamos productos igual que ya lo tenías ===
        $groupedProducts = [];
        foreach ($movements as $movement) {
            foreach ($movement->movement_details as $detail) {
                $el   = $detail->inventory->element;
                $name = $el->name ?? $el->product_name ?? 'N/A';
                $key  = $name;

                $price    = $detail->price;
                $amount   = $detail->amount;
                $subtotal = $amount * $price;

                if (!isset($groupedProducts[$key])) {
                    $groupedProducts[$key] = [
                        'producto'  => $name,
                        'cantidad'  => 0,
                        'min_price' => $price,
                        'max_price' => $price,
                        'subtotal'  => 0,
                    ];
                }
                $groupedProducts[$key]['cantidad']  += $amount;
                $groupedProducts[$key]['subtotal']  += $subtotal;
                $groupedProducts[$key]['min_price']  = min($groupedProducts[$key]['min_price'], $price);
                $groupedProducts[$key]['max_price']  = max($groupedProducts[$key]['max_price'], $price);
            }
        }

        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sales_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sales_title_view')
        ];

        return view('ptventa::reports.sales-form', [
            'view'            => $view,
            'start_date'      => $startDateInput,
            'end_date'        => $endDateInput,
            'movements'       => $movements,
            'groupedProducts' => array_values($groupedProducts),
        ]);
    }


    public function generateSalesPDF(Request $request)
    {
        $startDateInput = Carbon::parse($request->input('start_date'))->format('Y-m-d');
        $endDateInput   = Carbon::parse($request->input('end_date'))->format('Y-m-d');
        $startDate      = Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay();
        $endDate        = Carbon::createFromFormat('Y-m-d', $endDateInput)->endOfDay();

        // Tipo de movimiento Venta
        $movement_type = MovementType::where('name', 'Venta')->firstOrFail();

        // Movimientos (con relaciones para evitar N+1)
        $movements = Movement::with([
            'movement_details.inventory.element',
            'movement_responsibilities.person',
            'warehouse_movements',
        ])
            ->whereHas('warehouse_movements', function ($query) {
                $query->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
                    ->where('role', 'Entrega');
            })
            ->where('movement_type_id', $movement_type->id)
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->orderBy('registration_date', 'ASC')
            ->get();

        $puw = PUW::getAppPuw();

        // ========================
        // PEQUEÑO RESUMEN ESTADÍSTICO
        // ========================
        $totalVentas   = $movements->count();
        $totalItems    = 0;
        $granTotal     = 0;

        foreach ($movements as $movement) {
            $granTotal += $movement->price;
            foreach ($movement->movement_details as $detail) {
                $totalItems += $detail->amount;
            }
        }

        // ========================
        // CONFIGURACIÓN DEL PDF
        // ========================
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(15);

        $title = 'Reporte de Ventas - ' .
            \Carbon\Carbon::parse($startDateInput)->format('d/m/Y') . ' al ' .
            \Carbon\Carbon::parse($endDateInput)->format('d/m/Y');

        $pdf->SetTitle($title);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->AddPage();

        // ========================
        // ENCABEZADO (ESTILO LIMPIO VERDE)
        // ========================
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'CENTRO DE FORMACIÓN AGROINDUSTRIAL "LA ANGOSTURA"', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'Campoalegre - Huila', 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'REPORTE DE VENTAS', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(
            0,
            6,
            'Período: del ' . \Carbon\Carbon::parse($startDateInput)->format('d/m/Y') .
                ' al ' . \Carbon\Carbon::parse($endDateInput)->format('d/m/Y'),
            0,
            1,
            'C'
        );
        $pdf->Cell(
            0,
            6,
            'Bodega: ' . $puw->warehouse->name . ' | Unidad Productiva: ' . $puw->productive_unit->name,
            0,
            1,
            'C'
        );
        $pdf->Ln(4);

        // ========================
        // CSS TIPO DASHBOARD VERDE CLARO
        // ========================
        $html = '
<style>
    body {
        font-family: helvetica, sans-serif;
    }

    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }

    /* Encabezado principal de la tabla */
    .th-header {
        background-color: #16a34a;   /* verde vivo */
        color: #ffffff;
        border: 1px solid #0f5132;
        padding: 8px 5px;
        text-align: center;
        font-weight: bold;
    }

    .td-cell {
        border: 1px solid #d1d5db;
        padding: 6px 4px;
        color: #111827;
    }

    .row-even  { background-color: #f0fdf4; } /* verde muy clarito */
    .row-odd   { background-color: #ffffff; } /* blanco */

    .text-center { text-align: center; }
    .text-left   { text-align: left;   }
    .text-right  { text-align: right;  }

    .total-row {
        background-color: #15803d;
        color: #ffffff;
        font-weight: bold;
    }

    .badge-period {
        background-color: #22c55e;
        color: #064e3b;
        border-radius: 16px;
        padding: 6px 12px;
        display: inline-block;
        font-size: 9pt;
        margin-bottom: 8px;
        font-weight: bold;
    }

    /* Tabla resumen superior */
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        margin-bottom: 10px;
        font-size: 9.5pt;
    }

    .summary-table th {
        background-color: #e6f7ee;
        color: #14532d;
        border: 1px solid #bbf7d0;
        padding: 6px 8px;
        text-align: left;
        font-weight: bold;
    }

    .summary-table td {
        border: 1px solid #bbf7d0;
        padding: 6px 8px;
        text-align: right;
        font-weight: bold;
        color: #166534;
    }
</style>';

        // Badge del período
        $html .= '
<div class="badge-period">
    Ventas entre ' . \Carbon\Carbon::parse($startDateInput)->format('d/m/Y') . ' y ' .
            \Carbon\Carbon::parse($endDateInput)->format('d/m/Y') . '
</div>
<br/>';

        // Tabla RESUMEN tipo dashboard (arriba de la tabla de detalle)
        $html .= '
<table class="summary-table">
    <tr>
        <th>Ventas registradas</th>
        <th>Unidades vendidas</th>
        <th>Total general</th>
    </tr>
    <tr>
        <td>' . number_format($totalVentas, 0, ',', '.') . '</td>
        <td>' . number_format($totalItems, 0, ',', '.') . '</td>
        <td>$ ' . number_format($granTotal, 0, ',', '.') . '</td>
    </tr>
</table>
<br/>';

        // ========================
        // TABLA DE VENTAS DETALLADAS
        // ========================
        $html .= '
<table>
    <thead>
        <tr>
            <th class="th-header" width="5%">#</th>
            <th class="th-header" width="10%">N° Comp.</th>
            <th class="th-header" width="23%">Cliente</th>
            <th class="th-header" width="14%">Fecha</th>
            <th class="th-header" width="23%">Producto</th>
            <th class="th-header" width="8%">Cant.</th>
            <th class="th-header" width="8%">Precio</th>
            <th class="th-header" width="9%">Subtotal</th>
        </tr>
    </thead>
    <tbody>';

        if ($movements->isEmpty()) {
            $html .= '
        <tr>
            <td class="td-cell text-center" colspan="8" style="padding:20px; font-style:italic; background-color:#f9fafb; color:#6b7280;">
                No se encontraron ventas en el rango de fechas seleccionado.
            </td>
        </tr>';
        } else {
            foreach ($movements as $key => $movement) {
                $detalles = $movement->movement_details;
                $rowspan  = max(count($detalles), 1);

                $cliente = optional(
                    $movement->movement_responsibilities->where('role', 'CLIENTE')->first()
                )->person->full_name ?? 'PUNTO DE VENTA';

                $fecha = \Carbon\Carbon::parse($movement->registration_date)->format('d/m/Y H:i');

                $rowClass = (($key + 1) % 2 == 0) ? 'row-even' : 'row-odd';

                foreach ($detalles as $index => $detail) {
                    $html .= '<tr class="' . $rowClass . '">';

                    if ($index === 0) {
                        $html .= '<td class="td-cell text-center" rowspan="' . $rowspan . '">' . ($key + 1) . '</td>';
                        $html .= '<td class="td-cell text-center" rowspan="' . $rowspan . '">' . ($movement->voucher_number ?? '-') . '</td>';
                        $html .= '<td class="td-cell text-left"   rowspan="' . $rowspan . '">' . htmlspecialchars($cliente, ENT_QUOTES, "UTF-8") . '</td>';
                        $html .= '<td class="td-cell text-left"   rowspan="' . $rowspan . '">' . $fecha . '</td>';
                    }

                    $element      = optional(optional($detail->inventory)->element);
                    $productName  = $element->name ?? $element->product_name ?? 'SIN NOMBRE';
                    $subtotalLine = $detail->amount * $detail->price;

                    $html .= '<td class="td-cell text-left">' . htmlspecialchars($productName, ENT_QUOTES, "UTF-8") . '</td>';
                    $html .= '<td class="td-cell text-center">' . $detail->amount . '</td>';
                    $html .= '<td class="td-cell text-right">$ ' . number_format($detail->price, 0, ',', '.') . '</td>';
                    $html .= '<td class="td-cell text-right">$ ' . number_format($subtotalLine, 0, ',', '.') . '</td>';

                    $html .= '</tr>';
                }
            }
        }

        $html .= '</tbody>';

        if ($granTotal > 0) {
            $html .= '
    <tfoot>
        <tr class="total-row">
            <td class="td-cell text-right" colspan="7" style="padding-right:10px;">
                TOTAL GENERAL:
            </td>
            <td class="td-cell text-right">$ ' . number_format($granTotal, 0, ',', '.') . '</td>
        </tr>
    </tfoot>';
        }

        $html .= '</table>';

        // PIE
        $html .= '
<br><br>
<table width="100%">
    <tr>
        <td width="50%" style="border-top:1px solid #9ca3af; padding-top:16px; text-align:center; color:#4b5563;">
            Elaboró
        </td>
        <td width="50%" style="border-top:1px solid #9ca3af; padding-top:16px; text-align:center; color:#4b5563;">
            Revisó
        </td>
    </tr>
</table>
<div style="text-align:center; font-size:9pt; margin-top:14px; color:#9ca3af;">
    Reporte generado el ' . \Carbon\Carbon::now()->format('d/m/Y H:i') . ' | Módulo Punto de Venta - La Angostura
</div>
';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'Reporte_ventas_' . $startDateInput . '_al_' . $endDateInput . '.pdf';
        return $pdf->Output($filename, 'I');
    }


    public function generateSalesProductsPDF(Request $request)
    {
        $startDateInput = $request->input('start_date');
        $endDateInput   = $request->input('end_date');

        if (!$startDateInput || !$endDateInput) {
            return redirect()->back()->withErrors(['error' => 'Las fechas de inicio y fin son obligatorias.']);
        }

        $startDate = Carbon::parse($startDateInput)->startOfDay();
        $endDate   = Carbon::parse($endDateInput)->endOfDay();

        // Tipo de movimiento Venta
        $movement_type = MovementType::where('name', 'Venta')->firstOrFail();

        // Movimientos en el rango
        $movements = Movement::with(['movement_details.inventory.element'])
            ->whereHas('warehouse_movements', function ($q) {
                $q->where('productive_unit_warehouse_id', PUW::getAppPuw()->id)
                    ->where('role', 'Entrega');
            })
            ->where('movement_type_id', $movement_type->id)
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->orderBy('registration_date', 'ASC')
            ->get();

        // Agrupación de productos
        $grouped = [];
        foreach ($movements as $movement) {
            foreach ($movement->movement_details as $detail) {
                $el   = optional(optional($detail->inventory)->element);
                $name = $el->name ?? $el->product_name ?? 'Sin nombre';
                $key  = $name;

                $price    = $detail->price ?? 0;
                $amount   = $detail->amount ?? 0;
                $subtotal = $amount * $price;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'producto'  => $name,
                        'cantidad'  => 0,
                        'min_price' => $price,
                        'max_price' => $price,
                        'subtotal'  => 0,
                    ];
                }

                $grouped[$key]['cantidad']  += $amount;
                $grouped[$key]['subtotal']  += $subtotal;
                $grouped[$key]['min_price'] = min($grouped[$key]['min_price'], $price);
                $grouped[$key]['max_price'] = max($grouped[$key]['max_price'], $price);
            }
        }

        ksort($grouped); // Orden alfabético por nombre de producto

        // Resúmenes
        $totalProductos = count($grouped);
        $totalCantidad  = 0;
        $totalGeneral   = 0;
        foreach ($grouped as $item) {
            $totalCantidad += $item['cantidad'];
            $totalGeneral  += $item['subtotal'];
        }

        $puw = PUW::getAppPuw();

        // ========================
        // CONFIGURACIÓN PDF
        // ========================
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(15);
        $pdf->SetTitle('Reporte de Productos Vendidos - ' . $startDateInput . ' al ' . $endDateInput);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->AddPage();

        // ========================
        // ENCABEZADO
        // ========================
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'CENTRO DE FORMACIÓN AGROINDUSTRIAL "LA ANGOSTURA"', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'Campoalegre - Huila', 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'REPORTE DE PRODUCTOS VENDIDOS', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(
            0,
            6,
            'Período: del ' . \Carbon\Carbon::parse($startDateInput)->format('d/m/Y') .
                ' al ' . \Carbon\Carbon::parse($endDateInput)->format('d/m/Y'),
            0,
            1,
            'C'
        );
        $pdf->Cell(
            0,
            6,
            'Bodega: ' . $puw->warehouse->name . ' | Unidad Productiva: ' . $puw->productive_unit->name,
            0,
            1,
            'C'
        );
        $pdf->Ln(6);

        // ========================
        // RESUMEN SUPERIOR
        // ========================
        $html = '
    <style>
        table { border-collapse: collapse; width: 100%; font-size: 10pt; }
        .summary-table td {
            border: 1px solid #000;
            padding: 6px 8px;
        }
        .summary-label { font-weight: bold; background-color: #f4f4f4; }
        .summary-value { text-align: right; }
        .header-table th {
            background-color: #333;
            color: #fff;
            padding: 8px 6px;
            text-align: center;
            border: 2px solid #000;
        }
        .data-table td {
            padding: 6px 5px;
            border: 1.2px solid #000;
        }
        .text-left  { text-align: left; }
        .text-right { text-align: right; }
        .text-center{ text-align: center; }
        .row-even   { background-color: #f9f9f9; }
        .total-row  {
            background-color: #e6e6e6;
            font-weight: bold;
        }
    </style>';

        // tabla resumen
        $html .= '
    <table class="summary-table" style="margin-bottom:8px;">
        <tr>
            <td class="summary-label" width="33%">N° de productos</td>
            <td class="summary-label" width="33%">Cantidad total vendida</td>
            <td class="summary-label" width="34%">Valor total vendido</td>
        </tr>
        <tr>
            <td class="summary-value">' . number_format($totalProductos, 0, ',', '.') . '</td>
            <td class="summary-value">' . number_format($totalCantidad, 0, ',', '.') . '</td>
            <td class="summary-value">' . priceFormat($totalGeneral) . '</td>
        </tr>
    </table>';

        // ========================
        // TABLA PRINCIPAL
        // ========================
        $html .= '
    <table class="data-table">
        <thead class="header-table">
            <tr>
                <th width="6%">#</th>
                <th width="44%" class="text-left">PRODUCTO</th>
                <th width="15%">CANTIDAD</th>
                <th width="15%">PRECIO</th>
                <th width="20%">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($grouped)) {
            $html .= '
            <tr>
                <td colspan="5" style="padding:20px; text-align:center; font-style:italic;">
                    No se encontraron ventas en el rango de fechas seleccionado.
                </td>
            </tr>';
        } else {
            $i = 1;
            foreach ($grouped as $item) {
                $rowClass = ($i % 2 == 0) ? 'row-even' : '';
                $precioTexto = ($item['min_price'] == $item['max_price'])
                    ? priceFormat($item['min_price'])
                    : priceFormat($item['min_price']) . ' - ' . priceFormat($item['max_price']);

                $cantidad = number_format($item['cantidad'], 0, ',', '.');

                $html .= '
            <tr class="' . $rowClass . '">
                <td class="text-center">' . $i . '</td>
                <td class="text-left">' . htmlspecialchars($item['producto'], ENT_QUOTES, "UTF-8") . '</td>
                <td class="text-right">' . $cantidad . '</td>
                <td class="text-right">' . $precioTexto . '</td>
                <td class="text-right">' . priceFormat($item['subtotal']) . '</td>
            </tr>';
                $i++;
            }

            // Fila total general
            $html .= '
        <tr class="total-row">
            <td colspan="4" class="text-right" style="padding-right:10px;">TOTAL GENERAL:</td>
            <td class="text-right">' . priceFormat($totalGeneral) . '</td>
        </tr>';
        }

        $html .= '</tbody></table>';

        // ========================
        // PIE / FIRMAS
        // ========================
        $html .= '
        <br><br>
        <table width="100%">
            <tr>
                <td width="50%" style="border-top:1px solid #000; padding-top:18px; text-align:center;">
                    Elaboró
                </td>
                <td width="50%" style="border-top:1px solid #000; padding-top:18px; text-align:center;">
                    Revisó
                </td>
            </tr>
        </table>
        <div style="text-align:center; font-size:9pt; margin-top:18px; color:#555;">
            Reporte generado el ' . \Carbon\Carbon::now()->format('d/m/Y H:i') . ' | Sistema de Punto de Venta - La Angostura
        </div>
    ';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'Reporte_Productos_Vendidos_' . $startDateInput . '_al_' . $endDateInput . '.pdf';
        return $pdf->Output($filename, 'I');
    }

    public function exportSalesExcel(Request $request)
    {
        // 1. Validar fechas
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        // 2. OJO: solo cadenas de fecha, nada de pasar el Request
        $start = $request->input('start_date');   // ej. "2023-05-01"
        $end   = $request->input('end_date');     // ej. "2023-12-04"

        // 3. Pasar SOLO las fechas al export
        return Excel::download(
            new SalesReportExport($start, $end),
            "Reporte_ventas_{$start}_{$end}.xlsx"
        );
    }
}
