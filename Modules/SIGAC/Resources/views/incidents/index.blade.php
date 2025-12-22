@extends('sigac::layouts.master')

@section('content')

<div class="container mt-4">

    <h3 class="mb-4">Novedades de Ambiente</h3>

    <!-- FILTRO POR ESTADO -->
    <form method="GET" class="card p-3 mb-4">

        <div class="row">

            <div class="col-md-4">
                <label>Estado</label>
                <select name="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="ABIERTA"     {{ $status=='ABIERTA' ? 'selected':'' }}>Abierta</option>
                    <option value="EN_PROCESO"  {{ $status=='EN_PROCESO' ? 'selected':'' }}>En proceso</option>
                    <option value="CERRADA"     {{ $status=='CERRADA' ? 'selected':'' }}>Cerrada</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filtrar</button>
            </div>

        </div>

    </form>


    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Ambiente</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Reportado por</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Cambiar estado</th>
            </tr>
        </thead>

        <tbody>
            @foreach($incidents as $i)
                <tr>
                    <td>{{ $i->environment->name }}</td>
                    <td>{{ $i->type }}</td>
                    <td>{{ $i->description }}</td>
                    <td>{{ $i->reporter->name }}</td>
                    <td>{{ $i->reported_at }}</td>
                    <td>
                        <span class="badge bg-info">{{ $i->status }}</span>
                    </td>

                    <td>
                        <form method="POST" action="{{ route('sigac.coordinador.environment_incidents.updateStatus', $i->id) }}">
                            @csrf
                            @method('PUT')

                            <select name="status" class="form-control form-select">
                                <option value="ABIERTA">Abierta</option>
                                <option value="EN_PROCESO">En proceso</option>
                                <option value="CERRADA">Cerrada</option>
                            </select>

                            <button class="btn btn-primary btn-sm mt-1 w-100">Actualizar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</div>

@endsection
