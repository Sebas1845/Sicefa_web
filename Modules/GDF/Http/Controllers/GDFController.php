<?php

namespace Modules\GDF\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GDF\Entities\Activitie;
use Modules\GDF\Entities\Certificate;
use Modules\GDF\Entities\Activities;

class GDFController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('gdf::index');
    }

    public function official()
    {
        return view('gdf::welcome');
    }

    public function admin()
    {
        return view('gdf::admin.index');
    }

    public function academic_coordination()
    {
        return view('gdf::admin.admin');
    }

    public function support()
    {
        return view('gdf::welcome');
    }

    public function treasury()
    {
        return view('gdf::welcome');
    }

    public function developers()
    {
        return view('gdf::developers');
    }

    public function tecnologias()
    {
        return view('gdf::tecnologias');
    }

    public function gateway()
    {
        // Vista que contiene select de área/rol
        return view('gdf::gateway.index');
    }
    public function campesena()
    {
        return view('gdf::campesena.dashboard');
    }
    public function subdireccion()
    {
        return view('gdf::subdireccion.dashboard');
    }


    public function select(Request $request)
    {
        $data = $request->validate([
            'area' => 'required|in:academic,campesena',
            'role' => 'required|in:coord,support',
        ]);

        // Mapeo de roles (ajústalo a tus slugs reales)
        $roles = [
            'academic' => [
                'coord'   => 'gdf.academic_coordination',
                'support' => 'gdf.support_academic',
            ],
            'campesena' => [
                'coord'   => 'gdf.campesena_coordination',
                'support' => 'gdf.support_campesena',
            ],
        ];

        $neededRole = $roles[$data['area']][$data['role']] ?? null;

        if (!$neededRole) {
            return back()->with('error', 'Selección inválida.');
        }

        if (!function_exists('checkRol') || !checkRol($neededRole)) {
            return back()->with('error', 'No tienes permisos para acceder con esa selección.');
        }

        session([
            'gdf.area' => $data['area'],
            'gdf.mode' => $data['role'],
        ]);

        return $data['area'] === 'academic'
            ? redirect()->route('gdf.view.coordination')
            : redirect()->route('gdf.view.campesena');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('gdf::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('gdf::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('gdf::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
