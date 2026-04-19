<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

$scope = $_GET['scope'] ?? '';

if ($scope === 'empleados') {
    $filtroTipo    = 'E';
    $pageTitle     = "Consulta de Empleados";
    $accentClass   = 'emp';
    $labelTipos    = 'empleados';
    $urlVolver     = 'index.php';
    $portalContext = 'empleados';
} elseif ($scope === 'directores') {
    $filtroTipo    = 'AS';
    $pageTitle     = "Consulta de Referidos";
    $accentClass   = 'ref';
    $labelTipos    = 'referidos';
    $urlVolver     = 'index_directores.php';
    $portalContext = 'referidos';
} else {
    header('Location: index.php');
    exit();
}

function asegurarUTF8($string) {
    if ($string === null || $string === '') return $string;
    if (mb_check_encoding($string, 'UTF-8')) return $string;
    return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
}

function formatearFecha($fecha) {
    if ($fecha instanceof DateTime) {
        return $fecha->format('d/m/Y');
    }
    if (is_string($fecha) && $fecha !== '') {
        $ts = strtotime($fecha);
        return $ts !== false ? date('d/m/Y', $ts) : 'N/A';
    }
    return 'N/A';
}

$error        = null;
$clientes     = [];
$filtroEstado = $_GET['estado'] ?? '';

try {
    $cliente  = new Cliente();
    $filtros  = ['tipo' => $filtroTipo, 'estado' => $filtroEstado];
    $clientes = $cliente->consultarClientes($filtros);
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en consulta.php: " . $e->getMessage());
}

$totalClientes      = count($clientes);
$totalHabilitados   = count(array_filter($clientes, fn($c) => $c->Habilitado));
$totalInhabilitados = $totalClientes - $totalHabilitados;

$cuitsDuplicados = [];
if (!empty($clientes)) {
    $todosLosCuits = array_map(fn($cli) =>
        (isset($cli->Cuit) && !empty(trim($cli->Cuit)) && trim($cli->Cuit) !== '0')
            ? trim($cli->Cuit) : null,
        $clientes
    );
    $cuitsValidos = array_filter($todosLosCuits);
    if (!empty($cuitsValidos)) {
        $conteoCuits     = array_count_values($cuitsValidos);
        $cuitsDuplicados = array_keys(array_filter($conteoCuits, fn($count) => $count > 1));
    }
}

$extraHeaders = '
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
';

$mainContainerClass = 'py-5';

