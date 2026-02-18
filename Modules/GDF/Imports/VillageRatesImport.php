<?php

namespace Modules\GDF\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class VillageRatesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $villageId = (int)($row['village_id'] ?? 0);
                if ($villageId <= 0) continue;

                $v = DB::table('villages as v')
                    ->leftJoin('municipalities as m', 'm.id', '=', 'v.municipality_id')
                    ->select('v.id','v.name as village_name','v.municipality_id','m.name as municipality_name')
                    ->where('v.id', $villageId)
                    ->first();

                if (!$v) continue;

                $bus  = (float)($row['bus_amount'] ?? 0);
                $van  = (float)($row['van_amount'] ?? 0);
                $moto = (float)($row['motorcycle_amount'] ?? 0);

                if (($bus + $van + $moto) <= 0) continue;

                DB::table('village_rates')
                    ->where('village_id', $villageId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                DB::table('village_rates')->insert([
                    'municipality_id'   => (int)$v->municipality_id,
                    'village_id'        => $villageId,
                    'village_name'      => (string)$v->village_name,
                    'municipality_name' => (string)($v->municipality_name ?? ''),
                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,
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
