<?php

namespace Modules\GDF\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MunicipalityRatesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $municipalityId = (int)($row['municipality_id'] ?? 0);
                if ($municipalityId <= 0) continue;

                $munName = DB::table('municipalities')->where('id', $municipalityId)->value('name');
                if (!$munName) continue;

                $bus  = (float)($row['bus_amount'] ?? 0);
                $van  = (float)($row['van_amount'] ?? 0);
                $moto = (float)($row['motorcycle_amount'] ?? 0);
                $air  = (float)($row['air_amount'] ?? 0);

                // si todo viene en 0, puedes saltar la fila (opcional)
                if (($bus + $van + $moto + $air) <= 0) continue;

                DB::table('municipality_rates')
                    ->where('municipality_id', $municipalityId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                DB::table('municipality_rates')->insert([
                    'municipality_id'   => $municipalityId,
                    'municipality_name' => $munName,
                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,
                    'air_amount'        => $air,
                    'active'            => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
