<?php

namespace Modules\PTVENTA\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesDetailSheet implements FromArray, WithTitle, WithStyles
{
    protected array $rows;

    public function __construct(array $rows)
    {
        // $rows YA debe venir con cabecera y todas las filas listas
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Detalle_ventas';
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->rows);

        // Cabecera A1:G1
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => '0F6CBD'], // azul
            ],
        ]);

        // Ajustar ancho
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
}
