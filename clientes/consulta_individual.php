<?php
// AUMENTAR TIEMPO DE EJECUCIÓN AL INICIO
set_time_limit(120); // 2 minutos máximo
ini_set('max_execution_time', 120);
ini_set('default_socket_timeout', 30);

require_once 'config.php';
require_once ROOT_PATH . 'Class/Local.php';
require_once ROOT_PATH . 'Class/Conexion.php';
require_once ROOT_PATH . 'Class/Cliente.php';

// FUNCIÓN CORREGIDA - SIN utf8_encode()
function limpiarYCodificar($texto) {
    if ($texto === null || $texto === '') return '';
    
    // Eliminar caracteres de control
    $textoLimpio = preg_replace('/[[:cntrl:]]/', '', $texto);
    
    // Verificar codificación
    if (function_exists('mb_check_encoding') && mb_check_encoding($textoLimpio, 'UTF-8')) {
        return $textoLimpio;
    }
    
    // Convertir si es necesario
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($textoLimpio, 'UTF-8', 'ISO-8859-1');
    }
    
    return $textoLimpio; // Fallback
}

$pageTitle = "Consulta Individual";
$from = $_GET['from'] ?? 'directores';
$urlVolver = ($from === 'directores') ? 'index_directores.php' : 'index.php';
$filtroTipo = ($from === 'directores') ? 'AS' : 'E';

$dniBuscado = trim($_GET['dni'] ?? '');
$busquedaRealizada = false;
$sucursalesConDni = [];
$sucursalesSinDni = [];
$erroresConexion = [];
$listaClientesParaBusqueda = [];
$errorGeneral = null;

// VALIDACIÓN DEL DNI
if (!empty($dniBuscado)) {
    // Limpiar el DNI: quitar puntos, guiones y espacios
    $dniBuscado = preg_replace('/[^0-9]/', '', $dniBuscado);
    
    if (strlen($dniBuscado) < 7 || strlen($dniBuscado) > 11) {
        $errorGeneral = "El DNI/CUIT debe tener entre 7 y 11 dígitos numéricos.";
    }
}

