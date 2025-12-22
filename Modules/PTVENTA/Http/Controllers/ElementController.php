<?php

namespace Modules\PTVENTA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Modules\SICA\Entities\Element;
use Modules\SICA\Entities\Category;
use Modules\SICA\Entities\MeasurementUnit;
use Modules\SICA\Entities\KindOfPurchase;
use Illuminate\Support\Facades\Validator;

class ElementController extends Controller
{
    public function index(){ 
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_element_index_title_page'), 
            'titleView' => trans('ptventa::controllers.PTVENTA_element_index_title_view')
        ];
        return view('ptventa::element.index', compact('view'));
    }

    public function create()
    {
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_element_create_title_page'), 
            'titleView' => trans('ptventa::controllers.PTVENTA_element_create_title_view')
        ];
        $measurement_units = MeasurementUnit::orderBy('name', 'ASC')->get();
        $categories = Category::orderBy('name', 'ASC')->get();
        $kind_of_purchases = KindOfPurchase::orderBy('name', 'ASC')->get();
        return view('ptventa::element.create', compact('view', 'measurement_units', 'categories', 'kind_of_purchases'));
    }

    public function store(Request $request)
    {
        $request->merge(['price' => revertPriceFormat(e($request->input('price')))]);
        $rules = [
            'name' => 'required|unique:elements',
            'measurement_unit_id' => 'required',
            'kind_of_purchase_id' => 'required',
            'category_id' => 'required',
            'price' => 'required',
            'UNSPSC_code' => 'nullable|integer|unique:elements',
            'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:2048'
        ];
        $messages = [
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'Solo se permiten imágenes en formato JPEG, PNG, GIF o WEBP.',
            'image.max' => 'La imagen no debe superar los 2MB.'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $element = new Element();
        $element->name = e($request->input('name'));
        if ($request->hasFile('image')) { 
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $image_name = time() . '_' . uniqid() . '.' . $extension; 
            $image->move(public_path('modules/sica/images/elements/'), $image_name);
            $element->image = 'modules/sica/images/elements/' . $image_name;
        }
        $element->measurement_unit_id = e($request->input('measurement_unit_id'));
        $element->description = e($request->input('description'));
        $element->kind_of_purchase_id = e($request->input('kind_of_purchase_id'));
        $element->category_id = e($request->input('category_id'));
        $element->price = e($request->input('price'));
        $UNSPSC_code = e($request->input('UNSPSC_code'));
        $element->UNSPSC_code = !empty($UNSPSC_code) ? $UNSPSC_code : null;

        if ($element->save()) {
            $message_ptventa = "Elemento agregado exitosamente";
            $message_ptventa_type = 'success';
        } else {
            $message_ptventa = "Se ha producido un error en el momento de agregar el elemento";
            $message_ptventa_type = 'error';
        }
        return redirect(route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.element.index'))
            ->with('message_ptventa', $message_ptventa)
            ->with('message_ptventa_type', $message_ptventa_type);
    }

    public function edit(Element $element)
    { 
        $measurement_units = MeasurementUnit::orderBy('name', 'ASC')->get();
        $categories = Category::orderBy('name', 'ASC')->get();
        $kind_of_purchases = KindOfPurchase::orderBy('name', 'ASC')->get();
        $view = [
            'titlePage' => trans('ptventa::controllers.PTVENTA_element_edit_title_page'), 
            'titleView' => trans('ptventa::controllers.PTVENTA_element_edit_title_view')
        ];
        return view('ptventa::element.edit', compact('element', 'view', 'measurement_units', 'categories', 'kind_of_purchases'));
    }

    public function update(Request $request, Element $element)
    { 
        $request->merge(['price' => revertPriceFormat(e($request->input('price')))]);
        $rules = [
            'name' => 'required|unique:elements,name,' . $element->id,
            'measurement_unit_id' => 'required',
            'kind_of_purchase_id' => 'required',
            'category_id' => 'required',
            'price' => 'required',
            'UNSPSC_code' => 'nullable|integer|unique:elements,UNSPSC_code,' . $element->id,
            'image' => 'nullable|image|mimes:jpeg,png,gif,webp|max:2048'
        ];
        $messages = [
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'Solo se permiten imágenes en formato JPEG, PNG, GIF o WEBP.',
            'image.max' => 'La imagen no debe superar los 2MB.'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $element->name = e($request->input('name'));
        if ($request->hasFile('image')) { 
            $image = $request->file('image');
            if (!empty($element->image) && file_exists(public_path($element->image))) {
                @unlink(public_path($element->image));
            }
            $extension = $image->getClientOriginalExtension();
            $image_name = $element->id . '_' . time() . '.' . $extension; 
            $image->move(public_path('modules/sica/images/elements/'), $image_name);
            $element->image = 'modules/sica/images/elements/' . $image_name;
        }
        $element->measurement_unit_id = e($request->input('measurement_unit_id'));
        $element->description = e($request->input('description'));
        $element->kind_of_purchase_id = e($request->input('kind_of_purchase_id'));
        $element->category_id = e($request->input('category_id'));
        $element->price = e($request->input('price'));
        $UNSPSC_code = e($request->input('UNSPSC_code'));
        $element->UNSPSC_code = !empty($UNSPSC_code) ? $UNSPSC_code : null;

        if ($element->save()) {
            $message_ptventa = "Elemento actualizado exitosamente";
            $message_ptventa_type = 'success';
        } else {
            $message_ptventa = "Se ha producido un error en el momento de actualizar el elemento";
            $message_ptventa_type = 'error';
        }
        return redirect(route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.element.index'))
            ->with('message_ptventa', $message_ptventa)
            ->with('message_ptventa_type', $message_ptventa_type);
    }
}