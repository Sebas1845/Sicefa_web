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

class MonthlySummarySheet implements FromArray, WithTitle, WithCharts, WithStyles
{
    protected array $rows;

    public function __construct(array $rows)
    {
        // $rows viene de SalesReportExport: [ [header], [mes, total, unidades]... ]
        $this->rows = $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Resumen_mensual';
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->rows);

        // Cabecera
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => 'solid',
                'startColor' => ['rgb' => '16A34A'], // verde vivo
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        // Anchos automáticos
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Formato numérico con separador de miles
        if ($lastRow > 1) {
            $sheet->getStyle("B2:C{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        return [];
    }

    public function charts()
    {
        $sheetName = $this->title();
        $lastRow   = count($this->rows);

        // Si solo está la cabecera, no dibuja nada
        if ($lastRow <= 1) {
            return [];
        }

        // Categorías: Mes (columna A)
        $categories = [
            new DataSeriesValues(
                'String',
                "{$sheetName}!A$2:A$" . $lastRow,
                null,
                $lastRow - 1
            ),
        ];

        // Serie 1: Total ventas (columna B)
        $valuesVentas = [
            new DataSeriesValues(
                'Number',
                "{$sheetName}!B$2:B$" . $lastRow,
                null,
                $lastRow - 1
            ),
        ];

        // Serie 2: Unidades vendidas (columna C)
        $valuesUnidades = [
            new DataSeriesValues(
                'Number',
                "{$sheetName}!C$2:C$" . $lastRow,
                null,
                $lastRow - 1
            ),
        ];

        $series = [
            new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                range(0, count($valuesVentas) - 1),
                [new DataSeriesValues('String', "{$sheetName}!B$1", null, 1)],
                $categories,
                $valuesVentas
            ),
            new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                range(0, count($valuesUnidades) - 1),
                [new DataSeriesValues('String', "{$sheetName}!C$1", null, 1)],
                $categories,
                $valuesUnidades
            ),
        ];

        $plotArea = new PlotArea(null, $series);
        $legend   = new Legend(Legend::POSITION_RIGHT, null, false);
        $title    = new Title('Ventas mensuales (valor y unidades)');

        $chart = new Chart(
            'ventas_mensuales_chart',
            $title,
            $legend,
            $plotArea
        );

        // Posición del gráfico en la hoja
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('N25');

        return [$chart];
    }
}
