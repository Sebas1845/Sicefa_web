<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\GDF\Entities\Area;

class AreaController extends Controller
{
    // -------------------------
    // LISTADO DE ÁREAS
    // -------------------------
    public function index()
    {
        $areas = Area::orderBy('name')->get();

        return view('gdf::subdirection.areas.index', compact('areas'));
    }

    // -------------------------
    // FORM CREAR ÁREA
    // -------------------------
    public function create()
    {
        return view('gdf::subdirection.areas.create');
    }

    // -------------------------
    // GUARDAR ÁREA
    // -------------------------
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required','string','max:60','unique:areas,name'],
            'description' => ['nullable','string'],
            'active'      => ['nullable'],
        ]);

        $data['active'] = (bool) $request->input('active', true);

        Area::create($data);

        return redirect()
            ->route('gdf.subdirection.areas.index')
            ->with('success', 'Área creada correctamente.');
    }

    // -------------------------
    // EDITAR ÁREA
    // -------------------------
    public function edit($id)
    {
        $area = Area::findOrFail($id);

        return view('gdf::subdirection.areas.edit', compact('area'));
    }

    // -------------------------
    // ACTUALIZAR ÁREA
    // -------------------------
    public function update(Request $request, $id)
    {
        $area = Area::findOrFail($id);

        $data = $request->validate([
            'name'        => ['required','string','max:60','unique:areas,name,'.$area->id],
            'description' => ['nullable','string'],
            'active'      => ['nullable'],
        ]);

        $data['active'] = (bool) $request->input('active', true);

        $area->update($data);

        return redirect()
            ->route('gdf.subdirection.areas.index')
            ->with('success', 'Área actualizada correctamente.');
    }
}