try {
    $clienteModel = new Cliente();
    $listaClientesParaBusqueda = $clienteModel->obtenerClientesParaBusqueda($filtroTipo);

    if (!empty($dniBuscado) && empty($errorGeneral)) {
        $busquedaRealizada = true;
        
        $localModel = new Local();
        $conexionModel = new Conexion();
        
        // OBTENER SUCURSALES CON LIMITE PARA EVITAR TIMEOUT
        $sucursalesHabilitadas = $localModel->listarLocales();
        
        // LIMITAR A 15 SUCURSALES MÁXIMO PARA PRUEBAS
        $maxSucursales = 50;
        $sucursalesLimitadas = array_slice($sucursalesHabilitadas, 0, $maxSucursales);
        
        $totalSucursales = count($sucursalesLimitadas);
        $consultadas = 0;
        
        // AGREGAR LOG PARA DEBUG
        error_log("Iniciando búsqueda de DNI: $dniBuscado en $totalSucursales sucursales");

        foreach ($sucursalesLimitadas as $sucursal) {
            $consultadas++;
            $cid = null;
            
            $infoSucursalDisplay = htmlspecialchars(
                limpiarYCodificar($sucursal->NRO_SUCURSAL . ' - ' . $sucursal->DESC_SUCURSAL),
                ENT_QUOTES,
                'UTF-8'
            );

            try {
                // VERIFICAR CONFIGURACIÓN MÍNIMA
                if (empty($sucursal->Host) || empty($sucursal->DbName)) {
                    throw new Exception("Configuración incompleta");
                }

                if (empty($sucursal->UsuarioDns) || empty($sucursal->ClaveDns)) {
                    throw new Exception("Sin credenciales");
                }

                // CONEXIÓN CON TIMEOUT
                $startTime = microtime(true);
                $cid = $conexionModel->conexionSucursalPorHost(
                    $sucursal->Host, 
                    $sucursal->DbName, 
                    $sucursal->UsuarioDns, 
                    $sucursal->ClaveDns
                );
                
                $connectionTime = microtime(true) - $startTime;
                
                if (!$cid) {
                    throw new Exception("Conexión fallida");
                }
                
                if ($connectionTime > 5) {
                    error_log("Conexión lenta a {$sucursal->NRO_SUCURSAL}: {$connectionTime}s");
                }

                // CONSULTA OPTIMIZADA
                $sqlCheck = "SELECT TOP 1 CUIT FROM GVA14 WHERE CUIT = ?";
                $stmtCheck = @odbc_prepare($cid, $sqlCheck);
                
                if (!$stmtCheck) {
                    $error = odbc_errormsg($cid);
                    throw new Exception("Error preparando consulta: " . $error);
                }
                
                $executeStart = microtime(true);
                $ejecutado = @odbc_execute($stmtCheck, [$dniBuscado]);
                $executeTime = microtime(true) - $executeStart;
                
                if (!$ejecutado) {
                    $error = odbc_errormsg($cid);
                    throw new Exception("Error ejecutando consulta: " . $error);
                }
                
                if ($executeTime > 3) {
                    error_log("Consulta lenta en {$sucursal->NRO_SUCURSAL}: {$executeTime}s");
                }
                
                $result = @odbc_fetch_array($stmtCheck);

                if ($result && isset($result['CUIT'])) {
                    $sucursalesConDni[] = $infoSucursalDisplay;
                    error_log("✓ Encontrado en: {$sucursal->NRO_SUCURSAL}");
                } else {
                    $sucursalesSinDni[] = $infoSucursalDisplay;
                }

            } catch (Exception $e) {
                $mensajeError = $e->getMessage();
                $erroresConexion[] = $infoSucursalDisplay . ": " . $mensajeError;
                error_log("✗ Error en {$sucursal->NRO_SUCURSAL}: {$mensajeError}");
            } finally {
                if ($cid) {
                    @odbc_close($cid);
                }
            }
            
            // PEQUEÑA PAUSA CADA 3 SUCURSALES PARA NO SATURAR
            if ($consultadas % 3 == 0) {
                usleep(50000); // 0.05 segundos
            }
            
            // FORZAR FLUSH PARA MOSTRAR PROGRESO
            if ($consultadas % 5 == 0) {
                echo " ";
                flush();
                ob_flush();
            }
        }
        
        error_log("Búsqueda completada. Encontrado en: " . count($sucursalesConDni) . " sucursales");

    }
} catch (Exception $e) {
    $errorGeneral = "Error en el sistema: " . $e->getMessage();
    error_log("ERROR GENERAL: " . $e->getMessage());
}

// HEADERS Y SCRIPTS
$extraHeaders = '
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
';

$pageScripts = "
<script src=\"https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js\"></script>
<script>
$(document).ready(function() {
    $('#nombreBusqueda').select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba o seleccione un nombre',
        allowClear: true,
        width: '100%',
        dropdownParent: $('body')
    });
    
    // Mostrar loading durante búsqueda
    $('form').on('submit', function() {
        if ($('#dni').val().trim() !== '') {
            $('#loading').show();
        }
    });
});
</script>
";

include_once ROOT_PATH . 'templates/header.php';
?>

<!-- AGREGAR INDICADOR DE CARGA -->
<div id="loading" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.3);">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="mt-2">Buscando en sucursales...<br><small>Esto puede tomar unos segundos</small></p>
    </div>
</div>

