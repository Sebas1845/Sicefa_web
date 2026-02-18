<?php

namespace Modules\GDF\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizationPdfService
{
    // ✅ Ruta del JSON (storage/app/...)
    private const SIGNERS_JSON = 'gdf/authorization_signers.json';

    public static function generateForTravelRequest(int $travelRequestId, int $userId): array
    {
        // ============ 1) TR + Área ============
        $tr = DB::table('travel_requests as tr')
            ->leftJoin('areas as a', 'a.id', '=', 'tr.area_id')
            ->selectRaw("tr.*, COALESCE(a.name,'—') as area_name")
            ->where('tr.id', $travelRequestId)
            ->first();

        if (!$tr) {
            throw new \RuntimeException('Solicitud no encontrada');
        }

        $moduleKey = Str::lower((string)($tr->module ?? 'gdf'));
        if (!in_array($moduleKey, ['gdf', 'sitrav'], true)) $moduleKey = 'gdf';

        // areaKey (campesena / academic) a partir del nombre de área (como ya venías)
        $areaName = Str::lower((string)($tr->area_name ?? ''));
        $areaKey  = Str::contains($areaName, 'campesena') ? 'campesena' : 'academic';

        // ✅ Persona (nombre + cédula)
        $personId       = (int)($tr->person_id ?? 0);
        $personName     = self::resolvePersonName($personId);
        $personDocument = self::resolvePersonDocument($personId);

        // ========= Segments / Origen / Destino =========
        $segments   = self::fetchSegmentsWithDestinationName($travelRequestId);

        $originMain = (string)($tr->origin ?? '—');
        $destMain   = (string)($segments->first()->destination_name ?? ($tr->destination ?? '—'));

        // ========= Costos / Viáticos =========
        [$costs, $costsTotal] = self::fetchCosts($travelRequestId);
        [$allowances, $allowTotal] = self::fetchAllowances($travelRequestId);

        $totalAll = (float)$costsTotal + (float)$allowTotal;

        // ✅ Transporte ya “bonito” (NO JSON crudo)
        $transportText = self::buildTransportText($costs);

        $generatedBy = self::resolveUserName($userId);
        $generatedAt = now();

        // ✅ Firmantes desde JSON (module + global + área + orden)
        $signers = self::fetchAuthorizationSignersFromJson($moduleKey, $areaKey);

        // ============ 2) Render PDF ============
        $pdf = Pdf::loadView('gdf::subdirection.authorization', [
            'tr'            => $tr,
            'areaKey'       => $areaKey,

            'personName'     => $personName,
            'personDocument' => $personDocument,

            'originMain'    => $originMain,
            'destMain'      => $destMain,

            'segments'      => $segments,

            'costs'         => $costs,
            'allowances'    => $allowances,
            'costsTotal'    => $costsTotal,
            'allowTotal'    => $allowTotal,
            'totalAll'      => $totalAll,

            'transportText' => $transportText,

            'generatedBy'   => $generatedBy,
            'generatedAt'   => $generatedAt,

            'signers'       => $signers, // ✅ para mostrar firmas en el blade
        ])->setPaper('letter', 'portrait');

        // ============ 3) Guardar en storage/public ============
        $dir      = "gdf/requests/{$travelRequestId}";
        $filename = "autorizacion_{$travelRequestId}.pdf";
        $path     = "{$dir}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        // ============ 4) Upsert a travel_request_documents ============
        self::upsertAuthorizationDocument(
            travelRequestId: $travelRequestId,
            userId: $userId,
            path: $path,
            filename: $filename
        );

        return [
            'path'  => $path,
            'title' => 'Autorización',
            'disk'  => 'public',
        ];
    }

    /**
     * ✅ Guarda/actualiza en travel_request_documents (schema real tuyo)
     */
    private static function upsertAuthorizationDocument(int $travelRequestId, int $userId, string $path, string $filename): void
    {
        if (!self::tableExists('travel_request_documents')) return;

        $existing = DB::table('travel_request_documents')
            ->where('travel_request_id', $travelRequestId)
            ->where('document_type', 'authorization')
            ->whereNull('deleted_at')
            ->first();

        $payload = [
            'travel_request_id' => $travelRequestId,
            'document_type'     => 'authorization',

            'title'         => 'Autorización',
            'original_name' => $filename,
            'stored_name'   => $filename,

            'path'      => $path,
            'disk'      => 'public',
            'mime_type' => 'application/pdf',

            // enum: draft|submitted|approved|rejected
            'status' => 'approved',

            'uploaded_by' => $userId,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'is_required' => 0,

            'updated_at' => now(),
        ];

        try {
            $payload['size_bytes'] = (int) Storage::disk('public')->size($path);
        } catch (\Throwable $e) {
            // no tumbar
        }

        if (!$existing) {
            $payload['created_at'] = now();
            DB::table('travel_request_documents')->insert($payload);
        } else {
            DB::table('travel_request_documents')->where('id', (int)$existing->id)->update($payload);
        }
    }

