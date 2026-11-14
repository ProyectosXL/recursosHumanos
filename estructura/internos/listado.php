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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Personal</title>
    <link rel="icon" type="image/jpg" href="<?php echo ESTRUCTURA_URL_ICONO; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css" rel="stylesheet">
    <link href="<?php echo ESTRUCTURA_URL_ASSETS; ?>/internos_abm.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Header Section -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="card-title mb-0">
                                <i class="bi bi-people-fill me-2"></i>
                                Listado de Personal
                            </h1>
                            <?php if ($puede_crear): ?>
                            <button class="btn btn-primary" onclick="nuevoRegistro()">
                                <i class="bi bi-plus-lg me-2"></i>
                                Nuevo Registro
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Search Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="search-container">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" 
                                        id="searchInput" 
                                        class="search-input" 
                                        placeholder="Buscar por nombre, sector, mail..."
                                        autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center mt-2 mt-md-0">
                                <!-- Antes el ABM sólo listaba activos: una persona desactivada no se podía reactivar -->
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="verInactivos">
                                    <label class="form-check-label" for="verInactivos">Ver también las personas desactivadas</label>
                                </div>
                            </div>
                        </div>

                        <!-- Table Section -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Interno</th>
                                    <th>Nombre y Apellido</th>
                                    <th>Sector</th>
                                    <th>Mail</th>
                                    <th>Celular</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-end">Editar</th>
                                </tr>
                            </thead>
                                <tbody id="tableBody">
                                    <!-- Los datos se cargarán dinámicamente -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Loading State -->
                        <div id="loadingState" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="emptyState" class="text-center py-4 d-none">
                            <i class="bi bi-inbox-fill text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">No se encontraron resultados</p>
                        </div>
                    </div>
                </div>
            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php require __DIR__ . '/../partials/config_js.php'; ?>
    <script src="<?php echo ESTRUCTURA_URL_ASSETS; ?>/internos_abm.js"></script>
</body>
</html>