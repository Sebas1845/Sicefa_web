<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BaseOfficialController extends Controller
{
    protected function assertOfficialContext(array $ctx): void
    {
        $role = $ctx['role'] ?? null;
        $area = $ctx['area'] ?? null;

        if ($role !== 'official' || !in_array($area, ['academic', 'campesena'], true)) {
            abort(403, 'Contexto inválido para Instructor.');
        }
    }

    protected function areaIdsByKey(string $areaKey): array
    {
        $ids = (array) config("gdf.area_groups.$areaKey", []);
        return array_values(array_filter(array_map('intval', $ids)));
    }

    protected function ownerFilter(): array
    {
        $userId = auth()->id();
        $personId = DB::table('users')->where('id', $userId)->value('person_id');

        if ($personId) return ['person_id', (int) $personId];
        return ['created_by', (int) $userId];
    }

    /**
     * Gate: entrar vs crear
     */
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

    /**
     * Rubros permitidos para la persona en las áreas dadas.
     * Ajusta esta consulta si tu esquema difiere.
     */
    protected function allowedBudgetItemsForPersonInAreaIds(int $personId, array $areaIds): array
    {
        $areaIds = array_values(array_filter(array_map('intval', $areaIds)));
        if (!$personId || empty($areaIds)) return [];

        // Si existe asignación persona->área->rubro
        $rows = DB::table('person_area_budget_assignments as paba')
            ->join('budget_items as bi', 'bi.id', '=', 'paba.budget_item_id')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            ->whereIn('paba.area_id', $areaIds)
            ->whereNotNull('paba.budget_item_id')
            ->select('bi.id', DB::raw("COALESCE(bi.code,'') as code"), 'bi.name')
            ->orderBy('bi.name')
            ->get();

        // Fallback (si no hay asignación por persona): rubros del área
        if ($rows->isEmpty()) {
            $rows = DB::table('area_budget_items as abi')
                ->join('budget_items as bi', 'bi.id', '=', 'abi.budget_item_id')
                ->whereIn('abi.area_id', $areaIds)
                ->where('abi.active', 1)
                ->select('bi.id', DB::raw("COALESCE(bi.code,'') as code"), 'bi.name')
                ->orderBy('bi.name')
                ->get();
        }

        return $rows->toArray();
    }

    /**
     * Motos activas para la persona (si existe una asignación entregada/aprobada).
     * Ajusta estados y tabla según tu esquema.
     */
    protected function activeMotorcycleAssignmentForPerson(int $personId, array $areaIds = []): ?object
    {
        $q = DB::table('motorcycle_assignments as ma')
            ->where('ma.person_id', $personId)
            ->whereIn('ma.status', ['approved', 'delivered']);

        if (!empty($areaIds)) $q->whereIn('ma.area_id', array_map('intval', $areaIds));

        return $q->orderByDesc('ma.id')->first();
    }
}
