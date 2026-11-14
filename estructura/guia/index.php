<?php
/**
 * Guía de Consultas - pública, sin login. Referencia: docs/maquetas/Consultas.dc.html.
 *
 * Los locales escriben lo que les pasa y ven qué área y qué persona lo resuelve. Las áreas y los
 * temas activos se mandan una sola vez en la página y la búsqueda corre en el navegador
 * (assets/normalizar.js, la misma regla que Reglas.php), para que el resultado aparezca mientras
 * se escribe. Las fichas se dibujan acá (sin JS se ven igual); el JS sólo filtra y busca.
 *
 *   ?q=<texto>   abre con esa búsqueda hecha (la tarjeta del inicio de XL Gestión)
 *   ?area=<id>   abre con esa área elegida ("Ver temas y referentes" de Contactos por área)
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Reglas.php';
require_once __DIR__ . '/../Class/Directorio.php';

/** Chips "Probá con:" (definidos por negocio) */
$ejemplos = array('Me falta mercadería', 'No imprime', 'Cuándo llega mi pedido', 'Olvidé la contraseña', 'Precio mal cargado', 'Error en la app');

$dir = new Directorio();
$estado = $dir->estado();
$datos = array('areas' => array(), 'temas' => array());
if ($estado['ok']) {
    try {
        $datos = $dir->datosConsulta();
    } catch (Throwable $ex) {
        error_log('Guía de Consultas: ' . $ex->getMessage());
        $estado = array('ok' => false, 'faltan' => array(), 'sin_conexion' => true);
    }
}

$q = isset($_GET['q']) ? mb_substr(trim((string)$_GET['q']), 0, Reglas::LARGO_BUSQUEDA, 'UTF-8') : '';
$areaElegida = null;
foreach ($datos['areas'] as $a) {
    if (isset($_GET['area']) && (string)$a['id'] === (string)$_GET['area']) {
        $areaElegida = $a['id'];
    }
}
$temasPorArea = array();
foreach ($datos['temas'] as $t) {
    $temasPorArea[$t['id_area']][] = $t;
}
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$estructuraJsExtra = array(
    'reglas'        => Reglas::configJs(),
    'guia'          => $datos,
    'ajaxConsulta'  => ESTRUCTURA_URL_AJAX_CONSULTA,
    'contactos'     => ESTRUCTURA_URL_CONTACTOS,
);
$pagina = array(
    'titulo' => 'Guía de Consultas',
    'activo' => 'guia',
    'css'    => array('consulta.css'),
    'js'     => array('normalizar.js', 'guia.js'),
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado">
            <h1>Guía de Consultas</h1>
            <p>Buscá por lo que te pasa o elegí un área. El interno y el celular de cada persona están en Internos.</p>
        </div>

        <?php require __DIR__ . '/../partials/falta_script.php'; ?>

        <form class="est-panel guia-buscador" role="search" id="guiaForm" action="" method="get">
            <label for="q" class="guia-buscador-etiqueta">¿Qué necesitás consultar?</label>
            <div class="guia-buscador-caja">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="q" name="q" type="search" autocomplete="off" value="<?= $e($q) ?>"
                       placeholder="Ej.: me falta mercadería, no imprime, olvidé la contraseña"
                       aria-describedby="guiaResumen">
                <button type="button" class="guia-borrar" id="guiaBorrar" aria-label="Borrar búsqueda"<?= $q === '' ? ' hidden' : '' ?>>Borrar</button>
            </div>
            <div class="est-chips">
                <span class="est-etiqueta">Probá con:</span>
                <?php foreach ($ejemplos as $ej): ?>
                    <button type="button" class="est-chip est-chip--suave" data-ejemplo="<?= $e($ej) ?>"><?= $e($ej) ?></button>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if ($datos['areas']): ?>
        <div class="est-chips" role="group" aria-label="Filtrar por área" id="guiaAreas">
            <button type="button" class="est-chip" data-area="" aria-pressed="<?= $areaElegida === null ? 'true' : 'false' ?>">Todas las áreas</button>
            <?php foreach ($datos['areas'] as $a): ?>
                <button type="button" class="est-chip" data-area="<?= (int)$a['id'] ?>" aria-pressed="<?= $areaElegida === $a['id'] ? 'true' : 'false' ?>"><?= $e($a['nombre']) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section id="guiaResultados" class="guia-resultados" hidden aria-labelledby="guiaResumen">
            <div id="guiaResumen" class="guia-resumen" aria-live="polite"></div>
            <div id="guiaLista" class="guia-lista"></div>
            <div id="guiaSinResultados" class="est-vacio" hidden>
                <strong>No encontramos esa consulta.</strong> Probá con otras palabras o elegí un área arriba.<br>
                La búsqueda queda registrada para sumar el tema al mapa.
            </div>
        </section>

        <div id="guiaFichas" class="est-grilla-fichas">
            <?php foreach ($datos['areas'] as $a): ?>
            <section class="ficha" data-area="<?= (int)$a['id'] ?>" aria-labelledby="ficha-<?= (int)$a['id'] ?>"<?= ($areaElegida !== null && $areaElegida !== $a['id']) ? ' hidden' : '' ?>>
                <div class="ficha-banda" style="background: <?= $e($a['color']) ?>"></div>
                <div class="ficha-cabeza">
                    <h2 id="ficha-<?= (int)$a['id'] ?>"><?= $e($a['nombre']) ?></h2>
                    <?php if ($a['resuelve'] !== ''): ?><p><?= $e($a['resuelve']) ?></p><?php endif; ?>
                </div>
                <table class="ficha-temas">
                    <thead>
                        <tr><th scope="col">Tema</th><th scope="col">Referente</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (isset($temasPorArea[$a['id']]) ? $temasPorArea[$a['id']] : array() as $t): ?>
                        <tr>
                            <td><?= $e($t['tema']) ?></td>
                            <?php if ($t['referente'] !== null): ?>
                                <td class="ficha-ref"><?= $e($t['referente']) ?></td>
                            <?php else: ?>
                                <td class="ficha-ref est-pendiente">A definir</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($temasPorArea[$a['id']])): ?>
                        <tr><td colspan="2" class="est-pendiente">Temas a definir</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="ficha-pie">
                    <?php if ($a['mail'] !== ''): ?>
                        <a class="ficha-mail" href="mailto:<?= $e($a['mail']) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> <?= $e($a['mail']) ?></a>
                    <?php else: ?>
                        <span class="est-pendiente">Mail de sector a dar de alta</span>
                    <?php endif; ?>
                    <a class="ficha-link" href="<?= $e(ESTRUCTURA_URL_CONTACTOS) ?>#area-<?= (int)$a['id'] ?>">Celular, horario y responsables del área ›</a>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
<?php require __DIR__ . '/../partials/pie.php'; ?>
