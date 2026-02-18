<?php

namespace Modules\GTH\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\SICA\Entities\App;
use Modules\SICA\Entities\Permission;
use Modules\SICA\Entities\Role;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $permissions_admin = [];
        $permissions_brigadista = [];
        $permissions_registerattendance = [];

        $app = App::where('name', 'GTH')->first();

        if (!$app) {
            $this->command->error('Aplicación GTH no encontrada.');
            return;
        }

        // Asistencia
        $permission = Permission::updateOrCreate(['slug' => 'gth.registerattendance.registerattendance.index'], [
            'name' => 'Registrar Asistencia',
            'description' => 'Tendrá acceso a gestionar las asistencias',
            'description_english' => 'You will have access to manage attendance',
            'app_id' => $app->id
        ]);
        $permissions_registerattendance[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.index'], [
            'name' => 'Administrador',
            'description' => 'Tendrá acceso al menú del administrador',
            'description_english' => 'You will have access to the administrator menu',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.brigadista.attendancereport.index'], [
            'name' => 'Reporte',
            'description' => 'Tendrá acceso a gestionar las asistencias',
            'description_english' => 'You will have access to manage attendance',
            'app_id' => $app->id
        ]);
        $permissions_brigadista[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.registerattendance.attendancecourse.index'], [
            'name' => 'Asistencia por Curso',
            'description' => 'Tendrá acceso a gestionar las asistencias',
            'description_english' => 'You will have access to manage attendance',
            'app_id' => $app->id
        ]);
        $permissions_registerattendance[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.employeetypes.index'], [
            'name' => 'Tipo de Empleados',
            'description' => 'Tendrá acceso a los Tipos de Empleados',
            'description_english' => 'You will have access to Employee Types',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.contractortypes.index'], [
            'name' => 'Tipo de Contratos',
            'description' => 'Tendrá acceso a los Tipos de Contratos',
            'description_english' => 'You will have access to Contract Types',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.insurerentities.index'], [
            'name' => 'Entidad Aseguradora',
            'description' => 'Tendrá acceso a Entidad Aseguradora',
            'description_english' => 'You will have access to Insurance Entities',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.pensionentities.index'], [
            'name' => 'Entidad de Pensiones',
            'description' => 'Tendrá acceso a Entidad Pension',
            'description_english' => 'You will have access to Pension Entities',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.contractreports.index'], [
            'name' => 'Reporte de Contratos',
            'description' => 'Tendrá acceso a Reporte de Contrato',
            'description_english' => 'You will have access to Contract Reports',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.contractors.index'], [
            'name' => 'Contratos',
            'description' => 'Tendrá acceso a Contratos',
            'description_english' => 'You will have access to Contracts',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.position.index'], [
            'name' => 'Posición',
            'description' => 'Tendrá acceso a Posición',
            'description_english' => 'You will have access to Positions',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.officials.index'], [
            'name' => 'Funcionarios',
            'description' => 'Tendrá acceso a Funcionarios',
            'description_english' => 'You will have access to Officials',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        // ========== CERTIFICACIONES CONTRACTUALES ==========

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.pdf'], [
            'name' => 'Generar PDF Certificaciones',
            'description' => 'Tendrá acceso a generar PDF de certificaciones contractuales',
            'description_english' => 'You will have access to generate PDF certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.index'], [
            'name' => 'Ver Certificaciones Contractuales',
            'description' => 'Tendrá acceso a ver y buscar certificaciones contractuales',
            'description_english' => 'You will have access to view and search contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.create'], [
            'name' => 'Crear Certificaciones',
            'description' => 'Tendrá acceso a crear nuevas certificaciones contractuales',
            'description_english' => 'You will have access to create new contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.edit'], [
            'name' => 'Editar Certificaciones',
            'description' => 'Tendrá acceso a editar certificaciones contractuales',
            'description_english' => 'You will have access to edit contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.show'], [
            'name' => 'Ver Detalle Certificaciones',
            'description' => 'Tendrá acceso a ver el detalle de las certificaciones',
            'description_english' => 'You will have access to view certification details',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.cancel'], [
            'name' => 'Cancelar Certificaciones',
            'description' => 'Tendrá acceso a cancelar certificaciones contractuales',
            'description_english' => 'You will have access to cancel certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.certificates.list'], [
            'name' => 'Listar Certificaciones',
            'description' => 'Tendrá acceso a listar todas las certificaciones',
            'description_english' => 'You will have access to list all certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.certificateconfig.index'], [
            'name' => 'Configurar Certificaciones',
            'description' => 'Tendrá acceso a gestionar las configuraciones de certificaciones',
            'description_english' => 'You will have access to manage certification configurations',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.certificateconfig.update'], [
            'name' => 'Actualizar Configuración de Certificaciones',
            'description' => 'Tendrá acceso a actualizar las configuraciones de certificaciones',
            'description_english' => 'You will have access to update certification configurations',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.generate'], [
            'name' => 'Generar Certificaciones',
            'description' => 'Tendrá acceso a generar certificaciones contractuales',
            'description_english' => 'You will have access to generate contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.store'], [
            'name' => 'Almacenar Certificaciones',
            'description' => 'Tendrá acceso a almacenar nuevas certificaciones contractuales',
            'description_english' => 'You will have access to store new contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        // ========== SOLICITUD DE CERTIFICACIONES ==========

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.myrequests'], [
            'name' => 'Mis Solicitudes de Certificaciones',
            'description' => 'Tendrá acceso a ver sus solicitudes de certificaciones contractuales',
            'description_english' => 'You will have access to view your contractual certification requests',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.approve'], [
            'name' => 'Aprobar Solicitudes de Certificaciones',
            'description' => 'Tendrá acceso a aprobar solicitudes de certificaciones contractuales',
            'description_english' => 'You will have access to approve contractual certification requests',
            'app_id' => $app->id
        ]); 
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.pending'], [
            'name' => 'Ver Solicitudes Pendientes de Certificaciones',
            'description' => 'Tendrá acceso a ver las solicitudes pendientes de certificaciones contractuales',
            'description_english' => 'You will have access to view pending contractual certification requests',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.request'], [
            'name' => 'Solicitar Certificaciones',
            'description' => 'Tendrá acceso a solicitar certificaciones contractuales',
            'description_english' => 'You will have access to request contractual certifications',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.reject'], [
            'name' => 'Rechazar Solicitudes de Certificaciones',
            'description' => 'Tendrá acceso a rechazar solicitudes de certificaciones contractuales',
            'description_english' => 'You will have access to reject contractual certification requests',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.contractualcertificate.destroy'], [
            'name' => 'Eliminar Solicitudes de Certificaciones',
            'description' => 'Tendrá acceso a eliminar solicitudes de certificaciones contractuales',
            'description_english' => 'You will have access to delete contractual certification requests',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        // ========== PASANTES ==========

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.index'], [
            'name' => 'Pasantes',
            'description' => 'Tendrá acceso a Pasantes',
            'description_english' => 'You will have access to Interns',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.assign-warehouse'], [
            'name' => 'Asignar Área Productiva a Pasantes',
            'description' => 'Tendrá acceso a Asignar Área Productiva',
            'description_english' => 'You will have access to Assign Productive Area',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.update'], [
            'name' => 'Editar Pasantes',
            'description' => 'Tendrá acceso a editar la información de los pasantes',
            'description_english' => 'You will have access to edit intern information',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.show'], [
            'name' => 'Ver Pasantes',
            'description' => 'Tendrá acceso a ver la información de los pasantes',
            'description_english' => 'You will have access to view intern information',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.create'], [
            'name' => 'Crear Pasantes',
            'description' => 'Tendrá acceso a crear nuevos pasantes',
            'description_english' => 'You will have access to create new interns',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.store'], [
            'name' => 'Almacenar Pasantes',
            'description' => 'Tendrá acceso a almacenar nuevos pasantes',
            'description_english' => 'You will have access to store new interns',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        // ⭐ CORREGIDO: Cambiar el slug de 'delete' a 'destroy' para que coincida con la ruta
        $permission = Permission::updateOrCreate(['slug' => 'gth.admin.interns.destroy'], [
            'name' => 'Eliminar Pasantes',
            'description' => 'Tendrá acceso a eliminar pasantes',
            'description_english' => 'You will have access to delete interns',
            'app_id' => $app->id
        ]);
        $permissions_admin[] = $permission->id;

        // Asignar permisos a roles
        $rol_brigadista = Role::where('slug', 'gth.brigadista')->first();
        $rol_admin = Role::where('slug', 'gth.admin')->first();
        $rol_registerattendance = Role::where('slug', 'gth.registerattendance')->first();

        if ($rol_brigadista) {
            $rol_brigadista->permissions()->syncWithoutDetaching($permissions_brigadista);
        }

        if ($rol_admin) {
            $rol_admin->permissions()->syncWithoutDetaching($permissions_admin);
        }

        if ($rol_registerattendance) {
            $rol_registerattendance->permissions()->syncWithoutDetaching($permissions_registerattendance);
        }

        $this->command->info('✅ Permisos creados exitosamente.');
    }
}