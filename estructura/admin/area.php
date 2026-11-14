<?php
/**
 * Ficha de un área: Editar (?id=N) y Nueva área (sin id). Requiere el permiso de administración.
 * Referencia: docs/maquetas/Admin.dc.html.
 *
 * Los datos del área se dibujan acá; la tabla de temas la arma assets/admin_ficha.js (se agregan,
 * mueven y quitan filas). "Guardar ficha" manda todo junto a ajax/admin.php, que valida
 * (Class/Ficha.php) y guarda en una transacción: si algo no pasa, no se guarda nada y cada campo
 * muestra su motivo.
 *
 * Personas (gerente, jefe y referentes): se eligen entre las ACTIVAS de Internos. Si la ficha
 * tenía una persona que después se dio de baja, se ve con el aviso "… ya no está activa en Internos".
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Permisos.php';
require_once __DIR__ . '/../Class/Reglas.php';
require_once __DIR__ . '/../Class/Directorio.php';

Auth::exigir(ESTRUCTURA_PERMISO_ADMINISTRAR);

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
if (!Permisos::puedeEditarArea($id)) {
    http_response_code(403);
    exit('Tu usuario no tiene permiso para editar esta área.');
}

$dir = new Directorio();
$estado = $dir->estado();
$area = null;
$personas = array();
$noEncontrada = false;
if ($estado['ok']) {
    try {
        $personas = $dir->personas();
        if ($id !== null) {
            $area = $dir->area($id);
            $noEncontrada = !$area || !$area['activa'];
        }
    } catch (Throwable $ex) {
        error_log('Ficha de área: ' . $ex->getMessage());
        $estado = array('ok' => false, 'faltan' => array(), 'sin_conexion' => true);
    }
}

/** Persona elegida para el JS: id, nombre y el aviso si ya no está activa. */
$persona = function ($idPersona) use ($personas) {
    if ($idPersona === null) {
        return null;
    }
    return array(
        'id' => (int)$idPersona,
        'nombre' => isset($personas[$idPersona]) ? $personas[$idPersona]['nombre'] : '(ya no está en Internos)',
        'aviso' => Reglas::avisoPersona($idPersona, $personas),
    );
};

$ficha = array('id' => null, 'nombre' => '', 'color' => Reglas::PALETA[0], 'resuelve' => '', 'mail' => '', 'celular' => '',
    'horario' => '', 'gerente' => null, 'jefe' => null, 'temas' => array(), 'quitados' => array());
