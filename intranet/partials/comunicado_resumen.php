<?php
/**
 * Resumen de un comunicado en la columna "Intranet XL" del hub: ÚNICA plantilla para la columna
 * y para la solapa "Columna del hub" de la vista previa. Se usa con
 * Intranet::plantilla('comunicado_resumen', ['c' => $c, 'href' => ..., 'resaltar' => bool]).
 *
 * Nunca muestra el carrusel: si hay imágenes, solo la miniatura de la portada al costado del
 * título; si hay PDF, un clip al lado del título.
 * Comentarios y reacciones: solo los contadores mayores que cero, sin poder usarlos.
 */
$c = $c + Intranet::COMUNICADO_V2;
$href = $href ?? Intranet::linkComunicado($c);
$portada = Intranet::portada($c);
$clip = !empty($c['pdfs'])
    ? ' <i class="fa-solid fa-paperclip intra-com-clip" title="Tiene adjuntos" aria-label="Tiene adjuntos"></i>'
    : '';
$inter = Intranet::conInteraccionHabilitada($c) ? ($c['interaccion'] ?? null) : null;
$nReacciones = $inter && !empty($c['permite_reacciones']) ? (int)$inter['total_reacciones'] : 0;
$nComentarios = $inter && !empty($c['permite_comentarios']) ? (int)$inter['comentarios'] : 0;
// $resaltar (opcional, vista previa): marca los datos a completar [Nombre del local] del título
$tituloHtml = Intranet::e($c['titulo']);
if (!empty($resaltar)) {
    $tituloHtml = IntranetFormato::resaltarDatos($tituloHtml);
}
?>
<a href="<?= Intranet::e($href) ?>" class="intra-com<?= Intranet::clasesComunicado($c) ?>">
    <div class="intra-com-meta">
        <span class="intra-tag intra-tag-cat"><?= Intranet::e(Intranet::categoria($c['categoria'])) ?></span>
        <?php if (!empty($c['fijado'])): ?><span class="intra-tag intra-tag-fijado"><i class="fa-solid fa-thumbtack"></i> Fijado</span><?php endif; ?>
        <?= Intranet::etiquetaEstilo($c) ?>
        <?php if (!empty($c['es_nuevo'])): ?><span class="intra-tag intra-tag-nuevo">Nuevo</span><?php endif; ?>
        <?php if (!empty($c['automatico'])): ?><span class="intra-tag intra-tag-auto">Automático</span><?php endif; ?>
        <span><?= Intranet::e(Intranet::haceTiempo($c['fecha'])) ?></span>
    </div>
    <?php if ($portada): ?>
        <div class="intra-com-fila">
            <div class="intra-com-titulo"><?= $tituloHtml ?><?= $clip ?></div>
            <img class="intra-com-mini" src="<?= Intranet::e($portada['url_mini']) ?>" alt="" loading="lazy">
        </div>
    <?php else: ?>
        <div class="intra-com-titulo"><?= $tituloHtml ?><?= $clip ?></div>
    <?php endif; ?>
    <?php if ($nReacciones > 0 || $nComentarios > 0): ?>
        <div class="intra-com-inter">
            <?php if ($nReacciones > 0): ?>
                <span title="<?= $nReacciones === 1 ? '1 reacción' : $nReacciones . ' reacciones' ?>"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i> <?= $nReacciones ?><span class="visually-hidden"> <?= $nReacciones === 1 ? 'reacción' : 'reacciones' ?></span></span>
            <?php endif; ?>
            <?php if ($nComentarios > 0): ?>
                <span title="<?= $nComentarios === 1 ? '1 comentario' : $nComentarios . ' comentarios' ?>"><i class="fa-solid fa-comment" aria-hidden="true"></i> <?= $nComentarios ?><span class="visually-hidden"> <?= $nComentarios === 1 ? 'comentario' : 'comentarios' ?></span></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</a>
