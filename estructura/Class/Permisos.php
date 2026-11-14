<?php
/**
 * Estructura XL - permisos
 *
 * Un solo lugar para "quién puede qué" en estructura, para que el menú, las pantallas y los
 * endpoints AJAX usen exactamente la misma regla (si el menú esconde un botón pero el endpoint
 * lo deja pasar, la regla no sirve).
 *
 * - Administrar áreas y fichas: permiso del hub ESTRUCTURA_PERMISO_ADMINISTRAR (FP_PERMISOS,
 *   sql/04_estructura_permiso.sql). Hoy es centralizado (Innovación).
 *   Etapa siguiente, sin rehacer nada: todo lo de una ficha pasa por puedeEditarArea($idArea);
 *   alcanza con sumar ahí "o es responsable de esa área" (un permiso estructura.editar_ficha y una
 *   tabla de responsables por área). Las pantallas y los endpoints ya preguntan por área.
 * - Administrar internos (ABM): la regla que tenía comercial/recepcion/listado.php, sin cambios.
 *
 * Las consultas (Guía, Contactos, Internos y registrar una búsqueda) no piden login: no pasan por acá.
 */

if (!defined('ESTRUCTURA_PERMISO_ADMINISTRAR')) {
    define('ESTRUCTURA_PERMISO_ADMINISTRAR', 'estructura.administrar');
}

class Permisos
{
    /** Hay sesión del hub con el permiso de administración. Nunca redirige: sirve para el menú. */
    public static function puedeAdministrar()
    {
        return class_exists('Auth') && Auth::check() && Auth::puede(ESTRUCTURA_PERMISO_ADMINISTRAR);
    }

    /**
     * Puede editar la ficha de un área. Hoy es lo mismo que administrar; queda separado a
     * propósito para sumar los responsables por área sin tocar quien lo llama (ver arriba).
     */
    public static function puedeEditarArea($idArea)
    {
        return self::puedeAdministrar();
    }

