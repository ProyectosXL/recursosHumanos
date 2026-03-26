<?php
require_once 'config.php';

$scope = $_GET['scope'] ?? '';

if ($scope === 'empleados') {
    header('Location: crear.php?cod=E');
    exit();
} elseif ($scope === 'directores') {
    header('Location: crear.php?cod=AS');
    exit();
} else {
    // Si alguien intenta acceder sin un scope, lo mandamos al inicio
    header('Location: index.php');
    exit();
}