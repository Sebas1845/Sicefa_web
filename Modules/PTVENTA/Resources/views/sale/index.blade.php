@extends('ptventa::layouts.master')

@push('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.sale.index') }}" class="text-decoration-none">
            {{ trans('ptventa::sales.Breadcrumb_Sales_1') }}
        </a>
    </li>
    <li class="breadcrumb-item active">{{ trans('ptventa::sales.Breadcrumb_Active_Sales_1') }}</li>
@endpush

@section('content')
    <div class="card card-success card-outline shadow-sm" data-aos="zoom-in">
        <div class="card-body pt-0">
            <div class="row mb-3">
                <div class="col-md-12 text-end my-2">
                    @if (Auth::user()->havePermission('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.sale.register'))
                        <a href="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.sale.register') }}"
                            class="btn btn-sm btn-success">
                            <i class="fa-solid fa-plus fa-fade"></i>
                            {{ trans('ptventa::sales.Btn_Register_Sale') }}
                        </a>
                    @endif
                </div>
            </div>

            @if ($cashCount)
                <div class="table-responsive" @if (empty($groupedProducts)) hidden @endif>
                    <table class="table table-hover" id="products-table">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">{{ trans('ptventa::sales.3T_Number') }}</th>
                                <th>{{ trans('ptventa::sales.3T_Product') }}</th>
                                <th class="text-center">{{ trans('ptventa::sales.3T_Amount') }}</th>
                                <th class="text-center">{{ trans('ptventa::sales.3T_Subtotal') }}</th>
                                <th class="text-center">{{ trans('ptventa::sales.3T_Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            @foreach ($groupedProducts as $name => $item)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td>{{ $name }}</td>
                                    <td class="text-center">{{ $item['cantidad'] }}</td>
                                    <td class="text-center">
                                        {{ priceFormat($item['min_price']) }}
                                        @if ($item['min_price'] != $item['max_price'])
                                            - {{ priceFormat($item['max_price']) }}
                                        @endif
                                    </td>
                                    <td class="text-center"><strong>{{ priceFormat($item['subtotal']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-right" colspan="4">
                                    <h3><strong>{{ trans('ptventa::sales.1T_Total') }}</strong></h3>
                                </td>
                                <td class="text-center text-success">
                                    <h3><strong>{{ priceFormat(array_sum(array_column($groupedProducts, 'subtotal'))) }}</strong></h3>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="text-center text-danger" @if (!empty($groupedProducts)) hidden @endif>
                    <strong>{{ trans('ptventa::sales.Text_Optional_1') }}</strong>
                </div>
            @else
                <div class="text-center text-danger">
                    <strong>{{ trans('ptventa::sales.Text_Optional_2') }}</strong>
                </div>
            @endif
        </div>
    </div>
@endsection

@include('ptventa::layouts.partials.plugins.datatables')
@include('ptventa::layouts.partials.plugins.sweetalert2')

@push('scripts')
    <script>
        $(document).ready(function() {
            // Opciones comunes para todas las tablas DataTable
            var dataTableOptions = {
                "order": [],
                "paging": false,
                "columnDefs": [{
                    "targets": [0],
                    "orderable": false
                }],
                drawCallback: function(settings) {
                    var api = this.api();
                    // Recalcula los números iterados en la primera columna después de cada redibujado
                    api.column(0, {
                        search: 'applied',
                        order: 'applied'
                    }).nodes().each(function(cell, i) {
                        cell.innerHTML = i + 1;
                    });
                }
            };
            // Verificar el idioma actual y decidir si agregar la opción de idioma
            if ('{{ session('lang') }}' === 'es') {
                dataTableOptions.language = language_datatables;
            }
            // Inicializar DataTables con las opciones configuradas
            $('#products-table').DataTable(dataTableOptions);
        });
    </script>
    @if (session('error'))
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
            });
        </script>
    @endif
@endpush