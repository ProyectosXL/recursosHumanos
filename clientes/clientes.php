<?php
session_start();
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Local.php';

$codClient     = $_GET['cod'] ?? '';
$arrayClientes = [];
$arrayLocales  = [];
$error         = null;

if ($codClient === 'AS') {
    $pageTitle     = "Enviar Referidos a Sucursales";
    $labelClientes = "Referidos";
    $labelTipo     = "referido";
    $labelTipos    = "referidos";
    $btnEnviar     = "Enviar Referidos";
    $urlVolver     = 'index_directores.php';
    $portalContext = 'referidos';
    $accentClass   = 'ref';
} else {
    $pageTitle     = "Enviar Empleados a Sucursales";
    $labelClientes = "Empleados";
    $labelTipo     = "empleado";
    $labelTipos    = "empleados";
    $btnEnviar     = "Enviar Empleados";
    $urlVolver     = 'index.php';
    $portalContext = 'empleados';
    $accentClass   = 'emp';
}

$mainContainerClass = 'py-5';
$pageScripts        = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>';

try {
    $cliente       = new Cliente();
    $arrayClientes = $cliente->listarEmpleados($codClient);
    $local         = new Local();
    $arrayLocales  = $local->listarLocales();
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en clientes.php: " . $e->getMessage());
}

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 1100px;">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al portal
        </a>
    </div>

    <div class="portal-heading">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p>Seleccioná los <?= htmlspecialchars($labelTipos) ?> y las sucursales de destino.</p>
    </div>

    <?php if (isset($_SESSION['feedback'])): ?>
    <?php
        $fb    = $_SESSION['feedback'];
        $iconos = ['success'=>'bi-check-circle-fill','danger'=>'bi-x-circle-fill','warning'=>'bi-exclamation-triangle-fill'];
        $icono = $iconos[$fb['tipo']] ?? 'bi-bell-fill';
        unset($_SESSION['feedback']);
    ?>
    <div class="alert alert-<?= htmlspecialchars($fb['tipo']) ?> alert-feedback alert-dismissible fade show mb-4" role="alert">
        <i class="bi <?= $icono ?>"></i>
        <div><?= $fb['mensaje'] ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php else: ?>

            <form action="<?= BASE_URL ?>Controlador/procesar.php"
                  id="pedidos"
                  method="POST"
                  data-accent="<?= $accentClass ?>">

                <input type="hidden" name="cod_tipo_cliente" value="<?= htmlspecialchars($codClient) ?>">

                <div class="row g-5">

                    <!-- ── Clientes / Referidos ──────────────────── -->
                    <div class="col-lg-6">

                        <div class="list-col-header">
                            <span class="list-col-title">
                                <i class="bi bi-people-fill me-2 opacity-75" aria-hidden="true"></i>
                                1. <?= htmlspecialchars($labelClientes) ?>
                            </span>
                            <span class="counter-badge"
                                  id="clientes-counter"
                                  aria-live="polite"
                                  aria-label="Seleccionados">
                                0 seleccionados de <?= count($arrayClientes) ?>
                            </span>
                        </div>

                        <div class="mb-2">
                            <input type="text"
                                   id="clienteSearch"
                                   class="form-control"
                                   placeholder="Filtrar por nombre o código…"
                                   aria-label="Filtrar lista de <?= htmlspecialchars($labelTipos) ?>">
                        </div>

                        <div class="check-list border rounded"
                             id="clientes-list"
                             role="group"
                             aria-label="<?= htmlspecialchars($labelClientes) ?> disponibles">
                            <?php foreach ($arrayClientes as $v): ?>
                            <div class="form-check form-check-lg cliente-item">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="clientes[]"
                                       id="cli_<?= htmlspecialchars($v->Codigo ?? '') ?>"
                                       value="<?= htmlspecialchars($v->Codigo ?? '') ?>">
                                <label class="form-check-label"
                                       for="cli_<?= htmlspecialchars($v->Codigo ?? '') ?>">
                                    <span class="check-code"><?= htmlspecialchars($v->Codigo ?? '') ?></span>
                                    <?= htmlspecialchars($v->Nombre ?? '') ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($arrayClientes)): ?>
                            <p class="text-muted text-center py-4 mb-0 small">
                                No hay <?= htmlspecialchars($labelTipos) ?> disponibles.
                            </p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- ── Sucursales ────────────────────────────── -->
                    <div class="col-lg-6">

                        <div class="list-col-header">
                            <span class="list-col-title">
                                <i class="bi bi-shop me-2 opacity-75" aria-hidden="true"></i>
                                2. Sucursales
                            </span>
                            <span class="counter-badge"
                                  id="sucursales-counter"
                                  aria-live="polite"
                                  aria-label="Seleccionadas">
                                0 seleccionadas de <?= count($arrayLocales) ?>
                            </span>
                        </div>

                        <div class="region-chips region-chips--<?= $accentClass ?> mb-2"
                             id="region-chips"
                             role="group"
                             aria-label="Selección rápida por región">
                            <button type="button" class="region-chip" data-region="AMBA">AMBA</button>
                            <button type="button" class="region-chip" data-region="ROSARIO">Rosario</button>
                            <button type="button" class="region-chip" data-region="MARDEL">Mar del Plata</button>
                            <button type="button" class="region-chip" data-region="TODOS">Todas</button>
                        </div>

                        <div class="mb-2">
                            <input type="text"
                                   id="sucursalSearch"
                                   class="form-control"
                                   placeholder="Filtrar por nombre…"
                                   aria-label="Filtrar lista de sucursales">
                        </div>

                        <div class="check-list border rounded"
                             id="sucursales-list"
                             role="group"
                             aria-label="Sucursales disponibles">
                            <?php foreach ($arrayLocales as $v): ?>
                            <div class="form-check form-check-lg sucursal-item">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="dsn[]"
                                       id="suc_<?= htmlspecialchars($v->NRO_SUCURSAL) ?>"
                                       region="<?= htmlspecialchars($v->LOCALIDAD ?? '') ?>"
                                       value="<?= htmlspecialchars($v->Host . '|' . $v->DbName . '|' . $v->UsuarioDns . '|' . $v->ClaveDns) ?>">
                                <label class="form-check-label"
                                       for="suc_<?= htmlspecialchars($v->NRO_SUCURSAL) ?>">
                                    <span class="check-code"><?= htmlspecialchars($v->NRO_SUCURSAL) ?></span>
                                    <?= htmlspecialchars($v->DESC_SUCURSAL) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($arrayLocales)): ?>
                            <p class="text-muted text-center py-4 mb-0 small">
                                No hay sucursales disponibles.
                            </p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <hr class="my-4">

                <!-- Resumen dinámico -->
                <div class="resumen-callout"
                     id="resumen-ops"
                     style="display:none;"
                     role="status"
                     aria-live="polite">
                    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                    <span class="resumen-text"></span>
                </div>

                <!-- Acciones -->
                <div class="d-flex gap-2 justify-content-end align-items-start flex-wrap mt-3">
                    <a href="<?= htmlspecialchars($urlVolver) ?>"
                       class="btn btn-light btn-lg border me-auto">
                        Cancelar
                    </a>
                    <div class="text-end">
                        <button type="submit"
                                id="submitBtn"
                                class="btn btn-lg btn-crear btn-crear--<?= $accentClass ?>"
                                data-accent="<?= $accentClass ?>"
                                disabled
                                aria-describedby="btn-hint">
                            <span class="btn-spinner" id="btn-spinner" aria-hidden="true"></span>
                            <i class="bi bi-send-fill me-2" id="btn-icon" aria-hidden="true"></i>
                            <span class="btn-text"><?= htmlspecialchars($btnEnviar) ?></span>
                        </button>
                        <div class="btn-hint" id="btn-hint" aria-live="polite">
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Seleccioná al menos un <?= htmlspecialchars($labelTipo) ?> y una sucursal para continuar.
                        </div>
                    </div>
                </div>

            </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>
