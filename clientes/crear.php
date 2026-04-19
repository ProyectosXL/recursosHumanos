<?php
session_start();
require_once 'config.php';
require_once ROOT_PATH . 'Class/Conexion.php';

$codClient = $_GET['cod'] ?? '';
$nextNum = '';
$error = null;
$extraHeaders = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';

// --- LÓGICA AÑADIDA PARA TÍTULOS DINÁMICOS Y BOTÓN VOLVER ---
if ($codClient === 'AS') {
    $pageTitle = "Crear Nuevo Referido";
    $formTitle = "Formulario de Nuevo Referido";
    $btnCrear = "Crear Referido";
    $urlVolver = 'index_directores.php';
} else {
    $pageTitle = "Crear Nuevo Empleado";
    $formTitle = "Formulario de Nuevo Empleado";
    $btnCrear = "Crear Empleado";
    $urlVolver = 'index.php';
}
// --- FIN DE LA LÓGICA ---


if ($codClient === 'AS' || $codClient === 'E') {
    try {
        $conexion = new Conexion();
        $cid = $conexion->conexionLakerbis();
        
        $sql = "
            SELECT 
                RIGHT('0000' + CAST(
                    ISNULL(
                        MAX(
                            CAST(RIGHT(COD_CLIENT, 4) AS INT)
                        ), 0
                    ) + 1 
                AS VARCHAR(4)), 4) AS SiguienteNumero 
            FROM GVA14 
            WHERE 
                COD_CLIENT LIKE ? 
                AND PATINDEX('%[0-9][0-9][0-9][0-9]', COD_CLIENT) > 0
        ";
        
        $stmt = sqlsrv_query($cid, $sql, [$codClient . '%']);
        if ($stmt && ($v = sqlsrv_fetch_object($stmt))) {
            $nextNum = $v->SiguienteNumero;
        }

    } catch (Exception $e) {
        error_log("Error al sugerir siguiente código en crear.php: " . $e->getMessage());
    }
}

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 700px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-light border">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <h1 class="h3 mb-0 text-center"><?= htmlspecialchars($formTitle) ?></h1>
        <div style="width: 88px;"></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5">

            <?php
            if (isset($_SESSION['feedback'])) {
                $feedback = $_SESSION['feedback'];
                echo '<div class="alert alert-' . htmlspecialchars($feedback['tipo']) . ' alert-dismissible fade show" role="alert">';
                echo $feedback['mensaje'];
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['feedback']);
            }
            ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
                <form action="<?= BASE_URL ?>Controlador/procesar_crear.php" method="POST">
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="nombre" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control form-control-lg" name="nombre" id="nombre" placeholder="Ej: Juan Pérez" required>
                        </div>

                        <div class="col-md-5">
                            <label for="codClientNum" class="form-label">Código Asignado</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><?= htmlspecialchars($codClient) ?></span>
                                <input type="text" class="form-control" name="codClientNum" id="codClientNum" value="<?= htmlspecialchars($nextNum) ?>" required maxlength="4" pattern="[0-9]{4}" title="El código debe tener exactamente 4 dígitos numéricos.">
                                <input type="hidden" name="codClient" id="codClient">
                            </div>
                        </div>

                        <div class="col-md-7">
                            <label for="dni" class="form-label">DNI / CUIT</label>
                            <input type="text" class="form-control form-control-lg" name="dni" id="dni" placeholder="Sin puntos ni guiones">
                        </div>

                        <div class="col-md-5">
                            <label for="porcDesc" class="form-label">Porcentaje de Descuento</label>
                            <div class="input-group input-group-lg">
                                <?php
                                $descValue = ($codClient == 'E') ? '35' : '';
                                $extraAttrs = 'min="0"'; 
                                if ($codClient == 'E') {
                                    $extraAttrs .= ' readonly';
                                } elseif ($codClient == 'AS') {
                                    $extraAttrs .= ' max="35" title="El descuento no puede superar el 35%"';
                                }
                                ?>
                                <input type="number" step="0.01" class="form-control" name="porcDesc" id="porcDesc" value="<?= $descValue ?>" <?= $extraAttrs ?>>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn btn-secondary btn-lg me-2">Cancelar</a>
                        <button type="submit" name="Crear" class="btn btn-primary btn-lg"><?= htmlspecialchars($btnCrear) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const prefix = "<?= htmlspecialchars($codClient) ?>";
    const numInput = document.getElementById('codClientNum');
    const hiddenInput = document.getElementById('codClient');
    
    function updateHiddenCode() {
        if (hiddenInput) {
            hiddenInput.value = prefix + numInput.value;
        }
    }
    
    if(numInput) {
        updateHiddenCode(); 
        numInput.addEventListener('keyup', updateHiddenCode);
        numInput.addEventListener('change', updateHiddenCode);
    }
});
</script>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>