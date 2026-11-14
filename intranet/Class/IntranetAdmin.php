<?php
/**
 * Intranet XL - carga de novedades (comunicados y fechas)
 *
 * Lectura para los listados, validación y escritura en FP_INTRA_COMUNICADOS y FP_INTRA_FECHAS.
 * Lo usa recursosHumanos/intranet/admin.php. Los permisos los controla la pantalla antes de llamar acá.
 * Bajas lógicas (activo = 0), siempre con auditoría (quién y cuándo).
 */

require_once __DIR__ . '/Intranet.php';

class IntranetAdmin
{
    const MAX_TEXTO_V2 = 4000;  // con recursosHumanos/intranet/sql/08 (cuenta las marcas de formato)
    const MAX_FLYER    = 300;   // texto corto del esquema "Solo imagen (flyer)"

    const MAX = [
        'titulo'     => 120,
        'texto'      => 2000,   // sin recursosHumanos/intranet/sql/08; con 08, MAX_TEXTO_V2 (ver maxTexto())
        'link_url'   => 500,
        'link_texto' => 60,
        'detalle'    => 300,
    ];

    const ESTADOS = [
        'BORRADOR'   => 'Borrador',
        'VIGENTE'    => 'Vigente',
        'PROGRAMADO' => 'Programado',
        'VENCIDO'    => 'Vencido',
        'INACTIVO'   => 'Inactivo',
    ];

    const CATEGORIAS_CARGA = ['RRHH', 'COMERCIAL', 'SISTEMAS', 'DIRECCION', 'GENERAL'];
    const TIPOS_CARGA = ['COMERCIAL', 'OPERATIVA'];

    // ==================== Utilidades ====================

