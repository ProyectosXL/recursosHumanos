<?php
/**
 * Apertura de todas las pantallas de estructura: <head>, librerías, estética y menú lateral.
 * Una sola cabecera para que todas las pantallas carguen las mismas versiones de Bootstrap y de
 * los íconos (las de Internos, que ya estaban en uso) y el mismo CSS.
 *
 * Espera $pagina = ['titulo' => ..., 'activo' => ..., 'css' => [archivos de assets/ extra]].
 */
$paginaCss = isset($pagina['css']) ? $pagina['css'] : array();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pagina['titulo']) ?> · Directorio XL</title>
    <link rel="icon" type="image/jpg" href="<?= htmlspecialchars(ESTRUCTURA_URL_ICONO) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ESTRUCTURA_URL_ASSETS ?>/estructura.css?v=<?= (int)@filemtime(ESTRUCTURA_DIR . '/assets/estructura.css') ?>" rel="stylesheet">
    <?php foreach ($paginaCss as $css): ?>
    <link href="<?= ESTRUCTURA_URL_ASSETS ?>/<?= $css ?>?v=<?= (int)@filemtime(ESTRUCTURA_DIR . '/assets/' . $css) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body>
<a class="visually-hidden-focusable est-btn" href="#contenido">Ir al contenido</a>
<div class="est-app">
    <?php require __DIR__ . '/menu.php'; ?>
    <main class="est-main" id="contenido">
