
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Anticipos</title>
    <?php 
            require_once '../assets/css/css.php';
    ?>
    
    <!-- CSS -->
    <link href="css/libs/bootstrap.min.css" rel="stylesheet">
    <link href="css/libs/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/libs/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="css/libs/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/reporteAnticipos.css">
    <link rel="stylesheet" href="css/modalAyuda.css">

</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <div class="main-container">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Reporte de Anticipos
                    </h5>
                    <div class="header-buttons">
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-question-circle me-2"></i>Ayuda
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodosModal">
                            <i class="fas fa-cog me-2"></i>Administrar Períodos
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <div class="filters-section">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>Año
                            </label>
                            <select id="yearFilter" class="form-select">
                                <option value="">Todos los años</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">
                                <i class="fas fa-calendar-day me-1"></i>Mes
                            </label>
                            <select id="monthFilter" class="form-select">
                                <option value="">Todos los meses</option>
                                <option value="01">Enero</option>
                                <option value="02">Febrero</option>
                                <option value="03">Marzo</option>
                                <option value="04">Abril</option>
                                <option value="05">Mayo</option>
                                <option value="06">Junio</option>
                                <option value="07">Julio</option>
                                <option value="08">Agosto</option>
                                <option value="09">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-building me-1"></i>Departamento
                            </label>
                            <select id="departamentoFilter" class="form-select">
                                <option value="">Todos los departamentos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-search me-1"></i>Buscar
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="searchBox" class="form-control" placeholder="Buscar por legajo, nombre, DNI...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">&nbsp;</label>
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-eraser me-2"></i>Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading Spinner -->
                <div id="loadingSpinner" class="text-center my-4" style="display:none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando datos...</p>
                </div>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table id="reportTable" class="table table-hover table-bordered">
                        <thead class="table-header">
                            <tr>
                                <th>
                                    <i class="fas fa-id-badge me-2"></i>Legajo
                                </th>
                                <th>
                                    <i class="fas fa-user me-2"></i>Nombre y Apellido
                                </th>
                                <th>
                                    <i class="fas fa-id-card me-2"></i>DNI
                                </th>
                                <th>
                                    <i class="fas fa-calendar-alt me-2"></i>Período
                                </th>
                                <th>
                                    <i class="fas fa-dollar-sign me-2"></i>Importe
                                </th>
                                <th>
                                    <i class="fas fa-clock me-2"></i>Fecha de Carga
                                </th>
                                <th>
                                    <i class="fas fa-building me-2"></i>Departamento
                                </th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Modales -->
    <?php include 'modals/adminPeriodosReporte.php'; ?>
    <?php include 'modals/ayudaReporte.php'; ?>
    
    <!-- Scripts -->
    <script src="js/libs/jquery-3.6.0.min.js"></script>
    <script src="js/libs/bootstrap.bundle.min.js"></script>
    <script src="js/libs/jquery.dataTables.min.js"></script>
    <script src="js/libs/dataTables.bootstrap5.min.js"></script>
    <script src="js/libs/dataTables.buttons.min.js"></script>
    <script src="js/libs/buttons.bootstrap5.min.js"></script>
    <script src="js/libs/jszip.min.js"></script>
    <script src="js/libs/buttons.html5.min.js"></script>
    <script src="js/libs/buttons.print.min.js"></script>
    <script src="js/libs/sweetalert2.min.js"></script>
    
    <script src="js/reporteAnticipos.js"></script>
    <script src="js/ayudaReporteAnticipos.js"></script>
</body>
</html>