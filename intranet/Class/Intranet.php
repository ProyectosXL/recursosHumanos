<?php
/**
 * Intranet XL - datos y reglas de negocio
 *
 * Lee (nunca escribe) lo que muestran la columna "Intranet XL" del hub y la
 * pantalla recursosHumanos/intranet/index.php:
 *   - personal: FP_V_INTRA_PERSONAL (XL-APPS -> [XL-SUELDOS]), con caché diario en disco;
 *   - agenda: FP_V_INTRA_AGENDA (feriados + FP_INTRA_FECHAS);
 *   - comunicados: FP_INTRA_COMUNICADOS;
 *   - versiones nuevas de DocuGest: base central (Politicas_Procedimientos), caché de 10 minutos.
 *
 * Si una fuente falla, el método devuelve vacío (o null en personal) y la pantalla oculta ese
 * bloque: nada de esta clase puede romper el hub.
 *
 * PRIVACIDAD: de cada persona solo se manejan nombre, sector, día y mes de cumpleaños y fecha de
 * ingreso. La vista no expone el año de nacimiento.
 */

require_once __DIR__ . '/rutas.php';
require_once XL_HUB_DIR . '/Class/ConexionApps.php';

class Intranet
{
    const URL_INTRANET = '/recursosHumanos/intranet/index.php';
    const URL_ADMIN    = '/recursosHumanos/intranet/admin.php';
    const URL_DOCUGEST = '/recursosHumanos/p&p/index.php';
    const URL_ARCHIVO  = '/recursosHumanos/intranet/archivo.php';
    const URL_SUBIR    = '/recursosHumanos/intranet/archivo_subir.php';
    const URL_PREVIA   = '/recursosHumanos/intranet/vista_previa.php';
    const URL_INTERACCION = '/recursosHumanos/intranet/interaccion.php';

    const PERMISO_COMUNICADOS = 'intranet.comunicados.publicar';
    const PERMISO_FECHAS      = 'intranet.fechas.publicar';
    const PERMISO_PLANTILLAS  = 'intranet.plantillas.administrar';

    const CATEGORIAS = [
        'RRHH'      => 'RRHH',
        'COMERCIAL' => 'Comercial',
        'SISTEMAS'  => 'Sistemas',
        'DIRECCION' => 'Dirección',
        'GENERAL'   => 'General',
        'DOCUGEST'  => 'DocuGest',
    ];

    /** Estilos predeterminados de comunicado: código => [nombre, ícono]. Ver recursosHumanos/intranet/sql/07. */
    const ESTILOS = [
        'NORMAL'       => ['Clásico', ''],
        'DESTACADO'    => ['Destacado', 'fa-star'],
        'IMPORTANTE'   => ['Importante', 'fa-triangle-exclamation'],
        'CELEBRACION'  => ['Celebración', 'fa-champagne-glasses'],
        'RECORDATORIO' => ['Recordatorio', 'fa-bell'],
    ];

    /** Esquemas: dónde van las imágenes. Ver recursosHumanos/intranet/sql/08. Independiente del estilo (colores). */
    const ESQUEMAS = [
        'SIN_IMAGEN' => 'Solo texto',
        'ARRIBA'     => 'Imagen arriba',
        'IZQUIERDA'  => 'Imagen a la izquierda',
        'DERECHA'    => 'Imagen a la derecha',
        'CENTRO'     => 'Imagen al centro',
        'ABAJO'      => 'Imagen abajo',
        'FLYER'      => 'Solo imagen (flyer)',
    ];

    const TIPOS_FECHA = [
        'FERIADO'   => 'Feriado',
        'COMERCIAL' => 'Comercial',
        'OPERATIVA' => 'Operativa',
    ];

    const DIAS_DOCUGEST      = 15;   // versiones de DocuGest que se muestran como comunicado
    const SEG_CACHE_DOCUGEST = 600;  // la central se consulta como mucho cada 10 minutos
    const SEG_REINTENTO      = 600;  // si Sueldos o la central fallan, no reintentar antes de esto
    const TOPE_EQUIPO_HUB    = 4;    // bienvenidas y aniversarios que muestra la columna del hub (cada uno)
    const TOPE_EQUIPO_PAGINA = 10;   // aniversarios visibles en la intranet antes de "Ver todos"
    const TOPE_AVISOS        = 3;    // avisos emergentes a la vez al entrar al hub

    const DIAS_SEMANA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                   'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private static $conn = null;
    private static $memo = [];

    // ==================== Conexión y utilidades ====================

    /** Conexión propia a XL-APPS: el hub cierra la compartida de ConexionApps::get(). */
    public static function conexion()
    {
        if (self::$conn === null) {
            self::$conn = ConexionApps::nueva() ?: false;
        }
        return self::$conn ?: null;
    }