<div class="mx-auto" style="max-width: 900px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Volver</a>
        <h1 class="h3 mb-0 text-center"><?= htmlspecialchars($pageTitle) ?></h1>
        <div style="width: 88px;"></div>
    </div>

    <!-- MENSAJE DE ADVERTENCIA SI HAY ERROR -->
    <?php if ($errorGeneral): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= htmlspecialchars($errorGeneral) ?>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            
            <!-- BÚSQUEDA POR DNI -->
            <div class="mb-4">
                <form action="consulta_individual.php" method="GET" id="formDni">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <label for="dni" class="form-label fs-5">
                        <i class="bi bi-search me-1"></i>Ingrese el DNI / CUIT a buscar
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-lg" name="dni" id="dni" 
                               placeholder="Ej: 20123456789" value="<?= htmlspecialchars($dniBuscado) ?>" 
                               pattern="[0-9]{7,11}" title="Entre 7 y 11 dígitos">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                    </div>
                    <div class="form-text">Ingrese solo números, sin puntos ni guiones</div>
                </form>
            </div>

            <!-- BÚSQUEDA POR NOMBRE -->
            <div>
                <form action="consulta_individual.php" method="GET" id="formNombre">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <label for="nombreBusqueda" class="form-label fs-5">
                        <i class="bi bi-person me-1"></i>Seleccione un nombre de la lista
                    </label>
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <select class="form-select form-select-lg" id="nombreBusqueda" name="dni" required>
                                <option value=""></option>
                                <?php foreach ($listaClientesParaBusqueda as $cliente): ?>
                                    <?php
                                    $nombreCorregido = limpiarYCodificar($cliente->NOM_COM);
                                    $cuitLimpio = preg_replace('/[^0-9]/', '', $cliente->CUIT);
                                    ?>
                                    <option value="<?= htmlspecialchars($cuitLimpio, ENT_QUOTES, 'UTF-8') ?>" 
                                            <?= ($dniBuscado == $cuitLimpio) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nombreCorregido, ENT_QUOTES, 'UTF-8') ?> 
                                        (<?= htmlspecialchars($cuitLimpio, ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-lg ms-2" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- INFO ADICIONAL -->
            <?php if ($busquedaRealizada): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Nota:</strong> Por razones de rendimiento, esta app puede demorar unos segundos en la busqueda de un DNI particular.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RESULTADOS -->
    <?php if ($busquedaRealizada): ?>
    <div class="mt-4">
        <h2 class="text-center mb-4">
            <i class="bi bi-clipboard-data me-2"></i>
            Resultados para: <strong><?= htmlspecialchars($dniBuscado) ?></strong>
        </h2>
        
        <!-- RESUMEN -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Consultado</h5>
                        <p class="display-6 text-primary"><?= count($sucursalesConDni) + count($sucursalesSinDni) ?></p>
                        <p class="text-muted">sucursales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Encontrado en</h5>
                        <p class="display-6 text-success"><?= count($sucursalesConDni) ?></p>
                        <p class="text-muted">sucursales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">No encontrado</h5>
                        <p class="display-6 text-warning"><?= count($sucursalesSinDni) ?></p>
                        <p class="text-muted">sucursales</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETALLE -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-check-circle-fill me-2"></i> Encontrado en</span>
                        <span class="badge bg-light text-success"><?= count($sucursalesConDni) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($sucursalesConDni)): ?>
                                <li class="list-group-item text-center py-3">
                                    <i class="bi bi-emoji-frown fs-1 text-muted"></i>
                                    <p class="mt-2">No se encontró en ninguna sucursal</p>
                                </li>
                            <?php else: ?>
                                <?php foreach ($sucursalesConDni as $sucursal): ?>
                                    <li class="list-group-item">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        <?= $sucursal ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-x-circle-fill me-2"></i> No encontrado en</span>
                        <span class="badge bg-light text-dark"><?= count($sucursalesSinDni) ?></span>
                    </div>
                    <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($sucursalesSinDni)): ?>
                                <li class="list-group-item text-center py-3">
                                    <i class="bi bi-emoji-smile fs-1 text-muted"></i>
                                    <p class="mt-2">Se encontró en todas las sucursales</p>
                                </li>
                            <?php else: ?>
                                <?php foreach ($sucursalesSinDni as $sucursal): ?>
                                    <li class="list-group-item">
                                        <i class="bi bi-x-circle text-warning me-2"></i>
                                        <?= $sucursal ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ERRORES -->
        <?php if (!empty($erroresConexion)): ?>
        <div class="card mt-4">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle-fill me-2"></i> Errores de Conexión</span>
                <span class="badge bg-light text-danger"><?= count($erroresConexion) ?></span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($erroresConexion as $error): ?>
                        <li class="list-group-item text-danger">
                            <i class="bi bi-bug me-2"></i>
                            <small><?= htmlspecialchars($error) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>