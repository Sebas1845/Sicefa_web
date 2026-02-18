<?php

namespace Modules\CAFETO\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CAFETO\Models\Movement;
use Barryvdh\DomPDF\Facade as PDF;

class ReportController extends Controller
{
    /**
     * Display the main reports dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('cafeto::reports.index', [
            'view' => ['titlePage' => trans('cafeto::reports.Title_Select_Report')],
        ]);
    }

    /**
     * Display the inventory entries report form.
     *
     * @return \Illuminate\View\View
     */
    public function inventoryEntries()
    {
        $today = now()->format('Y-m-d');
        return view('cafeto::reports.inventory-entries', [
            'view' => ['titlePage' => trans('cafeto::reports.Title_Card_Inventory_Entries')],
            'start_date' => $today,
            'end_date' => $today,
        ]);
    }

    /**
     * Generate inventory entries report based on date range.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function generateInventoryEntries(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $movements = Movement::with(['movement_details.inventory.element', 'movement_responsibilities.person'])
            ->where('movement_type', 'ENTRADA')
            ->whereBetween('registration_date', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        return view('cafeto::reports.inventory-entries', [
            'view' => ['titlePage' => trans('cafeto::reports.Title_Card_Inventory_Entries')],
            'movements' => $movements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Generate PDF of current inventory.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateInventoryPDF(Request $request)
    {
        $inventory = Movement::with(['movement_details.inventory.element'])
            ->where('movement_type', 'ENTRADA')
            ->get();

        $pdf = PDF::loadView('cafeto::reports.inventory-pdf', ['inventory' => $inventory]);

        return $pdf->download('inventory.pdf');
    }

    /**
     * Generate PDF of inventory entries.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateEntriesPDF(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $movements = Movement::with(['movement_details.inventory.element', 'movement_responsibilities.person'])
            ->where('movement_type', 'ENTRADA')
            ->whereBetween('registration_date', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        $pdf = PDF::loadView('cafeto::reports.inventory-entries-pdf', [
            'movements' => $movements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $pdf->download('inventory_entries.pdf');
    }

    /**
     * Display the sales report form.
     *
     * @return \Illuminate\View\View
     */
    public function sales()
    {
        $today = now()->format('Y-m-d');
        return view('cafeto::reports.sales-form', [
            'view' => ['titlePage' => trans('cafeto::reports.Title_Card_Inventory_Sales')],
            'start_date' => $today,
            'end_date' => $today,
        ]);
    }

    /**
     * Generate sales report based on date range.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function generateSales(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $movements = Movement::with(['movement_details.inventory.element', 'movement_responsibilities.person'])
            ->where('movement_type', 'VENTA')
            ->whereBetween('registration_date', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        return view('cafeto::reports.sales-form', [
            'view' => ['titlePage' => trans('cafeto::reports.Title_Card_Inventory_Sales')],
            'movements' => $movements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Generate PDF of sales.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generateSalesPDF(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $movements = Movement::with(['movement_details.inventory.element', 'movement_responsibilities.person'])
            ->where('movement_type', 'VENTA')
            ->whereBetween('registration_date', [$startDate, $endDate . ' 23:59:59'])
            ->get();

        $pdf = PDF::loadView('cafeto::reports.sales-pdf', [
            'movements' => $movements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $pdf->download('sales.pdf');
    }
}
?>