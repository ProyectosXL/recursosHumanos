<?php
/** Permisos de la administración: ninguna acción queda abierta sin el permiso. */
require_once __DIR__ . '/../Class/Permisos.php';

prueba('permisos: sin el permiso, cada acción de administración responde error aunque se la llame directo', function () {
    foreach (Permisos::ACCIONES_ADMIN as $accion) {
        $r = Permisos::autorizarAdmin($accion, true, false, true, 'POST');
        verdadero(is_array($r) && $r[0] === 403, $accion . ' sin permiso');
        $r = Permisos::autorizarAdmin($accion, false, false, true, 'POST');
        verdadero(is_array($r) && $r[0] === 401, $accion . ' sin sesión');
    }
});

prueba('permisos: con permiso, las que escriben piden POST y CSRF', function () {
    foreach (Permisos::ACCIONES_ADMIN as $accion) {
        igual(null, Permisos::autorizarAdmin($accion, true, true, true, 'POST'), $accion);
        if ($accion !== 'plantilla') {
            verdadero(Permisos::autorizarAdmin($accion, true, true, false, 'POST') !== null, $accion . ' sin CSRF');
            verdadero(Permisos::autorizarAdmin($accion, true, true, true, 'GET') !== null, $accion . ' por GET');
        }
    }
    verdadero(Permisos::autorizarAdmin('borrarTodo', true, true, true, 'POST') !== null, 'acción inventada');
});

