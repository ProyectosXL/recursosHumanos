<?php
/**
 * Control de acceso de DocuGest 2.0 sobre el login del Hub y W:\Gestionusuarios.
 *
 * Niveles:
 *  - Ver:    cualquier usuario logueado en el hub.
 *  - Editar: permiso docugest.editar (o docugest.admin).
 *  - Admin:  permiso docugest.admin, o admin total del hub (Auth::puede devuelve true).
 *
 * Incluye además:
 *  - Enriquecimiento de perfil desde W:\Gestionusuarios (FP_SOF_USUARIOS, FP_SECTORES, FP_SUBSECTORES).
 *  - Protección CSRF por sesión (csrfToken / verificarCsrf / exigirCsrf).
 *  - Cabeceras HTTP de seguridad y sanitización de consultas para el motor IA.
 */

if (!class_exists('AccesoDocuGest')) {

    // Cargar el entorno de Conexion (htdocs/.env) ANTES que el hub.
    require_once dirname(__DIR__, 2) . '/Class/classEnv.php';
    try {
        (new DotEnv(DotEnv::archivo()))->listVars();
    } catch (Throwable $e) {
        error_log('AccesoDocuGest: no se pudo leer el .env: ' . $e->getMessage());
    }

    $docugestDocRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    if ($docugestDocRoot === '') {
        // Ejecución sin servidor web: htdocs es tres niveles arriba de DOCUGEST/Class
        $docugestDocRoot = dirname(__DIR__, 3);
    }
    $hubBootstrap = $docugestDocRoot . '/sistemas/hub/Class/bootstrap.php';
    if (is_file($hubBootstrap)) {
        require_once $hubBootstrap;
    }
    unset($docugestDocRoot, $hubBootstrap);

    class AccesoDocuGest
    {
        const PAGINA = 'pagina';
        const JSON   = 'json';

        const PERMISO_VER    = 'docugest.ver';
        const PERMISO_EDITAR = 'docugest.editar';
        const PERMISO_ADMIN  = 'docugest.admin';

        const MSG_SIN_PERMISO = 'No tenés permisos para esta acción';
        const MSG_SIN_SESION  = 'Tu sesión expiró. Volvé a ingresar.';

        /**
         * Aplica cabeceras HTTP de seguridad recomendadas (estilo Cerebro XL AuthMiddleware).
         */
        public static function aplicarHeadersSeguridad()
        {
            if (PHP_SAPI === 'cli' || headers_sent()) {
                return;
            }
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-XSS-Protection: 1; mode=block');
        }

        public static function logueado()
        {
            return class_exists('Auth') && Auth::check();
        }

        public static function puedeEditar()
        {
            return class_exists('Auth') && Auth::check() && Auth::puede([self::PERMISO_EDITAR, self::PERMISO_ADMIN]);
        }

        public static function esAdmin()
        {
            return class_exists('Auth') && Auth::check() && Auth::puede(self::PERMISO_ADMIN);
        }

        /**
         * Verifica si el usuario pertenece al rol Control Total / Admin Total del Hub.
         */
        public static function esControlTotal()
        {
            if (!self::logueado()) {
                return false;
            }
            if (class_exists('Auth') && method_exists('Auth', 'esAdmin') && Auth::esAdmin()) {
                return true;
            }
            $rol = '';
            if (class_exists('Auth') && method_exists('Auth', 'rolNombre')) {
                $rol = strtoupper(trim((string)Auth::rolNombre() . ' ' . (string)Auth::rol()));
            } else {
                $rol = strtoupper(trim((string)($_SESSION['rol'] ?? '')));
            }
            return str_contains($rol, 'CONTROL TOTAL') || str_contains($rol, 'CONTROLTOTAL');
        }

        /** 'Admin', 'Editar' o 'Ver' */
        public static function nivel()
        {
            if (self::esAdmin()) {
                return 'Admin';
            }
            return self::puedeEditar() ? 'Editar' : 'Ver';
        }

        /** Usuario del hub para grabar en historial / creado por / memoria */
        public static function usuario()
        {
            if (class_exists('Auth')) {
                $u = Auth::username();
                if ($u !== null && $u !== '') {
                    return (string)$u;
                }
            }
            return isset($_SESSION['username']) && $_SESSION['username'] !== '' ? (string)$_SESSION['username'] : 'Sistema';
        }

        /** Id del usuario del hub (FP_SOF_USUARIOS.id) o null */
        public static function usuarioId()
        {
            if (class_exists('Auth')) {
                $id = Auth::id();
                if (is_numeric($id)) {
                    return (int)$id;
                }
            }
            return isset($_SESSION['id']) && is_numeric($_SESSION['id']) ? (int)$_SESSION['id'] : null;
        }

        /**
         * Devuelve el perfil corporativo enriquecido desde Gestionusuarios
         * (sistemas.dbo.FP_SOF_USUARIOS + FP_SECTORES + FP_SUBSECTORES + FP_ROLES_PERMISOS_MAP).
         * Se cachea en sesión durante 10 minutos para evitar consultas repetidas a SQL Server.
         *
         * @return array<string, mixed>
         */
        public static function perfilUsuario($forceRefresh = false)
        {
            if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
                @session_start();
            }

            $username = self::usuario();
            $cached = $_SESSION['docugest_user_profile'] ?? null;
            if (
                !$forceRefresh
                && is_array($cached)
                && ($cached['username'] ?? '') === $username
                && isset($cached['_cached_at'])
                && (time() - (int)$cached['_cached_at']) < 600
            ) {
                return $cached;
            }

            // Auth::usuario() del Hub carga FP_SOF_USUARIOS, FP_SECTORES, FP_ROLES_PERMISOS_MAP y FP_PERMISOS
            $hubUser = [];
            if (class_exists('Auth') && method_exists('Auth', 'usuario')) {
                $rawHub = Auth::usuario();
                if (is_array($rawHub)) {
                    $hubUser = $rawHub;
                }
            }

            $sectorId    = !empty($hubUser['sector_id']) ? (int)$hubUser['sector_id'] : null;
            $subsectorId = !empty($hubUser['subsector_id']) ? (int)$hubUser['subsector_id'] : null;

            $profile = [
                'id'               => self::usuarioId(),
                'username'         => (string)($hubUser['username'] ?? $username),
                'nombre_completo'  => trim((string)($hubUser['nombre'] ?? ($_SESSION['nombre'] ?? $username))),
                'puesto'           => trim((string)($hubUser['puesto'] ?? '')),
                'rol'              => strtoupper(trim((string)($hubUser['rol_nombre'] ?? $hubUser['rol'] ?? ($_SESSION['rol'] ?? 'USUARIO')))),
                'rol_id'           => (int)($hubUser['rol_id'] ?? 0),
                'tipo'             => strtoupper(trim((string)($hubUser['tipo'] ?? ($_SESSION['tipo'] ?? '')))),
                'id_sector'        => $sectorId,
                'sector_codigo'    => trim((string)($hubUser['sector_codigo'] ?? '')),
                'sector_nombre'    => trim((string)($hubUser['sector_nombre'] ?? '')),
                'id_subsector'     => $subsectorId,
                'subsector_nombre' => '',
                'permisos'         => is_array($hubUser['permisos'] ?? null) ? $hubUser['permisos'] : [],
                'nivel'            => self::nivel(),
                'puede_editar'     => self::puedeEditar(),
                'es_admin'         => self::esAdmin(),
                '_cached_at'       => time(),
            ];

            if ($profile['nombre_completo'] === '') {
                $profile['nombre_completo'] = $profile['username'];
            }

            // Completar subsector_nombre (y sector_nombre si faltara) desde Gestionusuarios (sistemas / apps)
            if ($profile['username'] !== '' && $profile['username'] !== 'Sistema' && ($profile['id_subsector'] !== null || $profile['sector_nombre'] === '')) {
                try {
                    $conn = null;
                    if (class_exists('ConexionApps') && method_exists('ConexionApps', 'get')) {
                        $conn = ConexionApps::get();
                    }
                    if (!$conn) {
                        require_once dirname(__DIR__, 2) . '/Class/conexion.php';
                        $cx = new Conexion();
                        $conn = $cx->conectar('apps');
                    }
                    if ($conn) {
                        $sql = "SELECT TOP 1
                                    u.id,
                                    u.username,
                                    u.nombre_completo,
                                    u.sector_id,
                                    s.codigo AS sector_codigo,
                                    s.nombre AS sector_nombre,
                                    u.subsector_id,
                                    sub.nombre AS subsector_nombre
                                FROM dbo.FP_SOF_USUARIOS u WITH (NOLOCK)
                                LEFT JOIN dbo.FP_SECTORES s WITH (NOLOCK) ON s.id = u.sector_id
                                LEFT JOIN dbo.FP_SUBSECTORES sub WITH (NOLOCK) ON sub.id = u.subsector_id
                                WHERE u.username = ? OR (? IS NOT NULL AND u.id = ?)";
                        $uid = $profile['id'];
                        $stmt = @sqlsrv_query($conn, $sql, [$profile['username'], $uid, $uid]);
                        if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
                            if (!empty($row['id'])) {
                                $profile['id'] = (int)$row['id'];
                            }
                            if ($profile['nombre_completo'] === $profile['username'] && !empty($row['nombre_completo'])) {
                                $profile['nombre_completo'] = trim((string)$row['nombre_completo']);
                            }
                            if (!empty($row['sector_id'])) {
                                $profile['id_sector'] = (int)$row['sector_id'];
                            }
                            if ($profile['sector_codigo'] === '' && !empty($row['sector_codigo'])) {
                                $profile['sector_codigo'] = trim((string)$row['sector_codigo']);
                            }
                            if ($profile['sector_nombre'] === '' && !empty($row['sector_nombre'])) {
                                $profile['sector_nombre'] = trim((string)$row['sector_nombre']);
                            }
                            if (!empty($row['subsector_id'])) {
                                $profile['id_subsector'] = (int)$row['subsector_id'];
                            }
                            if (!empty($row['subsector_nombre'])) {
                                $profile['subsector_nombre'] = trim((string)$row['subsector_nombre']);
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('AccesoDocuGest::perfilUsuario error: ' . $e->getMessage());
                }
            }

            if (PHP_SAPI !== 'cli') {
                $_SESSION['docugest_user_profile'] = $profile;
            }

            return $profile;
        }

        /**
         * Genera y retorna el Token CSRF de la sesión actual.
         */
        public static function csrfToken()
        {
            if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
                @session_start();
            }
            if (empty($_SESSION['docugest_csrf_token'])) {
                $_SESSION['docugest_csrf_token'] = bin2hex(random_bytes(32));
            }
            return (string)$_SESSION['docugest_csrf_token'];
        }

        /**
         * Verifica token CSRF en header X-CSRF-Token o parámetro $_POST['_csrf_token'].
         */
        public static function verificarCsrf($providedToken = null)
        {
            $sessionToken = self::csrfToken();
            if ($providedToken === null || $providedToken === '') {
                $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN']
                    ?? ($_POST['_csrf_token'] ?? '');
            }
            if (!is_string($providedToken) || $providedToken === '') {
                return false;
            }
            return hash_equals($sessionToken, trim($providedToken));
        }

        /**
         * Exige token CSRF válido; responde 403 JSON si falla.
         */
        public static function exigirCsrf($providedToken = null)
        {
            if (!self::verificarCsrf($providedToken)) {
                self::responderError(403, 'Token CSRF inválido o expirado. Recargá la página e intentá nuevamente.');
            }
        }

        /**
         * Sanitiza y recorta la pregunta ingresada por el usuario antes de pasar al motor IA.
         */
        public static function sanitizarPregunta($text, $maxLength = 1500)
        {
            $clean = str_replace("\0", '', trim((string)$text));
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $clean) ?? $clean;
            $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
            $clean = trim($clean);
            if (mb_strlen($clean, 'UTF-8') > $maxLength) {
                $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
            }
            return $clean;
        }

        /**
         * Destino del botón "Volver al menú": XL Gestión o el menú central del hub.
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
            $refererLower = strtolower($referer);
            if ($referer !== ''
                && strpos($refererLower, '/recursoshumanos/docugest/') !== 0
                && strpos($refererLower, '/recursoshumanos/p&p/') !== 0
                && strpos($refererLower, '/recursoshumanos/p%26p/') !== 0
                && strpos($refererLower, '/sistemas/login.php') !== 0) {
                if (strpos($refererLower, '/sistemas/hub/') === 0) {
                    $origen = 'hub';
                } elseif (strpos($refererLower, '/sistemas/') === 0) {
                    $origen = 'xlgestion';
                }
            }

            if ($origen !== null) {
                $_SESSION['docugest_volver'] = $origen;
            } elseif (isset($_SESSION['docugest_volver'], $destinos[$_SESSION['docugest_volver']])) {
                $origen = $_SESSION['docugest_volver'];
            } else {
                $tipo = strtoupper(trim((string)($_SESSION['tipo'] ?? '')));
                $origen = in_array($tipo, ['LOCAL_PROPIO', 'LOCAL_PROPIO_UY', 'FRANQUICIA', 'FRANQUICIA_GA'], true)
                    ? 'xlgestion' : 'hub';
            }

            return $destinos[$origen];
        }

        public static function exigirLogin($modo = self::PAGINA)
        {
            self::aplicarHeadersSeguridad();
            if (self::logueado()) {
                return;
            }
            if ($modo === self::JSON) {
                self::responderError(401, self::MSG_SIN_SESION);
            }
            if (class_exists('Auth')) {
                Auth::exigir();
            } else {
                http_response_code(401);
                echo self::MSG_SIN_SESION;
            }
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
            Auth::exigir([self::PERMISO_EDITAR, self::PERMISO_ADMIN]);
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

        public static function exigirControlTotal($modo = self::JSON)
        {
            self::exigirLogin($modo);
            if (self::esControlTotal()) {
                return;
            }
            if ($modo === self::JSON) {
                self::responderError(403, 'Acceso restringido a usuarios con rol Control Total.');
            }
            Auth::exigir(self::PERMISO_ADMIN);
            exit;
        }

        private static function responderError($codigoHttp, $mensaje)
        {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code($codigoHttp);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => $mensaje,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
