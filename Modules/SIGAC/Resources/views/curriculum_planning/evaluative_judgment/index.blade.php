@extends('sigac::layouts.master')

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="row d-flex justify-content-center">
            <div class="card card-blue card-outline shadow col-md-10">
                <div class="card-header">
                    <h3 class="card-title">Consultar aprendices</h3>
                </div>

                <div class="card-body">
                    <div class="form_search">
                        <div class="row align-items-center">

                            <div class="col-md-8">
                                <div class="form-group">
                                    {!! Form::select('course_id', $courses, null, [
                                        'class' => 'form-control',
                                        'placeholder' => '-- Seleccione --',
                                        'id' => 'course_id',
                                    ]) !!}
                                </div>
                            </div>

                            <div class="col-md-2">
                                <a class="btn btn-outline-secondary w-100"
                                   href="{{ route('sigac.academic_coordination.curriculum_planning.evaluative_judgment.load.create') }}">
                                    Cargar Archivo
                                </a>
                            </div>

                            <div class="col-md-2">
                                <button id="btnPowerBI" class="btn btn-primary w-100" disabled>
                                    Generar reporte
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="divApprentices"></div>
{{-- POWER BI --}}
        <div class="row mt-4" id="divPowerBI" style="display:none;">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Reporte Power BI – Ficha seleccionada</h5>
                    </div>
                    <div class="card-body p-0">
                        <iframe id="iframePowerBI"
                                width="100%"
                                height="720"
                                frameborder="0"
                                allowfullscreen>
                        </iframe>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.7.1.js"></script>

<script>
$(document).ready(function () {

    $('#course_id').select2();

    $('#course_id').on('change', function () {

        const ficha = $(this).val();
        $('#btnPowerBI').prop('disabled', !ficha);

        $.ajax({
            type: 'POST',
            url: "{{ route('sigac.academic_coordination.curriculum_planning.evaluative_judgment.search') }}",
            data: {
                _token: "{{ csrf_token() }}",
                course_id: ficha
            },
            success: function (data) {
                $('#divApprentices').html(data);
            }
        });

        $('#iframePowerBI').attr('src', '');
        $('#divPowerBI').hide();
    });

    $('#btnPowerBI').on('click', function () {

        const ficha = $('#course_id').val();
        if (!ficha) return;

        const baseUrl = "https://app.powerbi.com/view?r=eyJrIjoiZWY2N2Q3MTUtNTFkZi00N2ViLTk4ZDgtMzY2MWRiZGI0OWQ5IiwidCI6ImNiYzJjMzgxLTJmMmUtNGQ5My05MWQxLTUwNmM5MzE2YWNlNyIsImMiOjR9";

        const filter = '&filter=' + encodeURIComponent(
            "DIM_Ficha/Ficha eq '" + ficha + "'"
        );

        $('#iframePowerBI').attr('src', baseUrl + filter);
        $('#divPowerBI').slideDown();
    });

});
</script>