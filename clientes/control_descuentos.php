<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

// --- Lógica de Scope y Títulos ---
$scope = $_GET['scope'] ?? 'empleados'; // Por defecto, Empleados

if ($scope === 'directores') {
    $tipoCliente = 'AS';
    $pageTitle = "Control de Descuentos - Referidos";
    $urlVolver = 'index_directores.php';
} else {
    $tipoCliente = 'E';
    $pageTitle = "Control de Descuentos - Empleados";
    $urlVolver = 'index.php';
}

// --- Lógica de Fechas ---
// Si no se especifican fechas, se usa el mes actual por defecto
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$resultados = [];
$error = null;

try {
    $clienteModel = new Cliente();
    $resultados = $clienteModel->obtenerResumenDescuentos($tipoCliente, $fechaDesde, $fechaHasta);
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en control_descuentos.php: " . $e->getMessage());
}

// --- Recursos para DataTables (igual que en consulta.php) ---
$extraHeaders = '
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
';

$pageScripts = "
<script src=\"https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js\"></script>
<script src=\"https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js\"></script>
<script>
$(document).ready(function() {
    $('#tablaDescuentos').DataTable({
    pageLength: 100,
        language: { /* ... (mismo bloque de idioma que en consulta.php) ... */ }
    });
});
</script>
";

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 1400px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Volver</a>
        <h1 class="h3 mb-0 text-center"><?= htmlspecialchars($pageTitle) ?></h1>
        <div style="width: 88px;"></div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="control_descuentos.php" method="GET">
                <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
<div class="row g-3 align-items-end">
    <div class="col-md-4">
        <label for="fecha_desde" class="form-label">Desde</label>
        <input type="date" class="form-control" name="fecha_desde" id="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
    </div>
    <div class="col-md-4">
        <label for="fecha_hasta" class="form-label">Hasta</label>
        <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
    </div>
    <div class="col-md-4 d-flex">
        <button type="submit" class="btn btn-primary w-100 me-2"><i class="bi bi-funnel-fill me-1"></i>Filtrar</button>
        <a href="exportar_excel_descuentos.php?scope=<?= htmlspecialchars($scope) ?>&fecha_desde=<?= htmlspecialchars($fechaDesde) ?>&fecha_hasta=<?= htmlspecialchars($fechaHasta) ?>" class="btn btn-success w-100" target="_blank">
            <i class="bi bi-file-earmark-excel-fill me-1"></i>Exportar
        </a>
    </div>
</div>
            </form>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tablaDescuentos" class="table table-striped table-hover align-middle" style="width:100%;">
                        <thead class="table-dark">
                            <tr>
                                <th>Código Cliente</th>
                                <th>Nombre</th>
                                <th class="text-center">Cant. Bonificaciones</th>
                                <th class="text-end">Total Bonificado ($)</th>
                                <th class="text-end">Promedio Descuento (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $res): ?>
                                <tr>
                                    <td><?= htmlspecialchars($res->COD_CLIENT ?? '') ?></td>
                                    <td><?= htmlspecialchars(mb_convert_encoding($res->NOMBRE_CLI ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center"><?= htmlspecialchars($res->CantidadBonificaciones ?? 0) ?></td>
                                    <td class="text-end"><?= number_format((float)($res->TotalBonificado ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format((float)($res->PromedioPorcentaje ?? 0), 2, ',', '.') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>