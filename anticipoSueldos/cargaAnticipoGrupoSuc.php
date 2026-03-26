
<?php
session_start();

// Verificar que el usuario tenga definida la variable de sesión numsuc y que NO sea Casa Central
$usuarioAutorizado = isset($_SESSION['numsuc']) && $_SESSION['numsuc'] != 'Casa Central';

if ($usuarioAutorizado) {
    $nombreSucursal = $_SESSION['descLocal'];
    $nroSucurs = $_SESSION['numsuc'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Anticipo de Sueldo Grupal</title>
    <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/css/css.php';
    ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/cargaAnticipoGrupo.css" class="rel">

</head>
<body class="bg-light">
    <div class="container mt-4">
        <?php if (!$usuarioAutorizado): ?>
        <!-- Mensaje de error para usuarios no autorizados -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white text-center">
                        <h5 class="mb-0">
                            <i class="fas fa-lock me-2"></i>
                            Acceso No Autorizado
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-danger mb-3">No tienes permisos para acceder a esta página</h4>
                        <p class="lead text-muted mb-4">
                            Para utilizar esta aplicación debes estar logueado correctamente en el sistema.
                        </p>
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¿Cómo solucionarlo?</strong><br>
                            Debes ingresar al sistema desde el login principal y luego volver a intentar.
                        </div>
                        <a href="https://app.xl.com.ar/sistemas/login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Ir al Login Principal
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Contenido normal de la aplicación para usuarios autorizados -->
        <div class="header-section mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Carga de Anticipo de Sueldo Grupal
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
                                    <span class="info-box-number" id="numSucursal" attr-numSucursal="<?php echo $nroSucurs ?>"><?php echo $nombreSucursal; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fecha de Depósito -->
                        <div class="col-md-4">
                            <div class="info-box bg-info bg-opacity-10">
                                <div class="info-box-content">
                                    <i class="fas fa-calendar-check me-2 text-info"></i>
                                    <span class="info-box-text">Fecha de Depósito:</span>
                                    <span class="info-box-number" id="fechaDeposito"></span>
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
                                                    <span class="info-box-number" id="vigDesde"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <small class="d-block text-muted">Hasta:</small>
                                                    <span class="info-box-number" id="vigHasta"></span>
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

        <!-- Sector Selector - Se mostrará solo cuando la sucursal sea Casa Central -->
        <div class="col-12" id="sectorSelector" style="display: none;">
            <div class="info-box bg-light">
                <div class="info-box-content">
                    <i class="fas fa-industry me-2 text-primary"></i>
                    <span class="info-box-text">Sector:</span>
                    <select id="sector" class="form-select mt-2">
                        <option value="">Seleccione sector</option>
                        <option value="FABRICA">FABRICA</option>
                        <option value="LOGISTICA">LOGISTICA</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <form id="adelantoForm" class="mb-4">
            <div class="card">
                <div class="card-body">
                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="empleadosTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Legajo</th>
                                    <th>Nombre y Apellido</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-end mt-3">
                <button type="button" class="btn btn-primary" onclick = "cargarAnticipoGrupo()">
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
    <script src="js/cargarAnticipoGrupo.js"></script>
    <?php endif; ?>
</body>
</html>