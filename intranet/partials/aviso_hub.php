<?php
/**
 * Contenido de la ventana "aviso al entrar al hub": ÚNICA plantilla para el hub (hub/index.php,
 * dentro de un .modal-content) y para la solapa "Aviso al entrar" de la vista previa de la carga.
 * Se usa con Intranet::plantilla('aviso_hub', ['avisos' => [...], 'previa' => bool]).
 *
 * Un paso por comunicado, con la tarjeta completa (sin "Leer más"). El navegador
 * (intranet_comunicados.js) quita los que ya se vieron hoy, muestra de a uno con "Siguiente" y
 * "1 de 3", y registra cada uno al mostrarlo. En la vista previa los botones no hacen nada.
 * Si el comunicado tiene reacciones, se puede reaccionar desde la ventana (intranet_interaccion.js);
 * los comentarios no se muestran: solo el enlace "Ver comentarios en la Intranet".
 */
$previa = !empty($previa);
?>
<div class="modal-header intra-aviso-cabecera">
    <h2 class="modal-title h6 fw-bold" id="avisoHubTitulo"><i class="fa-solid fa-bell"></i> Aviso importante</h2>
    <span class="intra-aviso-contador" data-aviso-contador<?= count($avisos) < 2 ? ' hidden' : '' ?>>1 de <?= count($avisos) ?></span>
    <button type="button" class="btn-close"<?= $previa ? ' disabled' : ' data-bs-dismiss="modal"' ?> aria-label="Cerrar"></button>
</div>
<div class="modal-body intra-aviso-cuerpo">
    <?php foreach (array_values($avisos) as $i => $c): ?>
    <div class="intra-aviso-paso" data-aviso-paso data-clave="<?= Intranet::e(Intranet::claveAviso($c)) ?>"
         data-link="<?= Intranet::e(Intranet::linkComunicado($c)) ?>"<?= $i > 0 ? ' hidden' : '' ?>>
        <?= Intranet::plantilla('comunicado_tarjeta', ['c' => $c, 't' => [
            'clases' => 'mb-0 intra-aviso-tarjeta', 'plegable' => false,
            // Reacciones sí (se puede reaccionar), comentarios no: la ventana no crece
            'interaccion' => 'reacciones', 'ejemplo' => $previa, 'resaltar' => $previa,
        ]]) ?>
    </div>
    <?php endforeach; ?>
</div>
<div class="modal-footer">
    <a class="intra-btn" data-aviso-ver href="<?= $previa ? '#' : Intranet::e(Intranet::linkComunicado($avisos[0])) ?>">
        <i class="fa-solid fa-arrow-right"></i> Ver en la Intranet
    </a>
    <button type="button" class="intra-btn intra-btn-primario" data-aviso-siguiente<?= count($avisos) < 2 ? ' hidden' : '' ?>>
        Siguiente <i class="fa-solid fa-chevron-right"></i>
    </button>
    <button type="button" class="intra-btn intra-btn-primario" data-aviso-entendido<?= $previa ? '' : ' data-bs-dismiss="modal"' ?><?= count($avisos) >= 2 ? ' hidden' : '' ?>>
        <i class="fa-solid fa-check"></i> Entendido
    </button>
</div>
