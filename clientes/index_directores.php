<?php
session_start();

require_once 'config.php';
$pageTitle = "Gestión de Referidos";

$mainContainerClass = 'd-flex align-items-center justify-content-center';

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="card shadow-sm mx-auto" style="max-width: 600px; position: relative;">
    <div class="card-body text-center p-5">

        <button type="button" class="btn btn-sm btn-outline-secondary" style="position: absolute; top: 1rem; right: 1rem;" data-bs-toggle="modal" data-bs-target="#ayudaModal">
            <i class="bi bi-question-circle-fill"></i> Ayuda
        </button>

        <h1 class="card-title h2 mb-3">Portal de Gestión de Referidos</h1>
        <p class="card-text text-muted">Seleccione una de las siguientes opciones para continuar.</p>

        <?php
        if (isset($_SESSION['feedback'])) {
            $feedback = $_SESSION['feedback'];
            echo '<div class="alert alert-' . htmlspecialchars($feedback['tipo']) . ' alert-dismissible fade show text-start mt-4" role="alert">';
            // Usamos nl2br para interpretar los saltos de línea <br> en el mensaje de feedback
            echo nl2br($feedback['mensaje']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['feedback']);
        }
        ?>
        
        <div class="d-grid gap-3 mt-4">
            <a href="elige_crear.php?scope=directores" class="btn btn-primary btn-lg">Crear Referido</a>
            <!-- ===== ELIMINADO: El botón "Enviar Referido" ya no es necesario =====
            <a href="elige.php?scope=directores" class="btn btn-secondary btn-lg">Enviar Referido</a>
            -->
            <a href="consulta.php?scope=directores" class="btn btn-success btn-lg">Consultar Referidos</a>
            <a href="consulta_individual.php?from=directores" class="btn btn-warning btn-lg">Consulta Individual</a>
            <a href="control_descuentos.php?scope=directores" class="btn btn-danger btn-lg">Control de Descuentos</a>
        </div>
    </div>
</div>

<!-- ===== INICIO: MODAL DE AYUDA ACTUALIZADO ===== -->
<div class="modal fade" id="ayudaModal" tabindex="-1" aria-labelledby="ayudaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ayudaModalLabel"><i class="bi bi-info-circle-fill me-2"></i> Guía de Uso - Portal de Referidos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Esta plataforma permite gestionar el ciclo de vida de los clientes de tipo **Referido** en el sistema central y en las sucursales.</p>
                
                <div class="accordion mt-3" id="ayudaAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingCrear">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCrear" aria-expanded="true" aria-controls="collapseCrear">
                                <i class="bi bi-person-plus-fill me-2"></i> Crear Referido
                            </button>
                        </h2>
                        <div id="collapseCrear" class="accordion-collapse collapse show" aria-labelledby="headingCrear" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                <!-- ===== TEXTO ACTUALIZADO ===== -->
                                Da de alta un nuevo Referido en el sistema central. <strong>Una vez creado, se distribuye automáticamente a todas las sucursales habilitadas.</strong> El sistema sugiere el siguiente código disponible y permite asignar un descuento de hasta el 35%.
                            </div>
                        </div>
                    </div>

                    <!-- ===== ELIMINADO: La sección de ayuda para "Enviar Referido" ya no es necesaria ===== -->

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingConsultar">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConsultar" aria-expanded="false" aria-controls="collapseConsultar">
                                <i class="bi bi-table me-2"></i> Consultar Referidos
                            </button>
                        </h2>
                        <div id="collapseConsultar" class="accordion-collapse collapse" aria-labelledby="headingConsultar" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Muestra una tabla interactiva con todos los Referidos. Permite buscar, filtrar por estado (Habilitado/Inhabilitado), y cambiar dicho estado al instante con el interruptor, cambiando el estado tanto en central como en sucursales. También puedes exportar la lista a Excel.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingIndividual">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIndividual" aria-expanded="false" aria-controls="collapseIndividual">
                                <i class="bi bi-person-vcard-fill me-2"></i> Consulta Individual
                            </button>
                        </h2>
                        <div id="collapseIndividual" class="accordion-collapse collapse" aria-labelledby="headingIndividual" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Herramienta de auditoría rápida. Ingresa un DNI/CUIT o el nombre y el sistema te mostrará en qué sucursales se encuentra registrado ese cliente y en cuáles no.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDescuentos">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDescuentos" aria-expanded="false" aria-controls="collapseDescuentos">
                                <i class="bi bi-graph-up-arrow me-2"></i> Control de Descuentos
                            </button>
                        </h2>
                        <div id="collapseDescuentos" class="accordion-collapse collapse" aria-labelledby="headingDescuentos" data-bs-parent="#ayudaAccordion">
                            <div class="accordion-body">
                                Genera un reporte que muestra cuántas veces ha recibido una bonificación cada Referido dentro de un rango de fechas. Puedes filtrar por fecha para analizar períodos específicos.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
<!-- ===== FIN: MODAL DE AYUDA ===== -->

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>