    public static function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }

    public static function puedePublicarComunicados(): bool
    {
        return Auth::puede(self::PERMISO_COMUNICADOS);
    }

    public static function puedePublicarFechas(): bool
    {
        return Auth::puede(self::PERMISO_FECHAS);
    }

    /** Administrar plantillas: solo con recursosHumanos/intranet/sql/11 corrido. */
    public static function puedeAdministrarPlantillas(): bool
    {
        return Auth::puede(self::PERMISO_PLANTILLAS) && self::tienePlantillas();
    }

    public static function puedeCargar(): bool
    {
        return self::puedePublicarComunicados() || self::puedePublicarFechas();
    }

    /**
     * Código contra envíos falsificados desde otro sitio: uno por sesión, compartido por la carga,
     * la vista previa, la subida de archivos y los comentarios y reacciones.
     */
    public static function csrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['intra_csrf'])) {
            $_SESSION['intra_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['intra_csrf'];
    }

    public static function csrfValido($valor): bool
    {
        return !empty($_SESSION['intra_csrf']) && hash_equals((string)$_SESSION['intra_csrf'], (string)$valor);
    }

    /**
     * Nombre con el que se muestra al usuario logueado al comentar (se guarda con el comentario):
     *   - con legajo: el "Nombre Apellido" de la intranet (Sueldos, FP_V_INTRA_PERSONAL);
     *   - si no: nombre_completo de FP_SOF_USUARIOS (en los locales, el nombre del local);
     *   - nunca el nombre de usuario técnico: si no hay otro, el del local o "Usuario de XL".
     */
    public static function nombreUsuario(): string
    {
        if (array_key_exists('nombre_usuario', self::$memo)) {
            return self::$memo['nombre_usuario'];
        }
        $nombre = '';
        $legajo = (int)Auth::legajo();
        if ($legajo > 0) {
            foreach (self::personal() ?? [] as $p) {
                if ((int)$p['legajo'] === $legajo) {
                    $nombre = trim((string)$p['nombre']);
                    break;
                }
            }
        }
        $tecnico = mb_strtolower(trim((string)Auth::username()));
        $candidatos = [$nombre, (string)Auth::nombre(), (string)($_SESSION['descLocal'] ?? '')];
        $nombre = '';
        foreach ($candidatos as $cand) {
            $cand = trim(preg_replace('/\s+/u', ' ', $cand) ?? '');
            if ($cand !== '' && mb_strtolower($cand) !== $tecnico) {
                $nombre = $cand;
                break;
            }
        }
        if ($nombre === '') {
            $usuario = Auth::usuario() ?? [];
            $suc = (int)($_SESSION['numsuc'] ?? 0);
            $esLocal = strtoupper(trim((string)($usuario['categoria_usuario'] ?? ''))) === 'LOCAL';
            $nombre = $esLocal && $suc > 0 ? 'Local N° ' . $suc : 'Usuario de XL';
        }
        return self::$memo['nombre_usuario'] = mb_substr($nombre, 0, 150, 'UTF-8');
    }

    /** SELECT con parámetros. Devuelve null si falla (y lo deja en el log). */
    private static function consultar($conn, string $sql, array $params = []): ?array
    {
        if (!$conn) {
            return null;
        }
        $stmt = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 15]);
        if ($stmt === false) {
            error_log('Intranet: error de consulta: ' . print_r(sqlsrv_errors(), true));
            return null;
        }
        $filas = [];
        while ($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $filas[] = $fila;
        }
        sqlsrv_free_stmt($stmt);
        return $filas;
    }

    private static function aFecha($valor): ?DateTimeImmutable
    {
        if ($valor instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($valor);
        }
        if (is_string($valor) && $valor !== '') {
            try {
                return new DateTimeImmutable($valor);
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    private static function soloDia(?DateTimeImmutable $f): ?DateTimeImmutable
    {
        return $f ? $f->setTime(0, 0) : null;
    }

    private static function rutaCache(string $nombre): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'xl_intranet_' . $nombre;
    }

    /** Escritura atómica: nunca queda un JSON a medio escribir si dos cargas coinciden. */
    private static function guardarArchivo(string $ruta, string $contenido): void
    {
        $tmp = $ruta . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $contenido) !== false) {
            @rename($tmp, $ruta);
        }
        @unlink($tmp);
    }

    private static function fallaReciente(string $marca): bool
    {
        return is_readable($marca) && (time() - (int)@file_get_contents($marca)) < self::SEG_REINTENTO;
    }

    // ==================== Personal ====================

    /**
     * Personal activo, desde el caché del día. null = no se pudo leer (se ocultan los bloques
     * de personas). Cada persona: legajo, nombre, pila, sector, dia, mes, ingreso (Y-m-d|null).
     */
    public static function personal(): ?array
    {
        if (array_key_exists('personal', self::$memo)) {
            return self::$memo['personal'];
        }

        $archivo = self::rutaCache('personal_' . date('Ymd') . '.json');
        $marcaFalla = self::rutaCache('personal_falla.txt');
        $datos = null;

        if (is_readable($archivo)) {
            $json = json_decode((string)@file_get_contents($archivo), true);
            if (is_array($json)) {
                $datos = $json;
            }
        }

        if ($datos === null && !self::fallaReciente($marcaFalla)) {
            $datos = self::leerPersonal();
            if ($datos === null) {
                @file_put_contents($marcaFalla, (string)time());
            } else {
                self::guardarArchivo($archivo, json_encode($datos, JSON_UNESCAPED_UNICODE));
                foreach (glob(self::rutaCache('personal_*.json')) ?: [] as $viejo) {
                    if ($viejo !== $archivo) {
                        @unlink($viejo);
                    }
                }
            }
        }

        return self::$memo['personal'] = $datos;
    }

    private static function leerPersonal(): ?array
    {
        $filas = self::consultar(self::conexion(),
            "SELECT legajo, nombre_mostrar, nombre_pila, sector, nacimiento_dia, nacimiento_mes, fecha_ingreso
               FROM dbo.FP_V_INTRA_PERSONAL");
        if ($filas === null) {
            return null;
        }

        $personas = [];
        foreach ($filas as $f) {
            $ingreso = self::aFecha($f['fecha_ingreso'] ?? null);
            $personas[] = [
                'legajo'  => (int)$f['legajo'],
                'nombre'  => trim((string)$f['nombre_mostrar']),
                'pila'    => trim((string)($f['nombre_pila'] ?: $f['nombre_mostrar'])),
                'sector'  => trim((string)($f['sector'] ?? '')),
                'dia'     => $f['nacimiento_dia'] !== null ? (int)$f['nacimiento_dia'] : null,
                'mes'     => $f['nacimiento_mes'] !== null ? (int)$f['nacimiento_mes'] : null,
                'ingreso' => $ingreso ? $ingreso->format('Y-m-d') : null,
            ];
        }
        return $personas;
    }

    /** Fecha del cumpleaños en un año dado. El 29/02 se muestra el 28/02 en años no bisiestos. */
    private static function cumpleEn(array $p, int $anio): ?DateTimeImmutable
    {
        if (empty($p['dia']) || empty($p['mes'])) {
            return null;
        }
        $dia = (int)$p['dia'];
        $mes = (int)$p['mes'];
        if ($mes === 2 && $dia === 29 && !checkdate(2, 29, $anio)) {
            $dia = 28;
        }
        if (!checkdate($mes, $dia, $anio)) {
            return null;
        }
        return (new DateTimeImmutable())->setDate($anio, $mes, $dia)->setTime(0, 0);
    }

    private static function conFecha(array $p, DateTimeImmutable $fecha, array $extra = []): array
    {
        return array_merge($p, ['fecha' => $fecha], $extra);
    }

    /** Quienes cumplen hoy. [] si no hay o si no se pudo leer el personal. */
    public static function cumpleHoy(): array
    {
        $hoy = self::hoy();
        $lista = [];
        foreach (self::personal() ?? [] as $p) {
            $c = self::cumpleEn($p, (int)$hoy->format('Y'));
            if ($c && $c == $hoy) {
                $lista[] = self::conFecha($p, $c);
            }
        }
        usort($lista, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return $lista;
    }

    /**
     * Si hoy cumple años el usuario logueado, sus datos (para el saludo personal); si no, null.
     * Se cruza por legajo (FP_SOF_USUARIOS.legajo): los usuarios compartidos no tienen y no ven saludo.
     */
    public static function cumpleUsuario(): ?array
    {
        $legajo = (int)Auth::legajo();
        if ($legajo <= 0) {
            return null;
        }
        foreach (self::cumpleHoy() as $p) {
            if ((int)$p['legajo'] === $legajo) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Vista previa de los saludos personales (?vista_previa=saludo), para revisar cómo se ven sin
     * esperar a un cumpleaños. Solo para quien puede cargar novedades (incluye admin total).
     * Devuelve [cumpleaños, aniversario] con el nombre del usuario logueado, o null.
     */
    public static function vistaPreviaSaludos(): ?array
    {
        if (($_GET['vista_previa'] ?? '') !== 'saludo' || !self::puedeCargar()) {
            return null;
        }
        $partes = preg_split('/\s+/u', trim((string)Auth::nombre()), -1, PREG_SPLIT_NO_EMPTY);
        $pila = mb_convert_case(mb_strtolower($partes[0] ?? 'equipo'), MB_CASE_TITLE, 'UTF-8');
        $base = ['pila' => $pila, 'previa' => true];
        return [$base, $base + ['anios' => 5, 'es_hito' => true]];
    }

    /** Quienes cumplen hoy años en XL (desde 1 año). */
    public static function aniversariosHoy(): array
    {
        return array_values(array_filter(self::aniversarios(), fn($p) => $p['es_hoy']));
    }

    /** Si hoy el usuario logueado cumple años en XL, sus datos (con 'anios'); si no, null. Mismo cruce por legajo. */
    public static function aniversarioUsuario(): ?array
    {
        $legajo = (int)Auth::legajo();
        if ($legajo <= 0) {
            return null;
        }
        foreach (self::aniversariosHoy() as $p) {
            if ((int)$p['legajo'] === $legajo) {
                return $p;
            }
        }
        return null;
    }

    /** "1 año" / "5 años" */
    public static function anios(int $n): string
    {
        return $n === 1 ? '1 año' : $n . ' años';
    }

    /** Próximo día (después de hoy) con cumpleaños: ['fecha' => ..., 'personas' => [...]] o null. */
    public static function proximoCumple(): ?array
    {
        $hoy = self::hoy();
        $anio = (int)$hoy->format('Y');
        $mejor = null;
        $personas = [];
        foreach (self::personal() ?? [] as $p) {
            $c = self::cumpleEn($p, $anio);
            if ($c && $c <= $hoy) {
                $c = self::cumpleEn($p, $anio + 1);
            }
            if (!$c) {
                continue;
            }
            if ($mejor === null || $c < $mejor) {
                $mejor = $c;
                $personas = [self::conFecha($p, $c)];
            } elseif ($c == $mejor) {
                $personas[] = self::conFecha($p, $c);
            }
        }
        if ($mejor === null) {
            return null;
        }
        usort($personas, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return ['fecha' => $mejor, 'personas' => $personas];
    }

    /**
     * Cumpleaños del mes actual, por día. Cada uno con es_hoy y paso (ya pasó este mes).
     * $desdeHoy = true deja solo los de hoy en adelante (columna del hub).
     */
    public static function cumpleMes(bool $desdeHoy = false): array
    {
        $hoy = self::hoy();
        $anio = (int)$hoy->format('Y');
        $mes = (int)$hoy->format('n');
        $lista = [];
        foreach (self::personal() ?? [] as $p) {
            $c = self::cumpleEn($p, $anio);
            if (!$c || (int)$c->format('n') !== $mes) {
                continue;
            }
            if ($desdeHoy && $c < $hoy) {
                continue;
            }
            $lista[] = self::conFecha($p, $c, ['es_hoy' => $c == $hoy, 'paso' => $c < $hoy]);
        }
        usort($lista, fn($a, $b) => [$a['fecha'], $a['nombre']] <=> [$b['fecha'], $b['nombre']]);
        return $lista;
    }

    /** Ingresaron en el mes y año actuales (primer ingreso). Cada uno con 'futuro' si todavía no llegó la fecha. */
    public static function bienvenidas(): array
    {
        $hoy = self::hoy();
        $lista = [];
        foreach (self::personal() ?? [] as $p) {
            $ing = self::aFecha($p['ingreso']);
            if ($ing && $ing->format('Y-m') === $hoy->format('Y-m')) {
                $lista[] = self::conFecha($p, $ing, ['futuro' => $ing > $hoy]);
            }
        }
        usort($lista, fn($a, $b) => [$a['fecha'], $a['nombre']] <=> [$b['fecha'], $b['nombre']]);
        return $lista;
    }

    /** Cumplen años en XL este mes, desde 1 año. Cada uno con 'anios' y la fecha de este año. */
    public static function aniversarios(): array
    {
        $hoy = self::hoy();
        $anio = (int)$hoy->format('Y');
        $mes = (int)$hoy->format('n');
        $lista = [];
        foreach (self::personal() ?? [] as $p) {
            $ing = self::aFecha($p['ingreso']);
            if (!$ing || (int)$ing->format('n') !== $mes) {
                continue;
            }
            $anios = $anio - (int)$ing->format('Y');
            if ($anios < 1) {
                continue;
            }
            $fecha = self::cumpleEn(['dia' => (int)$ing->format('j'), 'mes' => $mes], $anio);
            $lista[] = self::conFecha($p, $fecha, [
                'anios'  => $anios,
                'es_hito' => $anios % 5 === 0,   // 5, 10, 15... años: se resaltan
                'es_hoy' => $fecha == $hoy,
                'paso'   => $fecha < $hoy,
            ]);
        }
        usort($lista, fn($a, $b) => [$a['fecha'], $a['nombre']] <=> [$b['fecha'], $b['nombre']]);
        return $lista;
    }

    // ==================== Agenda ====================

    private static function filaAgenda(array $f): array
    {
        $desde = self::soloDia(self::aFecha($f['fecha_desde']));
        $hasta = self::soloDia(self::aFecha($f['fecha_hasta'])) ?? $desde;
        return [
            'desde'     => $desde,
            'hasta'     => $hasta,
            'titulo'    => trim((string)$f['titulo']),
            'tipo'      => trim((string)$f['tipo']),
            'detalle'   => trim((string)($f['detalle'] ?? '')),
            'origen'    => trim((string)$f['origen']),
            'origen_id' => (int)$f['origen_id'],
        ];
    }

    /**
     * Fechas de la agenda que siguen vigentes desde $desde (fecha_hasta >= $desde),
     * opcionalmente hasta $hasta (fecha_desde <= $hasta), con un tope y un tipo opcionales.
     */
    public static function agenda(DateTimeImmutable $desde, ?DateTimeImmutable $hasta = null,
                                  ?int $tope = null, ?string $tipo = null): array
    {
        $sql = "SELECT " . ($tope ? "TOP (" . (int)$tope . ") " : "") . "
                       fecha_desde, fecha_hasta, titulo, tipo, detalle, origen, origen_id
                  FROM dbo.FP_V_INTRA_AGENDA
                 WHERE fecha_hasta >= ?";
        $params = [$desde->format('Ymd')];
        if ($hasta) {
            $sql .= " AND fecha_desde <= ?";
            $params[] = $hasta->format('Ymd');
        }
        if ($tipo) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }
        $sql .= " ORDER BY fecha_desde, CASE tipo WHEN 'FERIADO' THEN 0 ELSE 1 END, titulo";

        $filas = self::consultar(self::conexion(), $sql, $params) ?? [];
        return array_map([self::class, 'filaAgenda'], $filas);
    }

    public static function proximasFechas(int $tope = 6): array
    {
        return self::agenda(self::hoy(), null, $tope);
    }

    /** Próximo evento de un tipo (FERIADO, COMERCIAL...) desde hoy, incluido el de hoy. */
    public static function proximoDeTipo(string $tipo): ?array
    {
        $clave = 'proximo_' . $tipo;
        if (!array_key_exists($clave, self::$memo)) {
            $r = self::agenda(self::hoy(), null, 1, $tipo);
            self::$memo[$clave] = $r[0] ?? null;
        }
        return self::$memo[$clave];
    }

    // ==================== Comunicados ====================

    /** Link a un documento de DocuGest. Único lugar a cambiar cuando DocuGest acepte ?doc=N. */
    public static function linkDocuGest(int $id, string $titulo): string
    {
        return self::URL_DOCUGEST . '?busqueda=' . rawurlencode(trim($titulo));
    }

    /** Valores por defecto de los campos de la versión 2 (comunicados viejos, DocuGest, sin recursosHumanos/intranet/sql/08). */
    const COMUNICADO_V2 = [
        'esquema' => 'SIN_IMAGEN', 'texto_inferior' => '', 'con_formato' => false, 'estado' => 'PUBLICADO',
        'aviso_emergente' => false, 'aviso_hasta' => null, 'modificado_en' => null, 'imagenes' => [], 'pdfs' => [],
        // Comentarios y reacciones (recursosHumanos/intranet/sql/10). interaccion: contadores y "mi reacción" (IntranetInteraccion::resumen)
        'permite_comentarios' => false, 'permite_reacciones' => false, 'interaccion' => null,
    ];

    /** Columnas de comentarios y reacciones para un SELECT sobre FP_INTRA_COMUNICADOS c (0 si falta recursosHumanos/intranet/sql/10). */
    public static function columnasInteraccion(): string
    {
        return self::tieneInteraccion()
            ? "c.permite_comentarios, c.permite_reacciones"
            : "0 AS permite_comentarios, 0 AS permite_reacciones";
    }

    /** ¿Ya se corrió recursosHumanos/intranet/sql/10 (comentarios y reacciones)? */
    public static function tieneInteraccion(): bool
    {
        if (!array_key_exists('tiene_interaccion', self::$memo)) {
            $f = self::consultar(self::conexion(),
                "SELECT COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'permite_comentarios') AS largo,
                        OBJECT_ID('dbo.FP_INTRA_COMENTARIOS', 'U') AS comentarios,
                        OBJECT_ID('dbo.FP_INTRA_REACCIONES', 'U') AS reacciones,
                        COL_LENGTH('dbo.FP_INTRA_REACCIONES', 'nombre_mostrado') AS nombre_reaccion");
            self::$memo['tiene_interaccion'] = !empty($f[0]['largo']) && !empty($f[0]['comentarios']) && !empty($f[0]['reacciones']);
            // Versión anterior del 10 (sin nombre en las reacciones): todo funciona igual; "Ver quiénes
            // reaccionaron" usa el nombre actual del usuario hasta que se vuelva a correr el script
            self::$memo['nombre_reaccion'] = !empty($f[0]['nombre_reaccion']);
        }
        return self::$memo['tiene_interaccion'];
    }

    /** ¿FP_INTRA_REACCIONES tiene nombre_mostrado (recursosHumanos/intranet/sql/10 actualizado)? */
    public static function reaccionConNombre(): bool
    {
        return self::tieneInteraccion() && !empty(self::$memo['nombre_reaccion']);
    }

    /** ¿Ya se corrió recursosHumanos/intranet/sql/11 (plantillas)? */
    public static function tienePlantillas(): bool
    {
        if (!array_key_exists('tiene_plantillas', self::$memo)) {
            $f = self::consultar(self::conexion(), "SELECT COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'plantilla_origen_id') AS largo");
            self::$memo['tiene_plantillas'] = !empty($f[0]['largo']);
        }
        return self::$memo['tiene_plantillas'];
    }

    /**
     * Condición para excluir las plantillas de todo lo que se publica o se lista como comunicado
     * (vacía si falta recursosHumanos/intranet/sql/11: todavía no hay plantillas). $alias: el de FP_INTRA_COMUNICADOS.
     */
    public static function sinPlantillas(string $alias = 'c'): string
    {
        return self::tienePlantillas() ? " AND $alias.es_plantilla = 0" : '';
    }

    /** ¿El comunicado muestra el bloque de comentarios o reacciones? Nunca en los de DocuGest. */
    public static function conInteraccionHabilitada(array $c): bool
    {
        return empty($c['automatico']) && (!empty($c['permite_comentarios']) || !empty($c['permite_reacciones']));
    }

    /** Columnas de la versión 2 para un SELECT sobre FP_INTRA_COMUNICADOS c (literales si falta recursosHumanos/intranet/sql/08). */
    public static function columnasV2(): string
    {
        return self::tieneV2()
            ? "c.esquema, c.texto_inferior, c.con_formato, c.estado, c.aviso_emergente, c.aviso_hasta"
            : "'SIN_IMAGEN' AS esquema, CAST(NULL AS NVARCHAR(1)) AS texto_inferior, 0 AS con_formato,
               'PUBLICADO' AS estado, 0 AS aviso_emergente, CAST(NULL AS DATE) AS aviso_hasta";
    }

    private static function filaComunicado(array $f): array
    {
        $desde = self::soloDia(self::aFecha($f['vigente_desde']));
        $creado = self::aFecha($f['creado_en']);
        return [
            'clave'       => 'c' . (int)$f['id'],
            'id'          => (int)$f['id'],
            'automatico'  => false,
            'categoria'   => trim((string)$f['categoria']),
            'estilo'      => self::estiloValido($f['estilo'] ?? 'NORMAL'),
            'esquema'     => self::esquemaValido($f['esquema'] ?? 'SIN_IMAGEN'),
            'titulo'      => trim((string)$f['titulo']),
            'texto'       => (string)$f['texto'],
            'texto_inferior'  => (string)($f['texto_inferior'] ?? ''),
            'con_formato'     => !empty($f['con_formato']),
            'estado'          => (string)($f['estado'] ?? 'PUBLICADO'),
            'aviso_emergente' => !empty($f['aviso_emergente']),
            'aviso_hasta'     => self::soloDia(self::aFecha($f['aviso_hasta'] ?? null)),
            'modificado_en'   => self::aFecha($f['modificado_en'] ?? null) ?? $creado,
            'imagenes'        => [],
            'pdfs'            => [],
            'permite_comentarios' => !empty($f['permite_comentarios']),
            'permite_reacciones'  => !empty($f['permite_reacciones']),
            'interaccion'         => null,
            'link_url'    => trim((string)($f['link_url'] ?? '')),
            'link_texto'  => trim((string)($f['link_texto'] ?? '')),
            'fijado'      => (bool)$f['fijado'],
            'fecha'       => $desde,
            'hasta'       => self::soloDia(self::aFecha($f['vigente_hasta'] ?? null)),
            'autor'       => trim((string)($f['sector_autor'] ?? '')),
            'marca'       => '',
            'es_nuevo'    => self::esNuevo($desde),
            // Mismo día: el cargado último va primero
            'orden'       => $desde->format('Ymd') . ($creado && $creado->format('Ymd') === $desde->format('Ymd') ? $creado->format('His') : '000000'),
        ];
    }

    /**
     * ¿Ya se corrió recursosHumanos/intranet/sql/07 (columna estilo)? Mientras no, todo se muestra como Clásico
     * y la carga no ofrece el selector.
     */
    public static function tieneEstilo(): bool
    {
        if (!array_key_exists('tiene_estilo', self::$memo)) {
            $f = self::consultar(self::conexion(), "SELECT COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'estilo') AS largo");
            self::$memo['tiene_estilo'] = !empty($f[0]['largo']);
        }
        return self::$memo['tiene_estilo'];
    }

    /** ¿Ya se corrió recursosHumanos/intranet/sql/08 (esquema, borradores, formato, aviso)? */
    public static function tieneV2(): bool
    {
        if (!array_key_exists('tiene_v2', self::$memo)) {
            $f = self::consultar(self::conexion(), "SELECT COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'esquema') AS largo");
            self::$memo['tiene_v2'] = !empty($f[0]['largo']);
        }
        return self::$memo['tiene_v2'];
    }

    /** ¿Ya se corrió recursosHumanos/intranet/sql/09 (tabla de imágenes y PDF)? */
    public static function tieneArchivos(): bool
    {
        if (!array_key_exists('tiene_archivos', self::$memo)) {
            $f = self::consultar(self::conexion(), "SELECT OBJECT_ID('dbo.FP_INTRA_COMUNICADOS_ARCHIVOS', 'U') AS id");
            self::$memo['tiene_archivos'] = !empty($f[0]['id']);
        }
        return self::$memo['tiene_archivos'];
    }

    public static function estiloValido($estilo): string
    {
        $estilo = strtoupper(trim((string)$estilo));
        return isset(self::ESTILOS[$estilo]) ? $estilo : 'NORMAL';
    }

    public static function esquemaValido($esquema): string
    {
        $esquema = strtoupper(trim((string)$esquema));
        return isset(self::ESQUEMAS[$esquema]) ? $esquema : 'SIN_IMAGEN';
    }

    public static function esquemaUsaImagen(string $esquema): bool
    {
        return $esquema !== 'SIN_IMAGEN';
    }

    /** Esquema con el que se muestra: sin imágenes (o sin archivos disponibles), siempre "Solo texto". */
    public static function esquemaEfectivo(array $c): string
    {
        $esquema = self::esquemaValido($c['esquema'] ?? 'SIN_IMAGEN');
        return empty($c['imagenes']) ? 'SIN_IMAGEN' : $esquema;
    }

    /** Imagen de portada de un comunicado (la marcada o la primera), o null. */
    public static function portada(array $c): ?array
    {
        if (self::esquemaEfectivo($c) === 'SIN_IMAGEN') {
            return null;
        }
        foreach ($c['imagenes'] as $im) {
            if (!empty($im['es_portada'])) {
                return $im;
            }
        }
        return $c['imagenes'][0];
    }

    /** Suma imágenes y PDF a una lista de comunicados cargados (una sola consulta). */
    public static function conArchivos(array $lista): array
    {
        $ids = [];
        foreach ($lista as $c) {
            if (empty($c['automatico']) && !empty($c['id'])) {
                $ids[] = (int)$c['id'];
            }
        }
        if (!$ids) {
            return $lista;
        }
        require_once __DIR__ . '/IntranetArchivos.php';
        $porComunicado = IntranetArchivos::paraMostrar($ids);
        foreach ($lista as &$c) {
            if (empty($c['automatico']) && isset($porComunicado[$c['id']])) {
                $c['imagenes'] = $porComunicado[$c['id']]['imagenes'];
                $c['pdfs'] = $porComunicado[$c['id']]['pdfs'];
            }
        }
        unset($c);
        return $lista;
    }

    /** HTML de una plantilla compartida de hub/partials (tarjeta o resumen de comunicado). */
    public static function plantilla(string $nombre, array $vars): string
    {
        require_once __DIR__ . '/IntranetFormato.php';
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/partials/' . basename($nombre) . '.php';
        return (string)ob_get_clean();
    }

    /** Id único dentro de la página (carruseles). */
    public static function idUnico(string $prefijo): string
    {
        static $n = 0;
        return $prefijo . '-' . (++$n);
    }

    /**
     * Clases CSS de la tarjeta según estilo y fijado. El estilo define el aspecto; el fondo
     * naranja de "fijado" queda solo para el estilo Clásico.
     */
    public static function clasesComunicado(array $c): string
    {
        $estilo = self::estiloValido($c['estilo'] ?? 'NORMAL');
        if ($estilo !== 'NORMAL') {
            return ' com-estilo-' . strtolower($estilo);
        }
        return !empty($c['fijado']) ? ' es-fijado' : '';
    }

    /** Etiqueta con ícono del estilo (vacía para Clásico). */
    public static function etiquetaEstilo(array $c): string
    {
        $estilo = self::estiloValido($c['estilo'] ?? 'NORMAL');
        if ($estilo === 'NORMAL') {
            return '';
        }
        [$nombre, $icono] = self::ESTILOS[$estilo];
        return '<span class="intra-tag intra-tag-estilo-' . strtolower($estilo) . '"><i class="fa-solid ' . $icono . '"></i> ' . self::e($nombre) . '</span>';
    }

    /** Comunicados cargados, activos y vigentes hoy (sin DocuGest). */
    public static function comunicadosCargados(): array
    {
        $hoy = self::hoy()->format('Ymd');
        $colEstilo = self::tieneEstilo() ? 'c.estilo' : "'NORMAL' AS estilo";
        $colV2 = self::columnasV2();
        $colInter = self::columnasInteraccion();
        // Los borradores nunca se muestran, aunque su vigencia ya haya empezado
        // Las plantillas nunca se publican (intranet, columna del hub, aviso y novedades salen de acá)
        $soloPublicados = (self::tieneV2() ? "AND c.estado = 'PUBLICADO'" : '') . self::sinPlantillas('c');
        $filas = self::consultar(self::conexion(),
            "SELECT c.id, c.titulo, c.texto, c.categoria, $colEstilo, $colV2, $colInter, c.link_url, c.link_texto, c.fijado,
                    c.vigente_desde, c.vigente_hasta, c.creado_en, c.modificado_en, a.sector_autor
               FROM dbo.FP_INTRA_COMUNICADOS c
              OUTER APPLY (SELECT TOP 1 s.nombre AS sector_autor
                             FROM dbo.FP_SOF_USUARIOS u
                             LEFT JOIN dbo.FP_SECTORES s ON s.id = u.sector_id
                            WHERE u.username = c.creado_por) a
              WHERE c.activo = 1
                $soloPublicados
                AND c.vigente_desde <= ?
                AND (c.vigente_hasta IS NULL OR c.vigente_hasta >= ?)", [$hoy, $hoy]) ?? [];
        return self::conInteraccion(self::conArchivos(array_map([self::class, 'filaComunicado'], $filas)));
    }

    /**
     * Suma contadores de reacciones y comentarios, y la reacción del usuario logueado, a los
     * comunicados que tienen comentarios o reacciones habilitados (una sola pasada por la base).
     */
    public static function conInteraccion(array $lista): array
    {
        $ids = [];
        foreach ($lista as $c) {
            if (self::conInteraccionHabilitada($c) && !empty($c['id'])) {
                $ids[] = (int)$c['id'];
            }
        }
        if (!$ids || !self::tieneInteraccion()) {
            return $lista;
        }
        require_once __DIR__ . '/IntranetInteraccion.php';
        $resumen = IntranetInteraccion::resumen($ids, (string)Auth::username());
        foreach ($lista as &$c) {
            if (self::conInteraccionHabilitada($c) && !empty($c['id'])) {
                $c['interaccion'] = $resumen[(int)$c['id']] ?? IntranetInteraccion::resumenVacio();
            }
        }
        unset($c);
        return $lista;
    }

    /** Versiones nuevas de DocuGest de los últimos DIAS_DOCUGEST días, como comunicados automáticos. */
    public static function docugestRecientes(): array
    {
        if (array_key_exists('docugest', self::$memo)) {
            return self::$memo['docugest'];
        }

        $archivo = self::rutaCache('docugest.json');
        $marcaFalla = self::rutaCache('docugest_falla.txt');
        $docs = null;

        if (is_readable($archivo) && (time() - filemtime($archivo)) < self::SEG_CACHE_DOCUGEST) {
            $json = json_decode((string)@file_get_contents($archivo), true);
            if (is_array($json) && ($json['dia'] ?? '') === date('Ymd')) {
                $docs = $json['docs'];
            }
        }

        if ($docs === null) {
            $docs = self::fallaReciente($marcaFalla) ? [] : self::leerDocuGest();
            if ($docs === null) {
                @file_put_contents($marcaFalla, (string)time());
                $docs = [];
            } else {
                self::guardarArchivo($archivo, json_encode(['dia' => date('Ymd'), 'docs' => $docs], JSON_UNESCAPED_UNICODE));
            }
        }

        $items = [];
        foreach ($docs as $d) {
            $fecha = self::aFecha($d['fecha']);
            if (!$fecha) {
                continue;
            }
            $titulo = trim((string)$d['titulo']);
            $version = trim((string)$d['version']);
            $nuevo = $d['marca'] === 'nuevo';
            $items[] = self::COMUNICADO_V2 + [
                'clave'      => 'd' . (int)$d['id'],
                'id'         => (int)$d['id'],
                'automatico' => true,
                'categoria'  => 'DOCUGEST',
                'estilo'     => 'NORMAL',
                'titulo'     => $titulo,
                'texto'      => $nuevo
                    ? 'Nuevo documento publicado en DocuGest' . ($version !== '' ? ' (versión ' . $version . ').' : '.')
                    : 'Se publicó una versión nueva en DocuGest' . ($version !== '' ? ': versión ' . $version . '.' : '.'),
                'link_url'   => self::linkDocuGest((int)$d['id'], $titulo),
                'link_texto' => 'Abrir en DocuGest',
                'fijado'     => false,
                'fecha'      => $fecha->setTime(0, 0),
                'hasta'      => null,
                'autor'      => '',
                // "Documento nuevo" / "Versión nueva": no confundir con la etiqueta "Nuevo" (publicado hoy o ayer)
                'marca'      => $nuevo ? 'Documento nuevo' : 'Versión nueva',
                'es_nuevo'   => self::esNuevo($fecha),
                'orden'      => $fecha->format('YmdHis'),
            ];
        }
        return self::$memo['docugest'] = $items;
    }

    private static function leerDocuGest(): ?array
    {
        $conn = ConexionApps::central();
        if (!$conn) {
            return null;
        }
        $limite = self::hoy()->modify('-' . self::DIAS_DOCUGEST . ' days')->format('Ymd');
        // Mismo criterio que DocuGest (Politica::marcaNovedad): fecha_ultima_version, no
        // fecha_actualizacion, que también cambia con bajas y ediciones de datos.
        $filas = self::consultar($conn,
            "SELECT id, titulo, version, fecha_creacion,
                    COALESCE(fecha_ultima_version, fecha_creacion) AS fecha
               FROM dbo.Politicas_Procedimientos
              WHERE estado = 'activo'
                AND COALESCE(fecha_ultima_version, fecha_creacion) >= ?
              ORDER BY COALESCE(fecha_ultima_version, fecha_creacion) DESC", [$limite]);
        sqlsrv_close($conn);
        if ($filas === null) {
            return null;
        }

        $docs = [];
        foreach ($filas as $f) {
            $fecha = self::aFecha($f['fecha']);
            $alta = self::aFecha($f['fecha_creacion']);
            $actualizado = $fecha && $alta && ($fecha->getTimestamp() - $alta->getTimestamp()) > 60;
            $docs[] = [
                'id'      => (int)$f['id'],
                'titulo'  => trim((string)$f['titulo']),
                'version' => trim((string)$f['version']),
                'fecha'   => $fecha ? $fecha->format('Y-m-d H:i:s') : null,
                'marca'   => $actualizado ? 'actualizado' : 'nuevo',
            ];
        }
        return $docs;
    }

    /** Todos los comunicados vigentes (cargados + DocuGest): fijados primero, después del más nuevo al más viejo. */
    public static function comunicadosVigentes(): array
    {
        if (!array_key_exists('vigentes', self::$memo)) {
            $todos = array_merge(self::comunicadosCargados(), self::docugestRecientes());
            usort($todos, fn($a, $b) => [$b['fijado'], $b['orden']] <=> [$a['fijado'], $a['orden']]);
            self::$memo['vigentes'] = $todos;
        }
        return self::$memo['vigentes'];
    }

    // ==================== Aviso al entrar al hub ====================

    /**
     * Comunicados publicados y vigentes marcados como aviso, cuyo "mostrar el aviso hasta" no pasó:
     * fijados primero y después del más nuevo al más viejo (el orden de comunicadosVigentes), hasta
     * TOPE_AVISOS. Cuáles ya se vieron hoy lo decide el navegador (localStorage), no el servidor:
     * en los locales el usuario es compartido.
     */
    public static function avisosHub(): array
    {
        if (!self::tieneV2()) {
            return [];
        }
        $hoy = self::hoy();
        $avisos = array_filter(self::comunicadosVigentes(), fn($c) =>
            empty($c['automatico']) && !empty($c['aviso_emergente']) && (!$c['aviso_hasta'] || $c['aviso_hasta'] >= $hoy));
        return array_slice(array_values($avisos), 0, self::TOPE_AVISOS);
    }

    /** Clave con la que el navegador recuerda que vio un aviso hoy: si el comunicado se edita, cambia. */
    public static function claveAviso(array $c): string
    {
        $mod = $c['modificado_en'] ?? null;
        return (int)$c['id'] . '-' . ($mod instanceof DateTimeInterface ? $mod->format('YmdHis') : '0');
    }

    // ==================== Resúmenes ====================

    /** "N novedades esta semana": comunicados de los últimos 7 días + fechas de los próximos 7. */
    public static function novedadesSemana(): int
    {
        $hoy = self::hoy();
        $hace7 = $hoy->modify('-6 days');
        $n = 0;
        foreach (self::comunicadosVigentes() as $c) {
            if ($c['fecha'] >= $hace7 && $c['fecha'] <= $hoy) {
                $n++;
            }
        }
        return $n + count(self::agenda($hoy, $hoy->modify('+7 days')));
    }

    /**
     * Aviso corto debajo de "Intranet XL" en Accesos Directos.
     * Cumpleaños > aniversarios de hoy; si además hay novedades, se suman: "Hoy cumple Lucía · 2 novedades".
     */
    public static function avisoAccesoDirecto(): string
    {
        $hoy = self::cumpleHoy();
        $aniv = self::aniversariosHoy();
        $evento = '';
        if (count($hoy) === 1) {
            $evento = 'Hoy cumple ' . $hoy[0]['pila'];
        } elseif (count($hoy) > 1) {
            $evento = 'Hoy cumplen ' . count($hoy) . ' personas';
        } elseif (count($aniv) === 1) {
            $evento = 'Hoy ' . $aniv[0]['pila'] . ' cumple ' . self::anios($aniv[0]['anios']) . ' en XL';
        } elseif (count($aniv) > 1) {
            $evento = 'Hoy celebramos ' . count($aniv) . ' aniversarios en XL';
        }

        $n = self::novedadesSemana();
        $novedades = $n === 1 ? '1 novedad' : $n . ' novedades';

        if ($evento !== '') {
            return $n > 0 ? $evento . ' · ' . $novedades : $evento;
        }
        if ($n > 0) {
            return $novedades . ' esta semana';
        }
        return 'Comunicados, cumpleaños y fechas';
    }

    /** Publicado hoy o ayer: lleva la etiqueta "Nuevo". */
    public static function esNuevo(DateTimeInterface $fecha): bool
    {
        $d = self::diasHasta($fecha);
        return $d <= 0 && $d >= -1;
    }

    /** Todo lo que muestra la columna del hub. Nunca lanza: ante un error devuelve lo mínimo. */
    public static function resumenHub(): array
    {
        $vacio = [
            'personal_ok' => false, 'cumple_hoy' => [], 'proximo_cumple' => null, 'cumple_mes' => [],
            'cumple_mes_total' => 0, 'feriado' => null, 'proximas_fechas' => [], 'fijados' => [],
            'ultimos' => [], 'aniversarios' => [], 'equipo_resto' => 0,
            'aviso' => 'Comunicados, cumpleaños y fechas', 'saludo' => null, 'saludo_aniv' => null, 'aniv_hoy' => [],
            'avisos' => [],
        ];
        try {
            $personalOk = self::personal() !== null;
            $cumpleHoy = self::cumpleHoy();
            $mes = self::cumpleMes(true);
            $vigentes = self::comunicadosVigentes();
            $fijados = array_values(array_filter($vigentes, fn($c) => $c['fijado']));
            $ultimos = array_slice(array_values(array_filter($vigentes, fn($c) => !$c['fijado'])), 0, 3);
            // Equipo XL en el hub: aniversarios de hoy en adelante. Las bienvenidas no van en la
            // columna: tienen su sección destacada en la intranet.
            $aniversarios = array_values(array_filter(self::aniversarios(), fn($p) => !$p['paso']));

            return [
                'personal_ok'      => $personalOk,
                'cumple_hoy'       => $cumpleHoy,
                'proximo_cumple'   => $cumpleHoy ? null : self::proximoCumple(),
                'cumple_mes'       => array_slice($mes, 0, 6),
                'cumple_mes_total' => count($mes),
                'feriado'          => self::proximoDeTipo('FERIADO'),
                'proximas_fechas'  => self::proximasFechas(6),
                'fijados'          => $fijados,
                'ultimos'          => $ultimos,
                'aniversarios'     => array_slice($aniversarios, 0, self::TOPE_EQUIPO_HUB),
                'equipo_resto'     => max(0, count($aniversarios) - self::TOPE_EQUIPO_HUB),
                'aviso'            => self::avisoAccesoDirecto(),
                'saludo'           => self::cumpleUsuario(),
                'saludo_aniv'      => self::aniversarioUsuario(),
                'aniv_hoy'         => self::aniversariosHoy(),
                'avisos'           => self::avisosHub(),
            ];
        } catch (Throwable $e) {
            error_log('Intranet::resumenHub: ' . $e->getMessage());
            return $vacio;
        }
    }

    // ==================== Formato ====================

    public static function e($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function ddmm(DateTimeInterface $f): string
    {
        return $f->format('d/m');
    }

    public static function diaSemana(DateTimeInterface $f): string
    {
        return self::DIAS_SEMANA[(int)$f->format('w')];
    }

    /** "LUN", "MIÉ"... */
    public static function diaSemanaCorto(DateTimeInterface $f): string
    {
        return mb_strtoupper(mb_substr(self::diaSemana($f), 0, 3));
    }

    /**
     * Link de un comunicado listo para usar en href: solo https://, http:// o ruta interna (/...).
     * Devuelve ['url' => ..., 'externo' => bool] o null si no es válido. La base ya lo valida;
     * esto es una segunda barrera al mostrar.
     */
    public static function linkSeguro(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return ['url' => $url, 'externo' => true];
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return ['url' => $url, 'externo' => false];
        }
        return null;
    }

    public static function mes(int $mes): string
    {
        return self::MESES[$mes] ?? '';
    }

    public static function mesCorto(DateTimeInterface $f): string
    {
        return mb_strtoupper(mb_substr(self::MESES[(int)$f->format('n')], 0, 3));
    }

    /** "lunes 16/11" */
    public static function diaYFecha(DateTimeInterface $f): string
    {
        return self::diaSemana($f) . ' ' . self::ddmm($f);
    }

    /** "27/11", "27 al 29/11" o "30/11 al 02/12" */
    public static function rango(DateTimeInterface $desde, ?DateTimeInterface $hasta): string
    {
        if (!$hasta || $hasta->format('Ymd') === $desde->format('Ymd')) {
            return self::ddmm($desde);
        }
        if ($desde->format('Ym') === $hasta->format('Ym')) {
            return $desde->format('d') . ' al ' . self::ddmm($hasta);
        }
        return self::ddmm($desde) . ' al ' . self::ddmm($hasta);
    }

    public static function diasHasta(DateTimeInterface $f): int
    {
        return (int)self::hoy()->diff(DateTimeImmutable::createFromInterface($f)->setTime(0, 0))->format('%r%a');
    }

    /** "hoy", "ayer", "hace 3 días", "hace 2 semanas" o "el 05/09" */
    public static function haceTiempo(DateTimeInterface $f): string
    {
        $dias = -self::diasHasta($f);
        if ($dias <= 0) return 'hoy';
        if ($dias === 1) return 'ayer';
        if ($dias < 7) return 'hace ' . $dias . ' días';
        if ($dias < 30) {
            $sem = intdiv($dias, 7);
            return $sem === 1 ? 'hace 1 semana' : 'hace ' . $sem . ' semanas';
        }
        return 'el ' . self::ddmm($f);
    }

    /** Con hora (comentarios): "recién", "hace 5 minutos", "hace 2 horas"; desde ayer, como haceTiempo. */
    public static function haceRato(DateTimeInterface $f): string
    {
        $seg = time() - $f->getTimestamp();
        if ($seg < 60) return 'recién';
        if ($seg < 3600) {
            $min = intdiv($seg, 60);
            return $min === 1 ? 'hace 1 minuto' : 'hace ' . $min . ' minutos';
        }
        if (self::diasHasta($f) === 0) {
            $h = intdiv($seg, 3600);
            return $h === 1 ? 'hace 1 hora' : 'hace ' . $h . ' horas';
        }
        return self::haceTiempo($f);
    }

    /** "en 12 días", "mañana" u "hoy" */
    public static function enDias(DateTimeInterface $f): string
    {
        $d = self::diasHasta($f);
        if ($d <= 0) return 'hoy';
        if ($d === 1) return 'mañana';
        return 'en ' . $d . ' días';
    }

    public static function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
        if (!$partes) {
            return '';
        }
        $ini = mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');
        return mb_strtoupper($ini);
    }

    public static function categoria(?string $cod): string
    {
        if ($cod === null || $cod === '') {
            return 'Sin categoría'; // solo borradores
        }
        return self::CATEGORIAS[$cod] ?? $cod;
    }

    public static function tipoFecha(string $cod): string
    {
        return self::TIPOS_FECHA[$cod] ?? $cod;
    }

    /** Link al comunicado dentro de la intranet (la pantalla lo busca, lo despliega y baja hasta él). */
    public static function linkComunicado(array $c): string
    {
        return self::URL_INTRANET . '?c=' . rawurlencode($c['clave']) . '#com-' . rawurlencode($c['clave']);
    }
}
