<!DOCTYPE html>
<html lang="es">
    <head>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<!-- Agregar Font Awesome en el header de tu layout -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        @include('sigac::layouts.partials.head')
        <!-- ✅ Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

        @stack('head')
    </head>
    <body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed sidebar-collapse">
        <div class="wrapper">
            <!-- Navbar -->
            @include('sigac::layouts.partials.navbar')
            <!-- /.navbar -->

            <!-- Main Sidebar Container -->
            @include('sigac::layouts.partials.sidebar')

            <!-- Content Wrapper. Contains page content -->
            <div class="content-wrapper">
                <!-- Content Header (Page header) -->
                <div class="content-header">
                    <div class="container-fluid">
                        <div class="row mb-2">
                            <div class="col-sm-6">
                              <h1 class="m-0">{{ $titleView }}</h1>
                            </div><!-- /.col -->
                            <div class="col-sm-6">
                                <ol class="breadcrumb float-sm-right">
                                    @stack('breadcrumbs')
                                </ol>
                            </div><!-- /.col -->
                        </div><!-- /.row -->
                    </div><!-- /.container-fluid -->
                </div>
                <!-- /.content-header -->

                <!-- Main content -->
                <div class="content">
                    <div class="container-fluid">
                        @section('content') @show
                    </div><!-- /.container-fluid -->
                </div>
                <!-- /.content -->
            </div>
            <!-- /.content-wrapper -->

            <!-- Control Sidebar -->
            @include('sigac::layouts.partials.controlSidebar')
            <!-- /.control-sidebar -->

        </div>
        <!-- ./wrapper -->

        <!-- REQUIRED SCRIPTS -->
        @include('sigac::layouts.partials.scripts')
        @stack('scripts')
        @section('js') @show

    </body>
</html>
