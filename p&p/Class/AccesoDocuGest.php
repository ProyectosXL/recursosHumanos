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

        /**
         * Destino del botón "Volver al menú": XL Gestión o el menú central del hub,
         * según de dónde se abrió DocuGest. Se recuerda en la sesión para que no se pierda
         * al navegar dentro de DocuGest (filtros, recargas).
         * @return array ['url' => string, 'texto' => string]
         */
        public static function menuDeVuelta()
        {
            $destinos = [
                'hub'       => ['url' => '/sistemas/hub/index.php', 'texto' => 'Volver al menú central'],
                'xlgestion' => ['url' => '/sistemas/index.php',     'texto' => 'Volver a XL Gestión'],
            ];

            $origen = null;
            $referer = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
            if ($referer !== '' && strpos($referer, '/recursosHumanos/p&p/') !== 0
                && strpos($referer, '/recursosHumanos/p%26p/') !== 0
                && strpos($referer, '/sistemas/login.php') !== 0) {
                if (strpos($referer, '/sistemas/hub/') === 0) {
                    $origen = 'hub';
                } elseif (strpos($referer, '/sistemas/') === 0) {
                    $origen = 'xlgestion';
                }
            }

            if ($origen !== null) {
                $_SESSION['docugest_volver'] = $origen;
            } elseif (isset($_SESSION['docugest_volver'], $destinos[$_SESSION['docugest_volver']])) {
                $origen = $_SESSION['docugest_volver'];
            } else {
                // Sin referer (el hub abre con noreferrer): los usuarios de locales usan XL Gestión
                $tipo = strtoupper(trim((string)($_SESSION['tipo'] ?? '')));
                $origen = in_array($tipo, ['LOCAL_PROPIO', 'LOCAL_PROPIO_UY', 'FRANQUICIA', 'FRANQUICIA_GA'], true)
                    ? 'xlgestion' : 'hub';
            }

            return $destinos[$origen];
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