if ($area && !$noEncontrada) {
    $ficha = array(
        'id' => $area['id'], 'nombre' => $area['nombre'], 'color' => strtoupper($area['color']), 'resuelve' => $area['resuelve'],
        'mail' => $area['mail_sector'], 'celular' => $area['celular_sector'] !== '' ? Reglas::formatearCelular($area['celular_sector']) : '',
        'horario' => $area['horario'], 'gerente' => $persona($area['id_gerente']), 'jefe' => $persona($area['id_jefe']),
        'temas' => array(), 'quitados' => $area['temas_quitados'],
    );
    foreach ($area['temas'] as $t) {
        $ficha['temas'][] = array('id' => $t['id'], 'tema' => $t['tema'], 'equipo' => $t['referente_equipo'],
            'referente' => $t['referente_equipo'] ? null : $persona($t['id_referente']), 'palabras_clave' => $t['palabras_clave']);
    }
}
$activas = array();
foreach ($personas as $pid => $p) {
    if ($p['activo']) {
        $activas[] = array('id' => $pid, 'nombre' => $p['nombre'], 'sector' => $p['sector']);
    }
}
$paleta = Reglas::PALETA;
if (!in_array($ficha['color'], $paleta, true)) {
    $paleta[] = $ficha['color'];   // un color importado que no está en la paleta se puede conservar
}
$esNueva = $ficha['id'] === null;
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$estructuraJsExtra = array(
    'ajaxAdmin' => ESTRUCTURA_URL_AJAX_ADMIN,
    'csrf'      => Permisos::csrf(),
    'ficha'     => $ficha,
    'personas'  => $activas,
    'urlFicha'  => ESTRUCTURA_URL_ADMIN_AREA,
    'reglas'    => Reglas::configJs(),
);
$pagina = array(
    'titulo' => $esNueva ? 'Nueva área' : 'Ficha de ' . $ficha['nombre'],
    'activo' => 'admin',
    'css'    => array('admin.css'),
    'js'     => array('normalizar.js', 'admin_comun.js', 'admin_ficha.js'),
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado">
            <a class="adm-volver" href="<?= $e(ESTRUCTURA_URL_ADMIN) ?>">‹ Áreas del Directorio</a>
            <h1><?= $esNueva ? 'Nueva área' : $e($ficha['nombre']) ?></h1>
            <p>Los cambios se ven al guardar en la Guía de Consultas y en Contactos por área.</p>
        </div>

        <?php require __DIR__ . '/../partials/falta_script.php'; ?>
        <?php if (isset($_GET['guardada']) && !$esNueva): ?>
            <div class="est-aviso est-aviso--ok" role="status">Ficha guardada.</div>
        <?php endif; ?>
        <div id="admMensaje" class="est-aviso est-aviso--error" role="alert" hidden></div>

        <?php if ($noEncontrada): ?>
            <div class="est-aviso est-aviso--error" role="alert">
                Esa área no existe o está desactivada. Si está desactivada, reactivala desde el <a href="<?= $e(ESTRUCTURA_URL_ADMIN) ?>">listado</a>.
            </div>
        <?php elseif ($estado['ok']): ?>

        <datalist id="personasActivas">
            <?php foreach ($activas as $p): ?>
                <option value="<?= $e($p['nombre']) ?>"><?= $e($p['sector']) ?></option>
            <?php endforeach; ?>
        </datalist>

        <form id="fichaForm" class="adm-ficha" novalidate>
            <section class="est-panel" aria-labelledby="datosTitulo">
                <h2 id="datosTitulo" class="adm-subtitulo">Datos del área</h2>
                <div class="adm-campos">
                    <div class="adm-campo adm-campo--ancho">
                        <label for="f_nombre" class="form-label">Nombre del área</label>
                        <input type="text" class="form-control" id="f_nombre" name="nombre" maxlength="<?= Reglas::LARGO_NOMBRE_AREA ?>"
                               value="<?= $e($ficha['nombre']) ?>" required>
                        <div class="invalid-feedback" data-error="nombre"></div>
                    </div>

                    <fieldset class="adm-campo adm-campo--ancho">
                        <legend class="form-label">Color</legend>
                        <div class="adm-colores">
                            <?php foreach ($paleta as $i => $c): ?>
                                <label class="adm-color" title="<?= $e($c) ?>">
                                    <input type="radio" name="color" value="<?= $e($c) ?>"<?= $c === $ficha['color'] ? ' checked' : '' ?>
                                           aria-label="Color <?= $i + 1 ?> (<?= $e($c) ?>)">
                                    <span style="background: <?= $e($c) ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="invalid-feedback" data-error="color"></div>
                    </fieldset>

                    <div class="adm-campo adm-campo--ancho">
                        <label for="f_resuelve" class="form-label">Qué resuelve el área (una línea)</label>
                        <input type="text" class="form-control" id="f_resuelve" name="resuelve" maxlength="<?= Reglas::LARGO_RESUELVE ?>"
                               value="<?= $e($ficha['resuelve']) ?>" placeholder="Ej.: Pedidos, reposición, stock y precios de los locales.">
                        <div class="invalid-feedback" data-error="resuelve"></div>
                    </div>

                    <div class="adm-campo">
                        <label for="f_mail" class="form-label">Mail de sector</label>
                        <input type="email" class="form-control" id="f_mail" name="mail" maxlength="<?= Reglas::LARGO_MAIL ?>"
                               value="<?= $e($ficha['mail']) ?>" placeholder="sector@xl.com.ar">
                        <div class="invalid-feedback" data-error="mail"></div>
                    </div>

                    <div class="adm-campo">
                        <label for="f_celular" class="form-label">Celular de sector</label>
                        <input type="tel" class="form-control" id="f_celular" name="celular" inputmode="tel"
                               value="<?= $e($ficha['celular']) ?>" placeholder="11 2345 6789" aria-describedby="f_celular_ayuda">
                        <div id="f_celular_ayuda" class="form-text">Con código de área, sin 0 ni 15.</div>
                        <div class="invalid-feedback" data-error="celular"></div>
                    </div>

                    <div class="adm-campo">
                        <label for="f_horario" class="form-label">Horario de atención</label>
                        <input type="text" class="form-control" id="f_horario" name="horario" maxlength="<?= Reglas::LARGO_HORARIO ?>"
                               value="<?= $e($ficha['horario']) ?>" placeholder="Ej.: Lunes a viernes de 9 a 18">
                        <div class="invalid-feedback" data-error="horario"></div>
                    </div>

                    <div class="adm-campo">
                        <label for="f_gerente" class="form-label">Gerente de área</label>
                        <input type="text" class="form-control" id="f_gerente" list="personasActivas" autocomplete="off"
                               data-persona="gerente" placeholder="Buscá en Internos (opcional)" aria-describedby="f_gerente_aviso">
                        <div class="adm-aviso-persona" id="f_gerente_aviso" aria-live="polite"></div>
                        <div class="invalid-feedback" data-error="id_gerente"></div>
                    </div>

                    <div class="adm-campo">
                        <label for="f_jefe" class="form-label">Jefe de área</label>
                        <input type="text" class="form-control" id="f_jefe" list="personasActivas" autocomplete="off"
                               data-persona="jefe" placeholder="Buscá en Internos (opcional)" aria-describedby="f_jefe_aviso">
                        <div class="adm-aviso-persona" id="f_jefe_aviso" aria-live="polite"></div>
                        <div class="invalid-feedback" data-error="id_jefe"></div>
                    </div>
                </div>
                <p class="form-text mb-0">Las personas se eligen de Internos: si alguien cambia de interno o se va, la ficha se actualiza sola.</p>
            </section>

            <section class="est-panel" aria-labelledby="temasTitulo">
                <div class="adm-temas-cabeza">
                    <h2 id="temasTitulo" class="adm-subtitulo">Temas</h2>
                    <button type="button" class="est-btn" id="agregarTema"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar tema</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle adm-temas">
                        <thead>
                            <tr>
                                <th scope="col">Tema</th>
                                <th scope="col">Referente (de Internos)</th>
                                <th scope="col">Palabras con que lo buscan los locales</th>
                                <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody id="temasCuerpo"></tbody>
                    </table>
                </div>
                <p class="est-pendiente mb-0" id="temasVacio" hidden>Todavía no hay temas. Agregá el primero.</p>
                <p class="form-text mb-0">Palabras clave separadas por coma. Quitar un tema lo da de baja (no se borra) al guardar.</p>

                <?php if ($ficha['quitados']): ?>
                <details class="adm-quitados">
                    <summary>Temas quitados (<?= count($ficha['quitados']) ?>)</summary>
                    <ul>
                        <?php foreach ($ficha['quitados'] as $q): ?>
                        <li>
                            <span><?= $e($q['tema']) ?></span>
                            <span class="est-etiqueta">quitado <?= $e($q['fecha_baja']) ?><?= $q['usuario_baja'] !== '' ? ' por ' . $e($q['usuario_baja']) : '' ?></span>
                            <button type="button" class="est-btn est-btn--chico" data-reactivar-tema="<?= (int)$q['id'] ?>">Reactivar</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </section>

            <div class="adm-guardar">
                <span class="est-etiqueta">
                    <?php if (!$esNueva): ?>
                        Última actualización: <?= $e($area['fecha_modif']) ?> por <?= $e($area['usuario_modif']) ?>
                    <?php endif; ?>
                </span>
                <div class="adm-botones">
                    <a class="est-btn" href="<?= $e(ESTRUCTURA_URL_ADMIN) ?>">Cancelar</a>
                    <button type="submit" class="est-btn est-btn--primario" id="guardarFicha">Guardar ficha</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
<?php require __DIR__ . '/../partials/pie.php'; ?>
