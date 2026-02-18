<?php

namespace Modules\GDF\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageAdminController extends Controller
{
    
    private string $disk = 'public';

   
    private array $blockedPrefixes = [
    ];

    public function index(Request $request)
    {
        $path = $this->normalizePath((string) $request->get('path', ''));

        if (!$this->isSafePath($path)) {
            abort(403, 'Ruta inválida.');
        }
        if ($this->isBlocked($path)) {
            abort(403, 'Carpeta bloqueada.');
        }

        $disk = Storage::disk($this->disk);

        $directories = $disk->directories($path);
        $files       = $disk->files($path);

        $dirStats = [];
        foreach ($directories as $dir) {
            $dirStats[$dir] = $this->folderStats($dir);
        }

        $fileRows = [];
        foreach ($files as $f) {
            $size = $this->safeSize($f);
            $fileRows[] = [
                'path'   => $f,
                'name'   => basename($f),
                'size'   => $size,
                'size_h' => $this->humanBytes($size),
                'last'   => $this->safeLastModified($f),
                'ext'    => pathinfo($f, PATHINFO_EXTENSION),
            ];
        }

        // Breadcrumb
        $crumbs = [];
        if ($path !== '') {
            $parts = preg_split('~[\/\\\\]+~', $path);
            $acc = '';
            foreach ($parts as $p) {
                if ($p === '') continue;
                $acc = $acc === '' ? $p : ($acc . '/' . $p);
                $crumbs[] = ['label' => $p, 'path' => $acc];
            }
        }

        return view('gdf::admin.storage.index', [
            'path'        => $path,
            'crumbs'      => $crumbs,
            'directories' => $directories,
            'files'       => $fileRows,
            'dirStats'    => $dirStats,
            'diskName'    => $this->disk,
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'type' => ['required', 'in:file,dir'],
            'path' => ['required', 'string'],
        ]);

        $type = (string) $request->input('type');
        $path = $this->normalizePath((string) $request->input('path'));

        if (!$this->isSafePath($path) || $this->isBlocked($path)) {
            return $this->respondError($request, 'Ruta inválida o bloqueada.', 403, [
                'type' => $type, 'path' => $path
            ]);
        }

        $disk = Storage::disk($this->disk);

        if ($type === 'file') {
            $existsBefore = $disk->exists($path);

            if (!$existsBefore) {
                return $this->respondError($request, 'El archivo no existe.', 404, [
                    'type' => $type, 'path' => $path
                ]);
            }

            $size = $this->safeSize($path);

            $ok = false;
            try {
                $ok = (bool) $disk->delete($path);
            } catch (\Throwable $e) {
                logger()->error('storage_delete_file_exception', [
                    'disk' => $this->disk,
                    'path' => $path,
                    'err'  => $e->getMessage(),
                ]);
            }

            $existsAfter = $disk->exists($path);

            logger()->info('storage_delete_file', [
                'disk'         => $this->disk,
                'path'         => $path,
                'ok'           => $ok,
                'exists_before'=> $existsBefore,
                'exists_after' => $existsAfter,
                'size'         => $size,
            ]);

            // Si no se borró, NO digas success
            if (!$ok || $existsAfter) {
                // típico en Windows: archivo en uso (PDF abierto) o permisos
                return $this->respondError(
                    $request,
                    'No se pudo eliminar el archivo. (Puede estar en uso o sin permisos).',
                    422,
                    ['type' => $type, 'path' => $path]
                );
            }

            return $this->respondOk(
                $request,
                'Archivo eliminado (' . $this->humanBytes($size) . ').',
                ['type' => $type, 'path' => $path]
            );
        }

        // === directorio ===
        $existsBefore = $disk->exists($path); // ojo: para directorio puede variar según driver

        $stats = $this->folderStats($path);

        $ok = false;
        try {
            $ok = (bool) $disk->deleteDirectory($path);
        } catch (\Throwable $e) {
            logger()->error('storage_delete_dir_exception', [
                'disk' => $this->disk,
                'path' => $path,
                'err'  => $e->getMessage(),
            ]);
        }

        // Revalida: si aún tiene archivos o sigue existiendo, lo consideramos fallo
        $existsAfter = $disk->exists($path);
        $statsAfter  = $this->folderStats($path);

        logger()->info('storage_delete_dir', [
            'disk'          => $this->disk,
            'path'          => $path,
            'ok'            => $ok,
            'exists_before' => $existsBefore,
            'exists_after'  => $existsAfter,
            'stats_before'  => $stats,
            'stats_after'   => $statsAfter,
        ]);

        // Si después del deleteDirectory aún hay archivos -> no se borró completo
        if ((!$ok && $existsAfter) || (($statsAfter['files'] ?? 0) > 0)) {
            return $this->respondError(
                $request,
                'No se pudo eliminar la carpeta completamente. (Archivos en uso/permisos).',
                422,
                ['type' => $type, 'path' => $path, 'stats_after' => $statsAfter]
            );
        }

        return $this->respondOk(
            $request,
            'Carpeta eliminada (' . (int)($stats['files'] ?? 0) . ' archivos, ' . ($stats['size_h'] ?? '0 B') . ').',
            ['type' => $type, 'path' => $path, 'stats' => $stats]
        );
    }

