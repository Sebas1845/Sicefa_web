@extends('layouts.app')

@section('title', 'Inscritos · Curso ' . $course->code)

@section('content')
@php
    $programName = $course->program->name ?? '—';
    $municipality = $course->municipality->name ?? '—';
    $total = $apprentices->count();
@endphp

<div class="container py-4">

    {{-- ENCABEZADO --}}
    <div class="mb-4">
        <h3 class="mb-1">Inscritos · Curso {{ $course->code }}</h3>
        <p class="text-muted mb-0">
            Programa: <strong>{{ $programName }}</strong><br>
            Municipio: <strong>{{ $municipality }}</strong>
        </p>
    </div>

    {{-- INFO CURSO --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row text-sm">
                <div class="col-md-4">
                    <strong>Inicio:</strong>
                    {{ optional($course->start_date)->format('Y-m-d') ?? '—' }}
                </div>
                <div class="col-md-4">
                    <strong>Fin:</strong>
                    {{ optional($course->end_date)->format('Y-m-d') ?? '—' }}
                </div>
                <div class="col-md-4">
                    <strong>Total inscritos:</strong>
                    {{ $total }}
                </div>
            </div>
        </div>
    </div>

    {{-- LISTADO --}}
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Listado de aprendices</h5>

            @if($apprentices->isEmpty())
                <div class="alert alert-warning mb-0">
                    No hay aprendices registrados en este curso.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:70px">#</th>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($apprentices as $i => $ap)
                            @php
                                $person = $ap->person;
                                $name = $person
                                    ? trim(($person->first_name ?? '').' '.($person->first_last_name ?? ''))
                                    : '—';
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $name }}</td>
                                <td>{{ $person->document_number ?? '—' }}</td>
                                <td>
                                    <span class="badge badge-info">
                                        {{ $ap->apprentice_status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- PIE --}}
    <div class="text-center text-muted mt-4 small">
        Consulta pública generada por SICEFA · SIGAC
    </div>

</div>
@endsection
