<?php
require_once 'config.php';
require_once ROOT_PATH . 'Class/Conexion.php';
require_once ROOT_PATH . 'Class/Cliente.php';

function limpiarYCodificar($texto) {
    if ($texto === null || $texto === '') return '';
    $t = preg_replace('/[[:cntrl:]]/', '', $texto);
    if (function_exists('mb_check_encoding') && mb_check_encoding($t, 'UTF-8')) return $t;
    return function_exists('mb_convert_encoding') ? mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1') : $t;
}

$from         = $_GET['from'] ?? 'directores';
$isDirectores = ($from === 'directores');

$filtroTipo    = $isDirectores ? 'AS'                  : 'E';
$accentClass   = $isDirectores ? 'ref'                 : 'emp';
$portalContext = $isDirectores ? 'referidos'            : 'empleados';
$urlVolver     = $isDirectores ? 'index_directores.php' : 'index.php';
$codEnviar     = $isDirectores ? 'AS'                  : 'E';
$canal         = 'PROPIOS';

$pageTitle          = "Consulta Individual";
$mainContainerClass = 'py-5';

// Pre-carga: si llega dni en la URL mostramos el valor en el input
$dniBuscado = preg_replace('/[^0-9]/', '', trim($_GET['dni'] ?? ''));

$listaClientesParaBusqueda = [];
$errorGeneral              = null;

try {
    $clienteModel              = new Cliente();
    $listaClientesParaBusqueda = $clienteModel->obtenerClientesParaBusqueda($filtroTipo);
} catch (Exception $e) {
    $errorGeneral = "Error al cargar la lista de clientes: " . $e->getMessage();
    error_log("ERROR en consulta_individual.php: " . $e->getMessage());
}

$extraHeaders = '';

