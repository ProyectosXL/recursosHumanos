
<?php
// modals/adminPeriodosReporte.php
?>

<!-- Modal para administrar períodos -->
<div class="modal fade" id="periodosModal" tabindex="-1" aria-labelledby="periodosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="periodosModalLabel">
                    <i class="fas fa-calendar-alt me-2"></i>Administrar Períodos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Header con controles -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="fas fa-calendar me-1"></i>Año
                        </label>
                        <select id="yearSelect" class="form-select">
                            <!-- Se llena dinámicamente -->
                        </select>
                    </div>
                    <div class="col-md-6">
                        <!-- Información de ayuda -->
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>
                                Configure las fechas de anticipo y vigencias para cada período. 
                                Los indicadores de color muestran el estado: 
                                <span class="badge bg-danger">Error</span>
                                <span class="badge bg-warning">Cambios</span>
                                <span class="badge bg-success">Válido</span>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="button" class="btn btn-success" id="guardarPeriodos">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                    </div>
                </div>
                
                <!-- Contenedor de períodos -->
                <div id="periodosContainer">
                    <!-- Se llena dinámicamente -->
                </div>

                <!-- Información adicional -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Reglas de Validación
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">
                                            <i class="fas fa-check-circle me-1"></i>Requisitos:
                                        </h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-arrow-right text-muted me-2"></i>Las fechas de vigencia deben estar en el mes correspondiente</li>
                                            <li><i class="fas fa-arrow-right text-muted me-2"></i>Fecha "hasta" debe ser posterior a fecha "desde"</li>
                                            <li><i class="fas fa-arrow-right text-muted me-2"></i>Las vigencias no pueden ser superiores a la fecha de anticipo</li>
                                            <li><i class="fas fa-arrow-right text-muted me-2"></i>Complete todos los campos o déjelos todos vacíos</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-info">
                                            <i class="fas fa-lightbulb me-1"></i>Consejos:
                                        </h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-star text-warning me-2"></i>Configure primero la fecha de anticipo</li>
                                            <li><i class="fas fa-star text-warning me-2"></i>Las vigencias suelen ser antes del anticipo</li>
                                            <li><i class="fas fa-star text-warning me-2"></i>Use el período vigente como referencia</li>
                                            <li><i class="fas fa-star text-warning me-2"></i>Los cambios se guardan al hacer clic en "Guardar"</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cerrar
                </button>
                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <i class="fas fa-question-circle me-2"></i>Ayuda
                </button>
                <button type="button" class="btn btn-success" id="guardarPeriodos2">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Script para sincronizar botones de guardar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar ambos botones de guardar
    $('#guardarPeriodos2').on('click', function() {
        $('#guardarPeriodos').click();
    });
    
    // Mantener estado del botón en el footer sincronizado
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'disabled') {
                const mainButton = $('#guardarPeriodos');
                const footerButton = $('#guardarPeriodos2');
                
                if (mainButton.prop('disabled')) {
                    footerButton.prop('disabled', true)
                              .removeClass('btn-success btn-danger')
                              .addClass('btn-secondary')
                              .html('<i class="fas fa-save me-2"></i>Sin cambios para guardar');
                } else {
                    footerButton.prop('disabled', false)
                              .removeClass('btn-secondary btn-danger')
                              .addClass('btn-success')
                              .html('<i class="fas fa-save me-2"></i>Guardar Cambios');
                }
            }
            
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const mainButton = $('#guardarPeriodos');
                const footerButton = $('#guardarPeriodos2');
                
                if (mainButton.hasClass('btn-danger')) {
                    footerButton.removeClass('btn-success btn-secondary')
                              .addClass('btn-danger')
                              .html('<i class="fas fa-exclamation-triangle me-2"></i>Hay errores');
                }
            }
        });
    });
    
    // Observar cambios en el botón principal
    const targetNode = document.getElementById('guardarPeriodos');
    if (targetNode) {
        observer.observe(targetNode, { 
            attributes: true, 
            attributeFilter: ['disabled', 'class'] 
        });
    }
});
</script>