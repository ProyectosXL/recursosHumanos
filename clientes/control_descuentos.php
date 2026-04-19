<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

$scope = $_GET['scope'] ?? 'empleados';

if ($scope === 'directores') {
    $tipoCliente   = 'AS';
    $pageTitle     = "Control de Descuentos – Referidos";
    $accentClass   = 'ref';
    $portalContext = 'referidos';
    $urlVolver     = 'index_directores.php';
} else {
    $tipoCliente   = 'E';
    $pageTitle     = "Control de Descuentos – Empleados";
    $accentClass   = 'emp';
    $portalContext = 'empleados';
    $urlVolver     = 'index.php';
}

$mainContainerClass = 'py-5';

$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

/* Límite mensual basado en el mes de $fechaHasta */
$mesHasta     = (int)date('n', strtotime($fechaHasta));
$limiteActual = ($mesHasta === 10 || $mesHasta === 12) ? 5 : 3;

/* Período para mostrar en el badge */
$fechaDesdeDisplay = date('d/m/Y', strtotime($fechaDesde));
$fechaHastaDisplay = date('d/m/Y', strtotime($fechaHasta));

$resultados = [];
$error      = null;

try {
    $clienteModel = new Cliente();
    $resultados   = $clienteModel->obtenerResumenDescuentos($tipoCliente, $fechaDesde, $fechaHasta);
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en control_descuentos.php: " . $e->getMessage());
}

/* ── KPIs (calculados de los datos ya obtenidos) ───────────────── */
$kpiClientes  = count($resultados);
$kpiBonificado  = 0.0;
$kpiCantidad    = 0;
$promedioSum    = 0.0;
$kpiExcedidos   = 0;

foreach ($resultados as $r) {
    $cant = (int)($r->CantidadBonificaciones ?? 0);
    $kpiBonificado += (float)($r->TotalBonificado   ?? 0);
    $kpiCantidad   += $cant;
    $promedioSum   += (float)($r->PromedioPorcentaje ?? 0);
    if ($cant > $limiteActual) $kpiExcedidos++;
}

$kpiPromedio = $kpiClientes > 0 ? $promedioSum / $kpiClientes : 0.0;
$pctExcedidos = $kpiClientes > 0 ? (int)round($kpiExcedidos / $kpiClientes * 100) : 0;

$extraHeaders = '
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
';