    public function deleteBulk(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:file,dir'],
            'items.*.path' => ['required', 'string'],
        ]);

        $disk = Storage::disk($this->disk);

        $deletedFiles = 0;
        $deletedDirs  = 0;
        $freedBytes   = 0;

        $failed = [];

        foreach ($request->input('items') as $item) {
            $type = (string)($item['type'] ?? '');
            $path = $this->normalizePath((string)($item['path'] ?? ''));

            if (!$this->isSafePath($path) || $this->isBlocked($path)) {
                $failed[] = ['type' => $type, 'path' => $path, 'reason' => 'invalid_or_blocked'];
                continue;
            }

            if ($type === 'file') {
                $existsBefore = $disk->exists($path);
                if (!$existsBefore) {
                    $failed[] = ['type' => $type, 'path' => $path, 'reason' => 'not_found'];
                    continue;
                }

                $size = $this->safeSize($path);
                $ok = false;

                try {
                    $ok = (bool) $disk->delete($path);
                } catch (\Throwable $e) {
                    logger()->error('storage_delete_bulk_file_exception', [
                        'disk' => $this->disk,
                        'path' => $path,
                        'err'  => $e->getMessage(),
                    ]);
                }

                $existsAfter = $disk->exists($path);

                logger()->info('storage_delete_bulk_file', [
                    'disk'          => $this->disk,
                    'path'          => $path,
                    'ok'            => $ok,
                    'exists_before' => $existsBefore,
                    'exists_after'  => $existsAfter,
                    'size'          => $size,
                ]);

                if (!$ok || $existsAfter) {
                    $failed[] = ['type' => $type, 'path' => $path, 'reason' => 'delete_failed_or_in_use'];
                    continue;
                }

                $freedBytes += $size;
                $deletedFiles++;
                continue;
            }

            // dir
            $stats = $this->folderStats($path);
            $ok = false;

            try {
                $ok = (bool) $disk->deleteDirectory($path);
            } catch (\Throwable $e) {
                logger()->error('storage_delete_bulk_dir_exception', [
                    'disk' => $this->disk,
                    'path' => $path,
                    'err'  => $e->getMessage(),
                ]);
            }

            $statsAfter = $this->folderStats($path);

            logger()->info('storage_delete_bulk_dir', [
                'disk'        => $this->disk,
                'path'        => $path,
                'ok'          => $ok,
                'stats_before'=> $stats,
                'stats_after' => $statsAfter,
            ]);

            if ((!$ok) && (($statsAfter['files'] ?? 0) > 0)) {
                $failed[] = ['type' => $type, 'path' => $path, 'reason' => 'dir_delete_failed_or_in_use'];
                continue;
            }

            $freedBytes += (int)($stats['size'] ?? 0);
            $deletedDirs++;
        }

        $msg = "Eliminado: {$deletedFiles} archivos, {$deletedDirs} carpetas. Liberado: ".$this->humanBytes($freedBytes).".";

        if (!empty($failed)) {
            // No lo hago “error” completo porque puede borrar algunos y fallar otros
            $msg .= " Fallidos: ".count($failed).".";
            logger()->warning('storage_delete_bulk_failed', [
                'disk' => $this->disk,
                'failed' => $failed,
            ]);
        }

        return $this->respondOk($request, $msg, [
            'deleted_files' => $deletedFiles,
            'deleted_dirs'  => $deletedDirs,
            'freed_bytes'   => $freedBytes,
            'failed'        => $failed,
        ]);
    }

    // =========================
    // Helpers
    // =========================

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, "/");
        // colapsa ////
        $path = preg_replace('~/{2,}~', '/', $path) ?: '';
        return $path;
    }

    private function isSafePath(string $path): bool
    {
        if ($path === '') return true;

        // bloquea traversal y null bytes
        if (Str::contains($path, ['..', "\0"])) return false;

        // no permitir rutas absolutas
        if (Str::startsWith($path, ['/'])) return false;

        // Windows drive letter (C:)
        if (preg_match('~^[a-zA-Z]:~', $path)) return false;

        return true;
    }

    private function isBlocked(string $path): bool
    {
        $p = trim(str_replace('\\', '/', $path), '/');

        foreach ($this->blockedPrefixes as $bp) {
            $bp = trim(str_replace('\\', '/', $bp), '/');
            if ($bp !== '' && ($p === $bp || Str::startsWith($p, $bp . '/'))) {
                return true;
            }
        }
        return false;
    }

    private function safeSize(string $path): int
    {
        try {
            return (int) Storage::disk($this->disk)->size($path);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function safeLastModified(string $path): ?string
    {
        try {
            $ts = Storage::disk($this->disk)->lastModified($path);
            return $ts ? date('Y-m-d H:i:s', $ts) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function folderStats(string $dir): array
    {
        $disk = Storage::disk($this->disk);

        $total = 0;
        $count = 0;

        if ($dir === '') {
            // si alguien intenta stats de raíz, igual lo soportamos
            $dir = '';
        }

        $stack = [$dir];
        while (!empty($stack)) {
            $current = array_pop($stack);

            try {
                foreach ($disk->files($current) as $f) {
                    $count++;
                    $total += $this->safeSize($f);
                }
                foreach ($disk->directories($current) as $d) {
                    $stack[] = $d;
                }
            } catch (\Throwable $e) {
                // si falla por permisos, no tumbar la vista
                logger()->warning('storage_folder_stats_exception', [
                    'disk' => $this->disk,
                    'dir'  => $current,
                    'err'  => $e->getMessage(),
                ]);
            }
        }

        return [
            'files'  => $count,
            'size'   => $total,
            'size_h' => $this->humanBytes($total),
        ];
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B','KB','MB','GB','TB'];
        $b = max(0, (float)$bytes);
        $i = 0;

        while ($b >= 1024 && $i < count($units)-1) {
            $b /= 1024;
            $i++;
        }

        return ($i === 0)
            ? (int)$b.' '.$units[$i]
            : number_format($b, 2).' '.$units[$i];
    }

    // =========================
    // Respuestas (web / ajax)
    // =========================

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    private function respondOk(Request $request, string $message, array $payload = [], int $status = 200)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => $payload,
            ], $status);
        }

        return back()->with('success', $message);
    }

    private function respondError(Request $request, string $message, int $status = 422, array $payload = [])
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'data' => $payload,
            ], $status);
        }

        return back()->with('error', $message);
    }
}
