<?php
/**
 * Tarjeta de comunicado: ÚNICA plantilla para la intranet, el aviso al entrar al hub y la vista
 * previa de la carga, para que no puedan diferir. Se usa con Intranet::plantilla('comunicado_tarjeta', [...]).
 *
 * Requiere:
 *   $c  comunicado (fila de Intranet::comunicadosVigentes o armada igual, con imagenes y pdfs)
 * Opcional:
 *   $t  ['id' => atributo id, 'clases' => clases extra, 'estilo' => atributo style,
 *        'plegable' => bool ("Leer más"; por defecto sí),
 *        'interaccion' => null | 'completo' | 'reacciones' (bloque de reacciones y comentarios al
 *                         pie, solo si el comunicado lo tiene habilitado; ver comunicado_interaccion.php),
 *        'ejemplo' => bool (vista previa: el bloque no se puede usar),
 *        'comentarios' => lista, 'moderar' => bool, 'yo' => username,
 *        'resaltar' => bool (vista previa: marca los datos a completar [Nombre del local])]
 *
 * Esquemas (dónde van las imágenes; ver Intranet::ESQUEMAS):
 *   SIN_IMAGEN  meta, título, texto                      (igual que antes de esta versión)
 *   ARRIBA      imágenes, meta, título, texto
 *   IZQUIERDA / DERECHA  imágenes y cuerpo lado a lado (40/60); en celular, imagen arriba
 *   CENTRO      meta, título, texto, imágenes, texto inferior
 *   ABAJO       meta, título, texto, imágenes
 *   FLYER       imagen, meta, título y un texto corto
 * "Leer más" pliega solo el texto (en CENTRO, el de abajo; en FLYER no hay). Las imágenes
 * siempre se ven. Con más de una imagen: carrusel (lo arranca recursosHumanos/intranet/assets/intranet_comunicados.js).
 */
$c = $c + Intranet::COMUNICADO_V2;
$t = ($t ?? []) + ['id' => '', 'clases' => '', 'estilo' => '', 'plegable' => true,
                   'interaccion' => null, 'ejemplo' => false, 'comentarios' => [], 'moderar' => false, 'yo' => '',
                   'resaltar' => false];

$esquema   = Intranet::esquemaEfectivo($c);
$imagenes  = $esquema === 'SIN_IMAGEN' ? [] : array_values($c['imagenes']);
$pdfs      = $c['pdfs'] ?? [];
$formato   = !empty($c['con_formato']);
$link      = Intranet::linkSeguro((string)($c['link_url'] ?? ''));
$nuevaPestana = $link && ($link['externo'] || !empty($c['automatico']));
$textoSup  = trim((string)$c['texto']);
$textoInf  = $esquema === 'CENTRO' ? trim((string)$c['texto_inferior']) : '';
$plegar    = $t['plegable'] && $esquema !== 'FLYER';

$resaltar = !empty($t['resaltar']);
$texto = function (string $s, bool $plegable) use ($formato, $resaltar) {
    if ($s === '') {
        return;
    }
    echo '<div class="com-texto' . ($formato ? ' con-formato' : '') . ($plegable ? ' com-plegable' : '') . '">'
        . IntranetFormato::html($s, $formato, $resaltar) . '</div>';
};

$meta = function () use ($c) {
    ?>
    <div class="intra-com-meta">
        <span class="intra-tag intra-tag-cat"><?= Intranet::e(Intranet::categoria($c['categoria'])) ?></span>
        <?php if (!empty($c['fijado'])): ?><span class="intra-tag intra-tag-fijado"><i class="fa-solid fa-thumbtack"></i> Fijado</span><?php endif; ?>
        <?= Intranet::etiquetaEstilo($c) ?>
        <?php if (!empty($c['es_nuevo'])): ?><span class="intra-tag intra-tag-nuevo">Nuevo</span><?php endif; ?>
        <?php if (!empty($c['automatico'])): ?><span class="intra-tag intra-tag-auto">Automático</span><?php endif; ?>
        <?php if (($c['marca'] ?? '') !== ''): ?><span class="intra-tag"><?= Intranet::e($c['marca']) ?></span><?php endif; ?>
        <span>
            <?= Intranet::e(Intranet::ddmm($c['fecha'])) ?> · <?= Intranet::e(Intranet::haceTiempo($c['fecha'])) ?>
            <?php if (($c['autor'] ?? '') !== ''): ?> · <?= Intranet::e($c['autor']) ?><?php endif; ?>
        </span>
    </div>
    <?php
};

$titulo = function () use ($c, $resaltar) {
    $html = Intranet::e($c['titulo']);
    echo '<h3 class="com-titulo">' . ($resaltar ? IntranetFormato::resaltarDatos($html) : $html) . '</h3>';
};

$figura = function (array $im, bool $carrusel) use ($c) {
    $alt = $im['epigrafe'] !== '' ? $im['epigrafe'] : $c['titulo'];
    ?>
    <figure class="com-figura">
        <div class="com-media-caja">
            <img src="<?= Intranet::e($im['url']) ?>" alt="<?= Intranet::e($alt) ?>" loading="lazy"
                 <?php if ($im['ancho'] && $im['alto']): ?>width="<?= (int)$im['ancho'] ?>" height="<?= (int)$im['alto'] ?>"<?php endif; ?>>
        </div>
        <?php if ($im['epigrafe'] !== ''): ?>
            <figcaption class="com-epigrafe"><?= Intranet::e($im['epigrafe']) ?></figcaption>
        <?php elseif ($carrusel): // reserva el lugar: así el carrusel no salta entre fotos con y sin epígrafe ?>
            <div class="com-epigrafe" aria-hidden="true"></div>
        <?php endif; ?>
    </figure>
    <?php
};

