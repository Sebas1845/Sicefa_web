@extends('ptventa::layouts.master')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">

                    {{-- ================== FILTROS PRINCIPALES ================== --}}
                    <div class="row mb-3 align-items-end">

                        {{-- Columna izquierda: filtros + botón buscar + dropdown --}}
                        <div class="col-md-9 d-flex align-items-end">

                            {{-- Formulario de filtros (ocupa todo el ancho disponible) --}}
                            <form class="form-inline flex-grow-1"
                                action="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales') }}"
                                method="POST" id="filter-form">
                                @csrf
                                <div class="form-row flex-wrap">

                                    {{-- Fecha inicio --}}
                                    <div class="form-group mr-3 mb-2">
                                        <label class="mr-2">
                                            {{ trans('ptventa::reports.Title_Form_Start_Date') }}
                                        </label>
                                        <input type="date" class="form-control" name="start_date" id="start_date"
                                            value="{{ $start_date ?? now()->format('Y-m-d') }}" required>
                                    </div>

                                    {{-- Fecha fin --}}
                                    <div class="form-group mr-3 mb-2">
                                        <label class="mr-2">
                                            {{ trans('ptventa::reports.Title_Form_End_Date') }}
                                        </label>
                                        <input type="date" class="form-control" name="end_date" id="end_date"
                                            value="{{ $end_date ?? now()->format('Y-m-d') }}" required>
                                    </div>

                                    {{-- Botón Buscar --}}
                                    @if (Auth::user()->havePermission('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales'))
                                        <div class="form-group mb-2">
                                            <button type="submit" class="btn btn-primary">
                                                {{ trans('ptventa::reports.Btn_Search') }}
                                                <i class="fa-solid fa-magnifying-glass"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </form>

                            {{-- Dropdown: Reportes de Ventas (a la derecha de Buscar) --}}
                            {{-- Dropdown: Reportes de Ventas --}}
                            <div class="btn-group mr-2">
                                <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Reportes de Ventas
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">

                                    {{-- Ventas PDF --}}
                                    @if (Auth::user()->havePermission(
                                            'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales.pdf'))
                                        <a class="dropdown-item" href="#"
                                            onclick="submitReportForm('form-sales-pdf'); return false;">
                                            <i class="fa-solid fa-file-pdf mr-1"></i>
                                            PDF de ventas
                                        </a>
                                    @endif

                                    {{-- Ventas Excel --}}
                                    @if (Auth::user()->havePermission(
                                            'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales.excel'))
                                        <a class="dropdown-item" href="#"
                                            onclick="submitReportForm('form-sales-excel'); return false;">
                                            <i class="fa-solid fa-file-excel mr-1"></i>
                                            Excel de ventas
                                        </a>
                                    @endif
                                </div>
                            </div>

                            {{-- Dropdown: Reportes de Productos --}}
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                    data-bs-toggle="dropdown" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    Reportes de Productos
                                </button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    {{-- Productos PDF --}}
                                    @if (Auth::user()->havePermission(
                                            'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.products.pdf'))
                                        <a class="dropdown-item" href="#"
                                            onclick="submitReportForm('form-products-pdf'); return false;">
                                            <i class="fa-solid fa-box-open mr-1"></i>
                                            PDF productos vendidos
                                        </a>
                                    @endif
                                    {{-- Aquí luego puedes agregar Excel de productos --}}
                                </div>
                            </div>

                        </div>

                        {{-- Columna derecha: solo formularios ocultos (no afecta el diseño) --}}
                        <div class="col-md-3 d-flex justify-content-md-end justify-content-start mt-3 mt-md-0">

                            {{-- ==== Formularios ocultos que realmente envían los reportes ==== --}}
                            @if (Auth::user()->havePermission(
                                    'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales.pdf'))
                                <form id="form-sales-pdf"
                                    action="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales.pdf') }}"
                                    method="post" target="_blank" class="d-none report-form">
                                    @csrf
                                    <input type="hidden" name="start_date">
                                    <input type="hidden" name="end_date">
                                    <input type="hidden" name="period_type">
                                    <input type="hidden" name="year_base">
                                    <input type="hidden" name="year_compare">
                                </form>
                            @endif

                            @if (Auth::user()->havePermission(
                                    'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.sales.excel'))
                                <form id="form-sales-excel" action="{{ route('reports.generate.sales.excel') }}"
                                    method="post" class="d-none report-form">
                                    @csrf
                                    <input type="hidden" name="start_date">
                                    <input type="hidden" name="end_date">
                                    <input type="hidden" name="period_type">
                                    <input type="hidden" name="year_base">
                                    <input type="hidden" name="year_compare">
                                </form>
                            @endif

                            @if (Auth::user()->havePermission(
                                    'ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.products.pdf'))
                                <form id="form-products-pdf"
                                    action="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.reports.generate.products.pdf') }}"
                                    method="post" target="_blank" class="d-none report-form">
                                    @csrf
                                    <input type="hidden" name="start_date">
                                    <input type="hidden" name="end_date">
                                    <input type="hidden" name="period_type">
                                    <input type="hidden" name="year_base">
                                    <input type="hidden" name="year_compare">
                                </form>
                            @endif

                        </div>
                    </div>

                    <hr>

                    {{-- ================== TABLA DE VENTAS DETALLADAS ================== --}}
                    @if (isset($movements) && $movements->count() > 0)
                        @include('ptventa::reports.sales-tables')
                    @else
                        <p>{{ trans('ptventa::reports.2T_Text_Optional') }}</p>
                    @endif

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function updateDateAttributes() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            if (startDateInput && endDateInput) {
                endDateInput.min = startDateInput.value;
                startDateInput.max = endDateInput.value;
            }
        }
        document.getElementById('start_date').addEventListener('change', updateDateAttributes);
        document.getElementById('end_date').addEventListener('change', updateDateAttributes);

        // Copiar filtros a los formularios ocultos y enviarlos
        function submitReportForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return;

            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;
            const periodType = document.getElementById('period_type') ? document.getElementById('period_type').value :
                'range';
            const yearBase = document.getElementById('year_base') ? document.getElementById('year_base').value : '';
            const yearCompare = document.getElementById('year_compare') ? document.getElementById('year_compare').value :
            '';

            form.querySelector('input[name="start_date"]').value = start;
            form.querySelector('input[name="end_date"]').value = end;
            form.querySelector('input[name="period_type"]').value = periodType;
            form.querySelector('input[name="year_base"]').value = yearBase;
            form.querySelector('input[name="year_compare"]').value = yearCompare;

            form.submit();
        }

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (t) => {
                t.addEventListener('mouseenter', Swal.stopTimer);
                t.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        Toast.fire({
            icon: 'info',
            title: '{{ trans('ptventa::reports.Title') }}'
        });
    </script>
@endpush
