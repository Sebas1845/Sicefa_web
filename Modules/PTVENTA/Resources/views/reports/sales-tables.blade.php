

{{-- ================= TABLA DE VENTAS DETALLADAS ================= --}}
<h4 class="mb-3">{{ trans('ptventa::reports.Title_Sales_Detailed') }}</h4>

<div class="table-responsive">
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th class="text-center">{{ trans('ptventa::reports.2T_Number') }}</th>
                <th class="text-center">{{ trans('ptventa::reports.2T_Voucher') }}</th>
                <th>{{ trans('ptventa::reports.2T_Responsible_Delivery') }}</th>
                <th>{{ trans('ptventa::reports.2T_Registration_Date') }}</th>
                <th>{{ trans('ptventa::reports.2T_Product') }}</th>
                <th class="text-center">{{ trans('ptventa::reports.2T_Amount') }}</th>
                <th class="text-center">{{ trans('ptventa::reports.2T_Price') }}</th>
                <th class="text-center">{{ trans('ptventa::reports.2T_Subtotal') }}</th>
                <th class="text-center">{{ trans('ptventa::reports.2T_Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $key => $mov)
                @foreach ($mov->movement_details as $index => $movement_detail)
                    <tr>
                        @if ($index === 0)
                            <td class="text-center"
                                rowspan="{{ $mov->movement_details->count() }}"
                                style="vertical-align: middle;">
                                {{ $key + 1 }}
                            </td>
                            <td class="text-center"
                                rowspan="{{ $mov->movement_details->count() }}"
                                style="vertical-align: middle;">
                                {{ $mov->voucher_number }}
                            </td>
                            <td rowspan="{{ $mov->movement_details->count() }}"
                                style="vertical-align: middle;">
                                {{ optional($mov->movement_responsibilities->where('role', 'CLIENTE')->first())->person->full_name ?? 'N/A' }}
                            </td>
                            <td rowspan="{{ $mov->movement_details->count() }}"
                                style="vertical-align: middle;">
                                {{ $mov->registration_date }}
                            </td>
                        @endif

                        @php
                            $el = optional(optional($movement_detail->inventory)->element);
                            $productName = $el->name ?? ($el->product_name ?? 'SIN NOMBRE');
                            $subtotal = $movement_detail->amount * $movement_detail->price;
                        @endphp

                        <td>{{ $productName }}</td>
                        <td class="text-center">{{ $movement_detail->amount }}</td>
                        <td class="text-center">{{ priceFormat($movement_detail->price) }}</td>
                        <td class="text-center">{{ priceFormat($subtotal) }}</td>

                        @if ($index === 0)
                            <td class="text-center fw-bold"
                                rowspan="{{ $mov->movement_details->count() }}"
                                style="vertical-align: middle;">
                                {{ priceFormat($mov->price) }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            @php
                $totalTotal = 0;
                foreach ($movements as $m) {
                    $totalTotal += $m->price;
                }
            @endphp
            <tr>
                <td colspan="8" class="text-end fw-bold">Total:</td>
                <td class="text-center fw-bold">{{ priceFormat($totalTotal) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- ================= TABLA DE PRODUCTOS AGRUPADOS ================= --}}
@if (isset($groupedProducts) && count($groupedProducts) > 0)
    <h4 class="mt-5 mb-3">{{ trans('ptventa::reports.Title_Products_Sold') }}</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">#</th>
                    <th>{{ trans('ptventa::reports.2T_Product') }}</th>
                    <th class="text-center">{{ trans('ptventa::reports.2T_Amount') }}</th>
                    <th class="text-center">{{ trans('ptventa::reports.2T_Price') }}</th>
                    <th class="text-center">{{ trans('ptventa::reports.2T_Subtotal') }}</th>
                </tr>
            </thead>
            <tbody>
                @php $totalProducts = 0; @endphp
                @foreach ($groupedProducts as $item)
                    @php $totalProducts += $item['subtotal']; @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>{{ $item['producto'] }}</td>
                        <td class="text-center">{{ $item['cantidad'] }}</td>
                        <td class="text-center">
                            @if ($item['min_price'] == $item['max_price'])
                                {{ priceFormat($item['min_price']) }}
                            @else
                                {{ priceFormat($item['min_price']) }} -
                                {{ priceFormat($item['max_price']) }}
                            @endif
                        </td>
                        <td class="text-center">{{ priceFormat($item['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end fw-bold">Total General:</td>
                    <td class="text-center fw-bold">{{ priceFormat($totalProducts) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