ob_start();
?>
<script>
$(document).ready(function () {

    /* ── Select2 ──────────────────────────────────────────────── */
    $('#nombreBusqueda').select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba o seleccione un nombre',
        allowClear: true,
        width: '100%',
        dropdownParent: $('body')
    });

    /* ── Referencias ──────────────────────────────────────────── */
    var dniInput    = document.getElementById('dniInput');
    var nombreSel   = document.getElementById('nombreBusqueda');
    var tabDni      = document.getElementById('tab-dni');
    var tabNombre   = document.getElementById('tab-nombre');
    var panelDni    = document.getElementById('panel-dni');
    var panelNombre = document.getElementById('panel-nombre');
    var searchForm  = document.getElementById('searchForm');
    var cardBody    = document.getElementById('search-card-body');
    var resultsSection = document.getElementById('results-section');

    var ACCENT = '<?= $accentClass ?>';
    var COD    = '<?= htmlspecialchars($codEnviar) ?>';
    var CANAL  = '<?= htmlspecialchars($canal) ?>';
    var FROM   = '<?= htmlspecialchars($from) ?>';

    /* ── Tabs ─────────────────────────────────────────────────── */
    function activateTab(tab) {
        if (tab === 'dni') {
            tabDni.classList.add('region-chip--active');
            tabNombre.classList.remove('region-chip--active');
            panelDni.style.display    = '';
            panelNombre.style.display = 'none';
            dniInput.name = 'dni';
            nombreSel.removeAttribute('name');
        } else {
            tabNombre.classList.add('region-chip--active');
            tabDni.classList.remove('region-chip--active');
            panelNombre.style.display = '';
            panelDni.style.display    = 'none';
            nombreSel.setAttribute('name', 'dni');
            dniInput.name = '';
        }
    }
    tabDni.addEventListener('click',    function () { activateTab('dni'); });
    tabNombre.addEventListener('click', function () { activateTab('nombre'); });

    /* ── Helpers de HTML ──────────────────────────────────────── */
    function esc(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function loadingHTML() {
        return [
            '<div class="ci-loading">',
            '  <div class="ci-loading__spinner">',
            '    <div class="spinner-border" role="status">',
            '      <span class="visually-hidden">Buscando…</span>',
            '    </div>',
            '  </div>',
            '  <p class="ci-loading__title">Consultando sucursales…</p>',
            '  <p class="ci-loading__sub">Esto puede tardar hasta 40 segundos.<br>No cierres esta pestaña.</p>',
            '  <div class="ci-progress"><div class="ci-progress__bar"></div></div>',
            '</div>'
        ].join('');
    }

    function errorHTML(msg) {
        return '<div class="alert alert-danger d-flex align-items-center gap-2 mt-3" role="alert">' +
               '<i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>' +
               '<div>' + esc(msg) + '</div></div>';
    }

    function renderResults(data, cuit) {
        var enc  = data.encontrado;
        var noEnc = data.noEncontrado;
        var errs  = data.errores;
        var total = enc.length + noEnc.length;
        var pctEnc  = total > 0 ? Math.round(enc.length  / total * 100) : 0;
        var pctNoEnc = total > 0 ? Math.round(noEnc.length / total * 100) : 0;

        var h = '';

        /* Título */
        h += '<div class="portal-heading">' +
             '<h1 style="font-size:1.25rem;">Resultados para: <strong>' + esc(cuit) + '</strong></h1>' +
             '<p>' + total + ' ' + (total === 1 ? 'sucursal consultada' : 'sucursales consultadas') + '</p>' +
             '</div>';

        /* Stat cards */
        h += '<div class="row g-3 mb-4">';
        h += '<div class="col-4"><div class="stat-card stat-card--blue">' +
             '<div class="stat-card__icon"><i class="bi bi-building" aria-hidden="true"></i></div>' +
             '<div class="stat-card__num">' + total + '</div>' +
             '<div class="stat-card__lbl">consultadas</div>' +
             '<div class="stat-progress"><div class="stat-progress__fill" style="width:100%"></div></div>' +
             '</div></div>';
        h += '<div class="col-4"><div class="stat-card stat-card--green">' +
             '<div class="stat-card__icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>' +
             '<div class="stat-card__num">' + enc.length + '</div>' +
             '<div class="stat-card__lbl">encontrado</div>' +
             '<div class="stat-progress"><div class="stat-progress__fill" style="width:' + pctEnc + '%"></div></div>' +
             '</div></div>';
        h += '<div class="col-4"><div class="stat-card stat-card--amber">' +
             '<div class="stat-card__icon"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></div>' +
             '<div class="stat-card__num">' + noEnc.length + '</div>' +
             '<div class="stat-card__lbl">no encontrado</div>' +
             '<div class="stat-progress"><div class="stat-progress__fill" style="width:' + pctNoEnc + '%"></div></div>' +
             '</div></div>';
        h += '</div>';

        /* Listas */
        h += '<div class="row g-4">';

        /* Encontrado en */
        h += '<div class="col-md-6"><div class="card h-100" style="border-top:3px solid #059669;">';
        h += '<div class="card-header d-flex justify-content-between align-items-center" style="background:#ecfdf5;border-bottom:1px solid #d1fae5;">';
        h += '<span class="fw-semibold" style="color:#065f46;"><i class="bi bi-check-circle-fill me-2" style="color:#059669;" aria-hidden="true"></i>Encontrado en</span>';
        h += '<span class="counter-badge ' + (enc.length > 0 ? 'counter-badge--active' : '') + '">' + enc.length + '</span>';
        h += '</div><div class="card-body p-0" style="max-height:320px;overflow-y:auto;"><ul class="list-group list-group-flush">';
        if (enc.length === 0) {
            h += '<li class="list-group-item text-center py-4 text-muted">' +
                 '<i class="bi bi-dash-circle fs-2 d-block mb-2" aria-hidden="true"></i>No se encontró en ninguna sucursal</li>';
        } else {
            enc.forEach(function (s) {
                h += '<li class="list-group-item d-flex align-items-center gap-2 py-2">' +
                     '<i class="bi bi-check-circle-fill text-success flex-shrink-0" aria-hidden="true"></i>' +
                     '<span>' + esc(s) + '</span></li>';
            });
        }
        h += '</ul></div></div></div>';

        /* No encontrado en */
        h += '<div class="col-md-6"><div class="card h-100 d-flex flex-column" style="border-top:3px solid #d97706;">';
        h += '<div class="card-header d-flex justify-content-between align-items-center" style="background:#fffbeb;border-bottom:1px solid #fef3c7;">';
        h += '<span class="fw-semibold" style="color:#78350f;"><i class="bi bi-x-circle-fill me-2" style="color:#d97706;" aria-hidden="true"></i>No encontrado en</span>';
        h += '<span class="counter-badge" style="background:#fef3c7;color:#92400e;">' + noEnc.length + '</span>';
        h += '</div><div class="card-body p-0 flex-grow-1" style="max-height:260px;overflow-y:auto;"><ul class="list-group list-group-flush">';
        if (noEnc.length === 0) {
            h += '<li class="list-group-item text-center py-4 text-muted">' +
                 '<i class="bi bi-check2-all fs-2 d-block mb-2 text-success" aria-hidden="true"></i>' +
                 'Registrado en todas las sucursales consultadas</li>';
        } else {
            noEnc.forEach(function (s) {
                h += '<li class="list-group-item d-flex align-items-center gap-2 py-2">' +
                     '<i class="bi bi-x-circle flex-shrink-0" style="color:#d97706;" aria-hidden="true"></i>' +
                     '<span>' + esc(s) + '</span></li>';
            });
        }
        h += '</ul></div>';
        if (noEnc.length > 0) {
            h += '<div class="card-footer bg-transparent border-top d-flex justify-content-end py-3">' +
                 '<a href="clientes.php?cod=' + esc(COD) + '" class="btn btn-sm btn-crear btn-crear--' + ACCENT + '">' +
                 '<i class="bi bi-send-fill me-1" aria-hidden="true"></i>Enviar a sucursales faltantes</a>' +
                 '</div>';
        }
        h += '</div></div>';

        h += '</div>'; /* row */

        /* Errores colapsados */
        if (errs.length > 0) {
            h += '<div class="mt-4">' +
                 '<button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#erroresDetalle" aria-expanded="false" aria-controls="erroresDetalle">' +
                 '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Ver ' + errs.length + ' ' + (errs.length === 1 ? 'error' : 'errores') + ' de conexión</button>' +
                 '<div class="collapse mt-2" id="erroresDetalle"><div class="card">' +
                 '<div class="card-header bg-danger-subtle text-danger-emphasis d-flex justify-content-between align-items-center">' +
                 '<span class="fw-semibold"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>Errores de conexión</span>' +
                 '<span class="badge bg-danger">' + errs.length + '</span></div>' +
                 '<ul class="list-group list-group-flush" style="max-height:200px;overflow-y:auto;">';
            errs.forEach(function (err) {
                h += '<li class="list-group-item d-flex align-items-start gap-2 py-2">' +
                     '<i class="bi bi-bug text-danger flex-shrink-0 mt-1" aria-hidden="true"></i>' +
                     '<small class="text-danger">' + esc(err) + '</small></li>';
            });
            h += '</ul></div></div></div>';
        }

        return h;
    }

    /* ── Búsqueda AJAX ────────────────────────────────────────── */
    var currentController = null;

    function runSearch(cuit) {
        if (!cuit) return;

        // Cancelar búsqueda anterior si todavía corre
        if (currentController) currentController.abort();
        currentController = new AbortController();

        // Mostrar spinner en la card
        cardBody.innerHTML = loadingHTML();
        resultsSection.style.display = 'none';
        resultsSection.innerHTML = '';

        // Actualizar URL sin recargar
        var params = new URLSearchParams(window.location.search);
        params.set('from', FROM);
        params.set('dni', cuit);
        history.replaceState(null, '', '?' + params.toString());

        var url = 'ajax_buscar_cuit.php?cuit=' + encodeURIComponent(cuit) + '&canal=' + encodeURIComponent(CANAL);
        var timeout = setTimeout(function () { currentController.abort(); }, 120000);

        fetch(url, { signal: currentController.signal })
            .then(function (r) {
                if (!r.ok) throw new Error('Respuesta HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                clearTimeout(timeout);
                currentController = null;

                // Restaurar card body con el formulario original
                restoreCardBody();

                if (!data.ok) {
                    resultsSection.innerHTML = errorHTML(data.error || 'Error desconocido.');
                } else {
                    resultsSection.innerHTML = renderResults(data, cuit);
                }
                resultsSection.style.display = '';
                resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function (err) {
                clearTimeout(timeout);
                currentController = null;
                restoreCardBody();

                var msg = err.name === 'AbortError'
                    ? 'La búsqueda tardó demasiado y fue cancelada. Intentá de nuevo.'
                    : 'Error de conexión con el servidor. Verificá tu red e intentá de nuevo.';
                resultsSection.innerHTML = errorHTML(msg);
                resultsSection.style.display = '';
            });
    }

    /* Guarda el HTML original de la card para poder restaurarlo */
    var originalCardBody = cardBody.innerHTML;
    function restoreCardBody() {
        cardBody.innerHTML = originalCardBody;
        /* Re-conectar Select2 y event listeners tras reinyectar el DOM */
        $('#nombreBusqueda').select2({
            theme: 'bootstrap-5',
            placeholder: 'Escriba o seleccione un nombre',
            allowClear: true,
            width: '100%',
            dropdownParent: $('body')
        });
        /* Re-vincular tabs */
        document.getElementById('tab-dni').addEventListener('click',    function () { activateTab('dni'); });
        document.getElementById('tab-nombre').addEventListener('click', function () { activateTab('nombre'); });
        /* Actualizar refs locales */
        dniInput  = document.getElementById('dniInput');
        nombreSel = document.getElementById('nombreBusqueda');
    }

    /* ── Submit ───────────────────────────────────────────────── */
    searchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var val = (dniInput.name === 'dni')
            ? dniInput.value.trim()
            : ($(nombreSel).val() || '');
        if (!val) return;
        runSearch(val);
    });

    /* ── Auto-búsqueda si llega dni en la URL ─────────────────── */
    var initialDni = '<?= htmlspecialchars($dniBuscado) ?>';
    if (initialDni) {
        dniInput.value = initialDni;
        runSearch(initialDni);
    }

});
</script>
<?php
$pageScripts = ob_get_clean();

