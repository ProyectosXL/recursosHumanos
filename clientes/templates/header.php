<?php
// templates/header.php
?>
<!doctype html>
<html lang="es" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Gestión de Clientes') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
    <?= $extraHeaders ?? '' ?>
</head>
<body class="d-flex flex-column">

<?php
$isEmpleados = ($portalContext ?? null) === 'empleados';
$isReferidos = ($portalContext ?? null) === 'referidos';
$hasPortal   = $isEmpleados || $isReferidos;
?>
<nav class="navbar app-navbar shadow-sm">
    <div class="container-fluid">

        <a class="navbar-brand" href="<?= BASE_URL ?>index.php">
            <i class="bi bi-people-fill me-2 opacity-75"></i>Gestión de Clientes
            <?php if ($isEmpleados): ?>
                <span class="portal-badge portal-badge--emp">Empleados</span>
            <?php elseif ($isReferidos): ?>
                <span class="portal-badge portal-badge--ref">Referidos</span>
            <?php endif; ?>
        </a>

        <?php if ($hasPortal): ?>
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn-navbar-help" data-bs-toggle="modal" data-bs-target="#ayudaModal">
                <i class="bi bi-question-circle me-1"></i>Ayuda
            </button>
        </div>
        <?php endif; ?>

    </div>
</nav>

<main class="container <?= htmlspecialchars($mainContainerClass ?? 'mt-5') ?>">
