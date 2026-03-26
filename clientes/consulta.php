<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

$scope = $_GET['scope'] ?? '';
$filtroTipo = '';
$pageTitle = "Consulta de Clientes";

if ($scope === 'empleados') {
    $filtroTipo = 'E';
    $pageTitle = "Consulta de Empleados";
} elseif ($scope === 'directores') {
    $filtroTipo = 'AS';
    $pageTitle = "Consulta de Referidos";
} else {
    header('Location: index.php');
    exit();
}

// Función de ayuda para asegurar UTF-8 sin usar utf8_encode()
function asegurarUTF8($string) {
    if ($string === null || $string === '') {
        return $string;
    }
    
    // Si ya está en UTF-8, devolver tal cual
    if (mb_check_encoding($string, 'UTF-8')) {
        return $string;
    }
    
    // Intentar convertir desde ISO-8859-1 (Latin-1)
    return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
}

$error = null;
$clientes = [];
$filtroEstado = $_GET['estado'] ?? '';

try {
    $cliente = new Cliente();
    $filtros = ['tipo' => $filtroTipo, 'estado' => $filtroEstado];
    $clientes = $cliente->consultarClientes($filtros);
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en consulta.php: " . $e->getMessage());
}

$cuitsDuplicados = [];
if (!empty($clientes)) {
    $todosLosCuits = array_map(function($cli) {
        return (isset($cli->Cuit) && !empty(trim($cli->Cuit)) && trim($cli->Cuit) !== '0') ? trim($cli->Cuit) : null;
    }, $clientes);
    $cuitsValidos = array_filter($todosLosCuits);
    if (!empty($cuitsValidos)) {
        $conteoCuits = array_count_values($cuitsValidos);
        $cuitsDuplicados = array_keys(array_filter($conteoCuits, function($count) {
            return $count > 1;
        }));
    }
}

$extraHeaders = '
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
';

$pageScripts = "
<script src=\"https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js\"></script>
<script src=\"https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js\"></script>
<script>
$(document).ready(function() {
    var table = $('#tablaClientes').DataTable({
    pageLength: 100,
        language: {
            \"processing\": \"Procesando...\",
            \"lengthMenu\": \"Mostrar _MENU_ registros\",
            \"zeroRecords\": \"No se encontraron resultados\",
            \"emptyTable\": \"Ningún dato disponible en esta tabla\",
            \"info\": \"Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros\",
            \"infoEmpty\": \"Mostrando registros del 0 al 0 de un total de 0 registros\",
            \"infoFiltered\": \"(filtrado de un total de _MAX_ registros)\",
            \"search\": \"Buscar:\",
            \"loadingRecords\": \"Cargando...\",
            \"paginate\": {
                \"first\": \"Primero\",
                \"last\": \"Último\",
                \"next\": \"Siguiente\",
                \"previous\": \"Anterior\"
            },
            \"aria\": {
                \"sortAscending\": \": Activar para ordenar la columna de manera ascendente\",
                \"sortDescending\": \": Activar para ordenar la columna de manera descendente\"
            }
        }
    });

    const toastEl = document.getElementById('liveToast');
    const toast = new bootstrap.Toast(toastEl);

    $('#tablaClientes tbody').on('change', '.estado-switch', function() {
        const checkbox = $(this);
        const codigoCliente = checkbox.data('codigo-cliente');
        const nuevoEstado = checkbox.is(':checked');
        
        checkbox.prop('disabled', true);
        
        fetch('ajax_toggle_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                codigo_cliente: codigoCliente,
                nuevo_estado: nuevoEstado
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#toastTitle').text('Éxito').removeClass('text-danger').addClass('text-success');
                $('#toastBody').html(data.message);
                toast.show();
            } else {
                checkbox.prop('checked', !nuevoEstado);
                $('#toastTitle').text('Error').removeClass('text-success').addClass('text-danger');
                $('#toastBody').html(data.message);
                toast.show();
            }
        })
        .catch(error => {
            checkbox.prop('checked', !nuevoEstado);
            $('#toastTitle').text('Error').removeClass('text-success').addClass('text-danger');
            $('#toastBody').text('Error de conexión con el servidor.');
            toast.show();
            console.error('Error:', error);
        })
        .finally(() => {
            checkbox.prop('disabled', false);
        });
    });
});
</script>
";

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 1400px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= ($scope === 'empleados') ? 'index.php' : 'index_directores.php' ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Volver</a>
        <h1 class="h3 mb-0 text-center"><?= htmlspecialchars($pageTitle) ?></h1>
        <div style="width: 88px;"></div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="consulta.php" method="GET">
                <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Filtrar por Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Cualquier Estado</option>
                            <option value="1" <?= ($filtroEstado == '1') ? 'selected' : '' ?>>Habilitado</option>
                            <option value="0" <?= ($filtroEstado == '0') ? 'selected' : '' ?>>Inhabilitado</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex">
                        <button type="submit" class="btn btn-primary w-100 me-2"><i class="bi bi-funnel-fill me-1"></i>Filtrar</button>
                        <a href="consulta.php?scope=<?= htmlspecialchars($scope) ?>" class="btn btn-secondary w-100 me-2"><i class="bi bi-arrow-clockwise me-1"></i>Limpiar</a>
                        <a href="exportar_excel.php?tipo=<?= htmlspecialchars($filtroTipo) ?>&estado=<?= htmlspecialchars($filtroEstado) ?>" class="btn btn-success w-100" target="_blank">
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
                    <table id="tablaClientes" class="table table-striped table-hover align-middle" style="width:100%;">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>DNI / CUIT</th>
                                <th class="text-end">% Descuento</th>
                                <th>Fecha de Alta</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cli): ?>
                                <tr id="cliente-<?= htmlspecialchars(asegurarUTF8($cli->Codigo ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <td><?= htmlspecialchars(asegurarUTF8($cli->Codigo ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(mb_convert_encoding($cli->Nombre ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php
                                    $cuitActual = isset($cli->Cuit) ? trim($cli->Cuit) : '';
                                    $esDuplicado = !empty($cuitActual) && in_array($cuitActual, $cuitsDuplicados);
                                    ?>
                                    <td class="<?= $esDuplicado ? 'duplicado' : '' ?>">
                                        <?= htmlspecialchars(asegurarUTF8($cuitActual), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="text-end"><?= number_format((float)($cli->PorcentajeDescuento ?? 0), 2) ?>%</td>
                                    <td><?= ($cli->FechaAlta ?? null) ? date('d/m/Y', strtotime($cli->FechaAlta)) : 'N/A'; ?></td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input estado-switch" type="checkbox" 
                                                   role="switch" 
                                                   data-codigo-cliente="<?= htmlspecialchars(asegurarUTF8($cli->Codigo ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                   <?= ($cli->Habilitado) ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toastTitle"></strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>