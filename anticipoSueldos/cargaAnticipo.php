
<?php

if (!isset($_SESSION['numsuc'])) {
    $_SESSION['numsuc'] = 'Casa Central';
}

$nroSucurs = $_SESSION['numsuc'];

?>

<!-- index.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Anticipo de Sueldo</title>
    <!-- ICONO DE XL -->
    <link rel="icon" type="image/jpg" href="../../image/icono.jpg">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/cargaAnticipo.css" class="rel">
    
</head>
<body class="bg-light">
    <div class="container mt-4">
    <div class="header-section mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-money-bill-wave me-2"></i>
                Carga de Anticipo de Sueldo
            </h5>
        </div>
        
        <div class="card-body">
            <div class="row g-3">
                <!-- Sucursal -->
                <div class="col-12">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <i class="fas fa-building me-2 text-primary"></i>
                            <span class="info-box-text">Sucursal:</span>
                            <span class="info-box-number" id="numSucursal">Casa Central</span>
                        </div>
                    </div>
                </div>
                
                <!-- Fecha de Depósito -->
                <div class="col-md-4">
                    <div class="info-box bg-info bg-opacity-10">
                        <div class="info-box-content">
                            <i class="fas fa-calendar-check me-2 text-info"></i>
                            <span class="info-box-text">Fecha de Depósito:</span>
                            <span class="info-box-number" id="fechaDeposito"></span> <!-- Remover valor por defecto -->
                        </div>
                    </div>
                </div>

                <!-- Vigencia -->
                <div class="col-md-8">
                    <div class="info-box bg-warning bg-opacity-10">
                        <div class="info-box-content">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-clock me-2 text-warning"></i>
                                <span class="info-box-text fw-bold">Vigencia</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <small class="d-block text-muted">Desde:</small>
                                            <span class="info-box-number" id="vigDesde"></span> <!-- Remover valor por defecto -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <small class="d-block text-muted">Hasta:</small>
                                            <span class="info-box-number" id="vigHasta"></span> <!-- Remover valor por defecto -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Main Form -->
        <form id="adelantoForm" class="mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="empleadoSelect" class="form-label">Seleccione Empleado</label>
                            <select class="form-select" id="empleadoSelect" style="width: 100%">
                                <option value="">Buscar empleado...</option>
                            </select>
                        </div>
                    </div>

                    <!-- DataTable for Records -->
                    <div class="table-responsive">
                        <table id="registrosTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Legajo</th>
                                    <th>Nombre y Apellido</th>
                                    <th>DNI</th>
                                    <th>Importe Adelanto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary" onclick = "cargarAnticipo()">    
                    <i class="fas fa-paper-plane me-2"></i>Enviar
                </button>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/mostrarDatos.js"></script>
    <script src="js/cargarAnticipo.js"></script>

</body>
</html>