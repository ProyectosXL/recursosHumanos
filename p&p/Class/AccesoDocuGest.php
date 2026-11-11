<?php
/**
 * Control de acceso de DocuGest sobre el login del Hub.
 *
 * Niveles:
 *  - Ver:    cualquier usuario logueado en el hub.
 *  - Editar: permiso docugest.editar (o docugest.admin).
 *  - Admin:  permiso docugest.admin, o admin total del hub (Auth::puede devuelve true).
 *
 * Los exigir*() reciben el modo de respuesta:
 *  - AccesoDocuGest::PAGINA: sin sesión redirige al login del hub (vuelve a la URL pedida);
 *    sin permiso muestra la pantalla de acceso no autorizado del hub.
 *  - AccesoDocuGest::JSON:   responde {status:'error', message:...} con HTTP 401 / 403.
 */

if (!class_exists('AccesoDocuGest')) {

    // Cargar el entorno de Conexion (htdocs/.env) ANTES que el hub.
    // Auth (ConexionApps) vuelca su .env en $_ENV sin putenv(), y DotEnv no pisa claves ya
    // presentes: si el hub carga primero, getenv() queda vacío y Conexion no conecta.
    require_once dirname(__DIR__, 2) . '/Class/classEnv.php';
    try {
        (new DotEnv(DotEnv::archivo()))->listVars();
    } catch (Throwable $e) {
        error_log('AccesoDocuGest: no se pudo leer el .env: ' . $e->getMessage());
    }

    $docugestDocRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    if ($docugestDocRoot === '') {
        // Ejecución sin servidor web: htdocs es tres niveles arriba de p&p/Class
        $docugestDocRoot = dirname(__DIR__, 3);
    }
    require_once $docugestDocRoot . '/sistemas/hub/Class/bootstrap.php';
    unset($docugestDocRoot);

    class AccesoDocuGest
    {
        const PAGINA = 'pagina';
        const JSON   = 'json';

        const PERMISO_EDITAR = 'docugest.editar';
        const PERMISO_ADMIN  = 'docugest.admin';

        const MSG_SIN_PERMISO = 'No tenés permisos para esta acción';
        const MSG_SIN_SESION  = 'Tu sesión expiró. Volvé a ingresar.';

        public static function logueado()
        {
            return Auth::check();
        }

        public static function puedeEditar()
        {
            return Auth::check() && Auth::puede([self::PERMISO_EDITAR, self::PERMISO_ADMIN]);
        }

        public static function esAdmin()
        {
            return Auth::check() && Auth::puede(self::PERMISO_ADMIN);
        }

        /** 'Admin', 'Editar' o 'Ver' */
        public static function nivel()
        {
            if (self::esAdmin()) {
                return 'Admin';
            }
            return self::puedeEditar() ? 'Editar' : 'Ver';
        }

        /** Usuario del hub para grabar en historial / creado por */
        public static function usuario()
        {
            $u = Auth::username();
            return ($u !== null && $u !== '') ? (string)$u : 'Sistema';
        }

        /** Id del usuario del hub (FP_SOF_USUARIOS.id) o null */
        public static function usuarioId()
        {
            $id = Auth::id();
            return is_numeric($id) ? (int)$id : null;
        }

        public static function exigirLogin($modo = self::PAGINA)
        {
            if (Auth::check()) {
                return;
            }
            if ($modo === self::JSON) {
                self::responderError(401, self::MSG_SIN_SESION);
            }
            Auth::exigir(); // redirige a /sistemas/login.php?next=...
            exit;
        }

        public static function exigirEditar($modo = self::PAGINA)
        {
            self::exigirLogin($modo);
            if (self::puedeEditar()) {
                return;
            }
            if ($modo === self::JSON) {
                self::responderError(403, self::MSG_SIN_PERMISO);
            }
            Auth::exigir([self::PERMISO_EDITAR, self::PERMISO_ADMIN]); // pantalla de acceso no autorizado
            exit;
        }

        public static function exigirAdmin($modo = self::PAGINA)
        {
            self::exigirLogin($modo);
            if (self::esAdmin()) {
                return;
            }
            if ($modo === self::JSON) {
                self::responderError(403, self::MSG_SIN_PERMISO);
            }
            Auth::exigir(self::PERMISO_ADMIN);
            exit;
        }

        private static function responderError($httpCode, $mensaje)
        {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
