<?php
/**
 * Contactos por área - pública, sin login. Referencia: docs/maquetas/Contactos.dc.html.
 *
 * El mail y el celular de cada SECTOR (no los personales): los atiende todo el equipo y la
 * consulta queda registrada. Un dato que falta se muestra "a dar de alta" o "a definir" en gris
 * itálica, nunca un lugar vacío, para que se note qué falta completar.
 *
 * Cada ficha tiene id="area-<ID>": la Guía de Consultas linkea a #area-<ID>. También acepta
 * ?area=<ID> (por si un link no puede llevar ancla) y lleva el foco a esa ficha.
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Reglas.php';
require_once __DIR__ . '/../Class/Directorio.php';

$dir = new Directorio();
$estado = $dir->estado();
$datos = array('areas' => array(), 'temas' => array());
if ($estado['ok']) {
    try {
        $datos = $dir->datosConsulta();
    } catch (Throwable $ex) {
        error_log('Contactos por área: ' . $ex->getMessage());
        $estado = array('ok' => false, 'faltan' => array(), 'sin_conexion' => true);
    }
}
$areaPedida = isset($_GET['area']) && ctype_digit((string)$_GET['area']) ? (int)$_GET['area'] : null;
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

/** Un dato o su "pendiente" en gris itálica: nunca un lugar vacío. */
$valor = function ($texto, $pendiente) use ($e) {
    return $texto !== null && $texto !== ''
        ? '<div class="contacto-valor">' . $e($texto) . '</div>'
        : '<div class="contacto-valor est-pendiente">' . $e($pendiente) . '</div>';
};

$pagina = array(
    'titulo' => 'Contactos por área',
    'activo' => 'contactos',
    'css'    => array('consulta.css'),
    'js'     => array(),
);
require __DIR__ . '/../partials/cabecera.php';
?>
        <div class="est-encabezado">
            <h1>Contactos por área</h1>
            <p>El mail y el celular de cada sector, no los personales: los atiende todo el equipo y la consulta queda registrada.</p>
        </div>

        <?php require __DIR__ . '/../partials/falta_script.php'; ?>

        <div class="est-grilla-fichas">
            <?php foreach ($datos['areas'] as $a): ?>
            <section class="ficha" id="area-<?= (int)$a['id'] ?>" aria-labelledby="contacto-<?= (int)$a['id'] ?>" tabindex="-1">
                <div class="ficha-banda" style="background: <?= $e($a['color']) ?>"></div>
                <div class="ficha-cabeza">
                    <h2 id="contacto-<?= (int)$a['id'] ?>"><?= $e($a['nombre']) ?></h2>
                    <?php if ($a['resuelve'] !== ''): ?><p><?= $e($a['resuelve']) ?></p><?php endif; ?>
                </div>
                <div class="contacto-datos">
                    <div class="contacto-dato">
                        <div class="est-etiqueta">Mail de sector</div>
                        <?php if ($a['mail'] !== ''): ?>
                            <div class="contacto-valor"><a href="mailto:<?= $e($a['mail']) ?>"><?= $e($a['mail']) ?></a></div>
                        <?php else: ?>
                            <?= $valor('', 'a dar de alta') ?>
                        <?php endif; ?>
                    </div>
                    <div class="contacto-dato">
                        <div class="est-etiqueta">Celular de sector</div>
                        <?php if ($a['celular'] !== ''): ?>
                            <div class="contacto-valor">
                                <?= $e($a['celular']) ?>
                                <?php if ($a['whatsapp']): ?>
                                <a class="est-btn est-btn--icono contacto-wa" href="<?= $e($a['whatsapp']) ?>" target="_blank" rel="noopener"
                                   aria-label="Escribir por WhatsApp a <?= $e($a['nombre']) ?> (se abre en una pestaña nueva)" title="WhatsApp Web">
                                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?= $valor('', 'a dar de alta') ?>
                        <?php endif; ?>
                    </div>
                    <div class="contacto-dato">
                        <div class="est-etiqueta">Horario de atención</div>
                        <?= $valor($a['horario'], 'a dar de alta') ?>
                    </div>
                    <div class="contacto-responsables">
                        <div class="contacto-dato">
                            <div class="est-etiqueta">Gerente</div>
                            <?= $valor($a['gerente'], 'a definir') ?>
                        </div>
                        <div class="contacto-dato">
                            <div class="est-etiqueta">Jefe de área</div>
                            <?= $valor($a['jefe'], 'a definir') ?>
                        </div>
                    </div>
                </div>
                <div class="ficha-pie">
                    <a class="ficha-link" href="<?= $e(ESTRUCTURA_URL_GUIA . '?area=' . (int)$a['id']) ?>">Ver temas y referentes ›</a>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
        <?php if ($areaPedida !== null): ?>
        <script>
            // ?area=<ID>: mismo efecto que el ancla #area-<ID> (llevar la vista y el foco a esa ficha)
            (function () {
                var f = document.getElementById('area-<?= (int)$areaPedida ?>');
                if (f) { f.scrollIntoView(); f.focus({ preventScroll: true }); }
            })();
        </script>
        <?php endif; ?>
<?php require __DIR__ . '/../partials/pie.php'; ?>
