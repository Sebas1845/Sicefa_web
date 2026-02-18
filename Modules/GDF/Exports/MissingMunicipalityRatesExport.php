<?php

namespace Modules\GDF\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MissingMunicipalityRatesExport implements FromCollection, WithHeadings
{
    protected Collection $rows;

    public function __construct($rows)
    {
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'municipality_id','municipality_name','department_id','department_name',
            'bus_amount','van_amount','motorcycle_amount','air_amount',
        ];
    }
}

