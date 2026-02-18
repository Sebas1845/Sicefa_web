<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BaseOfficialController extends Controller
{

    protected function assertOfficialContext(?array $ctx = null)
    {
        // si lo pasan, úsalo; si no, toma el de sesión
        $ctx = $ctx ?? session('gdf_context', []);
        $role = $ctx['role'] ?? null;
        $area = $ctx['area'] ?? null;

        if ($role !== 'official' || !in_array($area, ['academic', 'campesena'], true)) {
            return redirect()->route('gdf.gateway')
                ->with('warning', 'Selecciona tu área/contexto para continuar.');
        }

        return null;
    }

    protected function requireOfficialContext()
    {
        // reutiliza el alias para no duplicar lógica
        return $this->assertOfficialContext();
    }

    protected function ctx(): array
    {
        return session('gdf_context', []);
    }

    protected function areaIdsByKey(string $areaKey): array
    {
        $ids = (array) config("gdf.area_groups.$areaKey", []);
        return array_values(array_filter(array_map('intval', $ids)));
    }

    protected function personId(): int
    {
        $u = auth()->user();
        if (!$u) return 0;

        if (!empty($u->person_id)) return (int)$u->person_id;

        if (isset($u->person) && !empty($u->person->id)) return (int)$u->person->id;

        $pid = DB::table('users')->where('id', (int)$u->id)->value('person_id');
        return $pid ? (int)$pid : 0;
    }

    protected function ownerFilter(): array
    {
        $userId = auth()->id();
        $personId = DB::table('users')->where('id', $userId)->value('person_id');

        if ($personId) return ['person_id', (int) $personId];
        return ['created_by', (int) $userId];
    }

    protected function creationGateForCtxArea(string $areaKey, int $graceDays = 8): array
    {
        $userId = auth()->id();
        if (!$userId) {
            return ['can_enter' => false, 'can_create' => false, 'message' => 'Debes iniciar sesión.'];
        }

        $personId = DB::table('users')->where('id', $userId)->value('person_id');
        if (!$personId) {
            return ['can_enter' => false, 'can_create' => false, 'message' => 'Tu usuario no tiene person_id asociado.'];
        }

        $today = now()->toDateString();
        $graceStart = now()->addDays($graceDays)->toDateString();

        $areaIds = $this->areaIdsByKey($areaKey);
        if (empty($areaIds)) {
            return ['can_enter' => false, 'can_create' => false, 'message' => 'Área de contexto no configurada.'];
        }

        $assignment = DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', (int)$personId)
            ->where('paba.is_active', 1)
            ->whereIn('paba.area_id', $areaIds)
            ->orderByRaw("COALESCE(paba.start_date, '0000-01-01') asc")
            ->select('paba.start_date', 'paba.end_date', 'paba.area_id')
            ->first();

        if (!$assignment) {
            return [
                'can_enter'  => false,
                'can_create' => false,
                'message'    => 'No tienes asignación activa para esta área. Solicita que te asignen rubro/área.'
            ];
        }

        $start = $assignment->start_date;
        $end   = $assignment->end_date;

        if ($end && $end < $today) {
            return [
                'can_enter'  => false,
                'can_create' => false,
                'message'    => "Tu asignación finalizó el {$end}. Ya no puedes ingresar al módulo."
            ];
        }

        if ($start && $start > $today) {
            if ($start <= $graceStart) {
                return [
                    'can_enter'  => true,
                    'can_create' => false,
                    'message'    => "Tu asignación inicia el {$start}. Puedes ingresar, pero no crear solicitudes hasta esa fecha."
                ];
            }
            return [
                'can_enter'  => false,
                'can_create' => false,
                'message'    => "Tu asignación inicia el {$start}. Aún no puedes ingresar (permitido desde {$graceDays} días antes)."
            ];
        }

        return ['can_enter' => true, 'can_create' => true, 'message' => null];
    }

    protected function allowedBudgetItemsForPersonInAreaIds(int $personId, array $areaIds): array
    {
        $personId = (int)$personId;
        $areaIds  = array_values(array_filter(array_map('intval', $areaIds)));

        if ($personId <= 0 || empty($areaIds)) return [];

        $today = now()->toDateString();

        $rows = DB::table('person_area_budget_assignments as paba')
            ->join('budget_items as bi', 'bi.id', '=', 'paba.budget_item_id')
            ->where('paba.person_id', $personId)
            ->whereIn('paba.area_id', $areaIds)
            ->where('paba.is_active', 1)

            ->where(function ($q) use ($today) {
                $q->whereNull('paba.start_date')
                    ->orWhereDate('paba.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')
                    ->orWhereDate('paba.end_date', '>=', $today);
            })

            ->whereNotNull('paba.budget_item_id')

            ->select(
                'bi.id',
                DB::raw("COALESCE(bi.code,'') as code"),
                'bi.name'
            )
            ->distinct()
            ->orderBy('bi.name')
            ->get();

        return $rows->toArray();
    }


    protected function activeMotorcycleAssignmentForPerson(int $personId, array $areaIds = []): ?object
    {
        $q = DB::table('motorcycle_assignments as ma')
            ->leftJoin('motorcycles as m', 'm.id', '=', 'ma.motorcycle_id')
            ->where('ma.person_id', $personId)
            ->whereNull('ma.returned_at')
            ->whereIn('ma.status', [
                'approved',     // ✅ CLAVE (TU CASO)
                'delivered',
                'active',
                'assigned',
                'entregado'
            ]);

        if (!empty($areaIds)) {
            $q->whereIn('ma.area_id', array_map('intval', $areaIds));
        }

        return $q->orderByDesc('ma.id')
            ->select([
                'ma.*',
                'm.plate',
                'm.brand',
                'm.model',
                'm.current_odometer',
            ])
            ->first();
    }
}