    /**
     * ¿Ya se corrió sql/04_estructura_permiso.sql? Sin el permiso en FP_PERMISOS sólo entra el
     * administrador total (Auth::puede le da todo): la pantalla lo avisa para que no parezca un error.
     * null si no se pudo saber (sin conexión a XL-APPS).
     */
    public static function permisoRegistrado()
    {
        $conn = class_exists('ConexionApps') ? ConexionApps::get() : null;
        if (!$conn) {
            return null;
        }
        $st = sqlsrv_query($conn, 'SELECT COUNT(*) AS n FROM dbo.FP_PERMISOS WHERE clave = ?', array(ESTRUCTURA_PERMISO_ADMINISTRAR));
        $f = $st ? sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) : null;
        return $f ? (int)$f['n'] > 0 : null;
    }

    /** ['crear' => bool, 'editar' => bool, 'eliminar' => bool] del ABM de Internos para la sesión actual. */
    public static function abmInternos()
    {
        if (!class_exists('Auth') || !Auth::check()) {
            return self::reglaAbmInternos(null, false, null, null);
        }
        return self::reglaAbmInternos(Auth::usuario(), Auth::esAdmin(), Auth::username(), isset($_SESSION['rol']) ? $_SESSION['rol'] : null);
    }

    /** Ve "Administrar internos": hay sesión del hub y alguno de los tres permisos del ABM. */
    public static function puedeAdministrarInternos()
    {
        $p = self::abmInternos();
        return $p['crear'] || $p['editar'] || $p['eliminar'];
    }

    /**
     * Regla del ABM de Internos, copiada tal cual de comercial/recepcion/listado.php ($puede_crear,
     * $puede_editar, $puede_eliminar). Es pura (recibe el usuario, no lee la sesión) para probarla
     * sin base y para que listado.php, ajax.php y el menú no puedan separarse.
     *
     * @param array|null  $user       Auth::usuario()
     * @param bool        $esAdmin    Auth::esAdmin()
     * @param string|null $username   Auth::username()
     * @param string|null $rolSesion  $_SESSION['rol'] (el original lo miraba antes que el del usuario)
     */
    public static function reglaAbmInternos($user, $esAdmin, $username, $rolSesion)
    {
        if (!is_array($user)) {
            return array('crear' => false, 'editar' => false, 'eliminar' => false);
        }

        $rol = $rolSesion !== null ? $rolSesion : (isset($user['rol']) ? $user['rol'] : '');
        $rolNom = strtoupper(trim(isset($user['rol_nombre']) ? $user['rol_nombre'] : (string)$rol));
        $sectorCod = strtoupper(isset($user['sector_codigo']) ? $user['sector_codigo'] : '');
        $sectorNom = strtoupper(isset($user['sector_nombre']) ? $user['sector_nombre'] : '');
        $subsectorNom = strtoupper(isset($user['subsector_nombre']) ? $user['subsector_nombre'] : '');

        $esAdminSistemas = $esAdmin
            || in_array(strtolower((string)$rol), array('admin', 'sistemas', 'gerencia_adm', 'superadmin', 'controltotal'))
            || in_array(strtolower((string)$username), array('admin', 'sistemas', 'francopertus', 'franco.pertus'))
            || in_array($sectorCod, array('SISTEMAS', 'IT'));

        $esRecepcionista = ($rolNom === 'RECEPCIONISTA' || (isset($user['rol_id']) ? $user['rol_id'] : 0) == 18)
            || ($sectorNom === 'COMERCIAL Y VENTAS' && $subsectorNom === 'ANALISTAS' && $rolNom === 'RECEPCIONISTA');

        return array(
            'crear'    => $esAdminSistemas,
            'editar'   => $esAdminSistemas || $esRecepcionista,
            'eliminar' => $esAdminSistemas || $esRecepcionista,
        );
    }

    /**
     * Qué permiso del ABM pide cada acción de internos/ajax.php. Una tabla pura y no un if por
     * acción para poder probar que ninguna acción que escribe queda abierta (tests/run.php).
     *   null         → pública (la vista de Internos, sin login)
     *   'cualquiera' → alguno de los tres (leer datos sólo útiles para administrar)
     *   'crear' | 'editar' | 'eliminar'
     * Una acción desconocida pide 'crear' (lo más restrictivo) por si alguien agrega una y se olvida.
     */
    public static function permisoAccionInternos($accion, array $datos)
    {
        switch ($accion) {
            case 'buscar':
            case 'getSectores':
                return null;
            case 'listarAbm':
            case 'getEmpleado':
                return 'cualquiera';
            case 'guardar':
                return (isset($datos['modoEdicion']) && $datos['modoEdicion'] === '1') ? 'editar' : 'crear';
            case 'toggleStatus':
                return 'eliminar';
            default:
                return 'crear';
        }
    }

    /** ¿Las acciones que escriben piden CSRF? Sí: guardar y toggleStatus. */
    public static function accionInternosEscribe($accion)
    {
        return in_array($accion, array('guardar', 'toggleStatus'), true);
    }

    /** Resuelve si $abm (['crear','editar','eliminar']) alcanza para el permiso pedido. */
    public static function alcanzaPermisoInternos($requerido, array $abm)
    {
        if ($requerido === null) {
            return true;
        }
        if ($requerido === 'cualquiera') {
            return !empty($abm['crear']) || !empty($abm['editar']) || !empty($abm['eliminar']);
        }
        return !empty($abm[$requerido]);
    }

    /**
     * Acciones de ajax/admin.php. TODAS piden el permiso de administración (o el del área, que
     * hoy es lo mismo) y, salvo 'plantilla' (sólo lee), CSRF y POST.
     */
    const ACCIONES_ADMIN = array(
        'guardarFicha', 'desactivarArea', 'reactivarArea', 'moverArea', 'reactivarTema',
        'descartarBusqueda', 'previsualizarImportacion', 'confirmarImportacion', 'plantilla',
    );

    /**
     * ¿Se puede ejecutar esta acción de administración? Pura (recibe lo que ya se sabe de la
     * sesión) para probar que ninguna acción queda abierta aunque se la llame directo.
     *
     * @return array|null null si se puede; si no, [código HTTP, mensaje]
     */
    public static function autorizarAdmin($accion, $haySesion, $tienePermiso, $csrfValido, $metodo)
    {
        if (!in_array($accion, self::ACCIONES_ADMIN, true)) {
            return array(400, 'Acción no válida.');
        }
        if (!$haySesion) {
            return array(401, 'Tu sesión terminó. Volvé a ingresar al hub.');
        }
        if (!$tienePermiso) {
            return array(403, 'Tu usuario no tiene permiso para administrar las áreas.');
        }
        if ($accion !== 'plantilla') {
            if ($metodo !== 'POST') {
                return array(405, 'Acción no válida.');
            }
            if (!$csrfValido) {
                return array(403, 'La página quedó vieja. Recargala y probá de nuevo.');
            }
        }
        return null;
    }

    /**
     * Código contra envíos falsificados desde otro sitio (uno por sesión), para toda acción que
     * escribe: el ABM de Internos y la administración. Mismo esquema que la Intranet.
     */
    public static function csrf()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['estructura_csrf'])) {
            $_SESSION['estructura_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['estructura_csrf'];
    }

    public static function csrfValido($valor)
    {
        return !empty($_SESSION['estructura_csrf']) && is_string($valor)
            && hash_equals((string)$_SESSION['estructura_csrf'], $valor);
    }
}
