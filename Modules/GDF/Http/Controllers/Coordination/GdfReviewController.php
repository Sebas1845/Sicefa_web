<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

// AJUSTA ESTE MODELO A TU REALIDAD:
use Modules\GDF\Entities\TravelRequest;

class GdfReviewController extends Controller
{
    /**
     * GET: Bandeja de revisión para Coordinación (GDF por ahora).
     * Ruta esperada:
     * - gdf.academic.review
     * - gdf.campesena.review
     */
    public function review(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';
        $title       = $areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica';

        $q   = trim((string) $request->get('q', ''));
        $tab = $request->get('tab', 'gdf');
        if (!in_array($tab, ['gdf', 'sitrav', 'motos'], true)) {
            $tab = 'gdf';
        }

        // Por ahora SOLO GDF. Si piden sitrav/motos, devolvemos vacío.
        // (mañana conectamos SITRAV y luego motos)
        if ($tab !== 'gdf') {
            return view('gdf::coordination.review', [
                'areaKey'        => $areaKey,
                'routePrefix'    => $routePrefix,
                'title'          => $title,
                'q'              => $q,
                'tab'            => $tab,
                'gdfCount'       => 0,
                'sitravCount'    => null,
                'motosCount'     => null,
                'requests'       => collect(),
                'sitravRequests' => collect(),
                'motoRequests'   => collect(),
            ]);
        }

        // === Query GDF ===
        // Ajusta nombres de columnas según tu tabla real:
        // - status: approved_by_treasury (pendiente coordinación)
        // - area_id o area_key o similar: filtrar por área
        // - origin/destination/person/doc/id: para filtro q
        $query = TravelRequest::query();

        // 1) Filtrar por área (AJUSTA según tu schema)
        // Opción A: tienes area_id
        // $areaId = $this->areaIdFromKey($areaKey); // si quieres mapear
        // $query->where('area_id', $areaId);

        // Opción B: tienes area_key en la solicitud
        if ($this->columnExists($query, 'area_key')) {
            $query->where('area_key', $areaKey);
        }

        // 2) Solo las aprobadas por Tesorería (pendientes de Coordinación)
        $statusColumn = $this->columnExists($query, 'status') ? 'status' : null;
        if ($statusColumn) {
            $query->where($statusColumn, 'approved_by_treasury');
        }

        // 3) Filtro q (suave)
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                // Ajusta campos reales
                $like = '%' . $q . '%';

                foreach (['id', 'origin', 'destination', 'request_type', 'person_type'] as $col) {
                    if ($this->columnExists($qq, $col)) {
                        // id exact o like
                        if ($col === 'id' && ctype_digit($q)) {
                            $qq->orWhere($col, (int)$q);
                        } else {
                            $qq->orWhere($col, 'like', $like);
                        }
                    }
                }

                // Si guardas nombre/documento en columnas planas:
                foreach (['instructor_name', 'document_number', 'applicant_name'] as $col) {
                    if ($this->columnExists($qq, $col)) {
                        $qq->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        // 4) Orden
        if ($this->columnExists($query, 'updated_at')) {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderByDesc('id');
        }

        // Conteo para badge
        $gdfCount = (clone $query)->count();

        // Paginación
        $requests = $query->paginate(10)->appends($request->query());

        return view('gdf::coordination.review', [
            'areaKey'        => $areaKey,
            'routePrefix'    => $routePrefix,
            'title'          => $title,
            'q'              => $q,
            'tab'            => 'gdf',
            'gdfCount'       => $gdfCount,
            'sitravCount'    => null,
            'motosCount'     => null,

            'requests'       => $requests,
            'sitravRequests' => collect(), // mañana se conecta
            'motoRequests'   => collect(), // después
        ]);
    }

    /**
     * POST: Aprobar en Coordinación (GDF).
     * Ruta: $routePrefix.'.review.approve'
     */
    public function approve(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        DB::beginTransaction();
        try {
            $row = TravelRequest::lockForUpdate()->findOrFail($id);

            // Validación de área si aplica
            if ($this->hasAreaKey($row) && $row->area_key !== $areaKey) {
                abort(403);
            }

            // Validación estado actual
            if (isset($row->status) && $row->status !== 'approved_by_treasury') {
                return back()->with('warning', 'La solicitud ya no está en estado pendiente de Coordinación.');
            }

            // Estado nuevo
            if (isset($row->status)) {
                $row->status = 'approved_by_coordination';
            }

            // Auditoría mínima (ajusta a tu esquema)
            if ($this->propertyExists($row, 'coordinated_by')) {
                $row->coordinated_by = Auth::id();
            }
            if ($this->propertyExists($row, 'coordinated_at')) {
                $row->coordinated_at = Carbon::now();
            }

            $row->save();

            DB::commit();
            return back()->with('success', "Solicitud #{$row->id} aprobada por Coordinación.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }

    /**
     * POST: Devolver (GDF) a Apoyo o Solicitante.
     * Ruta: $routePrefix.'.review.return'
     * Body: target=support|applicant, comment=...
     */
    public function return(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $row = TravelRequest::lockForUpdate()->findOrFail($id);

            if ($this->hasAreaKey($row) && $row->area_key !== $areaKey) {
                abort(403);
            }

            if (isset($row->status) && $row->status !== 'approved_by_treasury') {
                return back()->with('warning', 'La solicitud ya no está en estado pendiente de Coordinación.');
            }

            // Nuevo estado
            if (isset($row->status)) {
                $row->status = ($data['target'] === 'support')
                    ? 'returned_to_support'
                    : 'returned_to_applicant';
            }

            // Comentario
            if ($this->propertyExists($row, 'coordination_comment')) {
                $row->coordination_comment = $data['comment'];
            } elseif ($this->propertyExists($row, 'comment')) {
                $row->comment = $data['comment'];
            }

            if ($this->propertyExists($row, 'coordinated_by')) {
                $row->coordinated_by = Auth::id();
            }
            if ($this->propertyExists($row, 'coordinated_at')) {
                $row->coordinated_at = Carbon::now();
            }

            $row->save();

            DB::commit();
            return back()->with('info', "Solicitud #{$row->id} devuelta a " . ($data['target'] === 'support' ? 'Apoyo' : 'Solicitante') . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo devolver: ' . $e->getMessage());
        }
    }

    /**
     * POST: Rechazar (GDF).
     * Ruta: $routePrefix.'.review.reject'
     * Body: comment=...
     */
    public function reject(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $row = TravelRequest::lockForUpdate()->findOrFail($id);

            if ($this->hasAreaKey($row) && $row->area_key !== $areaKey) {
                abort(403);
            }

            if (isset($row->status) && $row->status !== 'approved_by_treasury') {
                return back()->with('warning', 'La solicitud ya no está en estado pendiente de Coordinación.');
            }

            if (isset($row->status)) {
                $row->status = 'rejected_by_coordination';
            }

            if ($this->propertyExists($row, 'coordination_comment')) {
                $row->coordination_comment = $data['comment'];
            } elseif ($this->propertyExists($row, 'comment')) {
                $row->comment = $data['comment'];
            }

            if ($this->propertyExists($row, 'coordinated_by')) {
                $row->coordinated_by = Auth::id();
            }
            if ($this->propertyExists($row, 'coordinated_at')) {
                $row->coordinated_at = Carbon::now();
            }

            $row->save();

            DB::commit();
            return back()->with('success', "Solicitud #{$row->id} rechazada por Coordinación.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'No se pudo rechazar: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private function areaKeyFromPath(Request $request): string
    {
        // Ajustado a tu convención de rutas
        return str_contains($request->path(), 'gdf/campesena') ? 'campesena' : 'academic';
    }

    private function authorizeByArea(string $areaKey): void
    {
        $ok = function_exists('checkRol')
            ? ($areaKey === 'campesena'
                ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support'))
                : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support')))
            : false;

        if (!$ok) abort(403);
    }

    private function hasAreaKey($model): bool
    {
        return isset($model->area_key);
    }

    private function propertyExists($model, string $prop): bool
    {
        // property_exists no detecta atributos dinámicos de Eloquent si no existen en $fillable,
        // pero sí sirve para evitar fatal si el atributo está declarado.
        // Para Eloquent: usamos isset($model->$prop) OR in_array($prop, array_keys($model->getAttributes()))
        if (!is_object($model)) return false;

        try {
            $attrs = method_exists($model, 'getAttributes') ? array_keys($model->getAttributes()) : [];
            return property_exists($model, $prop) || in_array($prop, $attrs, true);
        } catch (\Throwable $e) {
            return property_exists($model, $prop);
        }
    }

    /**
     * Detección “suave” de columna para no romper si tu modelo/tabla no la tiene.
     * Nota: esto NO consulta schema; es heurística a nivel de builder/eloquent.
     */
    private function columnExists($builder, string $col): bool
    {
        // Para no hacer queries al schema, lo tratamos como “existe” y el dev ajusta.
        // Si quieres, mañana lo cambiamos por Schema::hasColumn(...) con cache.
        return true;
    }
}
