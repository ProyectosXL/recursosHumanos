
<?php
// modals/ayudaReporte.php
?>

<!-- Modal de Ayuda -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="fas fa-question-circle me-2"></i>Ayuda - Reporte de Anticipos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs de ayuda -->
                <ul class="nav nav-tabs" id="helpTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="fas fa-home me-1"></i>Vista General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="filters-tab" data-bs-toggle="tab" data-bs-target="#filters" type="button" role="tab">
                            <i class="fas fa-filter me-1"></i>Filtros y Búsqueda
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab">
                            <i class="fas fa-download me-1"></i>Exportación
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="periods-tab" data-bs-toggle="tab" data-bs-target="#periods" type="button" role="tab">
                            <i class="fas fa-calendar me-1"></i>Períodos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="troubleshooting-tab" data-bs-toggle="tab" data-bs-target="#troubleshooting" type="button" role="tab">
                            <i class="fas fa-tools me-1"></i>Solución de Problemas
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="helpTabContent">
                    <!-- Vista General -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <h6><i class="fas fa-info-circle text-info me-2"></i>¿Qué es el Reporte de Anticipos?</h6>
                        <p>Este sistema permite visualizar y exportar información sobre los anticipos de sueldo otorgados a los empleados, organizados por período mensual.</p>
                        
                        <h6><i class="fas fa-eye text-primary me-2"></i>Elementos de la pantalla:</h6>
                        <ul>
                            <li><strong>Período:</strong> Se muestra automáticamente el último período con datos cargados</li>
                            <li><strong>Tabla de datos:</strong> Muestra legajo, nombre, DNI, período, importe, fecha y departamento</li>
                            <li><strong>Botones de exportación:</strong> Permite descargar en Excel o PDF</li>
                            <li><strong>Administrar Períodos:</strong> Configura fechas de anticipo y vigencias</li>
                        </ul>

                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> El sistema carga automáticamente el período más reciente que tenga datos registrados.
                        </div>

                        <h6><i class="fas fa-keyboard text-success me-2"></i>Atajos de Teclado Disponibles:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><kbd>F1</kbd> - Abrir esta ayuda</li>
                                    <li><kbd>Ctrl + H</kbd> - Ayuda rápida</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><kbd>Ctrl + E</kbd> - Exportar a Excel</li>
                                    <li><kbd>Ctrl + P</kbd> - Exportar a PDF</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros y Búsqueda -->
                    <div class="tab-pane fade" id="filters" role="tabpanel">
                        <h6><i class="fas fa-filter text-success me-2"></i>Filtrar por Período</h6>
                        <ul>
                            <li>Use el selector "Período" para cambiar a un mes específico</li>
                            <li>Seleccione "Todos los períodos" para ver datos de todos los meses</li>
                            <li>Los períodos se muestran en formato "Mes-Año" (ej: 6-2025 = Junio 2025)</li>
                            <li>Por defecto se carga el último período que tenga datos registrados</li>
                        </ul>

                        <h6><i class="fas fa-search text-warning me-2"></i>Búsqueda Rápida</h6>
                        <ul>
                            <li>Use la caja "Buscar" para encontrar empleados específicos</li>
                            <li>Puede buscar por: nombre, apellido, legajo o DNI</li>
                            <li>La búsqueda es instantánea mientras escribe</li>
                            <li>Para limpiar la búsqueda, borre el texto del campo</li>
                            <li>No distingue entre mayúsculas y minúsculas</li>
                        </ul>

                        <div class="alert alert-success">
                            <i class="fas fa-magic me-2"></i>
                            <strong>Truco:</strong> Puede buscar solo parte del nombre o DNI, no necesita escribir completo.
                        </div>

                        <h6><i class="fas fa-sort text-primary me-2"></i>Ordenamiento de Datos</h6>
                        <ul>
                            <li>Haga clic en cualquier encabezado de columna para ordenar</li>
                            <li>Haga clic nuevamente para invertir el orden</li>
                            <li>Por defecto se ordena por fecha de carga (más recientes primero)</li>
                        </ul>
                    </div>

                    <!-- Exportación -->
                    <div class="tab-pane fade" id="export" role="tabpanel">
                        <h6><i class="fas fa-file-excel text-success me-2"></i>Exportar a Excel</h6>
                        <ul>
                            <li>Incluye todos los datos visibles en la tabla</li>
                            <li>Los importes se exportan como números (sin formato de moneda)</li>
                            <li>Los DNI se exportan sin puntos separadores</li>
                            <li>El archivo incluye el período en el nombre</li>
                            <li>Respeta los filtros aplicados (período y búsqueda)</li>
                        </ul>

                        <h6><i class="fas fa-file-pdf text-danger me-2"></i>Exportar a PDF</h6>
                        <ul>
                            <li>Mantiene el formato visual de la tabla</li>
                            <li>Ideal para impresión o presentaciones</li>
                            <li>Incluye título con el período seleccionado</li>
                            <li>Se adapta automáticamente al tamaño de página</li>
                        </ul>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> La exportación respeta los filtros aplicados. Si tiene un período específico seleccionado, solo exportará esos datos.
                        </div>

                        <h6><i class="fas fa-download text-info me-2"></i>Consejos para Exportación</h6>
                        <ul>
                            <li>Para exportar todo: seleccione "Todos los períodos" antes de exportar</li>
                            <li>Para un empleado específico: use la búsqueda antes de exportar</li>
                            <li>Los archivos se descargan automáticamente en su carpeta de descargas</li>
                            <li>Use Excel para análisis de datos y PDF para presentaciones</li>
                        </ul>
                    </div>

                    <!-- Períodos -->
                    <div class="tab-pane fade" id="periods" role="tabpanel">
                        <h6><i class="fas fa-calendar-plus text-primary me-2"></i>Administrar Períodos</h6>
                        <p>Use el botón "Administrar Períodos" para configurar las fechas de anticipo y vigencias:</p>

                        <h6><i class="fas fa-cog me-2"></i>Configuración de Períodos:</h6>
                        <ul>
                            <li><strong>Fecha Anticipo:</strong> Día en que se paga el anticipo al empleado</li>
                            <li><strong>Vigencia Desde:</strong> Inicio del período de vigencia para solicitudes</li>
                            <li><strong>Vigencia Hasta:</strong> Fin del período de vigencia para solicitudes</li>
                        </ul>

                        <h6><i class="fas fa-exclamation-circle text-warning me-2"></i>Reglas de Validación:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li>Las fechas de vigencia deben estar en el mes correspondiente</li>
                                    <li>Fecha "hasta" debe ser posterior a fecha "desde"</li>
                                    <li>Las vigencias no pueden ser superiores a la fecha de anticipo</li>
                                    <li>Todos los campos deben completarse o dejarse todos vacíos</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Indicadores Visuales:</h6>
                                        <p class="card-text">
                                            <span class="badge bg-danger me-1">●</span> <small>Errores de validación</small><br>
                                            <span class="badge bg-warning me-1">●</span> <small>Cambios pendientes válidos</small><br>
                                            <span class="badge bg-success me-1">●</span> <small>Datos guardados y válidos</small><br>
                                            <span class="badge bg-secondary me-1">●</span> <small>Sin datos configurados</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Los cambios no se guardan automáticamente. Debe hacer clic en "Guardar Cambios" para confirmar.
                        </div>
                    </div>

                    <!-- Solución de Problemas -->
                    <div class="tab-pane fade" id="troubleshooting" role="tabpanel">
                        <h6><i class="fas fa-bug text-danger me-2"></i>Problemas Comunes</h6>

                        <div class="accordion" id="troubleshootingAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#problem1">
                                        <i class="fas fa-table me-2"></i>No se muestran datos en la tabla
                                    </button>
                                </h2>
                                <div id="problem1" class="accordion-collapse collapse show" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <strong>Posibles soluciones:</strong>
                                        <ul>
                                            <li>Verifique que el período seleccionado tenga anticipos cargados</li>
                                            <li>Pruebe seleccionar "Todos los períodos" para ver si hay datos</li>
                                            <li>Revise si hay filtros de búsqueda aplicados y límpielos</li>
                                            <li>Actualice la página (F5) para recargar los datos</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem2">
                                        <i class="fas fa-download me-2"></i>Error al exportar archivos
                                    </button>
                                </h2>
                                <div id="problem2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <strong>Verifique lo siguiente:</strong>
                                        <ul>
                                            <li>Asegúrese de que el navegador permita descargas automáticas</li>
                                            <li>Verifique que tenga espacio suficiente en el disco</li>
                                            <li>Pruebe con un período con menos datos si el archivo es muy grande</li>
                                            <li>Cierre otros archivos Excel abiertos antes de exportar</li>
                                            <li>Intente con otro navegador si persiste el problema</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem3">
                                        <i class="fas fa-save me-2"></i>No puedo guardar períodos
                                    </button>
                                </h2>
                                <div id="problem3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <strong>Revise estos puntos:</strong>
                                        <ul>
                                            <li>Verifique que todas las fechas estén en el formato correcto</li>
                                            <li>Asegúrese de que no haya campos marcados en rojo (errores)</li>
                                            <li>Complete todos los campos de un período o déjelos todos vacíos</li>
                                            <li>Las fechas de vigencia deben ser del mes correspondiente</li>
                                            <li>La fecha "hasta" debe ser posterior a la fecha "desde"</li>
                                            <li>Las vigencias no pueden ser superiores a la fecha de anticipo</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem4">
                                        <i class="fas fa-clock me-2"></i>La página carga muy lenta
                                    </button>
                                </h2>
                                <div id="problem4" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                    <div class="accordion-body">
                                        <strong>Optimice el rendimiento:</strong>
                                        <ul>
                                            <li>Filtre por un período específico en lugar de "Todos los períodos"</li>
                                            <li>Use la búsqueda para limitar la cantidad de registros</li>
                                            <li>Cierre otras pestañas del navegador</li>
                                            <li>Verifique su conexión a internet</li>
                                            <li>Limpie el caché del navegador si es necesario</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="fas fa-phone me-2"></i>
                            <strong>¿Necesita más ayuda?</strong><br>
                            Contacte al área de Proyectos o consulte con su superior para asistencia adicional.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cerrar
                </button>
                <button type="button" class="btn btn-info" id="printHelp">
                    <i class="fas fa-print me-2"></i>Imprimir Ayuda
                </button>
            </div>
        </div>
    </div>
</div>