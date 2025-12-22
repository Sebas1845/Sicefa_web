<?php

namespace Modules\PTVENTA\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use Modules\SICA\Entities\Movement;
use Modules\SICA\Entities\MovementType;
use Modules\SICA\Entities\ProductiveUnitWarehouse as PUW;
use Modules\PTVENTA\Exports\Sheets\MonthlySummarySheet;
use Modules\PTVENTA\Exports\Sheets\ProductSummarySheet;
use Modules\PTVENTA\Exports\Sheets\SalesDetailSheet;
use Modules\PTVENTA\Exports\Sheets\DashboardSheet;

class SalesReportExport implements WithMultipleSheets
{
    use Exportable;

    protected $startDate;
    protected $endDate;

    protected $monthlyRows;
    protected $productRows;
    protected $detailRows;

    public function __construct(string $startDateInput, string $endDateInput)
    {
        // Validación correcta
        if (!is_string($startDateInput) || !is_string($endDateInput)) {
            throw new \InvalidArgumentException(
                "SalesReportExport espera cadenas Y-m-d y recibió: "
                    . gettype($startDateInput) . " / " . gettype($endDateInput)
            );
        }

        ini_set('memory_limit', '1024M');

        $this->startDate = Carbon::parse($startDateInput)->startOfDay();
        $this->endDate   = Carbon::parse($endDateInput)->endOfDay();

        // Construimos TODAS las hojas
        $this->monthlyRows = $this->buildMonthlySummaryRows();
        $this->productRows = $this->buildProductSummaryRows();
        $this->detailRows  = $this->buildDetailRows();
    }

    /* ============================================================
       1. RESUMEN MENSUAL PARA GRÁFICA
       ============================================================ */
    protected function buildMonthlySummaryRows(): array
    {
        $movementType = MovementType::where('name', 'Venta')->firstOrFail();
        $puw = PUW::getAppPuw();

        $monthly = DB::table('movements as m')
            ->join('warehouse_movements as wm', 'wm.movement_id', '=', 'm.id')
            ->join('movement_details as md', 'md.movement_id', '=', 'm.id')
            ->where('wm.productive_unit_warehouse_id', $puw->id)
            ->where('wm.role', 'Entrega')
            ->where('m.movement_type_id', $movementType->id)
            ->where('m.state', 'Aprobado')
            ->whereBetween('m.registration_date', [$this->startDate, $this->endDate])
            ->groupBy(DB::raw("DATE_FORMAT(m.registration_date,'%Y-%m')"))
            ->selectRaw("
                DATE_FORMAT(m.registration_date,'%Y-%m') as ym,
                SUM(m.price) as total_ventas,
                SUM(md.amount) as unidades
            ")
            ->orderBy('ym')
            ->get();

        $rows = [['Mes', 'Total ventas', 'Unidades vendidas']];

        foreach ($monthly as $row) {
            $monthLabel = Carbon::createFromFormat('Y-m', $row->ym)
                ->translatedFormat('F Y');

            $rows[] = [
                ucfirst($monthLabel),
                (float)$row->total_ventas,
                (float)$row->unidades,
            ];
        }

        return $rows;
    }

    /* ============================================================
       2. RESUMEN DE PRODUCTOS
       ============================================================ */
    protected function buildProductSummaryRows()
    {
        $movementType = MovementType::where('name', 'Venta')->firstOrFail();
        $puw = PUW::getAppPuw();

        $movements = Movement::with(['movement_details.inventory.element'])
            ->whereHas('warehouse_movements', function ($q) use ($puw) {
                $q->where('productive_unit_warehouse_id', $puw->id)
                    ->where('role', 'Entrega');
            })
            ->where('movement_type_id', $movementType->id)
            ->where('state', 'Aprobado')
            ->whereBetween('registration_date', [$this->startDate, $this->endDate])
            ->get();

        $rows = [['Producto', 'Cantidad', 'Subtotal']];

        $grouped = [];

        foreach ($movements as $mov) {
            foreach ($mov->movement_details as $detail) {
                $name = $detail->inventory->element->name ?? 'N/A';
                $subtotal = $detail->amount * $detail->price;

                if (!isset($grouped[$name])) {
                    $grouped[$name] = [0, 0];
                }

                $grouped[$name][0] += $detail->amount;
                $grouped[$name][1] += $subtotal;
            }
        }

        foreach ($grouped as $name => [$cantidad, $subtotal]) {
            $rows[] = [$name, $cantidad, $subtotal];
        }

        return $rows;
    }

    /* ============================================================
       3. DETALLE DE VENTAS
       ============================================================ */
    protected function buildDetailRows()
    {
        $movementType = MovementType::where('name', 'Venta')->firstOrFail();

        $movements = Movement::with(['movement_details.inventory.element'])
            ->where('movement_type_id', $movementType->id)
            ->whereBetween('registration_date', [$this->startDate, $this->endDate])
            ->get();

        $rows = [['Fecha', 'Producto', 'Cantidad', 'Precio', 'Subtotal']];

        foreach ($movements as $mov) {
            foreach ($mov->movement_details as $detail) {
                $rows[] = [
                    $mov->registration_date,
                    $detail->inventory->element->name ?? 'N/A',
                    $detail->amount,
                    $detail->price,
                    $detail->amount * $detail->price,
                ];
            }
        }

        return $rows;
    }

    /* ============================================================
       4. HOJAS DEL EXCEL
       ============================================================ */
    public function sheets(): array
    {
        return [
            new MonthlySummarySheet($this->monthlyRows),
            new ProductSummarySheet($this->productRows),
            new SalesDetailSheet($this->detailRows),
            new DashboardSheet(
                $this->monthlyRows,
                $this->productRows,
                $this->detailRows
            ),

        ];
    }
}
