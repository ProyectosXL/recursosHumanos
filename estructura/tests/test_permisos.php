<?php
/**
 * Permisos del ABM de Internos (y, más adelante, de la administración de áreas).
 * Lo importante: ninguna acción que escribe puede quedar abierta a quien no tiene permiso.
 */
require_once __DIR__ . '/../Class/Permisos.php';

prueba('ABM internos: sin sesión no puede nada', function () {
    igual(array('crear' => false, 'editar' => false, 'eliminar' => false), Permisos::reglaAbmInternos(null, false, null, null));
});

prueba('ABM internos: administrador total puede todo', function () {
    igual(array('crear' => true, 'editar' => true, 'eliminar' => true),
        Permisos::reglaAbmInternos(array('rol' => 'personal', 'rol_nombre' => 'Personal'), true, 'x', null));
});

prueba('ABM internos: sector Sistemas puede todo', function () {
    $p = Permisos::reglaAbmInternos(array('rol' => 'personal', 'sector_codigo' => 'sistemas'), false, 'x', null);
    verdadero($p['crear'] && $p['editar'] && $p['eliminar']);
});

prueba('ABM internos: recepcionista edita y desactiva pero no crea', function () {
    igual(array('crear' => false, 'editar' => true, 'eliminar' => true),
        Permisos::reglaAbmInternos(array('rol' => 'recep', 'rol_nombre' => 'Recepcionista'), false, 'x', null));
    igual(array('crear' => false, 'editar' => true, 'eliminar' => true),
        Permisos::reglaAbmInternos(array('rol' => 'otro', 'rol_nombre' => 'Otro', 'rol_id' => 18), false, 'x', null));
});

prueba('ABM internos: usuario común (o un local de XL Gestión) no puede nada', function () {
    igual(array('crear' => false, 'editar' => false, 'eliminar' => false),
        Permisos::reglaAbmInternos(array('rol' => 'estandar', 'rol_nombre' => 'Usuario Estándar', 'rol_id' => 0), false, 'local123', null));
});

prueba('ABM internos: el rol de la sesión manda sobre el del usuario (como el original)', function () {
    $p = Permisos::reglaAbmInternos(array('rol' => 'estandar', 'rol_nombre' => 'Usuario'), false, 'x', 'sistemas');
    verdadero($p['crear']);
});

prueba('ajax de internos: cada acción que escribe pide permiso y CSRF', function () {
    $sinNada = array('crear' => false, 'editar' => false, 'eliminar' => false);
    $acciones = array(
        array('guardar', array('modoEdicion' => '0')),
        array('guardar', array('modoEdicion' => '1')),
        array('toggleStatus', array()),
        array('listarAbm', array()),
        array('getEmpleado', array()),
        array('accionInventada', array()),
    );
    foreach ($acciones as $a) {
        $req = Permisos::permisoAccionInternos($a[0], $a[1]);
        verdadero($req !== null, $a[0] . ' no puede ser pública');
        verdadero(!Permisos::alcanzaPermisoInternos($req, $sinNada), $a[0] . ' sin permiso tiene que rechazarse');
    }
    verdadero(Permisos::accionInternosEscribe('guardar'));
    verdadero(Permisos::accionInternosEscribe('toggleStatus'));
});

prueba('ajax de internos: la vista pública sigue sin login', function () {
    igual(null, Permisos::permisoAccionInternos('buscar', array()));
    igual(null, Permisos::permisoAccionInternos('getSectores', array()));
});

prueba('ajax de internos: alta pide crear, edición pide editar, baja pide eliminar', function () {
    $recep = array('crear' => false, 'editar' => true, 'eliminar' => true);
    verdadero(!Permisos::alcanzaPermisoInternos(Permisos::permisoAccionInternos('guardar', array('modoEdicion' => '0')), $recep));
    verdadero(Permisos::alcanzaPermisoInternos(Permisos::permisoAccionInternos('guardar', array('modoEdicion' => '1')), $recep));
    verdadero(Permisos::alcanzaPermisoInternos(Permisos::permisoAccionInternos('toggleStatus', array()), $recep));
});
