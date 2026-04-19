<?php
session_start();
require_once 'config.php';
require_once ROOT_PATH . 'Class/Conexion.php';

$codClient = $_GET['cod'] ?? '';
$nextNum   = '';
$error     = null;

if ($codClient === 'AS') {
    $pageTitle     = "Crear Nuevo Referido";
    $formTitle     = "Nuevo Referido";
    $formSubtitle  = "Completá los datos para dar de alta en la base central.";
    $btnCrear      = "Crear Referido";
    $urlVolver     = 'index_directores.php';
    $portalContext = 'referidos';
    $accentClass   = 'ref';
    $isReferido    = true;
} elseif ($codClient === 'E') {
    $pageTitle     = "Crear Nuevo Empleado";
    $formTitle     = "Nuevo Empleado";
    $formSubtitle  = "Completá los datos para dar de alta en la base central.";
    $btnCrear      = "Crear Empleado";
    $urlVolver     = 'index.php';
    $portalContext = 'empleados';
    $accentClass   = 'emp';
    $isReferido    = false;
} else {
    $pageTitle     = "Error — Tipo de cliente no válido";
    $error         = "El tipo de cliente no es válido. Volvé al portal y volvé a intentarlo.";
    $urlVolver     = 'index.php';
    $portalContext = null;
    $accentClass   = 'emp';
    $isReferido    = false;
}

$mainContainerClass = 'py-5';
$extraHeaders       = '<link rel="stylesheet" href="' . BASE_URL . 'css/crear.css">';

