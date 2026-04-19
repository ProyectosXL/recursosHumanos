<?php
session_start();

require_once 'config.php';

$pageTitle     = "Gestión de Referidos";
$portalContext = 'referidos';
$mainContainerClass = 'py-5';

include_once ROOT_PATH . 'templates/header.php';
?>

<div style="max-width: 860px; margin: 0 auto;">

    <div class="portal-heading">
        <h1>Portal de Referidos</h1>
        <p>Seleccioná una opción para continuar.</p>
    </div>

    <?php if (isset($_SESSION['feedback'])): ?>
    <?php
        $feedback = $_SESSION['feedback'];
        $iconos = [
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-x-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
            'primary' => 'bi-info-circle-fill',
        ];
        $icono = $iconos[$feedback['tipo']] ?? 'bi-bell-fill';
        unset($_SESSION['feedback']);
    ?>
    <div class="alert alert-<?= htmlspecialchars($feedback['tipo']) ?> alert-feedback alert-dismissible fade show mb-4" role="alert">
        <i class="bi <?= $icono ?>"></i>
        <div><?= nl2br($feedback['mensaje']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endif; ?>

    <div class="portal-grid">

        <a href="elige_crear.php?scope=directores" class="action-card action-card--ref">
            <div class="action-card__icon"><i class="bi bi-person-plus-fill"></i></div>
            <div>
                <div class="action-card__title">Crear Referido</div>
                <p class="action-card__desc">Alta en base central con descuento configurable hasta el 35%. Al crearse, se distribuye automáticamente a todas las sucursales habilitadas.</p>
            </div>
            <div class="action-card__badges">
                <span class="badge-card badge-card--ref">Envío automático</span>
                <span class="badge-card badge-card--neutral">Hasta 35% desc.</span>
            </div>
        </a>

        <a href="consulta.php?scope=directores" class="action-card action-card--ref">
            <div class="action-card__icon"><i class="bi bi-table"></i></div>
            <div>
                <div class="action-card__title">Consultar Referidos</div>
                <p class="action-card__desc">Tabla interactiva con búsqueda, filtros por estado y switch para habilitar/inhabilitar sincronizado con todas las sucursales. Resalta CUITs duplicados. Exporta a Excel.</p>
            </div>
            <div class="action-card__badges">
                <span class="badge-card badge-card--ref">Tiempo real</span>
                <span class="badge-card badge-card--neutral">Exporta Excel</span>
            </div>
        </a>

        <a href="consulta_individual.php?from=directores" class="action-card action-card--ref">
            <div class="action-card__icon"><i class="bi bi-person-vcard-fill"></i></div>
            <div>
                <div class="action-card__title">Consulta Individual por DNI</div>
                <p class="action-card__desc">Auditoría rápida: ingresá DNI, CUIT o buscá por nombre. El sistema muestra en qué sucursales está registrado ese cliente y en cuáles no.</p>
            </div>
            <div class="action-card__badges">
                <span class="badge-card badge-card--neutral">Auditoría</span>
            </div>
        </a>

        <a href="control_descuentos.php?scope=directores" class="action-card action-card--ref">
            <div class="action-card__icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="action-card__title">Control de Descuentos</div>
                <p class="action-card__desc">Reporte de bonificaciones por rango de fechas: cantidad de usos, total bonificado y promedio % por referido.</p>
            </div>
            <div class="action-card__badges">
                <span class="badge-card badge-card--neutral">Por rango de fechas</span>
            </div>
        </a>

    </div>
</div>

<!-- Modal de ayuda -->
<div class="modal fade" id="ayudaModal" tabindex="-1" aria-labelledby="ayudaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ayudaModalLabel">
                    <i class="bi bi-info-circle-fill me-2"></i>Guía de Uso — Portal de Referidos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Cada tarjeta de la pantalla principal lleva directo a la función. Acá encontrás más detalle sobre cada una.</p>

                <div class="accordion" id="ayudaAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCrear" aria-expanded="true">
                                <i class="bi bi-person-plus-fill me-2" style="color: var(--ref-accent);"></i>Crear Referido
                            </button>
                        </h2>
                        <div id="collapseCrear" class="accordion-collapse collapse show" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Da de alta un nuevo referido en la base central. El sistema sugiere el siguiente código disponible y permite asignar un descuento de hasta el 35%. <strong>Al crearse, se distribuye automáticamente a todas las sucursales habilitadas</strong>, sin necesidad de un envío manual posterior.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConsultar" aria-expanded="false">
                                <i class="bi bi-table me-2" style="color: var(--ref-accent);"></i>Consultar Referidos
                            </button>
                        </h2>
                        <div id="collapseConsultar" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Tabla interactiva con todos los referidos. Permite buscar, ordenar, paginar y filtrar por estado (Habilitado/Inhabilitado). El switch actúa en tiempo real y sincroniza el cambio en central y en todas las sucursales. CUITs duplicados se resaltan en rojo. Exporta la lista a Excel.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIndividual" aria-expanded="false">
                                <i class="bi bi-person-vcard-fill me-2" style="color: var(--ref-accent);"></i>Consulta Individual por DNI
                            </button>
                        </h2>
                        <div id="collapseIndividual" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Herramienta de auditoría: ingresá un DNI, CUIT, o buscá por nombre usando el selector. El sistema muestra en qué sucursales está registrado ese cliente y en cuáles no está presente.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDescuentos" aria-expanded="false">
                                <i class="bi bi-graph-up-arrow me-2" style="color: var(--ref-accent);"></i>Control de Descuentos
                            </button>
                        </h2>
                        <div id="collapseDescuentos" class="accordion-collapse collapse" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Reporte de bonificaciones por rango de fechas. Muestra para cada referido: código, nombre, cantidad de usos, total bonificado y promedio %.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>
