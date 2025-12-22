<?php

namespace Modules\SIGAC\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SIGAC\Entities\EnvironmentRoundEntry;

class EnvironmentRoundEntryController extends Controller
{
    /**
     * Actualizar una entrada de la ronda (desde el formulario por ambiente).
     */
    public function update(Request $request, $id)
    {
        $entry = EnvironmentRoundEntry::with('round')->findOrFail($id);

        if ($entry->round->is_locked) {
            return back()->with('error', 'La ronda está cerrada, no se puede editar.');
        }

        $data = $request->validate([
            'present_in_environment' => ['nullable', 'in:SI,NO'],
            'attendance_status'      => ['nullable', 'in:OK,SIN_INSTRUCTOR,SOLO_APRENDICES,VACIO'],
            'observations'           => ['nullable', 'string'],
            'is_dirty'               => ['nullable', 'boolean'],
            'ac_status'              => ['nullable', 'in:OK,DANADO,NO_APLICA'],
            'other_issues'           => ['nullable', 'string'],
            'marked_for_relocation'  => ['nullable', 'boolean'],
            'suggested_environment_id' => ['nullable', 'integer'],
        ]);

        // Normalizar checkboxes
        $data['is_dirty']              = $request->boolean('is_dirty');
        $data['marked_for_relocation'] = $request->boolean('marked_for_relocation');
        $data['updated_by']            = auth()->id();

        $entry->update($data);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'entry' => $entry->fresh()]);
        }

        return back()->with('success', 'Entrada de ronda actualizada.');
    }
}