ob_start();
?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {

    /* ── DataTables ─────────────────────────────────────────────── */
    $('#tablaDescuentos').DataTable({
        pageLength: 100,
        order: [[3, 'desc']],
        language: {
            processing:   'Procesando...',
            lengthMenu:   'Mostrar _MENU_ registros',
            zeroRecords:  'No se encontraron resultados',
            emptyTable:   'Ningún dato disponible',
            info:         'Mostrando _START_ al _END_ de _TOTAL_ registros',
            infoEmpty:    'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_)',
            search:       'Buscar:',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });

    /* ── Tooltips Bootstrap ─────────────────────────────────────── */
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    /* ── Exportar con spinner breve ─────────────────────────────── */
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

    /* ── Atajos de fecha ────────────────────────────────────────── */
    function isoFecha(d) {
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    document.querySelectorAll('.date-shortcut').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var hoy  = new Date();
            var desde, hasta;
            switch (chip.dataset.range) {
                case 'mes':
                    desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                    hasta = hoy;
                    break;
                case 'mes-anterior':
                    desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
                    hasta = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
                    break;
                case '3meses':
                    desde = new Date(hoy.getFullYear(), hoy.getMonth() - 2, 1);
                    hasta = hoy;
                    break;
                case 'anio':
                    desde = new Date(hoy.getFullYear(), 0, 1);
                    hasta = hoy;
                    break;
                default:
                    return;
            }
            document.getElementById('fecha_desde').value = isoFecha(desde);
            document.getElementById('fecha_hasta').value = isoFecha(hasta);
            document.getElementById('filtroForm').submit();
        });
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

    <div class="portal-heading">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p>Bonificaciones aplicadas en el período seleccionado. Límite del mes: <strong><?= $limiteActual ?> bonificaciones</strong>.</p>
    </div>

    <!-- ── Filtros ────────────────────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <form action="control_descuentos.php" method="GET" id="filtroForm">
                <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">

                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label for="fecha_desde" class="form-label fw-semibold">Desde</label>
                        <input type="date" class="form-control" name="fecha_desde" id="fecha_desde"
                               value="<?= htmlspecialchars($fechaDesde) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_hasta" class="form-label fw-semibold">Hasta</label>
                        <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta"
                               value="<?= htmlspecialchars($fechaHasta) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit"
                                class="btn btn-crear btn-crear--<?= $accentClass ?> w-100">
                            <i class="bi bi-funnel-fill me-1" aria-hidden="true"></i>Filtrar
                        </button>
                    </div>
                </div>

                <!-- Atajos de período -->
                <div class="region-chips region-chips--<?= $accentClass ?>">
                    <button type="button" class="region-chip date-shortcut" data-range="mes">
                        Este mes
                    </button>
                    <button type="button" class="region-chip date-shortcut" data-range="mes-anterior">
                        Mes anterior
                    </button>
                    <button type="button" class="region-chip date-shortcut" data-range="3meses">
                        Últimos 3 meses
                    </button>
                    <button type="button" class="region-chip date-shortcut" data-range="anio">
                        Este año
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php else: ?>

    <!-- ── KPIs ──────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="stat-card stat-card--blue">
                <div class="stat-card__icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
                <div class="stat-card__num"><?= $kpiClientes ?></div>
                <div class="stat-card__lbl">con bonificaciones</div>
                <div class="stat-progress">
                    <div class="stat-progress__fill" style="width:100%"></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card stat-card--green">
                <div class="stat-card__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
                <div class="stat-card__num" style="font-size:1.6rem;">
                    $&nbsp;<?= number_format($kpiBonificado, 0, ',', '.') ?>
                </div>
                <div class="stat-card__lbl">total bonificado</div>
                <div class="stat-progress">
                    <div class="stat-progress__fill" style="width:100%"></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card stat-card--amber">
                <div class="stat-card__icon"><i class="bi bi-percent" aria-hidden="true"></i></div>
                <div class="stat-card__num"><?= number_format($kpiPromedio, 1, ',', '.') ?>%</div>
                <div class="stat-card__lbl">promedio de descuento</div>
                <div class="stat-progress">
                    <div class="stat-progress__fill" style="width:<?= min(100, (int)round($kpiPromedio * 5)) ?>%"></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card stat-card--red">
                <div class="stat-card__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
                <div class="stat-card__num"><?= $kpiExcedidos ?></div>
                <div class="stat-card__lbl">sobre el límite (<?= $limiteActual ?>)</div>
                <div class="stat-progress">
                    <div class="stat-progress__fill" style="width:<?= $pctExcedidos ?>%"></div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Tabla ──────────────────────────────────────────────────── -->
    <div class="card shadow-sm">

        <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
            <span class="fw-semibold">Detalle de bonificaciones</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted" style="font-size:.8rem;">
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($fechaDesdeDisplay) ?> – <?= htmlspecialchars($fechaHastaDisplay) ?>
                </span>
                <button type="button"
                        id="btn-exportar"
                        class="btn btn-success btn-sm"
                        data-href="exportar_excel_descuentos.php?scope=<?= urlencode($scope) ?>&fecha_desde=<?= urlencode($fechaDesde) ?>&fecha_hasta=<?= urlencode($fechaHasta) ?>">
                    <i class="bi bi-file-earmark-excel-fill export-icon me-1" aria-hidden="true"></i>
                    <span class="btn-spinner" style="display:none;" aria-hidden="true"></span>
                    Exportar a Excel
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive p-3">
                <table id="tablaDescuentos"
                       class="table table-striped table-hover align-middle"
                       style="width:100%;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:2.5rem;"
                                title="Estado respecto al límite de <?= $limiteActual ?> bonificaciones">
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                            </th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="text-center">Cant. Bonificaciones</th>
                            <th class="text-end">Total Bonificado ($)</th>
                            <th class="text-end">Prom. Descuento (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $res):
                            $cant      = (int)($res->CantidadBonificaciones ?? 0);
                            $excedida  = $cant > $limiteActual;
                            $enLimite  = $cant === $limiteActual;

                            if ($excedida) {
                                $semaforoOrder   = 2;
                                $semaforoClass   = 'bi-x-circle-fill text-danger';
                                $semaforoTooltip = "Excede el límite ({$cant}/{$limiteActual})";
                            } elseif ($enLimite) {
                                $semaforoOrder   = 1;
                                $semaforoClass   = 'bi-exclamation-circle-fill text-warning';
                                $semaforoTooltip = "En el límite ({$cant}/{$limiteActual})";
                            } else {
                                $semaforoOrder   = 0;
                                $semaforoClass   = 'bi-check-circle-fill text-success';
                                $semaforoTooltip = "Dentro del límite";
                            }
                        ?>
                        <tr class="<?= $excedida ? 'fila-excedida' : '' ?>">
                            <td class="text-center" data-sort="<?= $semaforoOrder ?>">
                                <i class="bi <?= $semaforoClass ?> fs-5"
                                   data-bs-toggle="tooltip"
                                   data-bs-title="<?= htmlspecialchars($semaforoTooltip) ?>"
                                   aria-hidden="true"></i>
                            </td>
                            <td><?= htmlspecialchars($res->COD_CLIENT ?? '') ?></td>
                            <td><?= htmlspecialchars(mb_convert_encoding($res->NOMBRE_CLI ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center fw-semibold"><?= $cant ?></td>
                            <td class="text-end"><?= number_format((float)($res->TotalBonificado ?? 0), 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float)($res->PromedioPorcentaje ?? 0), 2, ',', '.') ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($resultados)): ?>
                    <tfoot>
                        <tr class="table-secondary fw-semibold">
                            <td></td>
                            <td colspan="2">Totales</td>
                            <td class="text-center"><?= $kpiCantidad ?></td>
                            <td class="text-end"><?= number_format($kpiBonificado, 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format($kpiPromedio, 2, ',', '.') ?>%</td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div>

    <?php endif; ?>

</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>
