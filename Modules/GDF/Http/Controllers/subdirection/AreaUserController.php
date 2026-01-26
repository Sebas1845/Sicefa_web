<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use Modules\GDF\Entities\Area;
use Modules\SICA\Entities\Role;

class AreaUserController extends Controller
{
    private function guardSubdirection(): void
    {
        $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
        if (!$isSubdirection) abort(403);
    }

    private function normalizeAreaKey(Area $area): string
    {
        $name = mb_strtoupper(trim($area->name));
        if ($name === 'CAMPESENA') return 'campesena';
        // acepta COORDINACION ACADEMICA / COORDINACIÓN ACADÉMICA
        return 'academic';
    }

    private function roleSlugFor(string $areaKey, string $scope): string
    {
        $scope = strtolower(trim($scope));

        if ($scope === 'coordinator') {
            return $areaKey === 'campesena' ? 'gdf.campesena_coordinator' : 'gdf.academic_coordinator';
        }
        if ($scope === 'support') {
            return $areaKey === 'campesena' ? 'gdf.campesena_support' : 'gdf.academic_support';
        }

        // instructor (default)
        return 'gdf.instructor';
    }

    private function attachRoleIfMissing(User $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (!$role) {
            throw new \Exception("No existe el rol {$roleSlug} en BD.");
        }

        if (!$user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    public function index(Request $request)
    {
        $this->guardSubdirection();

        $q      = $request->get('q');
        $areaId = $request->get('area_id');
        $group  = $request->get('group', 'all'); // all|academic|campesena|shared|none

        $areas = Area::orderBy('name')->get();

        $academicAreaId  = optional($areas->first(fn($a) => mb_strtoupper(trim($a->name)) === 'COORDINACION ACADEMICA' || mb_strtoupper(trim($a->name)) === 'COORDINACIÓN ACADEMICA'))->id;
        $campesenaAreaId = optional($areas->first(fn($a) => mb_strtoupper(trim($a->name)) === 'CAMPESENA'))->id;

        $usersQuery = User::query()
            ->with(['person', 'activeGdfAreas' => function ($rel) {
                $rel->withPivot(['scope','active']); // clave
            }, 'roles'])
            ->whereDoesntHave('roles', fn($r) => $r->where('slug', 'sigac.apprentice'));

        if ($q) {
            $usersQuery->where(function ($sub) use ($q) {
                $sub->where('nickname', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('person', function ($p) use ($q) {
                        $p->where('first_name', 'like', "%{$q}%")
                          ->orWhere('first_last_name', 'like', "%{$q}%")
                          ->orWhere('second_last_name', 'like', "%{$q}%")
                          ->orWhere('document_number', 'like', "%{$q}%");
                    });
            });
        }

        if ($group === 'none') {
            $usersQuery->whereDoesntHave('activeGdfAreas');
            $areaId = null;
        } elseif ($group === 'shared') {
            if ($academicAreaId) {
                $usersQuery->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $academicAreaId));
            }
            if ($campesenaAreaId) {
                $usersQuery->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $campesenaAreaId));
            }
            $areaId = null;
        } elseif ($group === 'academic' && $academicAreaId) {
            $usersQuery->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $academicAreaId));
            $areaId = $academicAreaId;
        } elseif ($group === 'campesena' && $campesenaAreaId) {
            $usersQuery->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $campesenaAreaId));
            $areaId = $campesenaAreaId;
        } else {
            if ($areaId) {
                $usersQuery->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $areaId));
            }
        }

        $users = $usersQuery->orderByDesc('id')->paginate(12)->appends($request->query());

        $stats = [
            'all'       => User::whereDoesntHave('roles', fn($r) => $r->where('slug', 'sigac.apprentice'))->count(),
            'academic'  => $academicAreaId ? User::whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $academicAreaId))->count() : null,
            'campesena' => $campesenaAreaId ? User::whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $campesenaAreaId))->count() : null,
            'shared'    => ($academicAreaId && $campesenaAreaId)
                ? User::whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $academicAreaId))
                      ->whereHas('activeGdfAreas', fn($rel) => $rel->where('areas.id', $campesenaAreaId))
                      ->count()
                : null,
            'none'      => User::whereDoesntHave('activeGdfAreas')->count(),
        ];

        return view('gdf::subdirection.area_users.index', compact('users','areas','areaId','q','group','stats'));
    }

    public function assign(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'scope'   => ['nullable', 'in:coordinator,support,instructor'],
            '_redirect_qs' => ['nullable', 'string'],
        ]);

        $user  = User::with('roles')->findOrFail($data['user_id']);
        $area  = Area::findOrFail($data['area_id']);
        $scope = $data['scope'] ?? 'instructor';

        $areaKey = $this->normalizeAreaKey($area);

        // Si el usuario va a operar como support/coordinator en un área, garantizamos rol correcto.
        $requiredRoleSlug = $this->roleSlugFor($areaKey, $scope);

        DB::transaction(function () use ($user, $area, $scope, $requiredRoleSlug) {

            // 1) Garantizar rol coherente con (área + scope)
            $this->attachRoleIfMissing($user, $requiredRoleSlug);

            // 2) Upsert pivot
            DB::table('gdf_area_user')->updateOrInsert(
                ['user_id' => $user->id, 'area_id' => $area->id],
                [
                    'active'      => true,
                    'scope'       => $scope,
                    'assigned_by' => auth()->id(),
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        });

        // redirección conservando filtros (si lo mandas desde la vista)
        $qs = $data['_redirect_qs'] ?? '';
        if ($qs) {
            return redirect()->to(route('gdf.subdirection.area_users.index') . '?' . ltrim($qs,'?'))
                ->with('success', "Asignación aplicada: {$area->name} ({$scope}) + rol {$requiredRoleSlug}.");
        }

        return back()->with('success', "Asignación aplicada: {$area->name} ({$scope}) + rol {$requiredRoleSlug}.");
    }

    public function revoke(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'area_id' => ['required', 'exists:areas,id'],
            '_redirect_qs' => ['nullable', 'string'],
        ]);

        DB::table('gdf_area_user')
            ->where('user_id', $data['user_id'])
            ->where('area_id', $data['area_id'])
            ->update([
                'active'     => false,
                'updated_at' => now(),
            ]);

        $qs = $data['_redirect_qs'] ?? '';
        if ($qs) {
            return redirect()->to(route('gdf.subdirection.area_users.index') . '?' . ltrim($qs,'?'))
                ->with('success', "Asignación desactivada.");
        }

        return back()->with('success', "Asignación desactivada.");
    }
}
