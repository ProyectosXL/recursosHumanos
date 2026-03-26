<?php
// templates/header.php
?>
<!doctype html>
<html lang="es" class="h-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Gestión de Clientes') ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?= $extraHeaders ?? '' ?>

    <style>
        html, body {
            height: 100%;
        }
        body { 
            background-color: #f0f2f5;
            display: flex;
            flex-direction: column;
        }
        main {
            flex-grow: 1;
        }
        .duplicado {
            background-color: #f8d7da !important; /* Rojo claro de Bootstrap */
            color: #721c24 !important; /* Rojo oscuro de Bootstrap */
            font-weight: bold;
        }
        .card { 
            border: none;
            width: 100%;
        }
        .select2-container--bootstrap-5 .select2-selection--single {
            height: calc(1.5em + 1rem + 2px) !important;
            padding: .5rem 1rem !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 1rem) !important;
        }
    </style>
</head>
<body class="d-flex flex-column">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>index.php">Gestión de Clientes</a>
    </div>
</nav>

<main class="container <?= htmlspecialchars($mainContainerClass ?? 'mt-5') ?>">