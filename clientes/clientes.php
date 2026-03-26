<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Local.php';

$codClient = $_GET['cod'] ?? ''; 
$arrayClientes = [];
$arrayLocales = [];
$error = null;

// --- LÓGICA AÑADIDA PARA TÍTULOS DINÁMICOS Y BOTÓN VOLVER ---
if ($codClient === 'AS') {
    $pageTitle = "Enviar Referidos a Sucursales";
    $labelClientes = "Seleccione Referidos";
    $btnEnviar = "Enviar Referidos";
    $urlVolver = 'index_directores.php';
} else {
    $pageTitle = "Enviar Empleados a Sucursales";
    $labelClientes = "Seleccione Clientes";
    $btnEnviar = "Enviar Clientes";
    $urlVolver = 'index.php';
}
// --- FIN DE LA LÓGICA ---


try {
    $cliente = new Cliente();
    $arrayClientes = $cliente->listarEmpleados($codClient);
    $local = new Local();
    $arrayLocales = $local->listarLocales();
} catch (Exception $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    error_log("Error en clientes.php: " . $e->getMessage());
}

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 1100px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-light border"><i class="bi bi-arrow-left"></i> Volver</a>
        <h1 class="h3 mb-0 text-center"><?= htmlspecialchars($pageTitle) ?></h1>
        <div style="width: 88px;"></div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">
            <?php if ($error): ?>
                <div class="alert alert-danger"><h4>¡Ocurrió un error!</h4><p><?= htmlspecialchars($error) ?></p></div>
            <?php else: ?>
                <form action="<?= BASE_URL ?>Controlador/procesar.php" id="pedidos" method="POST">
                    <input type="hidden" name="cod_tipo_cliente" value="<?= htmlspecialchars($codClient) ?>">
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="clienteSearch" class="form-label fs-5">1. <?= htmlspecialchars($labelClientes) ?></label>
                                <input type="text" id="clienteSearch" class="form-control" placeholder="Escriba para filtrar...">
                            </div>
                            <div class="border rounded p-3" style="height: 350px; overflow-y: auto;" id="clientes-list">
                                <?php foreach ($arrayClientes as $v): ?>
                                    <div class="form-check form-check-lg cliente-item">
                                        <input class="form-check-input" type="checkbox" name="clientes[]" id="cli_<?= htmlspecialchars($v->Codigo ?? '') ?>" value="<?= htmlspecialchars($v->Codigo ?? '') ?>">
                                        <label class="form-check-label" for="cli_<?= htmlspecialchars($v->Codigo ?? '') ?>"><?= htmlspecialchars($v->Codigo ?? '') ?> - <?= htmlspecialchars($v->Nombre ?? '') ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="sucursalSearch" class="form-label fs-5">2. Seleccione Sucursales</label>
                                <input type="text" id="sucursalSearch" class="form-control" placeholder="Escriba para filtrar...">
                            </div>
                            <div class="border rounded p-3" style="height: 350px; overflow-y: auto;" id="sucursales-list">
                                <?php foreach ($arrayLocales as $v): ?>
                                    <div class="form-check form-check-lg sucursal-item">
                                        <input class="form-check-input" type="checkbox" name="dsn[]" id="suc_<?= htmlspecialchars($v->NRO_SUCURSAL) ?>" region="<?= htmlspecialchars($v->LOCALIDAD ?? '') ?>" value="<?= htmlspecialchars($v->Host . '|' . $v->DbName . '|' . $v->UsuarioDns . '|' . $v->ClaveDns) ?>">
                                        <label class="form-check-label" for="suc_<?= htmlspecialchars($v->NRO_SUCURSAL) ?>"><?= htmlspecialchars($v->NRO_SUCURSAL) ?> - <?= htmlspecialchars($v->DESC_SUCURSAL) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                             <div class="d-flex justify-content-start gap-4 mt-3">
                                 <div class="form-check">
                                     <input class="form-check-input" type="checkbox" id="checkBsas"> 
                                     <label class="form-check-label" for="checkBsas">Buenos Aires</label>
                                 </div>
                                 <div class="form-check">
                                     <input class="form-check-input" type="checkbox" id="checkRosario"> 
                                     <label class="form-check-label" for="checkRosario">Rosario</label>
                                 </div>
                                 <div class="form-check">
                                     <input class="form-check-input" type="checkbox" id="checkMardel"> 
                                     <label class="form-check-label" for="checkMardel">Mar del Plata</label>
                                 </div>
                                 <div class="form-check">
                                     <input class="form-check-input" type="checkbox" id="checkTodos"> 
                                     <label class="form-check-label" for="checkTodos">Todos</label>
                                 </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-secondary btn-lg me-2">Cancelar</a>
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg" disabled>
    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
    <span class="btn-text"><?= htmlspecialchars($btnEnviar) ?></span>
</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include_once ROOT_PATH . 'templates/footer.php'; ?>