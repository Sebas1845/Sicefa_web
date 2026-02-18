<?php

namespace Modules\GDF\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SICA\Entities\App;
use Modules\SICA\Entities\Permission;
use Modules\SICA\Entities\Role;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $app = App::where('name', 'GDF')->first();
        if (!$app) {
            throw new \Exception("App 'GDF' no existe en tabla apps.");
        }

        $allRoleSlugs = Role::whereNotNull('slug')->pluck('slug')->all();


        /**
         * Roles reales (según tu captura)
         * Nota: NO incluyo gdf.admin aquí.
         */
        $perms = [
            'gdf.subdirection'          => [],
            'gdf.treasury'              => [],
            'gdf.academic_coordinator'  => [],
            'gdf.campesena_coordinator' => [],
            'gdf.academic_support'      => [],
            'gdf.campesena_support'     => [],
            'gdf.instructor'            => [],
            // si existe en tu sistema:
            'gdf.superadmin'            => [],
        ];

        // Helper: crea permiso y lo asigna a roles por slug (strings)
        $grant = function (string $slug, string $name, string $description, array $roleSlugs) use ($app, &$perms) {
            $p = Permission::updateOrCreate(['slug' => $slug], [
                'name'        => $name,
                'description' => $description,
                'app_id'      => $app->id,
            ]);

            foreach ($roleSlugs as $r) {
                if (array_key_exists($r, $perms)) {
                    $perms[$r][] = $p->id;
                }
            }

            return $p;
        };

        // =========================
        // Roles “macro”
        // =========================
        $allUsers = [
            'gdf.superadmin',
            'gdf.subdirection',
            'gdf.treasury',
            'gdf.academic_coordinator',
            'gdf.campesena_coordinator',
            'gdf.academic_support',
            'gdf.campesena_support',
            'gdf.instructor',
        ];

        $coordUsers = [
            'gdf.superadmin',
            'gdf.academic_coordinator',
            'gdf.campesena_coordinator',
            'gdf.academic_support',
            'gdf.campesena_support',
        ];

        $subdir = ['gdf.superadmin', 'gdf.subdirection'];
        $treas  = ['gdf.superadmin', 'gdf.treasury'];

        $acoord = ['gdf.superadmin', 'gdf.academic_coordinator', 'gdf.academic_support'];
        $ccoord = ['gdf.superadmin', 'gdf.campesena_coordinator', 'gdf.campesena_support'];

        $asupp  = ['gdf.superadmin', 'gdf.academic_support'];
        $csupp  = ['gdf.superadmin', 'gdf.campesena_support'];

        $instr  = ['gdf.superadmin', 'gdf.instructor'];

        // =========================================================
        // PÚBLICAS / INICIALES / INFO
        // =========================================================

        $grant('cefa.gdf.index', 'Ver entrada GDF (CEFA)', 'GET /gdf/index', $allUsers);

        $grant('gdf.index', 'Ver página principal GDF', 'GET /gdf/index', $allUsers);

        $grant('gdf.access.by_token', 'Acceso por token', 'GET /gdf/access/{token}', ['gdf.superadmin']);

        // Security create-password (duplicada en rutas públicas y auth; mismo slug)
        $grant('gdf.security.password.create', 'Formulario crear contraseña', 'GET /gdf/security/create-password', ['gdf.superadmin']);
        $grant('gdf.security.password.store',  'Guardar contraseña',          'POST /gdf/security/create-password', ['gdf.superadmin']);

        // Coordination magic / create-password
        $grant('gdf.security.magic',               'Magic link Coordinación',       'GET /gdf/coordination/magic/{token}', $coordUsers);
        $grant('gdf.coordination.password.create', 'Formulario crear contraseña (Coord)', 'GET /gdf/coordination/create-password', $coordUsers);
        $grant('gdf.coordination.password.store',  'Guardar contraseña (Coord)',    'POST /gdf/coordination/create-password', $coordUsers);

        // Páginas informativas
        $grant('gdf.developers', 'Ver desarrolladores GDF', 'GET /gdf/gdf/developers', $allUsers);
        $grant('gdf.about',      'Ver acerca de GDF',       'GET /gdf/gdf/about',      $allUsers);
        $grant('gdf.tech',       'Ver tecnología GDF',      'GET /gdf/gdf/tech',       $allUsers);

        // =========================================================
        // AUTH: GATEWAY
        // =========================================================
        $grant('gdf.gateway',        'Ver gateway',              'GET /gdf/gateway',         $allUsers);
        $grant('gdf.gateway.select', 'Seleccionar contexto',     'POST /gdf/gateway/select', $allUsers);
        $grant('gdf.gateway.clear',  'Limpiar contexto gateway', 'POST /gdf/gateway/clear',  $allUsers);

        // =========================================================
        // ADMIN (Rutas existen, pero NO asignamos a gdf.admin)
        // Opcional: si quieres que solo superadmin del módulo pueda usarlas
        // =========================================================
        $grant('gdf.admin.dashboard',              'Dashboard Admin GDF',          'GET /gdf/admin/dashboard', ['gdf.superadmin']);
        $grant('gdf.admin.users.index',            'Listar usuarios Admin GDF',    'GET /gdf/admin/users', ['gdf.superadmin']);
        $grant('gdf.admin.users.assignRole',       'Asignar rol Admin GDF',        'POST /gdf/admin/users/assign-role', ['gdf.superadmin']);
        $grant('gdf.admin.users.revokeRole',       'Revocar rol Admin GDF',        'POST /gdf/admin/users/revoke-role', ['gdf.superadmin']);
        $grant('gdf.admin.users.createFromContractor', 'Crear usuario contratista', 'POST /gdf/admin/users/create-from-contractor', ['gdf.superadmin']);
        $grant('gdf.admin.users.import.index',     'Vista importar usuarios',      'GET /gdf/admin/users/import', ['gdf.superadmin']);
        $grant('gdf.admin.users.import.create',    'Importar usuarios',            'POST /gdf/admin/users/import/create', ['gdf.superadmin']);
        $grant('gdf.admin.users.createByDocument', 'Crear usuario por documento',  'POST /gdf/admin/users/create-by-document', ['gdf.superadmin']);
        $grant('gdf.admin.users.sendResetLink',    'Enviar reset link',            'POST /gdf/admin/users/{user}/send-reset-link', ['gdf.superadmin']);

        // =========================================================
        // APOYO ACADÉMICO
        // =========================================================
        $grant('gdf.support.academic.dashboard', 'Dashboard Apoyo Académico', 'GET /gdf/support/academica', $asupp);

        $grant('gdf.support.academic.requests.index', 'Listar solicitudes (Apoyo Académico)', 'GET /gdf/support/academica/requests/index', $asupp);
        $grant('gdf.support.academic.requests.show',  'Ver solicitud (Apoyo Académico)',     'GET /gdf/support/academica/requests/{travelRequest}', $asupp);

        $grant('gdf.support.academic.requests.seen',         'Marcar vista',        'POST /gdf/support/academica/requests/{travelRequest}/seen', $asupp);
        $grant('gdf.support.academic.requests.return',       'Devolver instructor', 'POST /gdf/support/academica/requests/{travelRequest}/return', $asupp);
        $grant('gdf.support.academic.requests.transport',    'Asignar transporte',  'POST /gdf/support/academica/requests/{travelRequest}/transport', $asupp);
        $grant('gdf.support.academic.requests.sendTreasury', 'Enviar a tesorería',  'POST /gdf/support/academica/requests/{travelRequest}/send-treasury', $asupp);

        $grant('gdf.support.academic.requests.segments.update',     'Actualizar segmento', 'POST /gdf/support/academica/requests/{travelRequest}/segments/{segmentId}', $asupp);
        $grant('gdf.support.academic.requests.perdiem.toggle',      'Toggle viático',      'POST /gdf/support/academica/requests/{travelRequest}/perdiem/toggle', $asupp);
        $grant('gdf.support.academic.requests.segments.suggestRate', 'Sugerir tarifa',      'GET /gdf/support/academica/requests/{travelRequest}/segments/{segmentId}/suggest-rate', $asupp);

        $grant('gdf.support.academic.rates.index', 'Ver tarifas', 'GET /gdf/support/academica/rates', $asupp);
        $grant('gdf.support.academic.rates.upsert', 'Upsert tarifas', 'POST /gdf/support/academica/rates/upsert', $asupp);
        $grant('gdf.support.academic.rates.catalog.municipality.create', 'Crear municipio', 'POST /gdf/support/academica/rates/catalog/municipality', $asupp);
        $grant('gdf.support.academic.rates.catalog.village.create',     'Crear vereda',   'POST /gdf/support/academica/rates/catalog/village', $asupp);

        $grant('gdf.support.academic.motorcycles.queue', 'Cola motos', 'GET /gdf/support/academica/motorcycles/queue', $asupp);
        $grant('gdf.support.academic.motorcycles.return', 'Devoluciones motos', 'GET /gdf/support/academica/motorcycles/return', $asupp);
        $grant('gdf.support.academic.motorcycles.assign.for_request', 'Asignar moto a solicitud', 'POST /gdf/support/academica/requests/{travelRequest}/moto/assign', $asupp);
        $grant('gdf.support.academic.motorcycles.release.for_request', 'Liberar moto de solicitud', 'POST /gdf/support/academica/requests/{travelRequest}/moto/release', $asupp);
        $grant('gdf.support.academic.motorcycles.storeDirect', 'Asignación directa', 'POST /gdf/support/academica/motorcycles/assignments/direct', $asupp);
        $grant('gdf.support.academic.motorcycles.assignments.receipt', 'Acta/recibo', 'GET /gdf/support/academica/motorcycles/assignments/{assignment}/receipt', $asupp);
        $grant('gdf.support.academic.motorcycles.store', 'Registrar moto', 'POST /gdf/support/academica/motorcycles', $asupp);

        $grant('gdf.support.academic.people.index', 'Personas por rubro', 'GET /gdf/support/academica/people', $asupp);
        $grant('gdf.support.academic.people.create', 'Crear persona (form)', 'GET /gdf/support/academica/people/create', $asupp);
        $grant('gdf.support.academic.people.store', 'Guardar persona', 'POST /gdf/support/academica/people', $asupp);
        $grant('gdf.support.academic.people.search', 'Buscar persona', 'GET /gdf/support/academica/people/search', $asupp);
        $grant('gdf.support.academic.budget_items.by_area', 'Rubros por área', 'GET /gdf/support/academica/people/budget-items-by-area', $asupp);

        $grant('gdf.support.academic.budgets.additions.store', 'Registrar adición', 'POST /gdf/support/academica/budgets/{budget}/additions', $asupp);
        $grant('gdf.support.academic.budgets.additions.apply', 'Aplicar adición', 'PATCH /gdf/support/academica/budget-additions/{addition}/apply', $asupp);

        // =========================================================
        // APOYO CAMPESENA
        // =========================================================
        $grant('gdf.support.campesena.dashboard', 'Dashboard Apoyo Campesena', 'GET /gdf/support/campesena', $csupp);

        $grant('gdf.support.campesena.requests.index', 'Listar solicitudes (Apoyo Campesena)', 'GET /gdf/support/campesena/requests/index', $csupp);
        $grant('gdf.support.campesena.requests.show',  'Ver solicitud (Apoyo Campesena)',     'GET /gdf/support/campesena/requests/{travelRequest}', $csupp);

        $grant('gdf.support.campesena.requests.seen',         'Marcar vista',        'POST /gdf/support/campesena/requests/{travelRequest}/seen', $csupp);
        $grant('gdf.support.campesena.requests.return',       'Devolver instructor', 'POST /gdf/support/campesena/requests/{travelRequest}/return', $csupp);
        $grant('gdf.support.campesena.requests.transport',    'Asignar transporte',  'POST /gdf/support/campesena/requests/{travelRequest}/transport', $csupp);
        $grant('gdf.support.campesena.requests.sendTreasury', 'Enviar a tesorería',  'POST /gdf/support/campesena/requests/{travelRequest}/send-treasury', $csupp);

        $grant('gdf.support.campesena.requests.segments.update',     'Actualizar segmento', 'POST /gdf/support/campesena/requests/{travelRequest}/segments/{segmentId}', $csupp);
        $grant('gdf.support.campesena.requests.perdiem.toggle',      'Toggle viático',      'POST /gdf/support/campesena/requests/{travelRequest}/perdiem/toggle', $csupp);
        $grant('gdf.support.campesena.requests.segments.suggestRate', 'Sugerir tarifa',      'GET /gdf/support/campesena/requests/{travelRequest}/segments/{segmentId}/suggest-rate', $csupp);

        $grant('gdf.support.campesena.rates.index', 'Ver tarifas', 'GET /gdf/support/campesena/rates', $csupp);
        $grant('gdf.support.campesena.rates.upsert', 'Upsert tarifas', 'POST /gdf/support/campesena/rates/upsert', $csupp);
        $grant('gdf.support.campesena.rates.catalog.municipality.create', 'Crear municipio', 'POST /gdf/support/campesena/rates/catalog/municipality', $csupp);
        $grant('gdf.support.campesena.rates.catalog.village.create',     'Crear vereda',   'POST /gdf/support/campesena/rates/catalog/village', $csupp);

        $grant('gdf.support.campesena.motorcycles.queue', 'Cola motos', 'GET /gdf/support/campesena/motorcycles/queue', $csupp);
        $grant('gdf.support.campesena.motorcycles.return', 'Devoluciones motos', 'GET /gdf/support/campesena/motorcycles/return', $csupp);
        $grant('gdf.support.campesena.motorcycles.assign.for_request', 'Asignar moto a solicitud', 'POST /gdf/support/campesena/requests/{travelRequest}/moto/assign', $csupp);
        $grant('gdf.support.campesena.motorcycles.release.for_request', 'Liberar moto de solicitud', 'POST /gdf/support/campesena/requests/{travelRequest}/moto/release', $csupp);
        $grant('gdf.support.campesena.motorcycles.storeDirect', 'Asignación directa', 'POST /gdf/support/campesena/motorcycles/assignments/direct', $csupp);
        $grant('gdf.support.campesena.motorcycles.assignments.receipt', 'Acta/recibo', 'GET /gdf/support/campesena/motorcycles/assignments/{assignment}/receipt', $csupp);
        $grant('gdf.support.campesena.motorcycles.store', 'Registrar moto', 'POST /gdf/support/campesena/motorcycles', $csupp);

        $grant('gdf.support.campesena.people.index', 'Personas por rubro', 'GET /gdf/support/campesena/people', $csupp);
        $grant('gdf.support.campesena.people.create', 'Crear persona (form)', 'GET /gdf/support/campesena/people/create', $csupp);
        $grant('gdf.support.campesena.people.store', 'Guardar persona', 'POST /gdf/support/campesena/people', $csupp);
        $grant('gdf.support.campesena.people.search', 'Buscar persona', 'GET /gdf/support/campesena/people/search', $csupp);
        $grant('gdf.support.campesena.budget_items.by_area', 'Rubros por área', 'GET /gdf/support/campesena/people/budget-items-by-area', $csupp);

        $grant('gdf.support.campesena.budgets.additions.store', 'Registrar adición', 'POST /gdf/support/campesena/budgets/{budget}/additions', $csupp);
        $grant('gdf.support.campesena.budgets.additions.apply', 'Aplicar adición', 'PATCH /gdf/support/campesena/budget-additions/{addition}/apply', $csupp);

        // =========================================================
        // TESORERÍA
        // =========================================================
        $grant('gdf.treasury.dashboard',      'Dashboard Tesorería',          'GET /gdf/treasury/review', $treas);
        $grant('gdf.treasury.requests.index', 'Listar solicitudes Tesorería', 'GET /gdf/treasury/requests', $treas);
        $grant('gdf.treasury.requests.show',  'Ver solicitud Tesorería',      'GET /gdf/treasury/requests/{id}', $treas);
        $grant('gdf.treasury.requests.approve', 'Aprobar Tesorería',           'POST /gdf/treasury/requests/{id}/approve', $treas);
        $grant('gdf.treasury.requests.return', 'Devolver Tesorería',          'POST /gdf/treasury/requests/{id}/return', $treas);
        $grant('gdf.treasury.requests.reject', 'Rechazar Tesorería',          'POST /gdf/treasury/requests/{id}/reject', $treas);

        // =========================================================
        // COORDINACIÓN ACADÉMICA
        // =========================================================
        $grant('gdf.academic.dashboard',      'Dashboard Coordinación Académica', 'GET /gdf/academic/dashboard', $acoord);

        $grant('gdf.academic.review',         'Bandeja revisión Académica', 'GET /gdf/academic/review', $acoord);
        $grant('gdf.academic.review.approve', 'Aprobar Académica',          'POST /gdf/academic/review/{id}/approve', $acoord);
        $grant('gdf.academic.review.return',  'Devolver Académica',         'POST /gdf/academic/review/{id}/return',  $acoord);
        $grant('gdf.academic.review.reject',  'Rechazar Académica',         'POST /gdf/academic/review/{id}/reject',  $acoord);

        $grant('gdf.academic.people.create',  'Crear persona (Académica)',  'GET /gdf/academic/people/create', $acoord);
        $grant('gdf.academic.people.search',  'Buscar persona (Académica)', 'GET /gdf/academic/people/search', $acoord);
        $grant('gdf.academic.people.store',   'Guardar persona (Académica)', 'POST /gdf/academic/people',       $acoord);
        $grant('gdf.academic.people.index',   'Listar personas (Académica)', 'GET /gdf/academic/people',       $acoord);
        $grant('gdf.academic.people.by_rubro', 'Personas por rubro (Académica)', 'GET /gdf/academic/people/by-rubro', $acoord);

        $grant('gdf.academic.budget_items.by_area', 'Rubros por área (Académica)', 'GET /gdf/academic/budget-items', $acoord);

        // OJO: en tus rutas estos quedaron como /gdf/auth/magic pero dentro del prefix academic
        $grant('gdf.academic.gdf.auth.magic',     'Magic link auth (Académica)', 'GET /gdf/academic/gdf/auth/magic/{token}', $acoord);
        $grant('gdf.academic.gdf.auth.magic.set', 'Set password magic (Académica)', 'POST /gdf/academic/gdf/auth/magic/{token}', $acoord);

        $grant('gdf.academic.motorcycles.index',        'Ver motos (Académica)', 'GET /gdf/academic/motorcycles', $acoord);
        $grant('gdf.academic.motorcycles.assign.create', 'Form asignar moto (Académica)', 'GET /gdf/academic/motorcycles/assign', $acoord);
        $grant('gdf.academic.motorcycles.assign.store', 'Guardar asignación moto (Académica)', 'POST /gdf/academic/motorcycles/assign', $acoord);
        $grant('gdf.academic.motorcycles.quota_status', 'Estado cupos moto (Académica)', 'GET /gdf/academic/motorcycles/quota-status', $acoord);
        $grant('gdf.academic.motorcycles.search_person', 'Buscar persona moto (Académica)', 'GET /gdf/academic/motorcycles/search-person', $acoord);

        $grant('gdf.academic.motorcycles.queue',   'Cola motos (Académica)', 'GET /gdf/academic/motorcycles/queue', $acoord);
        $grant('gdf.academic.motorcycles.assign',  'Asignar en cola (Académica)', 'POST /gdf/academic/motorcycles/assign/{assignment}', $acoord);
        $grant('gdf.academic.motorcycles.deliver', 'Entregar moto (Académica)', 'POST /gdf/academic/motorcycles/deliver/{assignment}', $acoord);
        $grant('gdf.academic.motorcycles.return',  'Recibir devolución (Académica)', 'POST /gdf/academic/motorcycles/return/{assignment}', $acoord);
        $grant('gdf.academic.motorcycles.cancel',  'Cancelar asignación (Académica)', 'POST /gdf/academic/motorcycles/cancel/{assignment}', $acoord);

        $grant('gdf.academic.requests.index', 'Ver requests index (Académica)', 'GET /gdf/academic/requests/{id}/index', $acoord);

        $grant('gdf.academic.motorcycles.assign_create',       'Asignación con moto (Académica)', 'GET /gdf/academic/motorcycles/assign-create', $acoord);
        $grant('gdf.academic.motorcycles.assign_create.store', 'Guardar asignación con moto (Académica)', 'POST /gdf/academic/motorcycles/assign-create', $acoord);

        // =========================================================
        // COORDINACIÓN CAMPESENA
        // =========================================================
        $grant('gdf.campesena.dashboard',      'Dashboard Coordinación Campesena', 'GET /gdf/campesena/dashboard', $ccoord);

        $grant('gdf.campesena.review',         'Bandeja revisión Campesena', 'GET /gdf/campesena/review', $ccoord);
        $grant('gdf.campesena.review.approve', 'Aprobar Campesena',          'POST /gdf/campesena/review/{id}/approve', $ccoord);
        $grant('gdf.campesena.review.return',  'Devolver Campesena',         'POST /gdf/campesena/review/{id}/return',  $ccoord);
        $grant('gdf.campesena.review.reject',  'Rechazar Campesena',         'POST /gdf/campesena/review/{id}/reject',  $ccoord);

        $grant('gdf.campesena.people.create',  'Crear persona (Campesena)',  'GET /gdf/campesena/people/create', $ccoord);
        $grant('gdf.campesena.people.search',  'Buscar persona (Campesena)', 'GET /gdf/campesena/people/search', $ccoord);
        $grant('gdf.campesena.people.store',   'Guardar persona (Campesena)', 'POST /gdf/campesena/people',       $ccoord);
        $grant('gdf.campesena.people.index',   'Listar personas (Campesena)', 'GET /gdf/campesena/people',       $ccoord);
        $grant('gdf.campesena.people.by_rubro', 'Personas por rubro (Campesena)', 'GET /gdf/campesena/people/by-rubro', $ccoord);

        $grant('gdf.campesena.budget_items.by_area', 'Rubros por área (Campesena)', 'GET /gdf/campesena/budget-items', $ccoord);

        $grant('gdf.campesena.gdf.auth.magic',     'Magic link auth (Campesena)', 'GET /gdf/campesena/gdf/auth/magic/{token}', $ccoord);
        $grant('gdf.campesena.gdf.auth.magic.set', 'Set password magic (Campesena)', 'POST /gdf/campesena/gdf/auth/magic/{token}', $ccoord);

        $grant('gdf.campesena.motorcycles.index',        'Ver motos (Campesena)', 'GET /gdf/campesena/motorcycles', $ccoord);
        $grant('gdf.campesena.motorcycles.assign.create', 'Form asignar moto (Campesena)', 'GET /gdf/campesena/motorcycles/assign', $ccoord);
        $grant('gdf.campesena.motorcycles.assign.store', 'Guardar asignación moto (Campesena)', 'POST /gdf/campesena/motorcycles/assign', $ccoord);
        $grant('gdf.campesena.motorcycles.quota_status', 'Estado cupos moto (Campesena)', 'GET /gdf/campesena/motorcycles/quota-status', $ccoord);
        $grant('gdf.campesena.motorcycles.search_person', 'Buscar persona moto (Campesena)', 'GET /gdf/campesena/motorcycles/search-person', $ccoord);
        $grant('gdf.campesena.motorcycles.people_in_area', 'Personas en área (Campesena)', 'GET /gdf/campesena/motorcycles/people-in-area', $ccoord);

        $grant('gdf.campesena.motorcycles.queue',   'Cola motos (Campesena)', 'GET /gdf/campesena/motorcycles/queue', $ccoord);
        $grant('gdf.campesena.motorcycles.assign',  'Asignar en cola (Campesena)', 'POST /gdf/campesena/motorcycles/assign/{assignment}', $ccoord);
        $grant('gdf.campesena.motorcycles.deliver', 'Entregar moto (Campesena)', 'POST /gdf/campesena/motorcycles/deliver/{assignment}', $ccoord);
        $grant('gdf.campesena.motorcycles.return',  'Recibir devolución (Campesena)', 'POST /gdf/campesena/motorcycles/return/{assignment}', $ccoord);
        $grant('gdf.campesena.motorcycles.cancel',  'Cancelar asignación (Campesena)', 'POST /gdf/campesena/motorcycles/cancel/{assignment}', $ccoord);

        $grant('gdf.campesena.requests.index', 'Ver requests index (Campesena)', 'GET /gdf/campesena/requests/{id}/index', $ccoord);

        $grant('gdf.campesena.motorcycles.assign_create',       'Asignación con moto (Campesena)', 'GET /gdf/campesena/motorcycles/assign-create', $ccoord);
        $grant('gdf.campesena.motorcycles.assign_create.store', 'Guardar asignación con moto (Campesena)', 'POST /gdf/campesena/motorcycles/assign-create', $ccoord);

        // =========================================================
        // INSTRUCTOR (GDF + SITRAV)
        // =========================================================
        $grant('gdf.instructor.dashboard',      'Dashboard Instructor', 'GET /gdf/instructor', $instr);

        $grant('gdf.instructor.requests.index', 'Listar solicitudes Instructor', 'GET /gdf/instructor/requests', $instr);
        $grant('gdf.instructor.requests.create', 'Crear solicitud (form)',        'GET /gdf/instructor/requests/create', $instr);
        $grant('gdf.instructor.requests.store', 'Guardar solicitud',             'POST /gdf/instructor/requests', $instr);
        $grant('gdf.instructor.requests.show',  'Ver solicitud',                 'GET /gdf/instructor/requests/{gdfRequest}', $instr);
        $grant('gdf.instructor.requests.cancel', 'Cancelar solicitud',            'POST /gdf/instructor/requests/{gdfRequest}/cancel', $instr);

        $grant('gdf.instructor.catalog.budgetItems',   'Consultar rubros',      'GET /gdf/instructor/catalog/budget-items', $instr);
        $grant('gdf.instructor.catalog.departments',   'Consultar departamentos', 'GET /gdf/instructor/catalog/departments', $instr);
        $grant('gdf.instructor.catalog.municipalities', 'Consultar municipios',   'GET /gdf/instructor/catalog/municipalities', $instr);
        $grant('gdf.instructor.catalog.villages',      'Consultar veredas',      'GET /gdf/instructor/catalog/villages', $instr);
        $grant('gdf.instructor.catalog.transportRate', 'Consultar tarifa transporte', 'GET /gdf/instructor/transport-rate', $instr);

        $grant('gdf.instructor.motorcycle.index',  'Ver moto (Instructor)',      'GET /gdf/instructor/motorcycle', $instr);
        $grant('gdf.instructor.motorcycle.store',  'Solicitar moto',             'POST /gdf/instructor/motorcycle', $instr);
        $grant('gdf.instructor.motorcycle.cancel', 'Cancelar solicitud moto',    'POST /gdf/instructor/motorcycle/{assignment}/cancel', $instr);
        $grant('gdf.instructor.motorcycle.active', 'Ver moto activa',            'GET /gdf/instructor/motorcycle/active', $instr);

        // SITRAV (programs)
        $grant('gdf.instructor.sitrav.programs.index', 'Ver programas', 'GET /gdf/instructor/programs', $instr);
        $grant('gdf.instructor.sitrav.programs.create', 'Crear desde programa', 'POST /gdf/instructor/programs/{programRequestId}/create', $instr);
        $grant('gdf.instructor.sitrav.programs.store', 'Guardar desde programa', 'POST /gdf/instructor/programs/{programRequestId}', $instr);

        $grant('gdf.instructor.sitrav.requests.edit',          'Editar solicitud SITRAV', 'GET /gdf/instructor/requests/{travelRequestId}/edit', $instr);
        $grant('gdf.instructor.sitrav.requests.storeDates',    'Guardar fechas SITRAV',   'POST /gdf/instructor/requests/{travelRequestId}/dates', $instr);
        $grant('gdf.instructor.sitrav.requests.storeCosts',    'Guardar costos SITRAV',   'POST /gdf/instructor/requests/{travelRequestId}/costs', $instr);
        $grant('gdf.instructor.sitrav.requests.costs.autosave', 'Autosave costos SITRAV',  'POST /gdf/instructor/requests/{travelRequestId}/costs/autosave', $instr);
        $grant('gdf.instructor.sitrav.requests.allowances.store', 'Guardar viáticos SITRAV', 'POST /gdf/instructor/requests/{travelRequestId}/allowances', $instr);
        $grant('gdf.instructor.sitrav.requests.submit',        'Enviar solicitud SITRAV', 'POST /gdf/instructor/requests/{travelRequestId}/submit', $instr);

        // =========================================================
        // SUBDIRECCIÓN
        // =========================================================
        $grant('gdf.subdirection.dashboard', 'Dashboard Subdirección', 'GET /gdf/subdirection/dashboard', $subdir);

        // Reportes + export
        $grant('gdf.subdirection.reports',           'Ver reportes',          'GET /gdf/subdirection/reports', $subdir);
        $grant('gdf.subdirection.reports.summary',   'Resumen reportes',      'GET /gdf/subdirection/reports/summary', $subdir);
        $grant('gdf.subdirection.reports.trend',     'Tendencia reportes',    'GET /gdf/subdirection/reports/trend', $subdir);
        $grant('gdf.subdirection.reports.top',       'Top destinos',          'GET /gdf/subdirection/reports/top-destinations', $subdir);
        $grant('gdf.subdirection.reports.map.gdf',   'Mapa puntos GDF',       'GET /gdf/subdirection/reports/map-points-gdf', $subdir);
        $grant('gdf.subdirection.reports.map.sitrav', 'Mapa puntos SITRAV',    'GET /gdf/subdirection/reports/map-points-sitrav', $subdir);
        $grant('gdf.subdirection.reports.export.pdf', 'Exportar PDF',          'GET /gdf/subdirection/reports/export/pdf', $subdir);
        $grant('gdf.subdirection.reports.export.zip', 'Exportar ZIP',          'GET /gdf/subdirection/reports/export/zip', $subdir);

        // Solicitudes
        $grant('gdf.subdirection.requests.index',   'Listar solicitudes (Subdirección)', 'GET /gdf/subdirection/requests', $subdir);
        $grant('gdf.subdirection.requests.show',    'Ver solicitud (Subdirección)',     'GET /gdf/subdirection/requests/{id}', $subdir);
        $grant('gdf.subdirection.requests.approve', 'Aprobar solicitud',                'POST /gdf/subdirection/requests/{id}/approve', $subdir);
        $grant('gdf.subdirection.requests.reject',  'Rechazar solicitud',               'POST /gdf/subdirection/requests/{id}/reject', $subdir);
        $grant('gdf.subdirection.requests.return',  'Devolver solicitud',               'POST /gdf/subdirection/requests/{id}/return', $subdir);

        // Rubros
        $grant('gdf.subdirection.rubros',        'Listar rubros',         'GET /gdf/subdirection/rubros', $subdir);
        $grant('gdf.subdirection.rubros.create', 'Crear rubro (form)',    'GET /gdf/subdirection/rubros/create', $subdir);
        $grant('gdf.subdirection.rubros.store',  'Guardar rubro',         'POST /gdf/subdirection/rubros', $subdir);
        $grant('gdf.subdirection.rubros.edit',   'Editar rubro (form)',   'GET /gdf/subdirection/rubros/{id}/edit', $subdir);
        $grant('gdf.subdirection.rubros.update', 'Actualizar rubro',      'PUT /gdf/subdirection/rubros/{id}', $subdir);

        // Presupuestos
        $grant('gdf.subdirection.budgets.index', 'Listar presupuestos', 'GET /gdf/subdirection/budgets', $subdir);
        $grant('gdf.subdirection.budgets.create', 'Crear presupuesto (form)', 'GET /gdf/subdirection/budgets/create', $subdir);
        $grant('gdf.subdirection.budgets.store', 'Guardar presupuesto', 'POST /gdf/subdirection/budgets', $subdir);
        $grant('gdf.subdirection.budgets.edit',  'Editar presupuesto (form)', 'GET /gdf/subdirection/budgets/{id}/edit', $subdir);
        $grant('gdf.subdirection.budgets.update', 'Actualizar presupuesto', 'PUT /gdf/subdirection/budgets/{id}', $subdir);

        $grant('gdf.subdirection.budgets.percentages.edit',  'Editar porcentajes', 'GET /gdf/subdirection/budgets/{id}/percentages', $subdir);
        $grant('gdf.subdirection.budgets.percentages.store', 'Guardar porcentajes', 'POST /gdf/subdirection/budgets/{id}/percentages', $subdir);

        $grant('gdf.subdirection.areas.activate',   'Activar área',   'PATCH /gdf/subdirection/areas/{id}/activate', $subdir);
        $grant('gdf.subdirection.areas.deactivate', 'Desactivar área', 'PATCH /gdf/subdirection/areas/{id}/deactivate', $subdir);

        // Instructores / reportes / auditoría
        $grant('gdf.subdirection.instructors',      'Ver instructores', 'GET /gdf/subdirection/instructors', $subdir);
        $grant('gdf.subdirection.audit',            'Ver auditoría',    'GET /gdf/subdirection/audit', $subdir);

        // Movements
        $grant('gdf.subdirection.movements.index', 'Ver movimientos', 'GET /gdf/subdirection/movements', $subdir);
        $grant('gdf.subdirection.movements.show',  'Ver movimiento',  'GET /gdf/subdirection/movements/{id}', $subdir);

        // Budgets -> areas
        $grant('gdf.subdirection.budgets.areas.edit',  'Editar áreas presupuesto', 'GET /gdf/subdirection/budgets/{id}/areas', $subdir);
        $grant('gdf.subdirection.budgets.areas.store', 'Guardar áreas presupuesto', 'POST /gdf/subdirection/budgets/{id}/areas', $subdir);

        // Users
        $grant('gdf.subdirection.users.index',          'Listar usuarios (Subdirección)', 'GET /gdf/subdirection/users', $subdir);
        $grant('gdf.subdirection.users.assignRole',     'Asignar rol usuario', 'POST /gdf/subdirection/users/assign-role', $subdir);
        $grant('gdf.subdirection.users.revokeRole',     'Revocar rol usuario', 'POST /gdf/subdirection/users/revoke-role', $subdir);
        $grant('gdf.subdirection.users.sendResetLink',  'Enviar reset link',   'POST /gdf/subdirection/users/{user}/send-reset-link', $subdir);
        $grant('gdf.subdirection.users.createByDocument', 'Crear usuario por doc', 'POST /gdf/subdirection/users/create-by-document', $subdir);
        $grant('gdf.subdirection.users.import.create',  'Importar usuarios',    'POST /gdf/subdirection/users/import-create', $subdir);

        // Area-users
        $grant('gdf.subdirection.area_users.index',  'Ver usuarios por área', 'GET /gdf/subdirection/area-users', $subdir);
        $grant('gdf.subdirection.area_users.assign', 'Asignar usuario a área', 'POST /gdf/subdirection/area-users/assign', $subdir);
        $grant('gdf.subdirection.area_users.revoke', 'Revocar usuario de área', 'POST /gdf/subdirection/area-users/revoke', $subdir);

        // Áreas catálogo
        $grant('gdf.subdirection.areas.index',  'Listar áreas', 'GET /gdf/subdirection/areas', $subdir);
        $grant('gdf.subdirection.areas.create', 'Crear área (form)', 'GET /gdf/subdirection/areas/create', $subdir);
        $grant('gdf.subdirection.areas.store',  'Guardar área', 'POST /gdf/subdirection/areas', $subdir);
        $grant('gdf.subdirection.areas.edit',   'Editar área (form)', 'GET /gdf/subdirection/areas/{id}/edit', $subdir);
        $grant('gdf.subdirection.areas.update', 'Actualizar área', 'PUT /gdf/subdirection/areas/{id}', $subdir);

        // Área -> rubros
        $grant('gdf.subdirection.area_budget_items.edit', 'Editar rubros por área', 'GET /gdf/subdirection/areas/{area}/rubros', $subdir);
        $grant('gdf.subdirection.area_rubros.update',      'Guardar rubros por área', 'POST /gdf/subdirection/areas/{area}/rubros', $subdir);

        // Motos Subdirección (macro)
        $grant('gdf.subdirection.motorcycles.index',  'Listar motos (Subdirección)', 'GET /gdf/subdirection/motorcycles', $subdir);
        $grant('gdf.subdirection.motorcycles.create', 'Crear moto (form)',          'GET /gdf/subdirection/motorcycles/create', $subdir);
        $grant('gdf.subdirection.motorcycles.store',  'Guardar moto',               'POST /gdf/subdirection/motorcycles', $subdir);
        $grant('gdf.subdirection.motorcycles.transfer', 'Transferir moto',           'POST /gdf/subdirection/motorcycles/{motorcycle}/transfer', $subdir);

        $grant('gdf.subdirection.motorcycles.quotas.index', 'Ver cupos motos', 'GET /gdf/subdirection/motorcycles/quotas', $subdir);
        $grant('gdf.subdirection.motorcycles.quotas.store', 'Guardar cupos motos', 'POST /gdf/subdirection/motorcycles/quotas', $subdir);

        $grantGlobal = function (string $slug, string $name, string $description, array $roleSlugs) use ($app) {
            $p = Permission::updateOrCreate(['slug' => $slug], [
                'name'        => $name,
                'description' => $description,
                'app_id'      => $app->id,
            ]);

            // Asignar a roles
            $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id');
            foreach ($roleIds as $rid) {
                // si tu relación es roles()->permissions()
                // normalmente se hace desde Role->permissions()->syncWithoutDetaching()
                // pero aquí lo hacemos rápido por rol:
                $role = Role::find($rid);
                if ($role) $role->permissions()->syncWithoutDetaching([$p->id]);
            }

            return $p;
        };

        // PERMISOS PARA TODOS
        $grantGlobal('profile.show', 'Ver perfil de usuario', 'GET /profile', $allRoleSlugs);
        $grantGlobal('profile.email.update', 'Actualizar correo del perfil', 'POST /profile/email', $allRoleSlugs);

        // =========================================================
        // SYNC a roles
        // =========================================================
        foreach ($perms as $roleSlug => $ids) {
            $role = Role::where('slug', $roleSlug)->first();
            if ($role && count($ids) > 0) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