include_once ROOT_PATH . 'templates/header.php';
?>

<div class="mx-auto" style="max-width: 900px;">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al portal
        </a>
    </div>

    <div class="portal-heading">
        <h1>Consulta Individual</h1>
        <p>Buscá un DNI o CUIT y verificá en qué sucursales está registrado.</p>
    </div>

    <?php if ($errorGeneral): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
        <div><?= htmlspecialchars($errorGeneral) ?></div>
    </div>
    <?php endif; ?>

    <!-- ── Card de búsqueda ───────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div id="search-card-body" class="card-body p-4">

            <!-- Callout de demora — siempre visible -->
            <div class="delay-callout mb-4">
                <i class="bi bi-clock callout-icon" aria-hidden="true"></i>
                <span>La búsqueda consulta todas las sucursales activas y puede tardar entre 20 y 40 segundos. No cierres la página mientras espera los resultados.</span>
            </div>

            <!-- Tabs de modo de búsqueda -->
            <div class="region-chips region-chips--<?= $accentClass ?> mb-3"
                 role="group"
                 aria-label="Modo de búsqueda">
                <button type="button" class="region-chip region-chip--active" id="tab-dni">
                    <i class="bi bi-123 me-1" aria-hidden="true"></i>DNI / CUIT
                </button>
                <button type="button" class="region-chip" id="tab-nombre">
                    <i class="bi bi-person me-1" aria-hidden="true"></i>Por nombre
                </button>
            </div>

            <!-- Formulario unificado -->
            <form action="consulta_individual.php" method="GET" id="searchForm" novalidate>
                <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">

                <!-- Panel: DNI / CUIT -->
                <div id="panel-dni">
                    <label for="dniInput" class="form-label fw-semibold">DNI / CUIT</label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control form-control-lg"
                               id="dniInput"
                               name="dni"
                               placeholder="Ej: 20123456789"
                               value="<?= htmlspecialchars($dniBuscado) ?>"
                               inputmode="numeric"
                               autocomplete="off">
                        <button type="submit"
                                class="btn btn-lg btn-crear btn-crear--<?= $accentClass ?>">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Buscar
                        </button>
                    </div>
                    <p class="form-text mt-1">Solo números, sin puntos ni guiones (7 a 11 dígitos).</p>
                </div>

                <!-- Panel: Por nombre -->
                <div id="panel-nombre" style="display:none;">
                    <label for="nombreBusqueda" class="form-label fw-semibold">Nombre</label>
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1">
                            <select class="form-select form-select-lg" id="nombreBusqueda">
                                <option value=""></option>
                                <?php foreach ($listaClientesParaBusqueda as $cliente): ?>
                                <?php
                                $nombreCorregido = limpiarYCodificar($cliente->NOM_COM);
                                $cuitLimpio      = preg_replace('/[^0-9]/', '', $cliente->CUIT);
                                ?>
                                <option value="<?= htmlspecialchars($cuitLimpio, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($dniBuscado == $cuitLimpio) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nombreCorregido, ENT_QUOTES, 'UTF-8') ?>
                                    (<?= htmlspecialchars($cuitLimpio, ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"
                                class="btn btn-lg btn-crear btn-crear--<?= $accentClass ?> flex-shrink-0">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Buscar
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Resultados (renderizados por JS) ─────────────────────── -->
    <div id="results-section" style="display:none;"></div>

</div>

<?php include_once ROOT_PATH . 'templates/footer.php'; ?>
