<?php
/**
 * Rutas para el JS de estructura (window.ESTRUCTURA).
 *
 * Por qué: el JS no puede leer Class/rutas.php y no queremos rutas escritas a mano en los .js
 * (cuando Internos se mudó, BASE_IMAGE_PATH quedó apuntando a comercial). Se incluye antes de
 * cualquier script de estructura. $estructuraJsExtra (opcional) suma datos propios de la pantalla.
 */
$estructuraJs = array(
    'url'          => ESTRUCTURA_URL,
    'internos'     => ESTRUCTURA_URL_INTERNOS,
    'internosAjax' => ESTRUCTURA_URL_INTERNOS_AJAX,
    'fotosUrl'     => ESTRUCTURA_FOTOS_URL,
);
if (!empty($estructuraJsExtra) && is_array($estructuraJsExtra)) {
    $estructuraJs = array_merge($estructuraJs, $estructuraJsExtra);
}
?>
<script>window.ESTRUCTURA = <?= json_encode($estructuraJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