    /** SELECT con parámetros (las fechas vuelven como DateTimeImmutable). null si falla. */
    public static function consultar(string $sql, array $params = []): ?array
    {
        $conn = Intranet::conexion();
        if (!$conn) {
            return null;
        }
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            error_log('IntranetAdmin: error de consulta: ' . print_r(sqlsrv_errors(), true));
            return null;
        }
        $filas = [];
        while ($f = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            foreach ($f as $k => $v) {
                if ($v instanceof DateTimeInterface) {
                    $f[$k] = DateTimeImmutable::createFromInterface($v);
                }
            }
            $filas[] = $f;
        }
        sqlsrv_free_stmt($stmt);
        return $filas;
    }

    public static function ejecutar(string $sql, array $params): bool
    {
        $conn = Intranet::conexion();
        if (!$conn) {
            return false;
        }
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            error_log('IntranetAdmin: error al guardar: ' . print_r(sqlsrv_errors(), true));
            return false;
        }
        sqlsrv_free_stmt($stmt);
        return true;
    }

    public static function ahora(): string
    {
        return date('Y-m-d\TH:i:s'); // ISO 8601: SQL Server lo interpreta igual con cualquier idioma
    }

    public static function texto($valor): string
    {
        // Saltos de línea uniformes (\n) para que el largo cuente igual que en la base
        return trim(str_replace(["\r\n", "\r"], "\n", (string)$valor));
    }

    public static function largo(string $s): int
    {
        return mb_strlen($s, 'UTF-8');
    }

    /** Largo máximo de cada texto de comunicado: 4000 con recursosHumanos/intranet/sql/08, 2000 sin él. */
    public static function maxTexto(): int
    {
        return Intranet::tieneV2() ? self::MAX_TEXTO_V2 : self::MAX['texto'];
    }

    /** 'Y-m-d' de un input date -> DateTimeImmutable, o null si está vacío o no es una fecha real. */
    public static function fechaInput($valor): ?DateTimeImmutable
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return null;
        }
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($f && $f->format('Y-m-d') === $valor) ? $f : null;
    }

    // ==================== Comunicados ====================

    /** Estado para el listado. Inactivo > Borrador > (por vigencia) Programado, Vencido o Vigente. */
    public static function estadoComunicado(array $c): string
    {
        $hoy = Intranet::hoy();
        if (empty($c['activo'])) return 'INACTIVO';
        if (($c['estado'] ?? 'PUBLICADO') === 'BORRADOR') return 'BORRADOR';
        if ($c['vigente_desde'] > $hoy) return 'PROGRAMADO';
        if ($c['vigente_hasta'] && $c['vigente_hasta'] < $hoy) return 'VENCIDO';
        return 'VIGENTE';
    }

    /** SELECT base de comunicados. La columna estilo solo si ya se corrió recursosHumanos/intranet/sql/07. */
    private static function selectComunicados(): string
    {
        $colEstilo = Intranet::tieneEstilo() ? 'c.estilo' : "'NORMAL' AS estilo";
        $colV2 = Intranet::columnasV2();
        $colInter = Intranet::columnasInteraccion();
        // Plantillas (recursosHumanos/intranet/sql/11): datos propios y, en un comunicado, de qué plantilla salió
        $colPla = Intranet::tienePlantillas()
            ? "c.es_plantilla, c.plantilla_nombre, c.plantilla_descripcion, c.plantilla_orden, c.plantilla_origen_id,
               po.plantilla_nombre AS plantilla_origen_nombre"
            : "0 AS es_plantilla, CAST(NULL AS NVARCHAR(1)) AS plantilla_nombre, CAST(NULL AS NVARCHAR(1)) AS plantilla_descripcion,
               CAST(NULL AS INT) AS plantilla_orden, CAST(NULL AS INT) AS plantilla_origen_id,
               CAST(NULL AS NVARCHAR(1)) AS plantilla_origen_nombre";
        $joinPla = Intranet::tienePlantillas()
            ? "LEFT JOIN dbo.FP_INTRA_COMUNICADOS po ON po.id = c.plantilla_origen_id"
            : "";
        return "
        SELECT c.id, c.titulo, c.texto, c.categoria, $colEstilo, $colV2, $colInter, $colPla, c.link_url, c.link_texto, c.fijado,
               c.vigente_desde, c.vigente_hasta, c.activo,
               c.creado_por, c.creado_en, c.modificado_por, c.modificado_en,
               uc.nombre_completo AS creado_por_nombre,
               um.nombre_completo AS modificado_por_nombre
          FROM dbo.FP_INTRA_COMUNICADOS c
          $joinPla
         OUTER APPLY (SELECT TOP 1 nombre_completo FROM dbo.FP_SOF_USUARIOS WHERE username = c.creado_por) uc
         OUTER APPLY (SELECT TOP 1 nombre_completo FROM dbo.FP_SOF_USUARIOS WHERE username = c.modificado_por) um";
    }

    /** Comunicados (sin las plantillas). null si la consulta falló. */
    public static function listarComunicados(): ?array
    {
        $filas = self::consultar(self::selectComunicados() . " WHERE 1 = 1" . Intranet::sinPlantillas('c')
            . " ORDER BY c.fijado DESC, c.vigente_desde DESC, c.id DESC");
        if ($filas === null) {
            return null;
        }
        // Contadores de comentarios y reacciones (aunque las casillas estén desmarcadas: se ocultan, no se borran)
        $resumen = [];
        if (Intranet::tieneInteraccion() && $filas) {
            require_once __DIR__ . '/IntranetInteraccion.php';
            $resumen = IntranetInteraccion::resumen(array_column($filas, 'id'), null);
        }
        foreach ($filas as &$f) {
            $f['estado'] = self::estadoComunicado($f);
            $f['interaccion'] = $resumen[(int)$f['id']] ?? null;
        }
        return $filas;
    }

    /**
     * Un comunicado ($plantilla = false, lo normal), una plantilla (true) o cualquiera de los dos
     * (null). Así una acción de comunicados nunca toca una plantilla, ni al revés.
     */
    public static function comunicado(int $id, ?bool $plantilla = false): ?array
    {
        $sql = self::selectComunicados() . " WHERE c.id = ?";
        $params = [$id];
        if (Intranet::tienePlantillas() && $plantilla !== null) {
            $sql .= " AND c.es_plantilla = ?";
            $params[] = $plantilla ? 1 : 0;
        } elseif ($plantilla === true) {
            return null; // sin recursosHumanos/intranet/sql/11 no hay plantillas
        }
        $filas = self::consultar($sql, $params);
        return $filas[0] ?? null;
    }

    /** Datos de un comunicado guardado, como los manda el formulario (para editar, publicar o duplicar). */
    public static function datosDeComunicado(array $c): array
    {
        return [
            'titulo' => (string)$c['titulo'], 'texto' => (string)$c['texto'],
            'texto_inferior' => (string)($c['texto_inferior'] ?? ''),
            'esquema' => Intranet::esquemaValido($c['esquema'] ?? 'SIN_IMAGEN'),
            'categoria' => (string)($c['categoria'] ?? ''),
            'estilo' => Intranet::estiloValido($c['estilo'] ?? 'NORMAL'),
            'link_url' => (string)$c['link_url'], 'link_texto' => (string)$c['link_texto'], 'fijado' => (bool)$c['fijado'],
            'vigente_desde' => $c['vigente_desde'] ? $c['vigente_desde']->format('Y-m-d') : '',
            'vigente_hasta' => $c['vigente_hasta'] ? $c['vigente_hasta']->format('Y-m-d') : '',
            'con_formato' => !empty($c['con_formato']),
            'aviso_emergente' => !empty($c['aviso_emergente']),
            'aviso_hasta' => !empty($c['aviso_hasta']) ? $c['aviso_hasta']->format('Y-m-d') : '',
            'permite_comentarios' => !empty($c['permite_comentarios']),
            'permite_reacciones' => !empty($c['permite_reacciones']),
            'plantilla_nombre' => (string)($c['plantilla_nombre'] ?? ''),
            'plantilla_descripcion' => (string)($c['plantilla_descripcion'] ?? ''),
            'plantilla_origen_id' => (int)($c['plantilla_origen_id'] ?? 0) ?: null,
        ];
    }

    // ==================== Plantillas (recursosHumanos/intranet/sql/11) ====================

    const MAX_PLANTILLA_NOMBRE = 80;
    const MAX_PLANTILLA_DESCRIPCION = 200;

    /** Plantillas en orden (activas e inactivas, o solo activas). null si la consulta falló. */
    public static function listarPlantillas(bool $soloActivas = false): ?array
    {
        if (!Intranet::tienePlantillas()) {
            return [];
        }
        return self::consultar(self::selectComunicados() . " WHERE c.es_plantilla = 1"
            . ($soloActivas ? " AND c.activo = 1" : "")
            . " ORDER BY ISNULL(c.plantilla_orden, 2147483647), c.id");
    }

    /**
     * Datos de una plantilla para empezar un comunicado nuevo: todo el contenido, vigente desde hoy,
     * sin fecha de fin, sin fijar y sin fecha de aviso.
     */
    public static function datosDesdePlantilla(array $p): array
    {
        $d = self::datosDeComunicado($p);
        $d['vigente_desde'] = Intranet::hoy()->format('Y-m-d');
        $d['vigente_hasta'] = '';
        $d['fijado'] = false;
        $d['aviso_hasta'] = '';
        $d['plantilla_nombre'] = '';
        $d['plantilla_descripcion'] = '';
        $d['plantilla_origen_id'] = (int)$p['id'];
        return $d;
    }

    /**
     * Valida una plantilla. Se guarda como un borrador (solo exige el título y lo que la base no
     * acepta; no exige imagen: se agrega al usarla) más el nombre y la categoría. No usa vigencia,
     * fijado ni fecha del aviso.
     */
    public static function validarPlantilla(array $in, int $imagenes, bool $conArchivos): array
    {
        $in['vigente_desde'] = Intranet::hoy()->format('Y-m-d');
        $in['vigente_hasta'] = '';
        $in['fijado'] = '';
        $in['aviso_hasta'] = '';
        [$d, $err] = self::validarComunicado($in, $imagenes, $conArchivos, 'BORRADOR');
        $d['estado'] = 'BORRADOR'; // segunda barrera: aunque una consulta no la excluyera, no se muestra
        $d['es_plantilla'] = true;
        $d['plantilla_nombre'] = self::texto($in['plantilla_nombre'] ?? '');
        $d['plantilla_descripcion'] = self::texto($in['plantilla_descripcion'] ?? '');

        if ($d['plantilla_nombre'] === '') {
            $err['plantilla_nombre'] = 'Poné un nombre para la plantilla.';
        } elseif (self::largo($d['plantilla_nombre']) > self::MAX_PLANTILLA_NOMBRE) {
            $err['plantilla_nombre'] = 'El nombre puede tener hasta ' . self::MAX_PLANTILLA_NOMBRE . ' caracteres.';
        }
        if (self::largo($d['plantilla_descripcion']) > self::MAX_PLANTILLA_DESCRIPCION) {
            $err['plantilla_descripcion'] = 'La descripción puede tener hasta ' . self::MAX_PLANTILLA_DESCRIPCION . ' caracteres.';
        }
        if ($d['categoria'] === '') {
            $err['categoria'] = 'Elegí una categoría de la lista.';
        }
        return [$d, $err];
    }

    private static function siguienteOrdenPlantilla(): int
    {
        $f = self::consultar("SELECT ISNULL(MAX(plantilla_orden), 0) + 1 AS orden FROM dbo.FP_INTRA_COMUNICADOS WHERE es_plantilla = 1");
        return (int)($f[0]['orden'] ?? 1);
    }

    /**
     * Duplica una plantilla: copia todo (también imágenes y PDF), con " (copia)" en el nombre,
     * INACTIVA y al final del orden (no se ofrece hasta revisarla). Devuelve el id nuevo o null.
     */
    public static function duplicarPlantilla(int $id, string $usuario): ?int
    {
        $p = self::comunicado($id, true);
        if (!$p) {
            return null;
        }
        $d = self::datosDeComunicado($p);
        $sufijo = ' (copia)';
        $d['plantilla_nombre'] = mb_substr($d['plantilla_nombre'], 0, self::MAX_PLANTILLA_NOMBRE - mb_strlen($sufijo), 'UTF-8') . $sufijo;
        $d['estado'] = 'BORRADOR';
        $d['es_plantilla'] = true;
        $d['activo'] = false;
        $d['vigente_desde'] = Intranet::hoy()->format('Y-m-d');
        $d['vigente_hasta'] = '';

        require_once __DIR__ . '/IntranetArchivos.php';
        $nuevo = self::guardarComunicado(null, $d, $usuario);
        if ($nuevo && !IntranetArchivos::copiar($id, $nuevo, $usuario)) {
            error_log("IntranetAdmin::duplicarPlantilla: no se copiaron todos los archivos de $id a $nuevo");
        }
        return $nuevo;
    }

    /** Sube ($paso = -1) o baja (+1) una plantilla un lugar. Primero deja el orden en 1, 2, 3... */
    public static function moverPlantilla(int $id, int $paso, string $usuario): bool
    {
        $lista = self::listarPlantillas();
        if (!$lista) {
            return false;
        }
        $ids = array_map(fn($p) => (int)$p['id'], $lista);
        $pos = array_search($id, $ids, true);
        $destino = $pos === false ? false : $pos + ($paso < 0 ? -1 : 1);
        if ($pos === false || $destino < 0 || $destino >= count($ids)) {
            return false;
        }
        [$ids[$pos], $ids[$destino]] = [$ids[$destino], $ids[$pos]];

        $ok = true;
        foreach ($ids as $i => $pid) {
            $actual = null;
            foreach ($lista as $p) {
                if ((int)$p['id'] === $pid) {
                    $actual = (int)$p['plantilla_orden'];
                }
            }
            if ($actual !== $i + 1) {
                $ok = self::ejecutar(
                    "UPDATE dbo.FP_INTRA_COMUNICADOS SET plantilla_orden = ?, modificado_por = ?, modificado_en = ?
                      WHERE id = ? AND es_plantilla = 1",
                    [$i + 1, $usuario, self::ahora(), $pid]) && $ok;
            }
        }
        return $ok;
    }

    /**
     * Datos a completar que quedaron en el título y los textos: lo que esté entre corchetes, como
     * "[Nombre del local]". Los links con formato [texto](url) no cuentan. Sin repetir, en orden.
     */
    public static function datosPendientes(array $d): array
    {
        $pendientes = [];
        foreach (['titulo', 'texto', 'texto_inferior'] as $campo) {
            // Mismo patrón que el resaltado de la vista previa (IntranetFormato::PATRON_DATO) y que intranet_carga.js
            if (preg_match_all('/\[([^\[\]\n<>]{1,80})\](?!\()/u', (string)($d[$campo] ?? ''), $m)) {
                foreach ($m[0] as $dato) {
                    $pendientes[$dato] = true;
                }
            }
        }
        return array_keys($pendientes);
    }

    /**
     * Valida lo que llega del formulario. Devuelve [datos normalizados, errores por campo].
     * $imagenes: cuántas imágenes quedan en el formulario. $conArchivos: si se pueden cargar
     * (si no, no se exige imagen para los esquemas con imagen: se muestran como "Solo texto").
     * $estado: PUBLICADO aplica todas las reglas; BORRADOR solo exige el título (y lo que la base
     * no acepta: largos, link y fechas válidas). Un borrador sin "vigente desde" queda con hoy.
     */
    public static function validarComunicado(array $in, int $imagenes = 0, bool $conArchivos = false, string $estado = 'PUBLICADO'): array
    {
        [$d, $err] = self::validarCompleto($in, $imagenes, $conArchivos);
        $d['estado'] = Intranet::tieneV2() && $estado === 'BORRADOR' ? 'BORRADOR' : 'PUBLICADO';
        if ($d['estado'] !== 'BORRADOR') {
            return [$d, $err];
        }

        $max = self::maxTexto();
        if ($d['categoria'] === '') {
            unset($err['categoria']);
        }
        if ($d['vigente_desde'] === '') {
            $d['vigente_desde'] = Intranet::hoy()->format('Y-m-d');
            unset($err['vigente_desde']);
        }
        // En un borrador el texto puede faltar o pasarse del tope del flyer; solo cuenta el de la base
        foreach (['texto', 'texto_inferior'] as $campo) {
            unset($err[$campo]);
            if (self::largo($d[$campo]) > $max) {
                $err[$campo] = 'El texto puede tener hasta ' . $max . ' caracteres.';
            }
        }
        if (isset(Intranet::ESQUEMAS[$d['esquema']])) {
            unset($err['esquema']); // esquema con imagen sin imágenes: se exige al publicar
        }
        return [$d, $err];
    }

    private static function validarCompleto(array $in, int $imagenes, bool $conArchivos): array
    {
        $v2 = Intranet::tieneV2();
        $esquema = strtoupper(trim((string)($in['esquema'] ?? 'SIN_IMAGEN'))) ?: 'SIN_IMAGEN';
        $d = [
            'titulo'        => self::texto($in['titulo'] ?? ''),
            'texto'         => self::texto($in['texto'] ?? ''),
            'texto_inferior' => $esquema === 'CENTRO' ? self::texto($in['texto_inferior'] ?? '') : '',
            'esquema'       => $v2 ? $esquema : 'SIN_IMAGEN',
            'categoria'     => strtoupper(trim((string)($in['categoria'] ?? ''))),
            'estilo'        => strtoupper(trim((string)($in['estilo'] ?? 'NORMAL'))) ?: 'NORMAL',
            'link_url'      => trim((string)($in['link_url'] ?? '')),
            'link_texto'    => self::texto($in['link_texto'] ?? ''),
            'fijado'        => !empty($in['fijado']),
            'vigente_desde' => trim((string)($in['vigente_desde'] ?? '')),
            'vigente_hasta' => trim((string)($in['vigente_hasta'] ?? '')),
            'con_formato'   => $v2, // todo lo que se guarda desde la carga nueva queda con formato
            'aviso_emergente' => $v2 && !empty($in['aviso_emergente']),
            'aviso_hasta'   => trim((string)($in['aviso_hasta'] ?? '')),
            'permite_comentarios' => Intranet::tieneInteraccion() && !empty($in['permite_comentarios']),
            'permite_reacciones'  => Intranet::tieneInteraccion() && !empty($in['permite_reacciones']),
        ];
        if (!$d['aviso_emergente']) {
            $d['aviso_hasta'] = ''; // sin aviso, la fecha no tiene sentido
        }
        $err = [];

        if ($d['titulo'] === '') {
            $err['titulo'] = 'Poné un título.';
        } elseif (self::largo($d['titulo']) > self::MAX['titulo']) {
            $err['titulo'] = 'El título puede tener hasta ' . self::MAX['titulo'] . ' caracteres.';
        }

        // Texto según el esquema: FLYER corto y opcional; CENTRO, al menos uno de los dos; el resto, obligatorio
        $max = self::maxTexto();
        if (!isset(Intranet::ESQUEMAS[$d['esquema']])) {
            $err['esquema'] = 'Elegí uno de los esquemas.';
        } elseif ($d['esquema'] === 'FLYER') {
            if (self::largo($d['texto']) > self::MAX_FLYER) {
                $err['texto'] = 'Con «Solo imagen» el texto puede tener hasta ' . self::MAX_FLYER . ' caracteres.';
            }
        } elseif ($d['esquema'] === 'CENTRO') {
            if ($d['texto'] === '' && $d['texto_inferior'] === '') {
                $err['texto'] = 'Escribí al menos uno de los dos textos.';
            }
            if (self::largo($d['texto']) > $max) {
                $err['texto'] = 'El texto puede tener hasta ' . $max . ' caracteres.';
            }
            if (self::largo($d['texto_inferior']) > $max) {
                $err['texto_inferior'] = 'El texto puede tener hasta ' . $max . ' caracteres.';
            }
        } elseif ($d['texto'] === '') {
            $err['texto'] = 'Escribí el texto del comunicado.';
        } elseif (self::largo($d['texto']) > $max) {
            $err['texto'] = 'El texto puede tener hasta ' . $max . ' caracteres.';
        }

        if (!isset($err['esquema']) && $conArchivos && Intranet::esquemaUsaImagen($d['esquema']) && $imagenes === 0) {
            $err['esquema'] = 'Elegiste un esquema con imagen pero no cargaste ninguna.';
        }

        if (!in_array($d['categoria'], self::CATEGORIAS_CARGA, true)) {
            $err['categoria'] = 'Elegí una categoría de la lista.';
            if ($d['categoria'] !== '') {
                $d['categoria'] = ''; // un valor fuera de la lista no se guarda nunca (ni en borrador)
            }
        }

        if (!isset(Intranet::ESTILOS[$d['estilo']])) {
            $err['estilo'] = 'Elegí uno de los estilos.';
        }

        $desde = self::fechaInput($d['vigente_desde']);
        $hasta = self::fechaInput($d['vigente_hasta']);
        if ($d['vigente_desde'] === '') {
            $err['vigente_desde'] = 'Poné desde cuándo se muestra.';
        } elseif (!$desde) {
            $err['vigente_desde'] = 'La fecha no es válida.';
        }
        if ($d['vigente_hasta'] !== '' && !$hasta) {
            $err['vigente_hasta'] = 'La fecha no es válida.';
        } elseif ($desde && $hasta && $hasta < $desde) {
            $err['vigente_hasta'] = '"Vigente hasta" no puede ser anterior a "vigente desde".';
        }

        // Aviso al entrar al hub: la fecha opcional tiene que caer dentro de la vigencia
        if ($d['aviso_hasta'] !== '') {
            $avisoHasta = self::fechaInput($d['aviso_hasta']);
            if (!$avisoHasta) {
                $err['aviso_hasta'] = 'La fecha no es válida.';
            } elseif ($hasta && $avisoHasta > $hasta) {
                $err['aviso_hasta'] = '"Mostrar el aviso hasta" no puede ser posterior a "vigente hasta".';
            } elseif ($desde && $avisoHasta < $desde) {
                $err['aviso_hasta'] = '"Mostrar el aviso hasta" no puede ser anterior a "vigente desde".';
            }
        }

        if ($d['link_url'] !== '') {
            if (!preg_match('#^(https?://|/)#i', $d['link_url'])) {
                $err['link_url'] = 'El link tiene que empezar con https://, http:// o /.';
            } elseif (self::largo($d['link_url']) > self::MAX['link_url']) {
                $err['link_url'] = 'El link puede tener hasta ' . self::MAX['link_url'] . ' caracteres.';
            }
        }
        if (self::largo($d['link_texto']) > self::MAX['link_texto']) {
            $err['link_texto'] = 'El texto del botón puede tener hasta ' . self::MAX['link_texto'] . ' caracteres.';
        }

        return [$d, $err];
    }

    /** Alta (id null) o edición. $d ya validado. Devuelve el id del comunicado, o null si falló. */
    public static function guardarComunicado(?int $id, array $d, string $usuario): ?int
    {
        $desde = self::fechaInput($d['vigente_desde'])->format('Ymd');
        $hasta = self::fechaInput($d['vigente_hasta']);
        $link = $d['link_url'] !== '' ? $d['link_url'] : null;
        $params = [
            $d['titulo'], $d['texto'], $d['categoria'] !== '' ? $d['categoria'] : null, // sin categoría: solo borradores
            $link, ($link && $d['link_texto'] !== '') ? $d['link_texto'] : null,
            $d['fijado'] ? 1 : 0, $desde, $hasta ? $hasta->format('Ymd') : null,
            $usuario, self::ahora(),
        ];

        // Columnas opcionales: solo si ya se corrió su script; si no, quedan los DEFAULT de la base
        $extra = [];
        if (Intranet::tieneEstilo()) {
            $extra['estilo'] = $d['estilo']; // recursosHumanos/intranet/sql/07
        }
        if (Intranet::tieneV2()) {
            // recursosHumanos/intranet/sql/08
            $extra['esquema'] = $d['esquema'];
            $extra['texto_inferior'] = $d['texto_inferior'] !== '' ? $d['texto_inferior'] : null;
            $extra['con_formato'] = !empty($d['con_formato']) ? 1 : 0;
            $extra['estado'] = ($d['estado'] ?? 'PUBLICADO') === 'BORRADOR' ? 'BORRADOR' : 'PUBLICADO';
            $avisoHasta = !empty($d['aviso_emergente']) ? self::fechaInput($d['aviso_hasta'] ?? '') : null;
            $extra['aviso_emergente'] = !empty($d['aviso_emergente']) ? 1 : 0;
            $extra['aviso_hasta'] = $avisoHasta ? $avisoHasta->format('Ymd') : null;
        }
        if (Intranet::tieneInteraccion()) {
            // recursosHumanos/intranet/sql/10. Desmarcar no borra comentarios ni reacciones: solo dejan de mostrarse
            $extra['permite_comentarios'] = !empty($d['permite_comentarios']) ? 1 : 0;
            $extra['permite_reacciones'] = !empty($d['permite_reacciones']) ? 1 : 0;
        }
        if (Intranet::tienePlantillas()) {
            // recursosHumanos/intranet/sql/11. Ser plantilla, el orden y el origen se fijan al crear; no cambian al editar
            $esPlantilla = !empty($d['es_plantilla']);
            if ($id === null) {
                $extra['es_plantilla'] = $esPlantilla ? 1 : 0;
                $extra['plantilla_orden'] = $esPlantilla ? self::siguienteOrdenPlantilla() : null;
                $extra['plantilla_origen_id'] = !$esPlantilla && !empty($d['plantilla_origen_id']) ? (int)$d['plantilla_origen_id'] : null;
                if (array_key_exists('activo', $d)) {
                    $extra['activo'] = $d['activo'] ? 1 : 0;
                }
            }
            if ($esPlantilla) {
                $extra['plantilla_nombre'] = $d['plantilla_nombre'];
                $extra['plantilla_descripcion'] = $d['plantilla_descripcion'] !== '' ? $d['plantilla_descripcion'] : null;
            }
        }
        $params = array_merge($params, array_values($extra));
        $cols = array_keys($extra);

        if ($id === null) {
            $filas = self::consultar(
                "INSERT INTO dbo.FP_INTRA_COMUNICADOS
                    (titulo, texto, categoria, link_url, link_texto, fijado, vigente_desde, vigente_hasta, creado_por, creado_en"
                    . implode('', array_map(fn($col) => ", $col", $cols)) . ")
                 OUTPUT INSERTED.id
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . str_repeat(', ?', count($cols)) . ")", $params);
            return !empty($filas[0]['id']) ? (int)$filas[0]['id'] : null;
        }
        $params[] = $id;
        $ok = self::ejecutar(
            "UPDATE dbo.FP_INTRA_COMUNICADOS
                SET titulo = ?, texto = ?, categoria = ?, link_url = ?, link_texto = ?, fijado = ?,
                    vigente_desde = ?, vigente_hasta = ?, modificado_por = ?, modificado_en = ?"
                    . implode('', array_map(fn($col) => ", $col = ?", $cols)) . "
              WHERE id = ?", $params);
        return $ok ? $id : null;
    }

    /** Publicar un borrador o volver un publicado a borrador (solo con recursosHumanos/intranet/sql/08). */
    public static function cambiarEstado(int $id, string $estado, string $usuario): bool
    {
        if (!Intranet::tieneV2() || !in_array($estado, ['BORRADOR', 'PUBLICADO'], true)) {
            return false;
        }
        return self::ejecutar(
            "UPDATE dbo.FP_INTRA_COMUNICADOS SET estado = ?, modificado_por = ?, modificado_en = ? WHERE id = ?",
            [$estado, $usuario, self::ahora(), $id]);
    }

    /**
     * Duplica un comunicado como borrador nuevo: textos, categoría, estilo, esquema, link, fijado,
     * casillas de comentarios y reacciones (no los comentarios ni las reacciones) y archivos
     * (copiados en disco: borrar el original no afecta a la copia). Título con " (copia)",
     * vigente desde hoy y sin fecha de fin. Devuelve el id nuevo o null.
     */
    public static function duplicarComunicado(int $id, string $usuario): ?int
    {
        $c = self::comunicado($id);
        if (!$c || !Intranet::tieneV2()) {
            return null;
        }
        $d = self::datosDeComunicado($c);
        $sufijo = ' (copia)';
        $d['titulo'] = mb_substr($d['titulo'], 0, self::MAX['titulo'] - mb_strlen($sufijo), 'UTF-8') . $sufijo;
        $d['vigente_desde'] = Intranet::hoy()->format('Y-m-d');
        $d['vigente_hasta'] = '';
        $d['estado'] = 'BORRADOR';
        $d['aviso_hasta'] = '';

        require_once __DIR__ . '/IntranetArchivos.php';
        $nuevo = self::guardarComunicado(null, $d, $usuario);
        if ($nuevo && !IntranetArchivos::copiar($id, $nuevo, $usuario)) {
            error_log("IntranetAdmin::duplicarComunicado: no se copiaron todos los archivos de $id a $nuevo");
        }
        return $nuevo;
    }

    /**
     * Comunicado armado con los datos del formulario, igual que lo arma Intranet para mostrarlo
     * (para la vista previa: se muestra con las mismas plantillas que la pantalla real).
     * $archivos: los de IntranetArchivos::delFormulario.
     */
    public static function paraMostrar(array $d, array $archivos, ?int $id, string $autor): array
    {
        require_once __DIR__ . '/IntranetArchivos.php';
        $desde = self::fechaInput($d['vigente_desde']) ?? Intranet::hoy();
        $media = IntranetArchivos::itemsParaMostrar($archivos);
        return [
            'clave' => 'c' . ($id ?: 0), 'id' => $id ?: 0, 'automatico' => false,
            'categoria' => $d['categoria'], 'estilo' => Intranet::estiloValido($d['estilo']),
            'esquema' => Intranet::esquemaValido($d['esquema']),
            'titulo' => $d['titulo'] !== '' ? $d['titulo'] : 'Título del comunicado',
            'texto' => $d['texto'], 'texto_inferior' => $d['texto_inferior'],
            'con_formato' => !empty($d['con_formato']), 'estado' => $d['estado'] ?? 'PUBLICADO',
            'link_url' => $d['link_url'], 'link_texto' => $d['link_texto'], 'fijado' => !empty($d['fijado']),
            'fecha' => $desde, 'hasta' => self::fechaInput($d['vigente_hasta']),
            'autor' => $autor, 'marca' => '', 'es_nuevo' => Intranet::esNuevo($desde),
            'aviso_emergente' => !empty($d['aviso_emergente']), 'aviso_hasta' => self::fechaInput($d['aviso_hasta'] ?? ''),
            'modificado_en' => new DateTimeImmutable(),
            'imagenes' => $media['imagenes'], 'pdfs' => $media['pdfs'],
            'permite_comentarios' => !empty($d['permite_comentarios']), 'permite_reacciones' => !empty($d['permite_reacciones']),
            'interaccion' => null, // vista previa: el bloque se muestra de ejemplo, en cero
        ];
    }

    /** Sector de un usuario (lo que la intranet muestra como autor). */
    public static function sectorDe(string $usuario): string
    {
        $f = self::consultar(
            "SELECT TOP 1 s.nombre FROM dbo.FP_SOF_USUARIOS u LEFT JOIN dbo.FP_SECTORES s ON s.id = u.sector_id WHERE u.username = ?",
            [$usuario]);
        return trim((string)($f[0]['nombre'] ?? ''));
    }

    // ==================== Fechas ====================

    /** Fechas de la intranet (activas e inactivas). null si la consulta falló. */
    public static function listarFechas(): ?array
    {
        return self::consultar(
            "SELECT f.id, f.fecha_desde, ISNULL(f.fecha_hasta, f.fecha_desde) AS fecha_fin, f.fecha_hasta,
                    f.titulo, f.tipo, f.detalle, f.activo, f.creado_por, f.modificado_por,
                    uc.nombre_completo AS creado_por_nombre
               FROM dbo.FP_INTRA_FECHAS f
              OUTER APPLY (SELECT TOP 1 nombre_completo FROM dbo.FP_SOF_USUARIOS WHERE username = f.creado_por) uc
              ORDER BY f.fecha_desde, f.id");
    }

    /** Feriados activos desde una fecha, solo para consulta. */
    public static function listarFeriados(DateTimeImmutable $desde): array
    {
        return self::consultar(
            "SELECT id, fecha AS fecha_desde, fecha AS fecha_fin, descripcion AS titulo
               FROM dbo.FP_VAC_FERIADOS
              WHERE ISNULL(activo, 1) = 1 AND fecha >= ?
              ORDER BY fecha", [$desde->format('Ymd')]) ?? [];
    }

    public static function fecha(int $id): ?array
    {
        $filas = self::consultar(
            "SELECT id, fecha_desde, fecha_hasta, titulo, tipo, detalle, activo
               FROM dbo.FP_INTRA_FECHAS WHERE id = ?", [$id]);
        return $filas[0] ?? null;
    }

    public static function validarFecha(array $in): array
    {
        $d = [
            'fecha_desde' => trim((string)($in['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string)($in['fecha_hasta'] ?? '')),
            'titulo'      => self::texto($in['titulo'] ?? ''),
            'tipo'        => strtoupper(trim((string)($in['tipo'] ?? ''))),
            'detalle'     => self::texto($in['detalle'] ?? ''),
        ];
        $err = [];

        $desde = self::fechaInput($d['fecha_desde']);
        $hasta = self::fechaInput($d['fecha_hasta']);
        if ($d['fecha_desde'] === '') {
            $err['fecha_desde'] = 'Poné la fecha.';
        } elseif (!$desde) {
            $err['fecha_desde'] = 'La fecha no es válida.';
        }
        if ($d['fecha_hasta'] !== '' && !$hasta) {
            $err['fecha_hasta'] = 'La fecha no es válida.';
        } elseif ($desde && $hasta && $hasta < $desde) {
            $err['fecha_hasta'] = '"Hasta" no puede ser anterior a "desde".';
        }

        if ($d['titulo'] === '') {
            $err['titulo'] = 'Poné un título.';
        } elseif (self::largo($d['titulo']) > self::MAX['titulo']) {
            $err['titulo'] = 'El título puede tener hasta ' . self::MAX['titulo'] . ' caracteres.';
        }

        if (!in_array($d['tipo'], self::TIPOS_CARGA, true)) {
            $err['tipo'] = 'Elegí si es comercial u operativa.';
        }

        if (self::largo($d['detalle']) > self::MAX['detalle']) {
            $err['detalle'] = 'El detalle puede tener hasta ' . self::MAX['detalle'] . ' caracteres.';
        }

        return [$d, $err];
    }

    public static function guardarFecha(?int $id, array $d, string $usuario): bool
    {
        $desde = self::fechaInput($d['fecha_desde']);
        $hasta = self::fechaInput($d['fecha_hasta']);
        // Un solo día: "hasta" igual a "desde" se guarda vacío
        if ($hasta && $hasta == $desde) {
            $hasta = null;
        }
        $params = [
            $desde->format('Ymd'), $hasta ? $hasta->format('Ymd') : null,
            $d['titulo'], $d['tipo'], $d['detalle'] !== '' ? $d['detalle'] : null,
            $usuario, self::ahora(),
        ];

        if ($id === null) {
            return self::ejecutar(
                "INSERT INTO dbo.FP_INTRA_FECHAS (fecha_desde, fecha_hasta, titulo, tipo, detalle, creado_por, creado_en)
                 VALUES (?, ?, ?, ?, ?, ?, ?)", $params);
        }
        $params[] = $id;
        return self::ejecutar(
            "UPDATE dbo.FP_INTRA_FECHAS
                SET fecha_desde = ?, fecha_hasta = ?, titulo = ?, tipo = ?, detalle = ?,
                    modificado_por = ?, modificado_en = ?
              WHERE id = ?", $params);
    }

    // ==================== Baja lógica ====================

    /** Desactivar o reactivar. $tabla: 'comunicados' o 'fechas'. */
    public static function cambiarActivo(string $tabla, int $id, bool $activo, string $usuario): bool
    {
        $tablas = ['comunicados' => 'dbo.FP_INTRA_COMUNICADOS', 'fechas' => 'dbo.FP_INTRA_FECHAS'];
        if (!isset($tablas[$tabla])) {
            return false;
        }
        return self::ejecutar(
            "UPDATE {$tablas[$tabla]} SET activo = ?, modificado_por = ?, modificado_en = ? WHERE id = ?",
            [$activo ? 1 : 0, $usuario, self::ahora(), $id]);
    }
}
