<?php
/**
 * Administrar internos (ABM) - migrado desde comercial/recepcion/listado.php.
 *
 * Antes cargaba nodo/Class/bootstrap.php; el Auth de Nodo es sólo un envoltorio que carga el
 * Auth del hub, así que pasar al bootstrap de estructura (que carga el del hub) deja la misma
 * identidad y la misma regla de permisos. subsector_nombre no lo da el Auth del hub (tampoco lo
 * daba vía Nodo): esa parte de $es_recepcionista ya dependía sólo de rolNom === 'RECEPCIONISTA'.
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Interno.php';
require_once __DIR__ . '/../Class/Permisos.php';

Auth::exigir();

// La regla de siempre, ahora en Permisos::reglaAbmInternos() para que ajax.php y el menú usen
// la misma. Antes $puede_editar y $puede_eliminar se calculaban pero no se usaban: cualquier
// usuario logueado podía editar y desactivar. Ahora esconden los botones y el servidor las valida.
$abm = Permisos::abmInternos();
$puede_crear    = $abm['crear'];
$puede_editar   = $abm['editar'];
$puede_eliminar = $abm['eliminar'];

$estructuraJsExtra = array(
    'abm'  => $abm,
    'csrf' => Permisos::csrf(),
);

$pagina = array(
    'titulo' => 'Administrar internos',
    'activo' => 'internos_abm',
    'css'    => array('internos.css', 'internos_abm.css'), // internos.css: el buscador es el mismo
    'js'     => array('internos_abm.js'),
    'libs'   => array('jquery', 'sweetalert'),
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado abm-encabezado">
            <div>
                <h1>Administrar internos</h1>
                <p>Alta, edición y baja de las personas que se ven en Internos.</p>
            </div>
            <?php if ($puede_crear): ?>
            <button type="button" class="est-btn est-btn--primario" onclick="nuevoRegistro()">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Nuevo registro
            </button>
            <?php endif; ?>
        </div>

        <div class="est-panel">
            <div class="abm-filtros">
                <div class="search-container">
                    <i class="bi bi-search search-icon" aria-hidden="true"></i>
                    <label for="searchInput" class="visually-hidden">Buscar por nombre</label>
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre" autocomplete="off">
                </div>
                <!-- Antes el ABM sólo listaba activos: una persona desactivada no se podía reactivar -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="verInactivos">
                    <label class="form-check-label" for="verInactivos">Ver también las personas desactivadas</label>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Interno</th>
                            <th scope="col">Nombre y Apellido</th>
                            <th scope="col">Sector</th>
                            <th scope="col">Mail</th>
                            <th scope="col">Celular</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Los datos se cargan acá -->
                    </tbody>
                </table>
            </div>

            <div id="loadingState" class="text-center py-4 d-none">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>

            <div id="emptyState" class="text-center py-4 d-none">
                <p class="est-pendiente mb-0">No se encontraron resultados</p>
            </div>
        </div>

        <!-- Modal Nuevo/Editar Empleado -->
        <div class="modal fade" id="empleadoModal" tabindex="-1" aria-labelledby="empleadoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="empleadoModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i>
                        <span id="modalTitle">Nuevo Empleado</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="empleadoForm" class="needs-validation" novalidate>
                        <input type="hidden" id="modoEdicion" name="modoEdicion" value="0">
                        <input type="hidden" id="nombreApellidoOriginal" name="nombreApellidoOriginal" value="">
                        
                        <!-- Interno -->
                        <div class="mb-3">
                            <label for="interno" class="form-label">Interno *</label>
                            <input type="text" 
                                class="form-control" 
                                id="interno" 
                                name="interno"
                                required
                                pattern="[0-9]*">
                            <div class="invalid-feedback">
                                Por favor ingrese un número de interno válido
                            </div>
                        </div>

                        <!-- Nombre y Apellido -->
                        <div class="mb-3">
                            <label for="nombreApellido" class="form-label">Nombre y Apellido *</label>
                            <input type="text" 
                                class="form-control" 
                                id="nombreApellido" 
                                name="nombre_apellido" 
                                required>
                            <div class="invalid-feedback">
                                Por favor ingrese el nombre y apellido
                            </div>
                        </div>

                        <!-- Sector -->
                        <div class="mb-3">
                            <label for="sector" class="form-label">Sector *</label>
                            <select class="form-select" id="sector" name="sector" required>
                                <option value="">Seleccionar...</option>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione un sector
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                class="form-control" 
                                id="email" 
                                name="mail">
                            <div class="invalid-feedback">
                                Por favor ingrese un email válido
                            </div>
                        </div>

                        <!-- Celular -->
                        <div class="mb-3">
                            <label for="celular" class="form-label">Celular</label>
                            <input type="text" 
                                class="form-control" 
                                id="celular" 
                                name="celular">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveEmpleado">Guardar</button>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../partials/pie.php'; ?>
