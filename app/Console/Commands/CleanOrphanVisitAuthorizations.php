<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\SIGAC\Entities\VisitSchedule;

class CleanOrphanVisitAuthorizations extends Command
{
    protected $signature = 'sigac:clean-visit-authorizations';
    protected $description = 'Elimina PDFs de autorizaciones de visita que no estén asociados a ninguna agenda';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dir  = 'sigac/visit_authorizations';

        if (!$disk->exists($dir)) {
            $this->info('No existe el directorio de autorizaciones. Nada que limpiar.');
            return Command::SUCCESS;
        }

        // 1) Rutas usadas en BD (normalizadas)
        $usedPaths = VisitSchedule::query()
            ->whereNotNull('authorization_path')
            ->pluck('authorization_path')
            ->map(function ($path) {
                $path = str_replace('\\', '/', $path);
                // por si guardaste con / al inicio
                return ltrim($path, '/');
            })
            ->toArray();

        $usedSet = array_flip($usedPaths); // para buscar rápido

        // 2) Listar todos los archivos del directorio
        $files = $disk->files($dir);
        $deleted = 0;

        foreach ($files as $file) {
            $normalized = ltrim(str_replace('\\', '/', $file), '/');

            if (!isset($usedSet[$normalized])) {
                // archivo huérfano → eliminar
                $disk->delete($file);
                $deleted++;
                $this->line("Eliminado: {$file}");
            }
        }

        $this->info("Limpieza completada. Archivos eliminados: {$deleted}");

        return Command::SUCCESS;
    }
}