$media = function () use ($imagenes, $figura) {
    if (!$imagenes) {
        return;
    }
    if (count($imagenes) === 1) {
        echo '<div class="com-media">';
        $figura($imagenes[0], false);
        echo '</div>';
        return;
    }
    // Carrusel: arranca en la portada
    $inicio = 0;
    foreach ($imagenes as $i => $im) {
        if (!empty($im['es_portada'])) {
            $inicio = $i;
            break;
        }
    }
    $id = Intranet::idUnico('com-carrusel');
    ?>
    <div class="com-media">
        <div id="<?= $id ?>" class="carousel slide carousel-fade com-carrusel" data-com-carrusel aria-roledescription="carrusel" aria-label="Imágenes del comunicado">
            <div class="carousel-inner">
                <?php foreach ($imagenes as $i => $im): ?>
                <div class="carousel-item<?= $i === $inicio ? ' active' : '' ?>" aria-roledescription="imagen" aria-label="<?= $i + 1 ?> de <?= count($imagenes) ?>">
                    <?php $figura($im, true); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#<?= $id ?>" data-bs-slide="prev">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Imagen anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#<?= $id ?>" data-bs-slide="next">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Imagen siguiente</span>
            </button>
            <div class="carousel-indicators">
                <?php foreach ($imagenes as $i => $im): ?>
                <button type="button" data-bs-target="#<?= $id ?>" data-bs-slide-to="<?= $i ?>" aria-label="Imagen <?= $i + 1 ?>"<?= $i === $inicio ? ' class="active" aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
};

$adjuntos = function () use ($pdfs) {
    if (!$pdfs) {
        return;
    }
    ?>
    <div class="com-adjuntos">
        <div class="com-adjuntos-titulo">Adjuntos</div>
        <ul class="com-adjuntos-lista">
            <?php foreach ($pdfs as $p): ?>
            <li class="com-adjunto">
                <i class="fa-regular fa-file-pdf com-adjunto-icono" aria-hidden="true"></i>
                <span class="com-adjunto-nombre"><?= Intranet::e($p['nombre']) ?></span>
                <span class="com-adjunto-peso"><?= Intranet::e($p['peso']) ?></span>
                <span class="com-adjunto-botones">
                    <a class="intra-btn" href="<?= Intranet::e($p['url']) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-eye"></i> Ver</a>
                    <a class="intra-btn" href="<?= Intranet::e($p['url_descargar']) ?>"><i class="fa-solid fa-download"></i> Descargar</a>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
};

$acciones = function () use ($c, $link, $nuevaPestana) {
    ?>
    <div class="com-acciones">
        <button type="button" class="com-leer" hidden>Leer más</button>
        <?php if ($link): ?>
            <a class="intra-btn" href="<?= Intranet::e($link['url']) ?>"<?= $nuevaPestana ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                <?= Intranet::e(($c['link_texto'] ?? '') !== '' ? $c['link_texto'] : 'Ver más información') ?>
                <i class="fa-solid <?= $nuevaPestana ? 'fa-arrow-up-right-from-square' : 'fa-arrow-right' ?>"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php
};
?>
<article class="intra-card com-card com-esq-<?= strtolower($esquema) ?><?= Intranet::clasesComunicado($c) ?><?= $t['clases'] !== '' ? ' ' . Intranet::e(trim($t['clases'])) : '' ?>"<?php
    if ($t['id'] !== ''): ?> id="<?= Intranet::e($t['id']) ?>"<?php endif;
    if ($t['estilo'] !== ''): ?> style="<?= Intranet::e($t['estilo']) ?>"<?php endif;
    ?> data-cat="<?= Intranet::e($c['categoria']) ?>">
<?php switch ($esquema):
    case 'ARRIBA':
        $media(); $meta(); $titulo(); $texto($textoSup, $plegar); $adjuntos(); $acciones();
        break;
    case 'ABAJO':
        $meta(); $titulo(); $texto($textoSup, $plegar); $media(); $adjuntos(); $acciones();
        break;
    case 'CENTRO':
        $meta(); $titulo(); $texto($textoSup, false); $media(); $texto($textoInf, $plegar); $adjuntos(); $acciones();
        break;
    case 'FLYER':
        $media(); $meta(); $titulo(); $texto($textoSup, false); $adjuntos(); $acciones();
        break;
    case 'IZQUIERDA':
    case 'DERECHA': ?>
        <div class="com-lado<?= $esquema === 'DERECHA' ? ' com-lado-derecha' : '' ?>">
            <div class="com-lado-media"><?php $media(); ?></div>
            <div class="com-lado-cuerpo"><?php $meta(); $titulo(); $texto($textoSup, $plegar); $adjuntos(); $acciones(); ?></div>
        </div>
        <?php break;
    default:
        $meta(); $titulo(); $texto($textoSup, $plegar); $adjuntos(); $acciones();
endswitch; ?>
<?php if ($t['interaccion'] && Intranet::conInteraccionHabilitada($c)): ?>
    <?= Intranet::plantilla('comunicado_interaccion', [
        'c' => $c, 'modo' => $t['interaccion'], 'ejemplo' => $t['ejemplo'],
        'comentarios' => $t['comentarios'], 'moderar' => $t['moderar'], 'yo' => $t['yo'],
    ]) ?>
<?php endif; ?>
</article>
