<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\GDF\Entities\Area;

class SubdirectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private function guardSubdirection(): void
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);
    }

    /* ===========================================================
     * DASHBOARD (Subdirección)
     * - Fix people.full_name (no existe)
     * - KPIs presupuestales + motos (robusto)
     * - Pendientes (GDF/SITRAV)
     * =========================================================== */
    public function index(Request $request)
    {
        $this->guardSubdirection();

        $year  = (int) $request->get('year', now()->year);
        $today = now()->toDateString();

        // Áreas (resumen)
        $areas = Area::query()->orderBy('name')->get();

        // ===== KPIs Presupuesto (si existen tablas) =====
        $available = $this->tableExists('budgets')
            ? (float) DB::table('budgets')->where('year', $year)->where('active', 1)->sum('current_amount')
            : 0.0;

        $executed = 0.0;
        if ($this->tableExists('budget_movements') && $this->tableExists('budgets')) {
            $executed = (float) DB::table('budget_movements as bm')
                ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->where('bm.type', 'execute')
                ->sum('bm.amount');
        }

        $total = $available + $executed;

        // ===== KPIs Motos (si existen tablas) =====
        $motos_total = $this->safeCount('motorcycles');
        $motos_available = $this->safeCountWhere('motorcycles', ['status' => 'available']);
        $motos_assigned  = $this->safeCountWhere('motorcycles', ['status' => 'assigned']);
        $moto_assignments_open = $this->safeCountWhereIn('motorcycle_assignments', 'status', ['approved','delivered']);

        // ===== Pendientes: travel_requests (si existe) =====
        $pendingRequests = collect();
        $pendingGdfRequests = collect();
        $pendingSitravRequests = collect();

        if ($this->tableExists('travel_requests')) {

            // Base: año por start_date y estado submitted (pendiente en subdirección según tu flujo)
            $pendingBase = DB::table('travel_requests as tr')
                ->whereYear('tr.start_date', $year)
                ->whereIn('tr.status', ['submitted']);

            // Fix nombre de people: NO usar full_name
            $peopleTable = $this->tableExists('people') ? 'people' : null;

            $nameExpr = $peopleTable
                ? "TRIM(CONCAT_WS(' ',
                    NULLIF(p.first_name,''),
                    NULLIF(p.first_last_name,''),
                    NULLIF(p.second_last_name,'')
                  ))"
                : "'—'";

            // GDF
            $pendingGdfRequests = (clone $pendingBase)
                ->where('tr.module', 'gdf')
                ->when($peopleTable, function ($q) use ($peopleTable) {
                    $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id');
                })
                ->selectRaw("
                    tr.id,
                    tr.total_amount as amount,
                    COALESCE(NULLIF($nameExpr,''), '—') as instructor_name
                ")
                ->orderBy('tr.created_at', 'desc')
                ->limit(5)
                ->get();

            // SITRAV
            $pendingSitravRequests = (clone $pendingBase)
                ->where('tr.module', 'sitrav')
                ->when($peopleTable, function ($q) use ($peopleTable) {
                    $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id');
                })
                ->selectRaw("
                    tr.id,
                    tr.total_amount as amount,
                    COALESCE(NULLIF($nameExpr,''), '—') as employee_name
                ")
                ->orderBy('tr.created_at', 'desc')
                ->limit(5)
                ->get();

            $pendingRequests = $pendingGdfRequests->concat($pendingSitravRequests);
        }

        $kpis = [
            'total_budget'     => (int) round($total),
            'executed_budget'  => (int) round($executed),
            'available_budget' => (int) round($available),
            'pending_requests' => (int) $pendingRequests->count(),

            'motos_total' => (int) $motos_total,
            'motos_available' => (int) $motos_available,
            'motos_assigned' => (int) $motos_assigned,
            'moto_assignments_open' => (int) $moto_assignments_open,
        ];

        // ===== Wallet/carrusel rubros (si tablas existen) =====
        $rubroCards = collect();
        $globalAvail = (int) round($available);
        $globalSpent = (int) round($executed);
        $globalTotal = (int) round($total);

        if ($this->tableExists('budgets') && $this->tableExists('budget_items')) {
            $rubrosBase = DB::table('budgets as b')
                ->join('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->selectRaw('b.budget_item_id, bi.code, bi.name')
                ->distinct()
                ->get();

            $availByItem = DB::table('budgets as b')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->selectRaw('b.budget_item_id, COALESCE(SUM(b.current_amount),0) as avail')
                ->groupBy('b.budget_item_id')
                ->pluck('avail', 'budget_item_id');

            $spentByItem = collect();
            if ($this->tableExists('budget_movements')) {
                $spentByItem = DB::table('budget_movements as bm')
                    ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->where('bm.type', 'execute')
                    ->selectRaw('b.budget_item_id, COALESCE(SUM(bm.amount),0) as spent')
                    ->groupBy('b.budget_item_id')
                    ->pluck('spent', 'budget_item_id');
            }

            $areasUsingByItem = collect();
            if ($this->tableExists('budget_area_allocations')) {
                $areasUsingByItem = DB::table('budget_area_allocations as baa')
                    ->join('budgets as b', 'b.id', '=', 'baa.budget_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->where('baa.active', 1)
                    ->selectRaw('b.budget_item_id, COUNT(DISTINCT baa.area_id) as areas_count')
                    ->groupBy('b.budget_item_id')
                    ->pluck('areas_count', 'budget_item_id');
            }

            $peopleByItem = collect();
            $primaryPeopleByItem = collect();
            if ($this->tableExists('person_area_budget_assignments')) {
                $peopleByItem = DB::table('person_area_budget_assignments as paba')
                    ->where('paba.is_active', 1)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
                    })
                    ->selectRaw('paba.budget_item_id, COUNT(DISTINCT paba.person_id) as people_count')
                    ->groupBy('paba.budget_item_id')
                    ->pluck('people_count', 'budget_item_id');

                $primaryPeopleByItem = DB::table('person_area_budget_assignments as paba')
                    ->where('paba.is_active', 1)
                    ->where('paba.is_primary', 1)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
                    })
                    ->selectRaw('paba.budget_item_id, COUNT(DISTINCT paba.person_id) as primary_count')
                    ->groupBy('paba.budget_item_id')
                    ->pluck('primary_count', 'budget_item_id');
            }

            $rubroCards = $rubrosBase->map(function ($r) use ($availByItem, $spentByItem, $areasUsingByItem, $peopleByItem, $primaryPeopleByItem) {
                $avail = (float) ($availByItem[$r->budget_item_id] ?? 0);
                $spent = (float) ($spentByItem[$r->budget_item_id] ?? 0);
                $total = $avail + $spent;

                return [
                    'id' => (int) $r->budget_item_id,
                    'code' => $r->code,
                    'name' => $r->name,
                    'avail' => (int) round($avail),
                    'spent' => (int) round($spent),
                    'total' => (int) round($total),
                    'areas_count' => (int) ($areasUsingByItem[$r->budget_item_id] ?? 0),
                    'people_count' => (int) ($peopleByItem[$r->budget_item_id] ?? 0),
                    'primary_count' => (int) ($primaryPeopleByItem[$r->budget_item_id] ?? 0),
                ];
            })->sortByDesc('avail')->values();
        }

        return view('gdf::subdirection.dashboard', compact(
            'year',
            'areas',
            'kpis',
            'pendingRequests',
            'pendingGdfRequests',
            'pendingSitravRequests',
            'rubroCards',
            'globalAvail',
            'globalSpent',
            'globalTotal'
        ));
    }

    /* ===========================================================
     * REPORTES (Vista)
     * =========================================================== */
    public function reports(Request $request)
    {
        $this->guardSubdirection();
        $year = (int) $request->get('year', now()->year);
        return view('gdf::subdirection.reports', compact('year'));
    }

    /* ===========================================================
     * REPORTES: SUMMARY (JSON)
     * =========================================================== */
    public function reportsSummary(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);

        $q = DB::table('travel_requests as tr');
        $this->applyTravelRequestFilters($q, $request, $year);

        // KPIs
        $total_requests = (int) (clone $q)->count();
        $total_amount = (float) (clone $q)->sum('tr.total_amount');
        $total_transport = (float) (clone $q)->sum('tr.total_transport');
        $total_per_diem = (float) (clone $q)->sum('tr.total_per_diem');
        $total_other = (float) (clone $q)->sum('tr.total_other');

        $pending_subdirection = (int) (clone $q)
            ->where('tr.status', 'submitted')
            ->count();

        return response()->json([
            'total_requests' => $total_requests,
            'total_amount' => $total_amount,
            'total_transport' => $total_transport,
            'total_per_diem' => $total_per_diem,
            'total_other' => $total_other,
            'pending_subdirection' => $pending_subdirection,
        ]);
    }

    /* ===========================================================
     * REPORTES: TREND mensual (JSON)
     * =========================================================== */
    public function reportsTrend(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);

        $q = DB::table('travel_requests as tr');
        $this->applyTravelRequestFilters($q, $request, $year);

        $rows = $q->selectRaw("
                DATE_FORMAT(tr.start_date, '%Y-%m') as ym,
                COUNT(*) as total_requests,
                COALESCE(SUM(tr.total_amount),0) as total_amount
            ")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return response()->json($rows);
    }

    /* ===========================================================
     * REPORTES: TOP destinos (JSON) usando travel_segments
     * =========================================================== */
    public function reportsTopDestinations(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_segments');
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);
        $limit = (int) $request->get('limit', 10);
        $limit = max(5, min($limit, 50));

        $q = DB::table('travel_segments as ts')
            ->join('travel_requests as tr', 'tr.id', '=', 'ts.travel_request_id');

        $this->applyTravelRequestFilters($q, $request, $year);

        $labelExpr = "COALESCE(NULLIF(ts.destination_display_name,''), NULLIF(ts.destination_place,''), '—')";

        $rows = $q->selectRaw("
                $labelExpr as label,
                ts.destination_type,
                COUNT(DISTINCT tr.id) as requests_count,
                COALESCE(SUM(ts.total_cost),0) as total_cost
            ")
            ->groupBy('label', 'ts.destination_type')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    /* ===========================================================
     * REPORTES: MAP POINTS separados por módulo (Leaflet)
     * =========================================================== */
    public function reportsMapPointsGdf(Request $request)
    {
        $this->guardSubdirection();
        return $this->reportsMapPointsByModule($request, 'gdf');
    }

    public function reportsMapPointsSitrav(Request $request)
    {
        $this->guardSubdirection();
        return $this->reportsMapPointsByModule($request, 'sitrav');
    }

    private function reportsMapPointsByModule(Request $request, string $module)
    {
        $this->ensureTableOrFail('travel_segments');
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);
        $limit = (int) $request->get('limit', 200);
        $limit = max(50, min($limit, 2000));

        $q = DB::table('travel_segments as ts')
            ->join('travel_requests as tr', 'tr.id', '=', 'ts.travel_request_id')
            ->where('tr.module', $module);

        $this->applyTravelRequestFilters($q, $request, $year);

        $q->whereNotNull('ts.destination_lat')
          ->whereNotNull('ts.destination_lng');

        $labelExpr = "COALESCE(NULLIF(ts.destination_display_name,''), NULLIF(ts.destination_place,''), '—')";

        $rows = $q->selectRaw("
                $labelExpr as label,
                ts.destination_type,
                COUNT(DISTINCT tr.id) as requests_count,
                COALESCE(SUM(ts.total_cost),0) as total_cost,
                AVG(ts.destination_lat) as lat,
                AVG(ts.destination_lng) as lng
            ")
            ->groupBy('label', 'ts.destination_type')
            ->orderByDesc('requests_count')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    /* ===========================================================
     * EXPORT: PDF
     * - Usa DomPDF si existe (barryvdh/laravel-dompdf)
     * - Si no existe, devuelve HTML para imprimir
     * =========================================================== */
    public function exportReportsPdf(Request $request)
    {
        $this->guardSubdirection();

        $year = (int) $request->get('year', now()->year);

        // Armamos data (mismo filtro que UI)
        $summary = $this->reportsSummary($request)->getData(true);
        $trend = $this->reportsTrend($request)->getData(true);
        $top = $this->reportsTopDestinations($request)->getData(true);
        $mapGdf = $this->reportsMapPointsByModule($request, 'gdf')->getData(true);
        $mapSitrav = $this->reportsMapPointsByModule($request, 'sitrav')->getData(true);

        $html = view('gdf::subdirection.reports_pdf', compact(
            'year','summary','trend','top','mapGdf','mapSitrav'
        ))->render();

        // DomPDF si está instalado
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $name = 'GDF_Subdireccion_Reportes_' . $year . '.pdf';
            return $pdf->download($name);
        }

        // Fallback HTML
        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /* ===========================================================
     * EXPORT: ZIP
     * - Incluye JSON (summary/trend/top/map_gdf/map_sitrav)
     * - Si DomPDF existe, también incluye PDF
     * =========================================================== */
    public function exportReportsZip(Request $request)
    {
        $this->guardSubdirection();

        $year = (int) $request->get('year', now()->year);

        $tmpDir = storage_path('app/tmp_reports_' . Str::random(10));
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0775, true);

        $summary = $this->reportsSummary($request)->getData(true);
        $trend = $this->reportsTrend($request)->getData(true);
        $top = $this->reportsTopDestinations($request)->getData(true);
        $mapGdf = $this->reportsMapPointsByModule($request, 'gdf')->getData(true);
        $mapSitrav = $this->reportsMapPointsByModule($request, 'sitrav')->getData(true);

        file_put_contents($tmpDir . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        file_put_contents($tmpDir . '/trend.json', json_encode($trend, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        file_put_contents($tmpDir . '/top_destinations.json', json_encode($top, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        file_put_contents($tmpDir . '/map_points_gdf.json', json_encode($mapGdf, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        file_put_contents($tmpDir . '/map_points_sitrav.json', json_encode($mapSitrav, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        // PDF opcional
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdfResponse = $this->exportReportsPdf($request);
            // Si download(), no nos sirve como file; generamos directo:
            $html = view('gdf::subdirection.reports_pdf', compact(
                'year','summary','trend','top','mapGdf','mapSitrav'
            ))->render();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            file_put_contents($tmpDir . '/report.pdf', $pdf->output());
        }

        $zipName = 'GDF_Subdireccion_Reportes_' . $year . '.zip';
        $zipPath = storage_path('app/' . $zipName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el ZIP.');
        }

        foreach (glob($tmpDir . '/*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // limpieza
        foreach (glob($tmpDir . '/*') as $file) @unlink($file);
        @rmdir($tmpDir);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    /* ===========================================================
     * FILTROS compartidos (travel_requests / travel_segments join)
     * =========================================================== */
    private function applyTravelRequestFilters($q, Request $request, int $year): void
    {
        // year por start_date (si hay tr.start_date)
        if ($this->columnExists('travel_requests', 'start_date')) {
            $q->whereYear('tr.start_date', $year);
        }

        // module
        if ($request->filled('module')) {
            $q->where('tr.module', $request->get('module'));
        }

        // status
        if ($request->filled('status')) {
            $q->where('tr.status', $request->get('status'));
        }

        // area_id
        if ($request->filled('area_id')) {
            $q->where('tr.area_id', (int) $request->get('area_id'));
        }

        // budget_item_id
        if ($request->filled('budget_item_id')) {
            $q->where('tr.budget_item_id', (int) $request->get('budget_item_id'));
        }

        // rango de fechas (si aplica)
        if ($request->filled('from')) {
            $q->whereDate('tr.start_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('tr.start_date', '<=', $request->get('to'));
        }
    }

    /* ===========================================================
     * Helpers robustez
     * =========================================================== */
    private function ensureTableOrFail(string $table): void
    {
        if (!$this->tableExists($table)) {
            abort(500, "Tabla requerida no existe: {$table}");
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeCount(string $table): int
    {
        if (!$this->tableExists($table)) return 0;
        return (int) DB::table($table)->count();
    }

    private function safeCountWhere(string $table, array $where): int
    {
        if (!$this->tableExists($table)) return 0;
        $q = DB::table($table);
        foreach ($where as $k => $v) $q->where($k, $v);
        return (int) $q->count();
    }

    private function safeCountWhereIn(string $table, string $col, array $vals): int
    {
        if (!$this->tableExists($table)) return 0;
        return (int) DB::table($table)->whereIn($col, $vals)->count();
    }
}
