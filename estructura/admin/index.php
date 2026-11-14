<?php
/**
 * Administrar áreas - requiere sesión del hub con el permiso estructura.administrar.
 * Referencia: docs/maquetas/Areas.dc.html, SIN la columna "Responsables de la ficha" (es de una
 * etapa siguiente).
 *
 * - Listado de áreas (activas primero) con temas, ficha completa, estado, última actualización
 *   y acciones (editar, subir, bajar, desactivar, reactivar).
 * - Búsquedas sin resultado de los últimos 30 días (en la maqueta está en la ficha; va acá porque
 *   una búsqueda sin resultado no tiene área).
 * - Importar listado (Excel): plantilla con los datos actuales, vista previa y confirmación.
 *
 * Auth::exigir() redirige al login del hub si no hay sesión y corta con 403 si no hay permiso,
 * aunque se escriba la dirección a mano. Cada acción vuelve a validar en ajax/admin.php.
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Permisos.php';
require_once __DIR__ . '/../Class/Reglas.php';
require_once __DIR__ . '/../Class/Directorio.php';

Auth::exigir(ESTRUCTURA_PERMISO_ADMINISTRAR);

$dir = new Directorio();
$estado = $dir->estado();
$areas = array();
$busquedas = array();
$personas = array();
if ($estado['ok']) {
    try {
        $personas = $dir->personas();
        $areas = $dir->areas(false);
        $busquedas = $dir->busquedasSinResultado(30);
    } catch (Throwable $ex) {
        error_log('Administrar áreas: ' . $ex->getMessage());
        $estado = array('ok' => false, 'faltan' => array(), 'sin_conexion' => true);
    }
}
$permisoRegistrado = Permisos::permisoRegistrado();
$activas = array_values(array_filter($areas, function ($a) { return $a['activa']; }));
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$estructuraJsExtra = array(
    'ajaxAdmin' => ESTRUCTURA_URL_AJAX_ADMIN,
    'csrf'      => Permisos::csrf(),
);
$pagina = array(
    'titulo' => 'Administrar áreas',
    'activo' => 'admin',
    'css'    => array('admin.css'),
    'js'     => array('admin_comun.js', 'admin_areas.js', 'admin_importar.js'),
    'libs'   => array('xlsx'),   // SheetJS: leer el Excel y generar la plantilla (la misma versión que Internos)
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado adm-encabezado">
            <div>
                <h1>Áreas del Directorio</h1>
                <p>Alta, baja y orden de las áreas, y la ficha de cada una. Lo que se guarda se ve al instante en la Guía de Consultas y en Contactos por área.</p>
            </div>
            <?php if ($estado['ok']): ?>
            <div class="adm-botones">
                <button type="button" class="est-btn" id="abrirImportar" aria-expanded="false" aria-controls="importar">
                    <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Importar listado
                </button>
                <a class="est-btn est-btn--primario" href="<?= $e(ESTRUCTURA_URL_ADMIN_AREA) ?>">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva área
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php require __DIR__ . '/../partials/falta_script.php'; ?>
        <?php if ($permisoRegistrado === false): ?>
        <div class="est-aviso" role="status">
            Falta correr <code>estructura/sql/04_estructura_permiso.sql</code> (en XL-APPS): hasta entonces sólo
            entra el administrador total y el permiso no se puede asignar a los roles.
        </div>
        <?php endif; ?>
        <div id="admMensaje" class="est-aviso" role="alert" hidden></div>

        <?php if ($estado['ok']): ?>
        <?php require __DIR__ . '/../partials/admin_importar.php'; ?>

        <div class="est-panel adm-panel-tabla">
            <div class="table-responsive">
                <table class="table align-middle mb-0 adm-tabla">
                    <caption class="visually-hidden">Áreas del Directorio</caption>
                    <thead>
                        <tr>
                            <th scope="col">Orden</th>
                            <th scope="col">Área</th>
                            <th scope="col" class="text-center">Temas</th>
                            <th scope="col">Ficha completa</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Última actualización</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($areas as $a):
                        $ficha = Reglas::fichaCompleta($a, $a['temas'], $personas);
                        $posicion = array_search($a, $activas, true); ?>
                        <tr class="<?= $a['activa'] ? '' : 'adm-inactiva' ?>">
                            <td><?= $a['activa'] ? (int)$posicion + 1 : '—' ?></td>
                            <td>
                                <span class="adm-area"><span class="est-punto" style="background: <?= $e($a['color']) ?>"></span><?= $e($a['nombre']) ?></span>
                            </td>
                            <td class="text-center"><?= count($a['temas']) ?></td>
                            <td>
                                <span class="est-badge <?= $ficha['completa'] ? 'est-badge--ok' : 'est-badge--warn' ?>"><?= $e($ficha['texto']) ?></span>
                            </td>
                            <td>
                                <span class="est-badge <?= $a['activa'] ? 'est-badge--ok' : 'est-badge--off' ?>"><?= $a['activa'] ? 'Activa' : 'Desactivada' ?></span>
                            </td>
                            <td class="adm-actualizacion">
                                <?= $e($a['fecha_modif']) ?><br><span class="est-etiqueta"><?= $e($a['usuario_modif']) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="adm-acciones">
                                <?php if ($a['activa']): ?>
                                    <a class="est-btn est-btn--chico" href="<?= $e(ESTRUCTURA_URL_ADMIN_AREA . '?id=' . $a['id']) ?>">Editar</a>
                                    <button type="button" class="est-btn est-btn--chico est-btn--icono" data-accion="moverArea" data-direccion="subir"
                                            data-id="<?= (int)$a['id'] ?>" aria-label="Subir <?= $e($a['nombre']) ?>"<?= $posicion === 0 ? ' disabled' : '' ?>>
                                        <i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                    <button type="button" class="est-btn est-btn--chico est-btn--icono" data-accion="moverArea" data-direccion="bajar"
                                            data-id="<?= (int)$a['id'] ?>" aria-label="Bajar <?= $e($a['nombre']) ?>"<?= $posicion === count($activas) - 1 ? ' disabled' : '' ?>>
                                        <i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                                    <button type="button" class="est-btn est-btn--chico est-btn--peligro" data-accion="desactivarArea" data-id="<?= (int)$a['id'] ?>"
                                            data-confirmar="¿Desactivar el área &quot;<?= $e($a['nombre']) ?>&quot;? Deja de verse en la Guía de Consultas y en Contactos por área. Se puede reactivar.">Desactivar</button>
                                <?php else: ?>
                                    <button type="button" class="est-btn est-btn--chico" data-accion="reactivarArea" data-id="<?= (int)$a['id'] ?>"
                                            data-confirmar="¿Reactivar el área &quot;<?= $e($a['nombre']) ?>&quot;? Vuelve a verse en la consulta, al final del orden.">Reactivar</button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$areas): ?>
                        <tr><td colspan="7" class="est-pendiente">Todavía no hay áreas. Creá una con "Nueva área" o importá el listado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="est-panel" aria-labelledby="busqTitulo">
            <h2 id="busqTitulo" class="adm-subtitulo">Búsquedas sin resultado</h2>
            <p class="adm-ayuda">Lo que buscaron en la Guía de Consultas en los últimos 30 días y no encontró ningún tema, de lo más
                buscado a lo menos. Sumalo como palabra clave o como tema nuevo; si es un error de tipeo, descartalo
                (no se borra: se esconde del panel). Esta lista es también la base para entrenar el chatbot de la etapa 2.</p>
            <?php if ($busquedas): ?>
            <ul class="adm-busquedas">
                <?php foreach ($busquedas as $b): ?>
                <li>
                    <span class="adm-busqueda-texto">"<?= $e($b['ejemplo']) ?>"</span>
                    <span class="est-etiqueta"><?= (int)$b['veces'] ?> <?= $b['veces'] === 1 ? 'vez' : 'veces' ?> · última: <?= $e($b['ultima']) ?></span>
                    <button type="button" class="est-btn est-btn--chico" data-accion="descartarBusqueda" data-normalizado="<?= $e($b['normalizado']) ?>"
                            aria-label="Descartar la búsqueda <?= $e($b['ejemplo']) ?>">Descartar</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="est-pendiente mb-0">No hay búsquedas sin resultado en los últimos 30 días.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
<?php require __DIR__ . '/../partials/pie.php'; ?>
