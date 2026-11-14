<?php
/**
 * Reacciones y comentarios de un comunicado, al pie de la tarjeta (partials/comunicado_tarjeta.php).
 * Solo se dibuja si el comunicado tiene alguna de las dos opciones habilitadas.
 * El comportamiento (reaccionar, comentar, borrar, ocultar) vive en recursosHumanos/intranet/assets/intranet_interaccion.js
 * y habla con recursosHumanos/intranet/interaccion.php.
 *
 * Requiere:
 *   $c            comunicado con permite_comentarios, permite_reacciones e interaccion (contadores)
 *   $modo         'completo' (intranet: reacciones y comentarios) o 'reacciones' (aviso al entrar
 *                 al hub: sin comentarios, con el enlace "Ver comentarios en la Intranet")
 * Opcional:
 *   $ejemplo      bool: vista previa de la carga, se ve igual pero no se puede usar
 *   $comentarios  lista de comentarios del comunicado (modo completo)
 *   $moderar      bool: quien mira es responsable de carga
 *   $yo           username de quien mira
 */
require_once dirname(__DIR__) . '/Class/IntranetInteraccion.php';

$ejemplo = !empty($ejemplo);
$comentarios = array_values($comentarios ?? []);
$moderar = !empty($moderar);
$yo = (string)($yo ?? '');
$r = ($c['interaccion'] ?? null) ?: IntranetInteraccion::resumenVacio();
$conReacciones = !empty($c['permite_reacciones']);
$conComentarios = !empty($c['permite_comentarios']);
$idCampo = Intranet::idUnico('com-comentar');
$plegados = max(0, count($comentarios) - IntranetInteraccion::COMENTARIOS_VISIBLES);
?>
<div class="com-inter" data-com-inter data-id="<?= (int)$c['id'] ?>"<?php
    if ($ejemplo): ?> data-ejemplo<?php else: ?> data-url="<?= Intranet::e(Intranet::URL_INTERACCION) ?>" data-csrf="<?= Intranet::e(Intranet::csrf()) ?>"<?php endif; ?>>
    <?php if ($conReacciones): ?>
    <div class="com-reacciones" role="group" aria-label="Reacciones">
        <?php foreach (IntranetInteraccion::REACCIONES as $cod => [$nombre, $icono]):
            $n = (int)($r['reacciones'][$cod] ?? 0);
            $mia = $r['mia'] === $cod; ?>
            <button type="button" class="com-reaccion<?= $mia ? ' es-mia' : '' ?>" data-reaccion="<?= Intranet::e($cod) ?>" data-nombre="<?= Intranet::e($nombre) ?>"
                    aria-pressed="<?= $mia ? 'true' : 'false' ?>" title="<?= Intranet::e($nombre . ': ' . $n) ?>" aria-label="<?= Intranet::e($nombre . ': ' . $n) ?>"<?= $ejemplo ? ' disabled' : '' ?>>
                <i class="fa-solid <?= $icono ?>" aria-hidden="true"></i>
                <span class="com-reaccion-n" aria-hidden="true"><?= $n ?></span>
            </button>
        <?php endforeach; ?>
        <?php if (!$ejemplo): ?>
            <button type="button" class="com-quienes-abrir" data-com-quienes aria-expanded="false"<?= (int)$r['total_reacciones'] === 0 ? ' hidden' : '' ?>>
                Ver quiénes reaccionaron
            </button>
        <?php endif; ?>
        <?php if ($modo === 'reacciones' && $conComentarios): ?>
            <a class="com-inter-ver" href="<?= $ejemplo ? '#' : Intranet::e(Intranet::linkComunicado($c)) ?>">
                <i class="fa-regular fa-comment" aria-hidden="true"></i> Ver comentarios en la Intranet
            </a>
        <?php endif; ?>
    </div>
    <div class="com-inter-aviso" role="status" aria-live="polite" hidden></div>
    <div class="com-quienes" data-com-quienes-panel aria-live="polite" hidden></div>
    <?php elseif ($modo === 'reacciones' && $conComentarios): ?>
    <div class="com-reacciones">
        <a class="com-inter-ver" href="<?= $ejemplo ? '#' : Intranet::e(Intranet::linkComunicado($c)) ?>">
            <i class="fa-regular fa-comment" aria-hidden="true"></i> Ver comentarios en la Intranet
        </a>
    </div>
    <?php endif; ?>

    <?php if ($modo === 'completo' && $conComentarios): ?>
    <section class="com-comentarios" aria-label="Comentarios">
        <h4 class="com-comentarios-titulo">Comentarios (<span data-com-total><?= (int)$r['comentarios'] ?></span>)</h4>
        <?php if ($plegados > 0): ?>
            <button type="button" class="com-ver-todos" data-com-ver-todos>Ver todos los comentarios</button>
        <?php endif; ?>
        <ol class="com-comentarios-lista" data-com-lista>
            <?php foreach ($comentarios as $i => $k): ?>
                <?= Intranet::plantilla('comunicado_comentario', ['k' => $k, 'moderar' => $moderar, 'yo' => $yo, 'plegado' => $i < $plegados]) ?>
            <?php endforeach; ?>
        </ol>
        <form class="com-comentar" data-com-comentar novalidate>
            <label class="visually-hidden" for="<?= $idCampo ?>">Escribí un comentario</label>
            <textarea class="form-control" id="<?= $idCampo ?>" name="texto" rows="2" maxlength="<?= IntranetInteraccion::MAX_COMENTARIO ?>"
                      placeholder="<?= $ejemplo ? 'Acá se va a poder comentar cuando esté publicado' : 'Escribí un comentario…' ?>"<?= $ejemplo ? ' disabled' : '' ?>></textarea>
            <div class="com-comentar-pie">
                <span class="com-comentar-contador" data-com-contador>0 / <?= IntranetInteraccion::MAX_COMENTARIO ?></span>
                <button type="submit" class="intra-btn intra-btn-primario" disabled><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Comentar</button>
            </div>
            <div class="com-comentar-error" role="alert" hidden></div>
        </form>
    </section>
    <?php endif; ?>
</div>
