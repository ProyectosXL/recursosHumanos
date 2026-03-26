
<?php
// Iniciar sesión para gestión de usuarios (si se implementa)
session_start();

// Incluir la clase Politica
require_once 'Class/Politica.php';

// Crear instancia de la clase
$politicaObj = new Politica();

// Obtener datos para la página principal
$sectores = $politicaObj->obtenerSectores();

// Obtener estadísticas
$estadisticas = $politicaObj->obtenerEstadisticas();

// Filtros para documentos recientes
$filtros = [];
if (isset($_GET['sector']) && !empty($_GET['sector'])) {
    $filtros['sector_id'] = $_GET['sector'];
}
if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    $filtros['tipo'] = $_GET['tipo'];
}
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $filtros['busqueda'] = $_GET['busqueda'];
}

// Obtener documentos recientes
$documentos_recientes = $politicaObj->obtenerTodos($filtros);

// Obtener términos del glosario
$glosario = $politicaObj->obtenerGlosario();

// ── Color único para todos los ítems de sector ──
$sectorColorMap = [];
foreach ($sectores as $i => $s) {
    $sectorColorMap[$s['id']] = '#5b8db8';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Políticas y Procedimientos</title>
    <link rel="icon" type="image/jpg" href="../../image/icono.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/chatbot.css">
    <style>
        /* Estilos para el modal de tags automáticos */
        #autoTagsModal .modal-content {
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        .close-modal {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s;
        }
        .close-modal:hover {
            color: #000;
        }
        .btn-primary, .btn-secondary {
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
    </style>
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script>
        // Configurar el worker de PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
    </script>
    
    <!-- Auto-inicio del servicio RAG y auto-indexación -->
    <script>
        // Variable global para controlar el estado de inicialización
        window.ragInitializing = true;
        window.ragInitDetails = [];
        
        // Función para finalizar inicialización
        function finalizarInicializacionRAG() {
            console.log('🏁 Finalizando inicialización RAG');
            window.ragInitializing = false;
            window.ragInitDetails = [];
            
            // Forzar actualización del estado del chatbot
            setTimeout(() => {
                if (typeof verificarServicioRAG === 'function') {
                    verificarServicioRAG();
                }
            }, 100);
        }
        
        // TIMEOUT ABSOLUTO DE SEGURIDAD: 10 segundos máximo
        setTimeout(() => {
            if (window.ragInitializing) {
                console.warn('⏱️ TIMEOUT FORZADO: Finalizando inicialización después de 10 segundos');
                finalizarInicializacionRAG();
            }
        }, 10000);
        
        // Ejecutar auto-inicio en segundo plano cuando cargue la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Iniciando sistema RAG automáticamente...');
            
            // Usar un timeout muy corto para no bloquear la UI
            const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Timeout')), 3000)
            );
            
            const fetchPromise = fetch('Controller/autostart_rag.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            }).then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
            
            Promise.race([fetchPromise, timeoutPromise])
                .then(data => {
                    window.ragInitDetails = data.detalles || [];
                    console.log('✅ Sistema RAG:', data.mensaje);
                    
                    if (data.success) {
                        // Si hay indexación, solo mostrar en consola pero no bloquear
                        if (data.documentos_indexados) {
                            console.log('📚 Indexando documentos en segundo plano (no bloqueante)...');
                        }
                    }
                    
                    // Finalizar inmediatamente para no bloquear la UI
                    setTimeout(() => finalizarInicializacionRAG(), 2000);
                })
                .catch(error => {
                    console.warn('⚠️ No se pudo conectar con autostart:', error.message);
                    // Finalizar inmediatamente en caso de error
                    finalizarInicializacionRAG();
                });
        });
    </script>
    
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <h1>DocuGest</h1>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#" class="active" onclick="showTab('overview')">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('sectors')">
                        <i class="fas fa-building"></i>
                        <span>Sectores</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('policies')">
                        <i class="fas fa-file-alt"></i>
                        <span>Políticas</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('procedures')">
                        <i class="fas fa-tasks"></i>
                        <span>Procedimientos</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showGlossary()">
                        <i class="fas fa-book"></i>
                        <span>Glosario</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('statistics')">
                        <i class="fas fa-chart-pie"></i>
                        <span>Estadísticas</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="toggleChatbot()" id="chatbotMenuBtn">
                        <i class="fas fa-headset"></i>
                        <span>Asistente IA</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('upload')">
                        <i class="fas fa-upload"></i>
                        <span>Subir Documento</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showTab('settings')">
                        <i class="fas fa-cog"></i>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <main class="main-content">
            <header class="header">
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Buscar políticas, procedimientos o términos...">
                </div>
                

            </header>

            <?php
            // Banner de filtro activo
            $filtroSectorId = isset($_GET['sector']) && !empty($_GET['sector']) ? (int)$_GET['sector'] : null;
            $filtroTipo     = isset($_GET['tipo'])   && !empty($_GET['tipo'])   ? $_GET['tipo']         : null;
            $filtroSectorNombre = '';
            if ($filtroSectorId) {
                foreach ($sectores as $s) {
                    if ($s['id'] == $filtroSectorId) { $filtroSectorNombre = $s['nombre']; break; }
                }
            }
            if ($filtroSectorId || $filtroTipo):
                $partes = [];
                if ($filtroSectorNombre) $partes[] = 'Sector: <strong>' . htmlspecialchars($filtroSectorNombre) . '</strong>';
                if ($filtroTipo)         $partes[] = 'Tipo: <strong>' . ($filtroTipo == 'politica' ? 'Pol&iacute;ticas' : 'Procedimientos') . '</strong>';
            ?>
            <div class="filter-banner">
                <span class="filter-banner-label"><i class="fas fa-filter"></i> Filtrando por: <?php echo implode(' &mdash; ', $partes); ?></span>
                <a href="index.php" class="filter-banner-clear"><i class="fas fa-times-circle"></i> Limpiar filtro</a>
            </div>
            <?php endif; ?>

            <div class="section-header">
                <h2>Panel de Control</h2>
                <p>Bienvenido al sistema de gestión de políticas y procedimientos</p>
            </div>
            
            <!-- Estadísticas generales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon politica">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo isset($estadisticas['total_politicas']) ? $estadisticas['total_politicas'] : 0; ?></h3>
                        <p>Políticas Totales</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="icon procedimiento">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo isset($estadisticas['total_procedimientos']) ? $estadisticas['total_procedimientos'] : 0; ?></h3>
                        <p>Procedimientos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="icon sectores">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($sectores); ?></h3>
                        <p>Sectores</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="icon glosario">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($glosario); ?></h3>
                        <p>Términos en Glosario</p>
                    </div>
                </div>
            </div>
            
            <!-- Tabs de navegación -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('overview')">Vista General</div>
                <div class="tab" onclick="showTab('sectors')">Sectores</div>
                <div class="tab" onclick="showTab('policies')">Políticas</div>
                <div class="tab" onclick="showTab('procedures')">Procedimientos</div>
            </div>
            
            <!-- Contenido principal - Vista general -->
            <div id="overview-content" class="tab-content">
                <div class="content-grid">
                    <div class="left-column">
                        <div class="card">
                            <div class="card-header">
                                <h3>Documentos Recientes</h3>
                                <a href="#" class="see-all" onclick="showTab('all-documents')">Ver todos <i class="fas fa-chevron-right"></i></a>
                            </div>
                            
                            <ul class="policy-list">
                                <?php if (empty($documentos_recientes)): ?>
                                <li class="no-documents">
                                    <p>No hay documentos disponibles.</p>
                                </li>
                                <?php else: ?>
                                    <?php foreach (array_slice($documentos_recientes, 0, 5) as $doc): ?>
                                    <li class="policy-item">
                                        <div class="policy-info">
                                            <div class="policy-icon">
                                                <i class="fas fa-file-pdf"></i>
                                            </div>
                                            <div class="policy-details">
                                                <h4><?php echo htmlspecialchars($doc['titulo']); ?></h4>
                                                <p>Sector <?php echo htmlspecialchars($doc['sector_nombre']); ?> - 
                                                Actualizado: <?php echo isset($doc['fecha_actualizacion']) ? date('d/m/Y', is_string($doc['fecha_actualizacion']) ? strtotime($doc['fecha_actualizacion']) : strtotime($doc['fecha_actualizacion']->format('Y-m-d'))) : 'Fecha no disponible'; ?></p>

                                            </div>
                                        </div>
                                        <div class="policy-actions">
                                            <button class="action-btn view" onclick="viewDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn download" onclick="downloadDocument(<?php echo $doc['id']; ?>)">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="card">
                            <div class="card-header">
                                <h3>Términos Específicos</h3>
                                <a href="#" class="see-all" onclick="showGlossary()">Ver glosario <i class="fas fa-chevron-right"></i></a>
                            </div>
                            
                            <ul class="glossary-list">
                                <?php if (empty($glosario)): ?>
                                <li class="no-glossary">
                                    <p>No hay términos en el glosario.</p>
                                </li>
                                <?php else: ?>
                                    <?php foreach (array_slice($glosario, 0, 4) as $term): ?>
                                    <li class="glossary-item">
                                        <h4><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($term['termino']); ?></h4>
                                        <p><?php echo htmlspecialchars($term['definicion']); ?></p>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vista de Sectores -->
            <div id="sectors-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Sectores</h2>
                    <p>Navegue por los diferentes sectores de la organización</p>
                </div>
                
                <div class="sectors-grid">
                    <?php if (empty($sectores)): ?>
                    <div class="no-sectors">
                        <p>No hay sectores disponibles.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($sectores as $sector): ?>
                        <?php $sc = $sectorColorMap[$sector['id']] ?? '#3498db'; ?>
                        <div class="sector-card" onclick="filterBySection(<?php echo $sector['id']; ?>)">
                            <div class="icon" style="background:<?php echo $sc; ?>1a; color:<?php echo $sc; ?>">
                                <i class="fas <?php echo htmlspecialchars($sector['icono'] ? $sector['icono'] : 'fa-folder'); ?>"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($sector['nombre']); ?></h3>
                            <p><?php echo htmlspecialchars($sector['descripcion']); ?></p>
                            
                            <?php
                            // Contar documentos por sector
                            $count = 0;
                            foreach ($documentos_recientes as $doc) {
                                if ($doc['sector_id'] == $sector['id']) {
                                    $count++;
                                }
                            }
                            ?>
                            <span class="count"><?php echo $count; ?> documentos</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Vista de Políticas -->
            <div id="policies-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Políticas</h2>
                    <p>Todas las políticas de la organización</p>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Políticas Corporativas</h3>
                        <div class="filter-options">
                            <select class="filter-select" id="sectorFilterPolicies" onchange="filterPolicies()">
                                <option value="">Todos los sectores</option>
                                <?php foreach ($sectores as $sector): ?>
                                <option value="<?php echo $sector['id']; ?>"><?php echo htmlspecialchars($sector['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="upload-btn" onclick="showTab('upload')">
                                <i class="fas fa-upload"></i> Subir Política
                            </button>
                        </div>
                    </div>
                    
                    <ul class="policy-list" id="policiesList">
                        <?php
                        $policies = array_filter($documentos_recientes, function($doc) {
                            return $doc['tipo'] == 'politica';
                        });
                        
                        if (empty($policies)): ?>
                        <li class="no-documents">
                            <p>No hay políticas disponibles.</p>
                        </li>
                        <?php else: ?>
                            <?php foreach ($policies as $policy): ?>
                            <li class="policy-item">
                                <div class="policy-info">
                                    <div class="policy-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="policy-details">
                                        <h4><?php echo htmlspecialchars($policy['titulo']); ?></h4>
                                        <p>Sector <?php echo htmlspecialchars($policy['sector_nombre']); ?> - 
                                           Actualizado: <?php echo date('d/m/Y', strtotime($policy['fecha_actualizacion'])); ?></p>
                                    </div>
                                </div>
                                <div class="policy-actions">
                                    <button class="action-btn view" onclick="viewDocument(<?php echo $policy['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn download" onclick="downloadDocument(<?php echo $policy['id']; ?>)">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Vista de Procedimientos -->
            <div id="procedures-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Procedimientos</h2>
                    <p>Todos los procedimientos documentados</p>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Procedimientos Operativos</h3>
                        <div class="filter-options">
                            <select class="filter-select" id="sectorFilterProcedures" onchange="filterProcedures()">
                                <option value="">Todos los sectores</option>
                                <?php foreach ($sectores as $sector): ?>
                                <option value="<?php echo $sector['id']; ?>"><?php echo htmlspecialchars($sector['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="upload-btn" onclick="showTab('upload')">
                                <i class="fas fa-upload"></i> Subir Procedimiento
                            </button>
                        </div>
                    </div>
                    
                    <ul class="policy-list" id="proceduresList">
                        <?php
                        $procedures = array_filter($documentos_recientes, function($doc) {
                            return $doc['tipo'] == 'procedimiento';
                        });
                        
                        if (empty($procedures)): ?>
                        <li class="no-documents">
                            <p>No hay procedimientos disponibles.</p>
                        </li>
                        <?php else: ?>
                            <?php foreach ($procedures as $procedure): ?>
                            <li class="policy-item">
                                <div class="policy-info">
                                    <div class="policy-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="policy-details">
                                        <h4><?php echo htmlspecialchars($procedure['titulo']); ?></h4>
                                        <p>Sector <?php echo htmlspecialchars($procedure['sector_nombre']); ?> - 
                                        Actualizado: <?php 
                                                        echo isset($procedure['fecha_actualizacion']) 
                                                            ? date('d/m/Y', is_string($procedure['fecha_actualizacion']) 
                                                                ? strtotime($procedure['fecha_actualizacion']) 
                                                                : strtotime($procedure['fecha_actualizacion']->format('Y-m-d'))) 
                                                            : 'Fecha no disponible'; 
                                                        ?>
                                                        </p>
                                    </div>
                                </div>
                                <div class="policy-actions">
                                    <button class="action-btn view" onclick="viewDocument(<?php echo $procedure['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn download" onclick="downloadDocument(<?php echo $procedure['id']; ?>)">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Vista de Estadísticas -->
            <div id="statistics-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Estadísticas</h2>
                    <p>An&aacute;lisis de uso y actividad del sistema</p>
                </div>

                <?php
                $total_docs = count($documentos_recientes);
                $total_pol = count(array_filter($documentos_recientes, fn($d) => $d['tipo'] == 'politica'));
                $total_proc = $total_docs - $total_pol;
                $max_vistas = 1;
                $max_descargas = 1;
                if (!empty($estadisticas['mas_vistos'])) {
                    foreach ($estadisticas['mas_vistos'] as $d) $max_vistas = max($max_vistas, $d['vistas']);
                }
                if (!empty($estadisticas['mas_descargados'])) {
                    foreach ($estadisticas['mas_descargados'] as $d) $max_descargas = max($max_descargas, $d['descargas']);
                }
                ?>

                <!-- Tarjetas resumen -->
                <div class="stats-summary-grid">
                    <div class="stats-summary-card blue">
                        <div class="ssc-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="ssc-body">
                            <span class="ssc-number"><?php echo $total_docs; ?></span>
                            <span class="ssc-label">Documentos totales</span>
                        </div>
                    </div>
                    <div class="stats-summary-card politica">
                        <div class="ssc-icon"><i class="fas fa-file-contract"></i></div>
                        <div class="ssc-body">
                            <span class="ssc-number"><?php echo $total_pol; ?></span>
                            <span class="ssc-label">Pol&iacute;ticas</span>
                        </div>
                    </div>
                    <div class="stats-summary-card procedimiento">
                        <div class="ssc-icon"><i class="fas fa-tasks"></i></div>
                        <div class="ssc-body">
                            <span class="ssc-number"><?php echo $total_proc; ?></span>
                            <span class="ssc-label">Procedimientos</span>
                        </div>
                    </div>
                    <div class="stats-summary-card orange">
                        <div class="ssc-icon"><i class="fas fa-building"></i></div>
                        <div class="ssc-body">
                            <span class="ssc-number"><?php echo count($sectores); ?></span>
                            <span class="ssc-label">Sectores activos</span>
                        </div>
                    </div>
                </div>

                <!-- Ranking de documentos -->
                <div class="stats-rankings">
                    <div class="card stats-rank-card">
                        <div class="card-header">
                            <h3><i class="fas fa-eye" style="color:#3498db;margin-right:8px;"></i>M&aacute;s vistos</h3>
                        </div>
                        <?php if (isset($estadisticas['mas_vistos']) && !empty($estadisticas['mas_vistos'])): ?>
                        <ul class="stats-rank-list">
                            <?php foreach ($estadisticas['mas_vistos'] as $i => $doc): ?>
                            <li class="stats-rank-item">
                                <span class="rank-pos rank-pos-<?php echo $i+1; ?>"><?php echo $i+1; ?></span>
                                <div class="rank-info">
                                    <span class="rank-title"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                    <div class="rank-bar-wrap">
                                        <div class="rank-bar blue-bar" style="width:<?php echo round(($doc['vistas']/$max_vistas)*100); ?>%"></div>
                                    </div>
                                </div>
                                <span class="rank-badge blue-badge"><?php echo $doc['vistas']; ?> <small>vistas</small></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="stats-empty"><i class="fas fa-chart-bar"></i><p>Sin datos disponibles</p></div>
                        <?php endif; ?>
                    </div>

                    <div class="card stats-rank-card">
                        <div class="card-header">
                            <h3><i class="fas fa-download" style="color:#2ecc71;margin-right:8px;"></i>M&aacute;s descargados</h3>
                        </div>
                        <?php if (isset($estadisticas['mas_descargados']) && !empty($estadisticas['mas_descargados'])): ?>
                        <ul class="stats-rank-list">
                            <?php foreach ($estadisticas['mas_descargados'] as $i => $doc): ?>
                            <li class="stats-rank-item">
                                <span class="rank-pos rank-pos-<?php echo $i+1; ?>"><?php echo $i+1; ?></span>
                                <div class="rank-info">
                                    <span class="rank-title"><?php echo htmlspecialchars($doc['titulo']); ?></span>
                                    <div class="rank-bar-wrap">
                                        <div class="rank-bar green-bar" style="width:<?php echo round(($doc['descargas']/$max_descargas)*100); ?>%"></div>
                                    </div>
                                </div>
                                <span class="rank-badge green-badge"><?php echo $doc['descargas']; ?> <small>descargas</small></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="stats-empty"><i class="fas fa-chart-bar"></i><p>Sin datos disponibles</p></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Distribución por sector -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie" style="color:#9b59b6;margin-right:8px;"></i>Distribuci&oacute;n por sector</h3>
                    </div>
                    <?php if (!empty($sectores)): ?>
                    <div class="stats-sectors-grid">
                        <?php foreach ($sectores as $sector):
                            $cnt = count(array_filter($documentos_recientes, fn($d) => $d['sector_id'] == $sector['id']));
                            $pct = $total_docs > 0 ? round(($cnt / $total_docs) * 100) : 0;
                            $sc2 = $sectorColorMap[$sector['id']] ?? '#3498db';
                        ?>
                        <div class="stats-sector-row">
                            <div class="ssr-label">
                                <i class="fas <?php echo htmlspecialchars($sector['icono'] ?: 'fa-folder'); ?>" style="color:<?php echo $sc2; ?>"></i>
                                <span><?php echo htmlspecialchars($sector['nombre']); ?></span>
                            </div>
                            <div class="ssr-bar-wrap">
                                <div class="ssr-bar" style="width:<?php echo $pct; ?>%; background:<?php echo $sc2; ?>"></div>
                            </div>
                            <span class="ssr-count"><?php echo $cnt; ?> doc<?php echo $cnt != 1 ? 's' : ''; ?></span>
                            <span class="ssr-pct"><?php echo $pct; ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="stats-empty"><i class="fas fa-chart-pie"></i><p>Sin datos disponibles</p></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Vista de Carga de Documentos -->
            <div id="upload-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Subir Documento</h2>
                    <p>Añadir nuevo documento al sistema de políticas y procedimientos</p>
                </div>
                
                <div class="upload-container">
                    <!-- Información sobre el proceso -->
                    <div class="upload-info">
                        <h4><i class="fas fa-info-circle"></i> Información sobre la carga de documentos</h4>
                        <ul>
                            <li>Sólo se permiten archivos en formato PDF.</li>
                            <li>El tamaño máximo permitido es de 10 MB.</li>
                            <li>Procure usar nombres descriptivos para facilitar la búsqueda.</li>
                            <li>Las etiquetas ayudan a categorizar y encontrar más fácilmente el documento.</li>
                        </ul>
                    </div>
                    
                    <!-- Formulario de carga -->
                    <div class="upload-form">
                        <form id="uploadForm" action="Controller/procesar_documento.php" method="post" enctype="multipart/form-data" onsubmit="handleUploadSubmit(event)">
                            <div class="form-group">
                                <label for="docTitle">Título del documento <span class="required">*</span></label>
                                <input type="text" id="docTitle" name="titulo" required placeholder="Ingrese un título descriptivo">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="docType">Tipo de documento <span class="required">*</span></label>
                                    <select id="docType" name="tipo" required>
                                        <option value="" disabled selected>Seleccione un tipo</option>
                                        <option value="politica">Política</option>
                                        <option value="procedimiento">Procedimiento</option>
                                    </select>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="docSector">Sector <span class="required">*</span></label>
                                    <select id="docSector" name="sector_id" required>
                                        <option value="" disabled selected>Seleccione un sector</option>
                                        <?php foreach ($sectores as $sector): ?>
                                        <option value="<?php echo $sector['id']; ?>"><?php echo htmlspecialchars($sector['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="docDescription">Descripción</label>
                                <textarea id="docDescription" name="descripcion" rows="4" placeholder="Describa brevemente el contenido del documento"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="docTags">Etiquetas (separadas por comas)</label>
                                <input type="text" id="docTags" name="tags" placeholder="ej. seguridad, finanzas, auditoría">
                                
                                <div class="tags-container" id="tagsContainer">
                                    <!-- Las etiquetas se mostrarán aquí dinámicamente -->
                                </div>
                                
                                <div class="tag-suggestions">
                                    <span onclick="addTag('importante')">importante</span>
                                    <span onclick="addTag('nuevo')">nuevo</span>
                                    <span onclick="addTag('urgente')">urgente</span>
                                    <span onclick="addTag('actualizado')">actualizado</span>
                                    <span onclick="addTag('confidencial')">confidencial</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Archivo PDF <span class="required">*</span></label>
                                <div class="file-upload-container">
                                    <div class="file-upload-button" id="fileUploadBtn">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div class="main-text">Arrastre y suelte su archivo aquí</div>
                                        <p>o haga clic para seleccionar un archivo</p>
                                        <p class="file-types">Solo archivos PDF (máx. 10 MB)</p>
                                    </div>
                                    <input type="file" id="docFile" name="archivo" accept=".pdf" required class="file-upload-input">
                                    
                                    <div class="file-info" id="fileInfo">
                                        <i class="fas fa-file-pdf"></i>
                                        <span class="file-name" id="fileName">nombre_del_archivo.pdf</span>
                                        <span class="file-size" id="fileSize">0 KB</span>
                                        <i class="fas fa-times remove-file" onclick="removeFile()"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn secondary" onclick="showTab('overview')">Cancelar</button>
                                <button type="submit" class="btn primary">
                                    <i class="fas fa-upload"></i> Subir Documento
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Vista de Configuración -->
            <div id="settings-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Configuraci&oacute;n</h2>
                    <p>Administrar sectores y glosario del sistema</p>
                </div>

                <div class="settings-layout">

                    <!-- Sectores -->
                    <div class="card cfg-card">
                        <div class="card-header cfg-card-header">
                            <div class="cfg-card-title">
                                <div class="cfg-card-icon orange">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div>
                                    <h3>Sectores</h3>
                                    <p class="cfg-card-subtitle">Gestionar los sectores de la organizaci&oacute;n</p>
                                </div>
                            </div>
                            <button class="btn primary btn-sm" id="newSectorBtn">
                                <i class="fas fa-plus"></i> Nuevo
                            </button>
                        </div>

                        <div id="newSectorForm" style="display: none;" class="cfg-inline-form">
                            <form id="sectorForm" action="procesar_sector.php" method="post">
                                <div class="cfg-form-row">
                                    <div class="form-group">
                                        <label for="sectorName">Nombre <span class="required">*</span></label>
                                        <input type="text" id="sectorName" name="nombre" required placeholder="Ej: Recursos Humanos">
                                    </div>
                                    <div class="form-group">
                                        <label for="sectorIcon">Icono</label>
                                        <select id="sectorIcon" name="icono">
                                            <option value="fa-folder">&#128193; Carpeta</option>
                                            <option value="fa-building">&#127970; Edificio</option>
                                            <option value="fa-users">&#128101; Personas</option>
                                            <option value="fa-laptop-code">&#128187; Programaci&oacute;n</option>
                                            <option value="fa-coins">&#128176; Finanzas</option>
                                            <option value="fa-shopping-cart">&#128722; Ventas</option>
                                            <option value="fa-cogs">&#9881;&#65039; Operaciones</option>
                                            <option value="fa-calculator">&#129518; Contabilidad</option>
                                            <option value="fa-bullhorn">&#128226; Marketing</option>
                                            <option value="fa-balance-scale">&#9878;&#65039; Legal</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="sectorDesc">Descripci&oacute;n</label>
                                    <textarea id="sectorDesc" name="descripcion" rows="2" placeholder="Descripci&oacute;n breve del sector..."></textarea>
                                </div>
                                <div class="cfg-form-actions">
                                    <button type="submit" class="btn primary"><i class="fas fa-check"></i> Guardar</button>
                                    <button type="button" class="btn secondary" id="cancelSectorBtn"><i class="fas fa-times"></i> Cancelar</button>
                                </div>
                            </form>
                        </div>

                        <ul class="cfg-list">
                            <?php if (empty($sectores)): ?>
                            <li class="cfg-empty">
                                <i class="fas fa-building"></i>
                                <p>No hay sectores creados a&uacute;n.</p>
                            </li>
                            <?php else: ?>
                                <?php
                                                foreach ($sectores as $idx => $sector):
                                    $color = $sectorColorMap[$sector['id']] ?? '#3498db';
                                ?>
                                <li class="cfg-list-item">
                                    <div class="cfg-item-icon" style="background:<?php echo $color; ?>20; color:<?php echo $color; ?>">
                                        <i class="fas <?php echo htmlspecialchars($sector['icono'] ?: 'fa-folder'); ?>"></i>
                                    </div>
                                    <div class="cfg-item-info">
                                        <h4><?php echo htmlspecialchars($sector['nombre']); ?></h4>
                                        <?php if (!empty($sector['descripcion'])): ?>
                                        <p><?php echo htmlspecialchars($sector['descripcion']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cfg-item-actions">
                                        <button class="cfg-action-btn cfg-edit" onclick="editSector(<?php echo $sector['id']; ?>)" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="cfg-action-btn cfg-delete" onclick="confirmDeleteSector(<?php echo $sector['id']; ?>)" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Glosario -->
                    <div class="card cfg-card">
                        <div class="card-header cfg-card-header">
                            <div class="cfg-card-title">
                                <div class="cfg-card-icon purple">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div>
                                    <h3>Glosario</h3>
                                    <p class="cfg-card-subtitle">Gestionar los t&eacute;rminos del glosario</p>
                                </div>
                            </div>
                            <button class="btn primary btn-sm" id="newTermBtn">
                                <i class="fas fa-plus"></i> Nuevo
                            </button>
                        </div>

                        <div id="newTermForm" style="display: none;" class="cfg-inline-form">
                            <form id="termForm" action="procesar_glosario.php" method="post">
                                <div class="form-group">
                                    <label for="termName">T&eacute;rmino <span class="required">*</span></label>
                                    <input type="text" id="termName" name="termino" required placeholder="Ej: Procedimiento Operativo">
                                </div>
                                <div class="form-group">
                                    <label for="termDefinition">Definici&oacute;n <span class="required">*</span></label>
                                    <textarea id="termDefinition" name="definicion" rows="3" required placeholder="Escriba la definici&oacute;n del t&eacute;rmino..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="termSector">Sector (opcional)</label>
                                    <select id="termSector" name="sector_id">
                                        <option value="">Sin sector espec&iacute;fico</option>
                                        <?php foreach ($sectores as $sector): ?>
                                        <option value="<?php echo $sector['id']; ?>"><?php echo htmlspecialchars($sector['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="cfg-form-actions">
                                    <button type="submit" class="btn primary"><i class="fas fa-check"></i> Guardar</button>
                                    <button type="button" class="btn secondary" id="cancelTermBtn"><i class="fas fa-times"></i> Cancelar</button>
                                </div>
                            </form>
                        </div>

                        <div class="cfg-glossary-count">
                            <i class="fas fa-info-circle"></i>
                            <?php echo count($glosario); ?> t&eacute;rmino<?php echo count($glosario) != 1 ? 's' : ''; ?> registrado<?php echo count($glosario) != 1 ? 's' : ''; ?>.
                            <a href="#" onclick="showGlossary(); return false;">Ver glosario completo <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Vista de Todos los Documentos -->
            <div id="all-documents-content" class="tab-content" style="display: none;">
                <div class="section-header">
                    <h2>Todos los Documentos</h2>
                    <?php if ($filtroSectorNombre || $filtroTipo): ?>
                    <p>Mostrando <?php echo count($documentos_recientes); ?> documento<?php echo count($documentos_recientes) != 1 ? 's' : ''; ?><?php echo $filtroSectorNombre ? ' del sector <strong>'.htmlspecialchars($filtroSectorNombre).'</strong>' : ''; ?><?php echo $filtroTipo ? ' &mdash; tipo <strong>'.($filtroTipo=='politica'?'Pol&iacute;tica':'Procedimiento').'</strong>' : ''; ?></p>
                    <?php else: ?>
                    <p>Listado completo de pol&iacute;ticas y procedimientos</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Documentos</h3>
                        <div class="filter-options">
                            <select class="filter-select" id="sectorFilterAll" onchange="filterAllDocuments()">
                                <option value="" <?php echo !$filtroSectorId ? 'selected' : ''; ?>>Todos los sectores</option>
                                <?php foreach ($sectores as $sector): ?>
                                <option value="<?php echo $sector['id']; ?>" <?php echo ($filtroSectorId == $sector['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sector['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="filter-select" id="typeFilterAll" onchange="filterAllDocuments()">
                                <option value="" <?php echo !$filtroTipo ? 'selected' : ''; ?>>Todos los tipos</option>
                                <option value="politica" <?php echo ($filtroTipo == 'politica') ? 'selected' : ''; ?>>Pol&iacute;ticas</option>
                                <option value="procedimiento" <?php echo ($filtroTipo == 'procedimiento') ? 'selected' : ''; ?>>Procedimientos</option>
                            </select>
                        </div>
                    </div>

                    <ul class="policy-list" id="allDocumentsList">
                        <?php if (empty($documentos_recientes)): ?>
                        <li class="no-documents">
                            <p>No hay documentos disponibles.</p>
                        </li>
                        <?php else: ?>
                            <?php foreach ($documentos_recientes as $doc): ?>
                            <li class="policy-item all-doc-item"
                                data-sector="<?php echo $doc['sector_id']; ?>"
                                data-tipo="<?php echo htmlspecialchars($doc['tipo']); ?>">
                                <div class="policy-info">
                                    <div class="policy-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="policy-details">
                                        <h4><?php echo htmlspecialchars($doc['titulo']); ?></h4>
                                        <p><strong><?php echo $doc['tipo'] == 'politica' ? 'Pol&iacute;tica' : 'Procedimiento'; ?></strong> &mdash;
                                        Sector <?php echo htmlspecialchars($doc['sector_nombre']); ?> &mdash;
                                        Actualizado: <?php echo isset($doc['fecha_actualizacion']) ? date('d/m/Y', is_string($doc['fecha_actualizacion']) ? strtotime($doc['fecha_actualizacion']) : strtotime($doc['fecha_actualizacion']->format('Y-m-d'))) : 'Fecha no disponible'; ?></p>
                                    </div>
                                </div>
                                <div class="policy-actions">
                                    <button class="action-btn view" onclick="viewDocument(<?php echo $doc['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn download" onclick="downloadDocument(<?php echo $doc['id']; ?>)">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para visor de PDF -->
    <div id="pdfViewerModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal" onclick="closePdfViewer()">&times;</span>
            <h3 id="pdfTitle">Título del Documento</h3>
            <div class="pdf-container">
                <!-- Aquí se cargará el PDF -->
                <div id="pdfViewer">
                    <p>Visor de PDF cargando...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para el glosario -->
    <div id="glossaryModal" class="modal" style="display: none;">
        <div class="modal-content glossary-modal">
            <span class="close-modal" onclick="closeGlossary()">&times;</span>
            
            <div class="glossary-search-container">
                <h3>Glosario de Términos</h3>
                <input type="text" class="glossary-search" id="glossarySearch" placeholder="Buscar término...">
            </div>
            
            <div class="glossary-content">
                <?php
                // Agrupar términos por letra inicial
                $glossaryByLetter = [];
                foreach ($glosario as $term) {
                    $firstLetter = strtoupper(substr($term['termino'], 0, 1));
                    if (!isset($glossaryByLetter[$firstLetter])) {
                        $glossaryByLetter[$firstLetter] = [];
                    }
                    $glossaryByLetter[$firstLetter][] = $term;
                }
                
                // Ordenar letras alfabéticamente
                ksort($glossaryByLetter);
                
                // Mostrar términos por letra
                foreach ($glossaryByLetter as $letter => $terms) {
                    echo '<div class="glossary-letter">' . $letter . '</div>';
                    echo '<ul class="glossary-list">';
                    foreach ($terms as $term) {
                        echo '<li class="glossary-item">';
                        echo '<h4><i class="fas fa-info-circle"></i> ' . htmlspecialchars($term['termino']) . '</h4>';
                        echo '<p>' . htmlspecialchars($term['definicion']) . '</p>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                
                // Si no hay términos
                if (empty($glosario)) {
                    echo '<div class="no-results-message">';
                    echo '<i class="fas fa-book"></i>';
                    echo '<p>No hay términos en el glosario.</p>';
                    echo '</div>';
                }
                ?>
                
                <div class="no-results-message" style="display: none;">
                    <i class="fas fa-search"></i>
                    <p>No se encontraron términos que coincidan con la búsqueda.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para confirmación de documento subido con tags automáticos -->
    <div id="autoTagsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; text-align: center; padding: 30px;">
            <span class="close-modal" onclick="closeAutoTagsModal()">&times;</span>
            
            <div style="margin-bottom: 20px;">
                <i class="fas fa-check-circle" style="font-size: 60px; color: #2ecc71;"></i>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 15px;">¡Documento Subido Exitosamente!</h3>
            
            <p style="color: #7f8c8d; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                Tu documento ha sido guardado correctamente.
            </p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px;">
                    <i class="fas fa-magic" style="color: #3498db; font-size: 24px;"></i>
                    <h4 style="color: #2c3e50; margin: 0;">Procesamiento Automático en Curso</h4>
                </div>
                <p style="color: #5a6c7d; font-size: 14px; margin: 0;">
                    En los próximos <strong>30-60 segundos</strong>, nuestro sistema de IA:<br>
                    • Generará etiquetas (tags) relevantes automáticamente<br>
                    • Extraerá términos importantes para el glosario
                </p>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="closeAutoTagsModal()" class="btn-primary" style="padding: 10px 30px;">
                    <i class="fas fa-check"></i> Entendido
                </button>
                <button onclick="closeAutoTagsModal(); location.reload();" class="btn-secondary" style="padding: 10px 30px;">
                    <i class="fas fa-sync"></i> Actualizar Ahora
                </button>
            </div>
            
            <p style="color: #95a5a6; font-size: 12px; margin-top: 20px; margin-bottom: 0;">
                <i class="fas fa-info-circle"></i> Los tags aparecerán automáticamente al refrescar la página
            </p>
        </div>
    </div>
    
    <!-- Contenedor de notificaciones -->
    <div class="notification-container"></div>
    
    <!-- ==================== CHATBOT RAG - ASISTENTE IA ==================== -->
    <div id="chatbot-container" class="chatbot-container">
        <div class="chatbot-header">
            <div class="chatbot-header-content">
                <i class="fas fa-headset"></i>
                <div>
                    <h3>Asistente DocuGest</h3>
                    <p class="chatbot-status">
                        <span id="chatbot-status-icon" class="status-dot"></span>
                        <span id="chatbot-status-text">Verificando...</span>
                    </p>
                </div>
            </div>
            <button class="chatbot-close-btn" onclick="toggleChatbot()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chatbot-messages" id="chatbot-messages">
            <div class="chatbot-message bot">
                <div class="message-avatar">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="message-content">
                    <p>👋 ¡Hola! Soy tu asistente virtual de DocuGest.</p>
                    <p>Puedo ayudarte a encontrar información en las políticas y procedimientos.</p>
                    <p><strong>Ejemplos de preguntas:</strong></p>
                    <ul>
                        <li>¿Cómo se cargan proyectos de desarrollo en Trello?</li>
                        <li>¿Cómo funciona el sistema de etiquetas de precios?</li>
                        <li>¿Cuál es el procedimiento de remisión y despacho de mercadería en locales propios?</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="chatbot-input-container">
            <textarea 
                id="chatbot-input" 
                class="chatbot-input" 
                placeholder="Escribe tu pregunta aquí..."
                rows="1"
            ></textarea>
            <button id="chatbot-send-btn" class="chatbot-send-btn" onclick="sendChatbotMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
    
    <button id="chatbot-toggle-btn" class="chatbot-toggle-btn" onclick="toggleChatbot()">
        <i class="fas fa-comment-dots"></i>
        <span class="chatbot-badge" id="chatbot-badge" style="display: none;">1</span>
    </button>
    <!-- ==================================================================== -->
 
    <!-- Inclusión de archivos JavaScript al final del body -->
    <script src="js/search.js"></script>
    <script src="js/document-viewer.js"></script>
    <script src="js/glossary.js"></script>
    <script src="js/upload-form.js"></script>
    <script src="js/sectors.js"></script>
    <script src="js/chatbot.js"></script>
    <script src="js/main.js"></script>
 
</body>
</html>
