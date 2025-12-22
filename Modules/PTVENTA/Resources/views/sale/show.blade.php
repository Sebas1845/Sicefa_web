@extends('ptventa::layouts.master')

@push('breadcrumbs')
    <li class="breadcrumb-item active">
        <a href="{{ route('ptventa.' . getRoleRouteName(Route::currentRouteName()) . '.sale.index') }}"
           class="text-decoration-none">{{ trans('ptventa::sales.Breadcrumb_Show_1') }}</a>
    </li>
    <li class="breadcrumb-item active">{{ trans('ptventa::sales.Breadcrumb_Active_Show_1') }}</li>
@endpush

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-body">
                <div class="ribbon-wrapper ribbon-xl">
                    <div class="ribbon bg-olive">
                        {{ trans('ptventa::sales.Form_Title_Voucher') }} {{ $movement->voucher_number }}
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-md-2">
                        <label class="form-label">{{ trans('ptventa::sales.Form_Title_Date') }}</label>
                        <input type="text" class="form-control" value="{{ $movement->registration_date }}" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ trans('ptventa::sales.Form_Title_Customer') }}</label>
                        <input type="text" class="form-control"
                            value="{{ optional($movement->movement_responsibilities->where('role', 'CLIENTE')->first())->person->full_name }}"
                            readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('ptventa::sales.Form_Title_Seller') }}</label>
                        <input type="text" class="form-control"
                            value="{{ optional($movement->movement_responsibilities->where('role', 'VENDEDOR')->first())->person->full_name }}"
                            readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ trans('ptventa::sales.Form_Title_Movement_Type') }}</label>
                        <input type="text" class="form-control" value="{{ $movement->movement_type->name }}" readonly>
                    </div>
                </div>

                <hr>

                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col" class="text-center">{{ trans('ptventa::sales.3T_Number') }}</th>
                                <th scope="col">{{ trans('ptventa::sales.3T_Product') }}</th>
                                <th scope="col" class="text-center">{{ trans('ptventa::sales.3T_Amount') }}</th>
                                <th scope="col" class="text-center">{{ trans('ptventa::sales.3T_Subtotal') }}</th>
                                <th scope="col" class="text-center">{{ trans('ptventa::sales.3T_Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $processed_elements = [];
                                $iteration_group = 0;
                            @endphp
                            @foreach ($movement->movement_details as $md)
                                @php $element_id = $md->inventory->element_id; @endphp
                                @if (!in_array($element_id, $processed_elements))
                                    @php
                                        $processed_elements[] = $element_id;
                                        $iteration_group++;
                                        $total_amount = $movement->movement_details
                                            ->where('inventory.element_id', $element_id)
                                            ->sum('amount');
                                    @endphp
                                    <tr>
                                        <th scope="row" class="text-center">{{ $iteration_group }}</th>
                                        <td>{{ $md->inventory->element->product_name }}</td>
                                        <td class="text-center">{{ $total_amount }}</td>
                                        <td class="text-center">{{ priceFormat($md->price) }}</td>
                                        <td class="text-center fw-bold">{{ priceFormat($md->price * $total_amount) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"></td>
                                <td class="text-center fw-bold">Total:</td>
                                <td class="text-center fw-bold">{{ priceFormat($movement->price) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="text-center mt-4">
                    <button class="btn btn-success" onclick="printTicket()" id="printButton">
                        {{ trans('ptventa::sales.Btn_Generate_Ticket') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@include('ptventa::layouts.partials.plugins.sweetalert2')
@include('ptventa::layouts.partials.plugins.toastr')

@push('scripts')
    <script src="{{ asset('modules/ptventa/js/sale/conector_javascript_POS80C.js') }}"></script>
    <script src="{{ asset('libs/cleave.js-1.6.0/dist/cleave.js') }}"></script>
    <script src="{{ asset('modules/ptventa/js/data-formats.js') }}"></script>
    <script src="{{ asset('modules/ptventa/js/pos_print/prints.js') }}"></script>

    <script>
        async function printTicket() {
            const printButton = document.getElementById("printButton");
            try {
                printButton.disabled = true;
                var movement = @json($movement);
                let respuesta = await print_sale(movement);
                if (respuesta) {
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: 'Factura generada correctamente.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            } catch (error) {
                toastr.options.timeOut = 0;
                toastr.options.closeButton = true;
                toastr.error(
                    'Es posible que no esté en ejecución el plugin_impresora_termica en el equipo.',
                    'Error de impresión'
                );
            } finally {
                printButton.disabled = false;
            }
        }
    </script>
@endpush