if (!$error) {
    try {
        $conexion = new Conexion();
        $cid = $conexion->conexionLakerbis();

        $sql = "
            SELECT
                RIGHT('0000' + CAST(
                    ISNULL(
                        MAX(CAST(RIGHT(COD_CLIENT, 4) AS INT)),
                        0
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

<div class="mx-auto" style="max-width: 680px;">

    <div class="d-flex align-items-center mb-4">
        <a href="<?= htmlspecialchars($urlVolver) ?>" class="btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al portal
        </a>
    </div>

    <?php if ($error): ?>

        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>

    <?php else: ?>

    <div class="form-card form-card--<?= $accentClass ?>">

        <div class="form-card__header form-card__header--<?= $accentClass ?>">
            <div class="form-card__header-icon" aria-hidden="true">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div>
                <h1><?= htmlspecialchars($formTitle) ?></h1>
                <p><?= htmlspecialchars($formSubtitle) ?></p>
            </div>
        </div>

        <div class="p-4 p-md-5">

            <?php if (isset($_SESSION['feedback'])): ?>
            <?php
                $fb = $_SESSION['feedback'];
                unset($_SESSION['feedback']);
            ?>
            <div class="alert alert-<?= htmlspecialchars($fb['tipo']) ?> alert-dismissible fade show mb-4" role="alert">
                <?= $fb['mensaje'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>Controlador/procesar_crear.php"
                  method="POST"
                  id="form-crear"
                  novalidate>

                <!-- ── A: Datos del cliente ────────────────────────── -->
                <div class="form-section-label" aria-hidden="true">
                    <i class="bi bi-person-fill"></i> Datos del cliente
                </div>

                <div class="mb-3">
                    <label for="nombre" class="form-label fw-medium">
                        Nombre Completo <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="input-wrap">
                        <input type="text"
                               class="form-control form-control-lg"
                               name="nombre"
                               id="nombre"
                               placeholder="Ej: García, María Laura"
                               autocomplete="name"
                               required
                               aria-required="true">
                        <span class="status-icon" id="nombre-status-icon" aria-hidden="true"></span>
                    </div>
                    <div class="field-helper">Se sugiere Título Case al salir del campo (podés modificarlo).</div>
                </div>

                <div class="mb-4">
                    <label for="dni" class="form-label fw-medium">
                        DNI / CUIT <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="input-wrap input-wrap--dni">
                        <input type="text"
                               class="form-control form-control-lg"
                               name="dni"
                               id="dni"
                               placeholder="Solo dígitos, sin puntos ni guiones"
                               maxlength="11"
                               inputmode="numeric"
                               aria-required="true"
                               aria-describedby="dni-feedback">
                        <span class="status-icon" id="dni-status-icon" aria-hidden="true"></span>
                        <span class="dni-counter" id="dni-counter" aria-hidden="true">0/11</span>
                    </div>
                    <div class="field-feedback" id="dni-feedback" role="status" aria-live="polite"></div>
                </div>

                <!-- ── B: Código asignado ──────────────────────────── -->
                <div class="form-section-label" aria-hidden="true">
                    <i class="bi bi-hash"></i> Código asignado
                </div>

                <div class="mb-4">
                    <label for="codClientNum" class="form-label fw-medium">
                        Código <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group input-group-lg" style="max-width: 17rem;">
                        <span class="input-group-text prefix-badge--<?= $accentClass ?>"
                              aria-label="Prefijo <?= htmlspecialchars($codClient) ?>">
                            <?= htmlspecialchars($codClient) ?>
                        </span>
                        <input type="text"
                               class="form-control"
                               name="codClientNum"
                               id="codClientNum"
                               value="<?= htmlspecialchars($nextNum) ?>"
                               required
                               maxlength="4"
                               pattern="[0-9]{4}"
                               inputmode="numeric"
                               title="El código debe tener exactamente 4 dígitos numéricos."
                               style="letter-spacing:.15em; font-weight:600;"
                               aria-describedby="codigo-helper codigo-feedback">
                        <span class="input-group-text status-icon-group" aria-hidden="true">
                            <span id="codigo-status-icon"></span>
                        </span>
                    </div>
                    <div class="field-helper" id="codigo-helper">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Sugerido automáticamente. Podés modificarlo si necesitás.
                    </div>
                    <div class="field-feedback" id="codigo-feedback" role="status" aria-live="polite"></div>
                    <input type="hidden" name="codClient" id="codClient">
                </div>

                <!-- ── C: Descuento ───────────────────────────────── -->
                <div class="form-section-label" aria-hidden="true">
                    <i class="bi bi-percent"></i> Descuento
                </div>

                <?php if ($codClient === 'E'): ?>

                <div class="mb-5">
                    <div class="desc-badge" role="note" aria-label="Descuento fijo del 35% para empleados">
                        <div class="desc-badge__pct" aria-hidden="true">35%</div>
                        <div class="desc-badge__info">
                            <div class="title">Descuento fijo para empleados</div>
                            <div class="sub">Aplicado automáticamente — no modificable.</div>
                        </div>
                        <i class="bi bi-lock-fill ms-auto" style="color:#6366f1; opacity:.5; font-size:1.1rem;" aria-hidden="true"></i>
                    </div>
                    <input type="hidden" name="porcDesc" value="35">
                </div>

                <?php else: ?>

                <div class="mb-4">
                    <label for="porcDesc" class="form-label fw-medium">Porcentaje de descuento</label>
                    <div class="desc-slider-row">
                        <input type="range"
                               class="form-range"
                               min="0" max="35" step="0.5" value="0"
                               id="porcDesc-range"
                               aria-label="Deslizador de descuento (0 a 35%)">
                        <div class="input-group desc-number-group">
                            <input type="number"
                                   class="form-control"
                                   name="porcDesc"
                                   id="porcDesc"
                                   value="0"
                                   min="0" max="35" step="0.5"
                                   aria-describedby="porc-helper">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="field-helper" id="porc-helper">Máximo permitido: 35%.</div>
                </div>

                <div class="replica-callout mb-4" role="note">
                    <i class="bi bi-broadcast callout-icon" aria-hidden="true"></i>
                    <div>
                        <strong>Envío automático a todas las sucursales.</strong><br>
                        Al crear el referido, los datos se replicarán en cada sucursal habilitada. Esta operación puede tardar unos segundos.
                    </div>
                </div>

                <?php endif; ?>

                <!-- ── Acciones ───────────────────────────────────── -->
                <hr class="mb-4">
                <div class="d-flex gap-2 justify-content-end align-items-start flex-wrap">

                    <a href="<?= htmlspecialchars($urlVolver) ?>"
                       class="btn btn-light btn-lg border me-auto">
                        Cancelar
                    </a>

                    <div class="text-end">
                        <button type="submit"
                                class="btn btn-lg btn-crear btn-crear--<?= $accentClass ?>"
                                id="btn-crear"
                                disabled
                                aria-describedby="btn-hint">
                            <span class="btn-spinner" id="btn-spinner" aria-hidden="true"></span>
                            <i class="bi bi-person-plus-fill me-2" id="btn-icon" aria-hidden="true"></i>
                            <span id="btn-text"><?= htmlspecialchars($btnCrear) ?></span>
                        </button>
                        <div class="btn-hint" id="btn-hint" aria-live="polite">
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Completá todos los campos válidos para continuar.
                        </div>
                    </div>

                </div>

            </form>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php
ob_start();
$jsCodClient  = json_encode($codClient);
$jsBtnCrear   = json_encode($btnCrear);
$jsIsReferido = json_encode($isReferido);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
(function () {
  'use strict';

  const PREFIX      = <?= $jsCodClient ?>;
  const IS_REFERIDO = <?= $jsIsReferido ?>;
  const BTN_LABEL   = <?= $jsBtnCrear ?>;

  // ── Referencias DOM ──────────────────────────────────────────
  const form          = document.getElementById('form-crear');
  if (!form) return; // type inválido → no hay formulario

  const nombreInput   = document.getElementById('nombre');
  const dniInput      = document.getElementById('dni');
  const codInput      = document.getElementById('codClientNum');
  const hiddenCod     = document.getElementById('codClient');
  const submitBtn     = document.getElementById('btn-crear');
  const btnSpinner    = document.getElementById('btn-spinner');
  const btnIcon       = document.getElementById('btn-icon');
  const btnText       = document.getElementById('btn-text');
  const btnHint       = document.getElementById('btn-hint');
  const dniCounter    = document.getElementById('dni-counter');
  const dniStatusIcon = document.getElementById('dni-status-icon');
  const dniFeedback   = document.getElementById('dni-feedback');
  const codStatusIcon = document.getElementById('codigo-status-icon');
  const codFeedback   = document.getElementById('codigo-feedback');
  const nombreIcon    = document.getElementById('nombre-status-icon');
  const porcRange     = document.getElementById('porcDesc-range');
  const porcNum       = document.getElementById('porcDesc');

  // ── Estado ───────────────────────────────────────────────────
  let nombreOk    = false;
  let dniOk       = false;
  let codigoOk    = false;
  let ajaxPending = false;

  // ── Helpers UI ───────────────────────────────────────────────
  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setFieldState(iconEl, feedbackEl, state, msg) {
    if (iconEl)   iconEl.innerHTML   = '';
    if (feedbackEl) {
      feedbackEl.className = 'field-feedback';
      feedbackEl.innerHTML = '';
    }

    if (state === 'ok') {
      if (iconEl)     iconEl.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#059669;"></i>';
      if (feedbackEl) {
        feedbackEl.classList.add('field-feedback--ok');
        feedbackEl.innerHTML = '<i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>' + esc(msg);
      }
    } else if (state === 'err') {
      if (iconEl)     iconEl.innerHTML = '<i class="bi bi-x-circle-fill" style="color:#dc2626;"></i>';
      if (feedbackEl) {
        feedbackEl.classList.add('field-feedback--err');
        feedbackEl.innerHTML = '<i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>' + esc(msg);
      }
    } else if (state === 'wait') {
      if (iconEl)     iconEl.innerHTML = '<span class="mini-spinner" aria-hidden="true"></span>';
      if (feedbackEl) {
        feedbackEl.classList.add('field-feedback--neutral');
        feedbackEl.innerHTML = '<span class="mini-spinner me-1" aria-hidden="true"></span>' + esc(msg);
      }
    }
  }

  function updateSubmitBtn() {
    const ok = nombreOk && dniOk && codigoOk && !ajaxPending;
    submitBtn.disabled      = !ok;
    btnHint.style.display   = ok ? 'none' : '';
  }

  function updateHiddenCode() {
    if (hiddenCod && codInput) hiddenCod.value = PREFIX + codInput.value;
  }

  // ── Nombre ───────────────────────────────────────────────────
  function onNombreInput() {
    const ok = nombreInput.value.trim().length > 0;
    nombreOk = ok;
    nombreIcon.innerHTML = ok
      ? '<i class="bi bi-check-circle-fill" style="color:#059669;"></i>'
      : '';
    updateSubmitBtn();
  }

  function onNombreBlur() {
    const val = nombreInput.value.trim();
    if (!val) return;
    nombreInput.value = val.replace(/\b\w/g, c => c.toUpperCase());
  }

  // ── DNI ──────────────────────────────────────────────────────
  function onDniInput() {
    dniInput.value = dniInput.value.replace(/\D/g, '');
    const len = dniInput.value.length;
    dniCounter.textContent = len + '/11';

    if (len === 0) {
      setFieldState(dniStatusIcon, dniFeedback, null, '');
      dniOk = false;
      updateSubmitBtn();
      return;
    }
    if (len < 7) {
      setFieldState(dniStatusIcon, dniFeedback, 'err', 'Ingresá entre 7 y 11 dígitos');
      dniOk = false;
      updateSubmitBtn();
      return;
    }
    // Longitud válida → AJAX
    setFieldState(dniStatusIcon, dniFeedback, 'wait', 'Verificando…');
    dniOk = false;
    updateSubmitBtn();
    debouncedValidate();
  }

  // ── Código ───────────────────────────────────────────────────
  function onCodigoInput() {
    codInput.value = codInput.value.replace(/\D/g, '');
    updateHiddenCode();
    const len = codInput.value.length;

    if (len === 0) {
      setFieldState(codStatusIcon, codFeedback, null, '');
      codigoOk = false;
      updateSubmitBtn();
      return;
    }
    if (len < 4) {
      setFieldState(codStatusIcon, codFeedback, 'err', 'El código debe tener exactamente 4 dígitos');
      codigoOk = false;
      updateSubmitBtn();
      return;
    }
    // 4 dígitos → AJAX
    setFieldState(codStatusIcon, codFeedback, 'wait', 'Verificando…');
    codigoOk = false;
    updateSubmitBtn();
    debouncedValidate();
  }

  // ── AJAX con debounce ────────────────────────────────────────
  function debounce(fn, ms) {
    let t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn, ms);
    };
  }

  const debouncedValidate = debounce(runAjaxValidation, 350);

  function runAjaxValidation() {
    const dni    = dniInput.value;
    const codigo = codInput.value;
    const shouldValidateDni    = dni.length >= 7 && dni.length <= 11;
    const shouldValidateCodigo = codigo.length === 4;

    if (!shouldValidateDni && !shouldValidateCodigo) return;

    ajaxPending = true;
    updateSubmitBtn();

    const params = new URLSearchParams({ tipo: PREFIX, codigo: codigo, dni: dni });

    fetch('ajax_validar_creacion.php?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        ajaxPending = false;

        if (data.error) {
          if (shouldValidateCodigo) { setFieldState(codStatusIcon, codFeedback, 'err', 'Error al verificar'); codigoOk = false; }
          if (shouldValidateDni)    { setFieldState(dniStatusIcon, dniFeedback, 'err', 'Error al verificar'); dniOk = false; }
          updateSubmitBtn();
          return;
        }

        if (shouldValidateCodigo) {
          if (data.codigo_disponible) {
            setFieldState(codStatusIcon, codFeedback, 'ok', 'Código ' + data.codigo_completo + ' disponible');
            codigoOk = true;
          } else {
            setFieldState(codStatusIcon, codFeedback, 'err', 'El código ' + data.codigo_completo + ' ya está en uso');
            codigoOk = false;
          }
        }

        if (shouldValidateDni) {
          if (data.dni_disponible) {
            setFieldState(dniStatusIcon, dniFeedback, 'ok', 'DNI disponible');
            dniOk = true;
          } else {
            setFieldState(dniStatusIcon, dniFeedback, 'err', 'El DNI/CUIT ya está registrado');
            dniOk = false;
          }
        }

        updateSubmitBtn();
      })
      .catch(function () {
        ajaxPending = false;
        if (shouldValidateCodigo) { setFieldState(codStatusIcon, codFeedback, 'err', 'Sin conexión al verificar'); codigoOk = false; }
        if (shouldValidateDni)    { setFieldState(dniStatusIcon, dniFeedback, 'err', 'Sin conexión al verificar'); dniOk = false; }
        updateSubmitBtn();
      });
  }

  // ── Slider ↔ número (Referidos) ─────────────────────────────
  if (porcRange && porcNum) {
    porcRange.addEventListener('input', function () {
      porcNum.value = porcRange.value;
    });
    porcNum.addEventListener('input', function () {
      var v = parseFloat(porcNum.value);
      if (isNaN(v) || v < 0) v = 0;
      if (v > 35) v = 35;
      porcNum.value = v;
      porcRange.value = v;
    });
  }

  // ── Confirmación SweetAlert2 + envío ────────────────────────
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submitBtn.disabled) return;

    var nombre   = nombreInput.value.trim();
    var codComp  = PREFIX + codInput.value;
    var dni      = dniInput.value;
    var descuento = IS_REFERIDO
      ? (porcNum ? porcNum.value + '%' : '0%')
      : '35% (fijo)';

    var html = '<div class="text-start" style="font-size:.9rem; line-height:1.8;">'
      + '<div><strong>Nombre:</strong> '   + esc(nombre)   + '</div>'
      + '<div><strong>Código:</strong> '   + esc(codComp)  + '</div>'
      + '<div><strong>DNI / CUIT:</strong> ' + esc(dni)    + '</div>'
      + '<div><strong>Descuento:</strong> ' + esc(descuento) + '</div>'
      + (IS_REFERIDO
        ? '<div class="mt-2" style="color:#78350f;font-size:.82rem;">'
          + '<i class="bi bi-broadcast me-1"></i>Se replicará en todas las sucursales habilitadas.</div>'
        : '')
      + '</div>';

    Swal.fire({
      title: '¿Confirmás la creación?',
      html: html,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, crear',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: IS_REFERIDO ? '#059669' : '#4f46e5',
      cancelButtonColor: '#6c757d',
      reverseButtons: true,
      focusCancel: true,
    }).then(function (result) {
      if (!result.isConfirmed) return;

      // Activar spinner y bloquear botón durante el POST
      btnSpinner.style.display = 'inline-block';
      btnIcon.style.display    = 'none';
      btnText.textContent      = IS_REFERIDO ? 'Replicando a sucursales…' : 'Creando…';
      submitBtn.disabled       = true;
      btnHint.style.display    = 'none';

      form.submit();
    });
  });

  // ── Inicialización ───────────────────────────────────────────
  nombreInput.addEventListener('input', onNombreInput);
  nombreInput.addEventListener('blur',  onNombreBlur);
  dniInput.addEventListener('input',    onDniInput);
  codInput.addEventListener('input',    onCodigoInput);

  updateHiddenCode();

  // Validar código sugerido al cargar (detecta race conditions)
  if (codInput.value.length === 4) {
    setFieldState(codStatusIcon, codFeedback, 'wait', 'Verificando código sugerido…');
    debouncedValidate();
  }

})();
</script>
<?php
$pageScripts = ob_get_clean();
include_once ROOT_PATH . 'templates/footer.php';
?>
