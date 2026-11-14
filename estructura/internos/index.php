<?php
/**
 * Internos (Directorio de Personal) - vista pública, sin login, como en comercial/recepcion.
 *
 * Hace lo mismo que antes (búsqueda por nombre, filtro por sector, tarjetas y lista, fotos,
 * exportar a Excel y el filtro de "Direccion" según $_SESSION['entorno']), con el menú lateral y la
 * estética de estructura. El filtro de sectores pasó de la barra lateral al contenido, arriba de
 * los resultados, porque la barra lateral ahora es el menú compartido.
 *
 * ?q=<nombre> abre con esa persona buscada: lo usa "Ver en Internos" de la Guía de Consultas.
 * El bootstrap arranca la sesión sin exigir login (hace falta para el filtro de "Direccion").
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Interno.php';

$interno = new Interno();
$sectores = $interno->getSectores();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$pagina = array(
    'titulo' => 'Internos',
    'activo' => 'internos',
    'css'    => array('internos.css'),
    'js'     => array('internos.js'),
    'libs'   => array('jquery', 'sweetalert', 'xlsx', 'filesaver'),
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado">
            <h1>Internos</h1>
            <p>El interno, el mail y el celular de cada persona de XL.</p>
        </div>

        <div class="est-panel int-filtros">
            <div class="int-filtro int-filtro--buscar">
                <label for="searchInput" class="form-label">Buscar por nombre</label>
                <div class="search-container">
                    <i class="bi bi-search search-icon" aria-hidden="true"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Nombre o apellido"
                           autocomplete="off" value="<?= $e($q) ?>">
                    <button type="button" class="clear-search" id="clearSearch" aria-label="Borrar búsqueda"
                            <?= $q === '' ? 'style="display: none;"' : '' ?>>
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="int-filtro">
                <label for="sectorSelect" class="form-label">Sector</label>
                <select id="sectorSelect" class="form-select">
                    <option value="">Todos los sectores</option>
                    <?php foreach ($sectores as $sector): ?>
                        <option value="<?= $e($sector) ?>"><?= $e($sector) ?> (<?= (int)$interno->getEmpleadosPorSector($sector) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="int-filtro int-filtro--vista">
                <span class="form-label d-block" id="vistaEtiqueta">Ver como</span>
                <div class="btn-group view-toggles" role="group" aria-labelledby="vistaEtiqueta">
                    <button type="button" class="btn btn-toggle active" data-view="cards" aria-pressed="true">
                        <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i> Tarjetas
                    </button>
                    <button type="button" class="btn btn-toggle" data-view="list" aria-pressed="false">
                        <i class="bi bi-list-ul" aria-hidden="true"></i> Lista
                    </button>
                </div>
            </div>
        </div>

        <div id="viewContainers">
            <div id="cardsContainer" class="row">
                <!-- Las tarjetas se cargan acá -->
            </div>

            <div id="listView" class="est-panel" style="display: none;">
                <div class="list-controls">
                    <div class="search-container">
                        <i class="bi bi-search search-icon" aria-hidden="true"></i>
                        <label for="tableSearchInput" class="visually-hidden">Filtrar la lista</label>
                        <input type="text" id="tableSearchInput" class="search-input" placeholder="Filtrar por nombre, sector, mail...">
                        <button type="button" class="clear-search" id="clearTableSearch" aria-label="Borrar filtro" style="display: none;">
                            <i class="bi bi-x" aria-hidden="true"></i>
                        </button>
                    </div>
                    <button type="button" class="est-btn" id="exportExcel">
                        <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Exportar a Excel
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="empleadosTable">
                        <thead>
                            <tr>
                                <th>Nombre y Apellido</th>
                                <th>Sector</th>
                                <th>Interno</th>
                                <th>Email</th>
                                <th>Celular</th>
                            </tr>
                        </thead>
                        <tbody id="listContainer">
                            <!-- Las filas se cargan acá -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php require __DIR__ . '/../partials/pie.php'; ?>
