<?php

namespace Modules\PTVENTA\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DashboardSheet implements FromArray, WithTitle, WithStyles
{
    protected array $monthlyRows;
    protected array $productRows;
    protected array $detailRows;

    public function __construct(array $monthlyRows, array $productRows, array $detailRows)
    {
        $this->monthlyRows = $monthlyRows;
        $this->productRows = $productRows;
        $this->detailRows  = $detailRows;
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        // 1. Extraer totales de monthlyRows
        // Filas: [Mes, Total ventas, Unidades]
        $totalVentas = 0;
        $totalUnidades = 0;

        foreach (array_slice($this->monthlyRows, 1) as $row) {
            $totalVentas   += $row[1];
            $totalUnidades += $row[2];
        }

        // 2. Top productos desde productRows
        // productRows: [ ['Producto','Cantidad','Min','Max','Subtotal'], ... ]
        $topProducts = array_slice($this->productRows, 1, 10);

        $rows = [];

        // ======== SECCIÓN PRINCIPAL DEL DASHBOARD =========
        $rows[] = ['Dashboard de Ventas'];
        $rows[] = [];
        $rows[] = ['Total Vendido', $totalVentas];
        $rows[] = ['Unidades Vendidas', $totalUnidades];
        $rows[] = [];
        $rows[] = ['Top 10 productos más vendidos'];
        $rows[] = ['Producto', 'Cantidad', 'Subtotal'];

        $rows[] = [
            $p['producto'] ?? ($p[0] ?? 'N/D'),
            $p['cantidad'] ?? ($p[1] ?? 0),
            $p['subtotal'] ?? ($p[2] ?? 0)
        ];

        $rows[] = [];
        $rows[] = ['NOTA:', 'Este dashboard se alimenta de las otras hojas del informe.'];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Título
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // Ajustes de columnas
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        return [];
    }
}
