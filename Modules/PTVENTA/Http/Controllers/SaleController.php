<?php

namespace Modules\PTVENTA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Modules\SICA\Entities\Movement;
use Modules\SICA\Entities\MovementType;
use Modules\SICA\Entities\CashCount;
use Modules\SICA\Entities\ProductiveUnitWarehouse as PUW;
use Modules\SICA\Entities\MovementDetail; // Agregado para detalles
use Carbon\Carbon;
use TCPDF;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sale_index_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sale_index_title_view')
        ];

        $app_puw = PUW::getAppPuw();
        $cashCount = CashCount::where('productive_unit_warehouse_id', $app_puw->id)
            ->where('state', 'Abierta')
            ->first();

        $groupedProducts = [];

        if ($cashCount) {
            $startDate = Carbon::parse($cashCount->opening_date);
            $endDate = Carbon::now();

            $movement_type = MovementType::where('name', 'Venta')->firstOrFail();

            $sales = Movement::with([
                'movement_responsibilities.person',
                'movement_details.inventory.element'
            ])
                ->where('movement_type_id', $movement_type->id)
                ->where('state', 'Aprobado')
                ->whereHas('warehouse_movements', function ($query) use ($app_puw) {
                    $query->where('productive_unit_warehouse_id', $app_puw->id)
                        ->where('role', 'Entrega');
                })
                ->whereBetween('registration_date', [$startDate, $endDate])
                ->orderBy('registration_date', 'DESC')
                ->get();

            foreach ($sales as $s) {
                foreach ($s->movement_details as $detail) {
                    $name = $detail->inventory->element->product_name;
                    $price = $detail->price;

                    if (isset($groupedProducts[$name])) {
                        $groupedProducts[$name]['cantidad'] += $detail->amount;
                        $groupedProducts[$name]['subtotal'] += $detail->amount * $price;
                        $groupedProducts[$name]['min_price'] = min($groupedProducts[$name]['min_price'], $price);
                        $groupedProducts[$name]['max_price'] = max($groupedProducts[$name]['max_price'], $price);
                    } else {
                        $groupedProducts[$name] = [
                            'producto' => $name,
                            'cantidad' => $detail->amount,
                            'subtotal' => $detail->amount * $price,
                            'min_price' => $price,
                            'max_price' => $price,
                        ];
                    }
                }
            }
        }

        return view('ptventa::sale.index', compact('view', 'cashCount', 'groupedProducts'));
    }

    public function register()
    {
        $app_puw = PUW::getAppPuw();
        $open_cash_count = CashCount::where('productive_unit_warehouse_id', $app_puw->id)
            ->where('state', 'Abierta')
            ->first();

        if (!$open_cash_count) {
            return redirect(route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.sale.index'))
                ->with('error', 'Primero debes abrir una caja.');
        }

        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sale_register_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sale_register_title_view')
        ];
        return view('ptventa::sale.register', compact('view'));
    }

    public function show(Movement $movement)
    {
        $movement->loadMissing([
            'movement_type',
            'movement_responsibilities.person',
            'movement_details.inventory.element.measurement_unit',
        ]);

        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sale_show_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sale_show_title_view')
        ];
        return view('ptventa::sale.show', compact('view', 'movement'));
    }

    public function generateSales(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        if (!$start_date || !$end_date) {
            return redirect()->back()->withErrors(['error' => 'Las fechas de inicio y fin son obligatorias.']);
        }

        $startDate = Carbon::parse($start_date)->startOfDay();
        $endDate = Carbon::parse($end_date)->endOfDay();

        if ($startDate->gt($endDate)) {
            return redirect()->back()->withErrors(['error' => 'La fecha de inicio no puede ser posterior a la fecha de fin.']);
        }

        $app_puw = PUW::getAppPuw();
        $movement_type = MovementType::where('name', 'Venta')->firstOrFail();

        $cashCount = CashCount::where('productive_unit_warehouse_id', $app_puw->id)
            ->where('state', 'Abierta')
            ->first();

        $movements = Movement::with([
            'movement_responsibilities.person',
            'movement_details.inventory.element'
        ])
            ->where('movement_type_id', $movement_type->id)
            ->where('state', 'Aprobado')
            ->whereHas('warehouse_movements', function ($query) use ($app_puw) {
                $query->where('productive_unit_warehouse_id', $app_puw->id)
                    ->where('role', 'Entrega');
            })
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->orderBy('registration_date', 'DESC')
            ->get();

        $groupedProducts = [];
        foreach ($movements as $movement) {
            foreach ($movement->movement_details as $detail) {
                $name = $detail->inventory->element->product_name;
                $price = $detail->price;

                if (isset($groupedProducts[$name])) {
                    $groupedProducts[$name]['cantidad'] += $detail->amount;
                    $groupedProducts[$name]['subtotal'] += $detail->amount * $price;
                    $groupedProducts[$name]['min_price'] = min($groupedProducts[$name]['min_price'], $price);
                    $groupedProducts[$name]['max_price'] = max($groupedProducts[$name]['max_price'], $price);
                } else {
                    $groupedProducts[$name] = [
                        'producto' => $name,
                        'cantidad' => $detail->amount,
                        'subtotal' => $detail->amount * $price,
                        'min_price' => $price,
                        'max_price' => $price,
                    ];
                }
            }
        }

        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_sale_index_title_page'),
            'titleView' => trans('ptventa::controllers.PTVENTA_sale_index_title_view')
        ];

        return view('ptventa::sale.sale-form', compact(
            'view',
            'movements',
            'cashCount',
            'start_date',
            'end_date',
            'groupedProducts'
        ));
    }

    public function generateSalesProductsPDF(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        if (!$start_date || !$end_date) {
            return redirect()->back()->withErrors(['error' => 'Las fechas de inicio y fin son obligatorias.']);
        }

        $startDate = Carbon::parse($start_date)->startOfDay();
        $endDate = Carbon::parse($end_date)->endOfDay();

        if ($startDate->gt($endDate)) {
            return redirect()->back()->withErrors(['error' => 'La fecha de inicio no puede ser posterior a la fecha de fin.']);
        }

        $app_puw = PUW::getAppPuw();
        $movement_type = MovementType::where('name', 'Venta')->firstOrFail();

        $movements = Movement::where('movement_type_id', $movement_type->id)
            ->whereHas('warehouse_movements', function ($query) use ($app_puw) {
                $query->where('productive_unit_warehouse_id', $app_puw->id)
                    ->where('role', 'Entrega');
            })
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$startDate, $endDate])
            ->with('movement_details.inventory.element')
            ->get();

        $products = [];
        foreach ($movements as $movement) {
            foreach ($movement->movement_details as $detail) {
                $name = $detail->inventory->element->product_name;
                $price = $detail->price;

                if (isset($products[$name])) {
                    $products[$name]['amount'] += $detail->amount;
                    $products[$name]['subtotal'] += $detail->amount * $price;
                    $products[$name]['min_price'] = min($products[$name]['min_price'], $price);
                    $products[$name]['max_price'] = max($products[$name]['max_price'], $price);
                } else {
                    $products[$name] = [
                        'amount' => $detail->amount,
                        'subtotal' => $detail->amount * $price,
                        'min_price' => $price,
                        'max_price' => $price,
                    ];
                }
            }
        }

        $puw = PUW::getAppPuw();
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $title = 'Reporte de Productos Vendidos - ' . $start_date . ' al ' . $end_date;
        $pdf->SetTitle($title);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();
        $pdf->SetY(15);
        $header = 'Centro de Formación Agroindustrial "La Angostura" | Campoalegre - Huila';
        $pdf->Cell(0, 0, $header, 0, 1, 'C');

        $html = '<h4 style="text-align:center;"><strong>Bodega:</strong> ' . $puw->warehouse->name .
            ' - <strong>Unidad Productiva:</strong> ' . $puw->productive_unit->name . '</h4>';
        $html .= '<h3 style="text-align:center;">' . $title . '</h3>';

        $html .= '<table style="border-collapse:collapse;width:100%;">';
        $html .= '<thead style="background-color:#f2f2f2;"><tr>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:25px;"><b>#</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:left;padding:8px;"><b>Producto</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:60px;"><b>Cantidad</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:80px;"><b>Precio</b></th>';
        $html .= '<th style="border:1px solid #ddd;text-align:center;padding:8px;width:80px;"><b>Subtotal</b></th>';
        $html .= '</tr></thead><tbody>';

        $total = 0;
        $i = 0;
        foreach ($products as $name => $item) {
            $total += $item['subtotal'];
            $i++;

            $priceLabel = ($item['min_price'] == $item['max_price'])
                ? priceFormat($item['min_price'])
                : priceFormat($item['min_price']) . ' - ' . priceFormat($item['max_price']);

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $i . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:left;padding:8px;">' . $name . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $item['amount'] . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . $priceLabel . '</td>';
            $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;">' . priceFormat($item['subtotal']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot><tr>';
        $html .= '<td colspan="4" style="border:1px solid #ddd;text-align:right;padding:8px;"><strong>Total General:</strong></td>';
        $html .= '<td style="border:1px solid #ddd;text-align:center;padding:8px;"><strong>' . priceFormat($total) . '</strong></td>';
        $html .= '</tr></tfoot></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $filename = 'Reporte_productos_vendidos_' . $start_date . '_al_' . $end_date . '.pdf';
        $pdf->Output($filename, 'I');
    }
}