    /**
     * ✅ Lee storage/app/gdf/authorization_signers.json
     *     - module: gdf|sitrav|both
     *     - area_key: academic|campesena|null (null=global)
     *     - orden: subdirection -> coordination -> treasury -> support
     *
     * ✅ Importante:
     * - SIEMPRE mezcla global + área.
     * - Si un rol existe en área, reemplaza al global de ese rol.
     */
    private static function fetchAuthorizationSignersFromJson(string $moduleKey, string $areaKey)
    {
        if (!Storage::disk('local')->exists(self::SIGNERS_JSON)) return collect();

        $raw = Storage::disk('local')->get(self::SIGNERS_JSON);
        $arr = json_decode($raw, true);
        if (!is_array($arr)) return collect();

        // 1) filtros: active=1 y módulo (gdf/sitrav/both)
        $rows = array_values(array_filter($arr, function ($r) use ($moduleKey) {
            $active = (int)($r['active'] ?? 0) === 1;
            if (!$active) return false;

            $m = Str::lower((string)($r['module'] ?? 'both'));
            return in_array($m, [$moduleKey, 'both'], true);
        }));

        // 2) separar global vs area
        $areaRows   = array_values(array_filter($rows, fn($r) => (string)($r['area_key'] ?? '') === $areaKey));
        $globalRows = array_values(array_filter($rows, fn($r) => empty($r['area_key'])));

        // 3) map por role_key (global primero)
        $map = [];
        foreach ($globalRows as $r) {
            $rk = Str::lower((string)($r['role_key'] ?? ''));
            if ($rk !== '') $map[$rk] = $r;
        }
        // 4) sobreescribir con área si existe
        foreach ($areaRows as $r) {
            $rk = Str::lower((string)($r['role_key'] ?? ''));
            if ($rk !== '') $map[$rk] = $r;
        }

        $picked = array_values($map);

        // 5) ordenar por rol
        $prio = [
            'subdirection' => 1,
            'coordination' => 2,
            'treasury'     => 3,
            'support'      => 4,
        ];

        usort($picked, function ($a, $b) use ($prio) {
            $pa = $prio[Str::lower((string)($a['role_key'] ?? 'support'))] ?? 99;
            $pb = $prio[Str::lower((string)($b['role_key'] ?? 'support'))] ?? 99;

            if ($pa !== $pb) return $pa <=> $pb;

            $ia = (int)($a['id'] ?? 0);
            $ib = (int)($b['id'] ?? 0);
            return $ia <=> $ib;
        });

        // 6) normalizar signature_abs (ruta real en disco) para DomPDF
        $picked = array_map(function ($r) {
            $sig = trim((string)($r['signature_path'] ?? ''));
            $r['signature_abs'] = $sig !== '' ? self::signerSignatureAbsolutePath($sig) : null;
            return (object)$r;
        }, $picked);

        return collect($picked);
    }

    /**
     * ✅ Convierte "gdf/Firmas/xxx.png" a ruta absoluta para DomPDF
     * (DomPDF necesita path real de disco)
     */
    public static function signerSignatureAbsolutePath(?string $signaturePath): ?string
    {
        $signaturePath = trim((string)$signaturePath);
        if ($signaturePath === '') return null;

        // signature_path viene como "gdf/Firmas/....png"
        $full = public_path('storage/' . $signaturePath);

        // ✅ normaliza slashes (Windows) para DomPDF
        $full = str_replace('\\', '/', $full);

        return file_exists($full) ? $full : null;
    }

    // =================== helpers existentes ===================

    private static function fetchSegmentsWithDestinationName(int $travelRequestId)
    {
        if (!self::tableExists('travel_segments')) return collect();

        $seg = DB::table('travel_segments as ts')
            ->where('ts.travel_request_id', $travelRequestId)
            ->orderBy('ts.id');

        if (self::tableExists('municipality_rates')) {
            $seg->leftJoin('municipality_rates as mr', 'mr.id', '=', 'ts.municipality_rate_id');
        }
        if (self::tableExists('village_rates')) {
            $seg->leftJoin('village_rates as vr', 'vr.id', '=', 'ts.village_rate_id');
        }

        $destExpr = "
            COALESCE(
                NULLIF(ts.destination_display_name,''),
                NULLIF(mr.municipality_name,''),
                NULLIF(vr.village_name,''),
                NULLIF(ts.destination_place,''),
                '—'
            )
        ";

        return $seg->selectRaw("ts.*, {$destExpr} as destination_name")->get();
    }

