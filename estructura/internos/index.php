<?php
/**
 * Internos (Directorio de Personal) - vista pública, sin login, como en comercial/recepcion.
 * El bootstrap de estructura arranca la sesión (antes era session_start()) sin exigir login:
 * hace falta para el filtro de "Direccion" según $_SESSION['entorno'].
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Interno.php';
$interno = new Interno();
$sectores = $interno->getSectores();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Personal</title>
    <link rel="icon" type="image/jpg" href="<?php echo ESTRUCTURA_URL_ICONO; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo ESTRUCTURA_URL_ASSETS; ?>/internos.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 col-xl-2 sidebar">
                <h4 class="mb-4">Filtros</h4>
                
                <!-- Búsqueda -->
                <div class="search-container">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" 
                        id="searchInput" 
                        class="search-input" 
                        placeholder="Buscar por nombre...">
                    <button type="button" 
                            class="clear-search" 
                            id="clearSearch" 
                            style="display: none;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>

                <!-- Selector de Sector -->
                <div class="sector-selector">
                    <button class="sector-button" type="button" data-bs-toggle="collapse" data-bs-target="#sectorCollapse" aria-expanded="false" aria-controls="sectorCollapse">
                        <span>
                            <i class="bi bi-building me-2"></i>
                            Sectores
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    
                    <div class="collapse sector-dropdown" id="sectorCollapse">
                        <div class="list-group">
                            <button class="list-group-item active" data-sector="">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Todos los sectores</span>
                                    <span class="sector-count"><?php echo count($sectores); ?></span>
                                </div>
                            </button>
                            <?php foreach($sectores as $sector): ?>
                                <button class="list-group-item" data-sector="<?php echo htmlspecialchars($sector); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($sector); ?></span>
                                        <span class="sector-count">
                                            <?php 
                                            // Si quieres mostrar la cantidad de empleados por sector,
                                            // necesitarías agregar esta función en tu clase Interno
                                            echo $interno->getEmpleadosPorSector($sector); 
                                            ?>
                                        </span>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9 col-xl-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="main-title mb-0">
                        <i class="bi bi-people-fill"></i>
                        Internos Extra Large
                    </h1>
                    <div class="view-toggles">
                        <div class="btn-group" role="group" aria-label="Vista">
                            <button type="button" class="btn btn-toggle active" data-view="cards">
                                <i class="bi bi-grid-3x3-gap-fill"></i> Tarjetas
                            </button>
                            <button type="button" class="btn btn-toggle" data-view="list">
                                <i class="bi bi-list-ul"></i> Lista
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenedores de vistas -->
                <div id="viewContainers">
                    <!-- Vista de Cards -->
                    <div id="cardsContainer" class="row">
                        <!-- Las cards se cargarán aquí -->
                    </div>

                    <!-- Vista de Lista -->
                    <div id="listView" class="table-responsive" style="display: none;">
                        <!-- Controles de lista -->
                        <div class="list-controls mb-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="search-container">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" 
                                            id="tableSearchInput" 
                                            class="search-input" 
                                            placeholder="Buscar por nombre, sector, mail...">
                                        <button type="button" 
                                                class="clear-search" 
                                                id="clearTableSearch" 
                                                style="display: none;">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-success" id="exportExcel">
                                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                        Exportar a Excel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <table class="table table-hover align-middle" id="empleadosTable">
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
                                <!-- Las filas se cargarán aquí -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <?php require __DIR__ . '/../partials/config_js.php'; ?>
    <script src="<?php echo ESTRUCTURA_URL_ASSETS; ?>/internos.js"></script>

</body>
</html>