ob_start();
?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function () {

    $('#tablaClientes').DataTable({
        pageLength: 100,
        language: {
            processing:   'Procesando...',
            lengthMenu:   'Mostrar _MENU_ registros',
            zeroRecords:  'No se encontraron resultados',
            emptyTable:   'Ningún dato disponible',
            info:         'Mostrando _START_ al _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_)',
            search:       'Buscar:',
            paginate:     { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    var accentColor = '<?= $accentClass === 'ref' ? '#059669' : '#4f46e5' ?>';

    $('#tablaClientes tbody').on('change', '.estado-switch', function () {
        var checkbox      = $(this);
        var cell          = checkbox.closest('.switch-cell');
        var spinner       = cell.find('.switch-spinner');
        var codigoCli     = checkbox.data('codigo-cliente');
        var nuevoEstado   = checkbox.is(':checked');

        checkbox.prop('disabled', true);
        spinner.show();

        fetch('ajax_toggle_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ codigo_cliente: codigoCli, nuevo_estado: nuevoEstado })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                Swal.fire({
                    toast: true, position: 'top-end',
                    icon: 'success', title: data.message,
                    showConfirmButton: false, timer: 4000,
                    timerProgressBar: true, iconColor: accentColor
                });
            } else {
                checkbox.prop('checked', !nuevoEstado);
                Swal.fire({
                    toast: true, position: 'top-end',
                    icon: 'error', title: data.message,
                    showConfirmButton: false, timer: 4000,
                    timerProgressBar: true
                });
            }
        })
        .catch(function () {
            checkbox.prop('checked', !nuevoEstado);
            Swal.fire({
                toast: true, position: 'top-end',
                icon: 'error', title: 'Error de conexión con el servidor.',
                showConfirmButton: false, timer: 4000,
                timerProgressBar: true
            });
        })
        .finally(function () {
            checkbox.prop('disabled', false);
            spinner.hide();
        });
    });

    document.getElementById('btn-exportar').addEventListener('click', function () {
        var btn     = this;
        var icon    = btn.querySelector('.export-icon');
        var spinner = btn.querySelector('.btn-spinner');
        btn.disabled          = true;
        icon.style.display    = 'none';
        spinner.style.display = 'inline-block';
        setTimeout(function () {
            window.open(btn.dataset.href, '_blank');
            btn.disabled          = false;
            icon.style.display    = 'inline-block';
            spinner.style.display = 'none';
        }, 500);
    });

});
</script>
<?php
$pageScripts = ob_get_clean();

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 1400px;">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al portal
        </a>
    </div>

    <div class="portal-heading d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (!$error): ?>
            <p>
                <?= $totalClientes ?> <?= htmlspecialchars($labelTipos) ?>
                &nbsp;&middot;&nbsp;
                <span class="text-success fw-semibold"><?= $totalHabilitados ?> habilitados</span>
                &nbsp;/&nbsp;
                <span class="text-danger fw-semibold"><?= $totalInhabilitados ?> inhabilitados</span>
            </p>
            <?php else: ?>
            <p>Consultá y gestioná el estado de los <?= htmlspecialchars($labelTipos) ?>.</p>
            <?php endif; ?>
        </div>

        <div class="region-chips region-chips--<?= $accentClass ?>"
             role="group"
             aria-label="Filtrar por estado">
            <a href="consulta.php?scope=<?= urlencode($scope) ?>"
               class="region-chip <?= ($filtroEstado === '') ? 'region-chip--active' : '' ?>">
                Todos
            </a>
            <a href="consulta.php?scope=<?= urlencode($scope) ?>&estado=1"
               class="region-chip <?= ($filtroEstado === '1') ? 'region-chip--active' : '' ?>">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Habilitados
            </a>
            <a href="consulta.php?scope=<?= urlencode($scope) ?>&estado=0"
               class="region-chip <?= ($filtroEstado === '0') ? 'region-chip--active' : '' ?>">
                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Inhabilitados
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php else: ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">

            <div class="d-flex justify-content-end p-3 border-bottom">
                <button type="button"
                        id="btn-exportar"
                        class="btn btn-success btn-sm"
                        data-href="exportar_excel.php?tipo=<?= urlencode($filtroTipo) ?>&estado=<?= urlencode($filtroEstado) ?>">
                    <i class="bi bi-file-earmark-excel-fill export-icon me-1" aria-hidden="true"></i>
                    <span class="btn-spinner" style="display:none;" aria-hidden="true"></span>
                    Exportar a Excel
                </button>
            </div>

            <div class="table-responsive p-3">
                <table id="tablaClientes"
                       class="table table-striped table-hover align-middle"
                       style="width:100%;">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>DNI / CUIT</th>
                            <th class="text-end">% Desc.</th>
                            <th>Fecha de Alta</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cli): ?>
                        <?php
                        $cuitActual  = isset($cli->Cuit) ? trim($cli->Cuit) : '';
                        $esDuplicado = !empty($cuitActual) && $cuitActual !== '0' && in_array($cuitActual, $cuitsDuplicados);
                        $codigoCli   = htmlspecialchars(asegurarUTF8($cli->Codigo ?? ''), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr id="cliente-<?= $codigoCli ?>">
                            <td><?= $codigoCli ?></td>
                            <td><?= htmlspecialchars(mb_convert_encoding($cli->Nombre ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($esDuplicado): ?>
                                <span class="duplicado"
                                      data-bs-toggle="tooltip"
                                      data-bs-title="CUIT duplicado en la lista">
                                    <?= htmlspecialchars(asegurarUTF8($cuitActual), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php else: ?>
                                <?= htmlspecialchars(asegurarUTF8($cuitActual), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((float)($cli->PorcentajeDescuento ?? 0), 2) ?>%</td>
                            <td><?= formatearFecha($cli->FechaAlta ?? null) ?></td>
                            <td class="text-center">
                                <div class="switch-cell">
                                    <div class="form-check form-switch d-inline-block mb-0">
                                        <input class="form-check-input estado-switch"
                                               type="checkbox"
                                               role="switch"
                                               data-codigo-cliente="<?= $codigoCli ?>"
                                               <?= ($cli->Habilitado) ? 'checked' : '' ?>>
                                    </div>
                                    <i class="bi bi-arrow-repeat switch-spinner spin-icon"
                                       style="display:none;"
                                       aria-hidden="true"></i>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <?php endif; ?>
</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>
