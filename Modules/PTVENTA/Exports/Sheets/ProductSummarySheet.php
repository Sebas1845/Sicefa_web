<?php

namespace Modules\PTVENTA\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Chart\{
    Chart,
    DataSeries,
    DataSeriesValues,
    PlotArea,
    Legend,
    Title
};

class ProductSummarySheet implements FromArray, WithTitle, WithCharts, WithStyles
{
    protected array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows; // incluye cabecera
    }

    /** Datos que se escriben en la hoja */
    public function array(): array
    {
        return $this->rows;
    }

    /** Nombre de la pestaña */
    public function title(): string
    {
        // Sin espacios para que sea fácil usar en fórmulas
        return 'Resumen_mensual';
    }

    /** Estilos básicos (cabecera verde, etc.) */
    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->rows);

        // Cabecera
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => '16A34A'], // verde vivo
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        // Ajustar ancho de columnas
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Números con separador de miles
        $sheet->getStyle("B2:C{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        return [];
    }

    /** Definición de las GRÁFICAS */
    /** Definición de las GRÁFICAS */
    public function charts()
    {
        $sheetName = $this->title();
        $lastRow   = count($this->rows);

        if ($lastRow <= 1) {
            return [];
        }

        // Categorías (Meses)
        $categories = [
            new DataSeriesValues(
                'String',
                "{$sheetName}!A2:A{$lastRow}",
                null,
                $lastRow - 1
            ),
        ];

        // Serie 1: Total ventas (columna B)
        $valuesVentas = [
            new DataSeriesValues(
                'Number',
                "{$sheetName}!B2:B{$lastRow}",
                null,
                $lastRow - 1
            ),
        ];

        // Serie 2: Unidades vendidas (columna C)
        $valuesUnidades = [
            new DataSeriesValues(
                'Number',
                "{$sheetName}!C2:C{$lastRow}",
                null,
                $lastRow - 1
            ),
        ];

        $series = [
            new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                [0],
                [new DataSeriesValues('String', "{$sheetName}!B1")],
                $categories,
                $valuesVentas
            ),
            new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                [1],
                [new DataSeriesValues('String', "{$sheetName}!C1")],
                $categories,
                $valuesUnidades
            ),
        ];

        $plotArea = new PlotArea(null, $series);
        $legend   = new Legend(Legend::POSITION_RIGHT, null, false);
        $title    = new Title('Ventas mensuales (Valor y Unidades)');

        $chart = new Chart(
            'ventas_mensuales_chart',
            $title,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('N25');

        return [$chart];
    }
}