    private static function fetchCosts(int $travelRequestId): array
    {
        if (!self::tableExists('travel_costs')) return [collect(), 0.0];

        $costs = DB::table('travel_costs')
            ->where('travel_request_id', $travelRequestId)
            ->orderBy('id')
            ->get();

        $total = 0.0;
        foreach ($costs as $c) $total += (float)($c->amount ?? 0);

        return [$costs, (float)$total];
    }

    private static function fetchAllowances(int $travelRequestId): array
    {
        if (!self::tableExists('travel_allowances')) return [collect(), 0.0];

        $allowances = DB::table('travel_allowances')
            ->where('travel_request_id', $travelRequestId)
            ->orderBy('id')
            ->get();

        $sumExpr = "COALESCE(approved_amount, calculated_amount)";
        $total = (float) DB::table('travel_allowances')
            ->where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum(DB::raw($sumExpr));

        return [$allowances, $total];
    }

    private static function resolvePersonName(int $personId): string
    {
        if ($personId <= 0) return '—';
        if (!self::tableExists('people')) return '—';

        $p = DB::table('people')->where('id', $personId)->first();
        if (!$p) return '—';

        $name = trim(implode(' ', array_filter([
            trim((string)($p->first_name ?? '')),
            trim((string)($p->first_last_name ?? '')),
            trim((string)($p->second_last_name ?? '')),
        ])));

        return $name !== '' ? $name : '—';
    }

    private static function resolvePersonDocument(int $personId): string
    {
        if ($personId <= 0) return '—';
        if (!self::tableExists('people')) return '—';

        $p = DB::table('people')->where('id', $personId)->first();
        if (!$p) return '—';

        foreach (['document_number','document','identification_number','number_document','dni'] as $col) {
            if (isset($p->{$col}) && trim((string)$p->{$col}) !== '') return trim((string)$p->{$col});
        }

        return '—';
    }

    private static function buildTransportText($costs): string
    {
        if (!$costs || $costs->count() === 0) return '—';

        $first = $costs->first();

        foreach (['transport_json', 'meta', 'details', 'data', 'notes'] as $col) {
            if (!isset($first->{$col})) continue;
            $raw = trim((string)($first->{$col} ?? ''));
            if ($raw === '') continue;

            if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
                $d = json_decode($raw, true);
                if (is_array($d)) {
                    $t = trim((string)($d['transport'] ?? ''));
                    if ($t !== '') return self::prettyTransport($t, $d);
                }
            }
        }

        foreach (['transport', 'description', 'concept', 'name'] as $col) {
            if (isset($first->{$col})) {
                $v = trim((string)($first->{$col} ?? ''));
                if ($v !== '') return $v;
            }
        }

        return '—';
    }

    private static function prettyTransport(string $transport, array $d = []): string
    {
        $t = Str::lower($transport);

        $label = match ($t) {
            'bus', 'transporte_publico', 'public_transport' => 'Bus / Transporte público',
            'van', 'camioneta'                              => 'Camioneta',
            'moto', 'motorcycle'                            => 'Moto',
            'carro', 'car', 'vehiculo'                      => 'Vehículo',
            default                                         => Str::ucfirst($transport),
        };

        // lista múltiple
        if (isset($d['transports']) && is_array($d['transports']) && count($d['transports']) > 0) {
            $parts = [];
            foreach ($d['transports'] as $x) $parts[] = self::prettyTransport((string)$x);
            $parts = array_values(array_unique(array_filter($parts)));
            if (count($parts)) return implode(' + ', $parts);
        }

        // direction / ida-vuelta si existe
        $dir = Str::lower((string)($d['direction'] ?? ''));
        $dirText = match ($dir) {
            'one_way' => 'Ida',
            'two_way', 'round_trip' => 'Ida y vuelta',
            default => '',
        };

        return $dirText !== '' ? "{$label} ({$dirText})" : $label;
    }

    private static function resolveUserName(int $userId): string
    {
        if ($userId <= 0) return '—';
        if (!self::tableExists('users')) return '—';

        $u = DB::table('users')->where('id', $userId)->first();
        if (!$u) return '—';

        if (isset($u->person_id) && self::tableExists('people')) {
            $p = DB::table('people')->where('id', (int)$u->person_id)->first();
            if ($p) {
                $name = trim(implode(' ', array_filter([
                    trim((string)($p->first_name ?? '')),
                    trim((string)($p->first_last_name ?? '')),
                    trim((string)($p->second_last_name ?? '')),
                ])));
                if ($name !== '') return $name;
            }
        }

        foreach (['name', 'full_name', 'username', 'email'] as $col) {
            if (isset($u->{$col}) && trim((string)$u->{$col}) !== '') return (string)$u->{$col};
        }

        return '—';
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
