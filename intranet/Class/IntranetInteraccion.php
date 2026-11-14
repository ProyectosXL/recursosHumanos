<?php
/**
 * Intranet XL - comentarios y reacciones de los comunicados (recursosHumanos/intranet/sql/10)
 *
 * Lectura (contadores, "mi reacción", comentarios) y escritura (reaccionar, comentar, borrar el
 * propio, ocultar y volver a mostrar). Los permisos, la sesión y el CSRF los controla
 * recursosHumanos/intranet/interaccion.php antes de llamar acá; esta clase controla las reglas de negocio:
 *   - solo comunicados activos, publicados, vigentes hoy y con la opción habilitada;
 *   - una reacción por usuario y comunicado (en un local, el usuario compartido cuenta como uno);
 *   - comentarios de texto plano, hasta MAX_COMENTARIO, no más de uno cada SEG_ENTRE_COMENTARIOS
 *     por usuario;
 *   - nada se borra físicamente: el autor da de baja lo suyo (borrado) y un responsable de carga
 *     oculta (oculto, con quién y cuándo).
 * El usuario se identifica por su username, como creado_por en el resto de la intranet.
 */

require_once __DIR__ . '/IntranetAdmin.php';

class IntranetInteraccion
{
    /** Reacciones: código => [nombre, ícono de Font Awesome]. Ver el CHECK de recursosHumanos/intranet/sql/10. */
    const REACCIONES = [
        'ME_GUSTA'   => ['Me gusta', 'fa-thumbs-up'],
        'APLAUSOS'   => ['Aplausos', 'fa-hands-clapping'],
        'ME_ENCANTA' => ['Me encanta', 'fa-heart'],
    ];

    const MAX_COMENTARIO        = 500;
    const SEG_ENTRE_COMENTARIOS = 10;
    const COMENTARIOS_VISIBLES  = 3;   // los más recientes; el resto, con "Ver todos los comentarios"

    const T_COMENTARIOS = 'dbo.FP_INTRA_COMENTARIOS';
    const T_REACCIONES  = 'dbo.FP_INTRA_REACCIONES';

    public static function resumenVacio(): array
    {
        return [
            'reacciones'       => array_fill_keys(array_keys(self::REACCIONES), 0),
            'total_reacciones' => 0,
            'mia'              => null,
            'comentarios'      => 0,   // visibles para todos
            'ocultos'          => 0,
        ];
    }

    private static function marcas(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    // ==================== Lectura ====================

    /**
     * Contadores de varios comunicados: [id => resumenVacio() con los datos]. $usuario: para saber
     * cuál es su reacción (null = no importa, por ejemplo en el listado de carga).
     */
    public static function resumen(array $ids, ?string $usuario): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !Intranet::tieneInteraccion()) {
            return [];
        }
        $res = [];
        foreach ($ids as $id) {
            $res[$id] = self::resumenVacio();
        }

        $filas = IntranetAdmin::consultar(
            "SELECT comunicado_id, tipo, COUNT(*) AS cantidad,
                    MAX(CASE WHEN usuario = ? THEN 1 ELSE 0 END) AS mia
               FROM " . self::T_REACCIONES . "
              WHERE comunicado_id IN (" . self::marcas($ids) . ")
              GROUP BY comunicado_id, tipo", array_merge([(string)$usuario], $ids)) ?? [];
        foreach ($filas as $f) {
            $cid = (int)$f['comunicado_id'];
            $tipo = (string)$f['tipo'];
            if (!isset($res[$cid]) || !isset(self::REACCIONES[$tipo])) {
                continue;
            }
            $res[$cid]['reacciones'][$tipo] = (int)$f['cantidad'];
            $res[$cid]['total_reacciones'] += (int)$f['cantidad'];
            if ($usuario !== null && (int)$f['mia'] === 1) {
                $res[$cid]['mia'] = $tipo;
            }
        }

        $filas = IntranetAdmin::consultar(
            "SELECT comunicado_id,
                    SUM(CASE WHEN oculto = 0 THEN 1 ELSE 0 END) AS visibles,
                    SUM(CASE WHEN oculto = 1 THEN 1 ELSE 0 END) AS ocultos
               FROM " . self::T_COMENTARIOS . "
              WHERE borrado = 0 AND comunicado_id IN (" . self::marcas($ids) . ")
              GROUP BY comunicado_id", $ids) ?? [];
        foreach ($filas as $f) {
            $cid = (int)$f['comunicado_id'];
            if (isset($res[$cid])) {
                $res[$cid]['comentarios'] = (int)$f['visibles'];
                $res[$cid]['ocultos'] = (int)$f['ocultos'];
            }
        }
        return $res;
    }

    private static function selectComentarios(): string
    {
        return "SELECT id, comunicado_id, usuario, nombre_mostrado, texto, creado_en,
                       oculto, oculto_por_nombre, oculto_en
                  FROM " . self::T_COMENTARIOS;
    }

    /**
     * Comentarios no borrados de varios comunicados, del más viejo al más nuevo:
     * [id => [comentario, ...]]. Los ocultos solo si $conOcultos (responsables de carga).
     */
    public static function comentarios(array $ids, bool $conOcultos): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !Intranet::tieneInteraccion()) {
            return [];
        }
        $filas = IntranetAdmin::consultar(self::selectComentarios() . "
             WHERE borrado = 0 AND comunicado_id IN (" . self::marcas($ids) . ")"
            . ($conOcultos ? '' : ' AND oculto = 0') . "
             ORDER BY comunicado_id, creado_en, id", $ids) ?? [];
        $res = [];
        foreach ($filas as $f) {
            $res[(int)$f['comunicado_id']][] = $f;
        }
        return $res;
    }

    public static function comentario(int $id): ?array
    {
        $filas = IntranetAdmin::consultar(self::selectComentarios() . " WHERE id = ? AND borrado = 0", [$id]);
        return $filas[0] ?? null;
    }

    /**
     * El comunicado, si hoy se puede comentar o reaccionar en él: activo, publicado, vigente y no
     * plantilla. null si no. Devuelve permite_comentarios y permite_reacciones para que se controle
     * la opción que corresponde.
     */
    public static function comunicadoAbierto(int $id): ?array
    {
        if (!Intranet::tieneInteraccion()) {
            return null;
        }
        $colEstado = Intranet::tieneV2() ? "c.estado" : "'PUBLICADO' AS estado";
        $filas = IntranetAdmin::consultar(
            "SELECT c.id, c.activo, c.vigente_desde, c.vigente_hasta, $colEstado, c.permite_comentarios, c.permite_reacciones
               FROM dbo.FP_INTRA_COMUNICADOS c WHERE c.id = ?" . Intranet::sinPlantillas('c'), [$id]);
        $c = $filas[0] ?? null;
        $hoy = Intranet::hoy();
        if (!$c || empty($c['activo']) || $c['estado'] !== 'PUBLICADO'
            || $c['vigente_desde'] > $hoy || ($c['vigente_hasta'] && $c['vigente_hasta'] < $hoy)) {
            return null;
        }
        return $c;
    }

    // ==================== Reacciones ====================

    /**
     * Toca una reacción: si es la que ya tenía, la deshace; si tenía otra, la cambia; si no tenía,
     * la marca. Devuelve ['ok' => bool, 'mia' => tipo|null].
     */
    public static function reaccionar(int $comunicadoId, string $usuario, string $nombre, string $tipo): array
    {
        $nombre = mb_substr($nombre, 0, 150, 'UTF-8');
        if (!isset(self::REACCIONES[$tipo])) {
            return ['ok' => false, 'mia' => null];
        }
        $filas = IntranetAdmin::consultar(
            "SELECT tipo FROM " . self::T_REACCIONES . " WHERE comunicado_id = ? AND usuario = ?", [$comunicadoId, $usuario]);
        if ($filas === null) {
            return ['ok' => false, 'mia' => null];
        }
        $actual = $filas[0]['tipo'] ?? null;
        $ahora = IntranetAdmin::ahora();

        if ($actual === $tipo) {
            $ok = IntranetAdmin::ejecutar(
                "DELETE FROM " . self::T_REACCIONES . " WHERE comunicado_id = ? AND usuario = ?", [$comunicadoId, $usuario]);
            return ['ok' => $ok, 'mia' => $ok ? null : $actual];
        }
        $conNombre = Intranet::reaccionConNombre(); // sin la columna (versión anterior del 10), no se guarda el nombre
        if ($actual !== null) {
            $ok = $conNombre
                ? IntranetAdmin::ejecutar(
                    "UPDATE " . self::T_REACCIONES . " SET tipo = ?, nombre_mostrado = ?, modificado_en = ? WHERE comunicado_id = ? AND usuario = ?",
                    [$tipo, $nombre, $ahora, $comunicadoId, $usuario])
                : IntranetAdmin::ejecutar(
                    "UPDATE " . self::T_REACCIONES . " SET tipo = ?, modificado_en = ? WHERE comunicado_id = ? AND usuario = ?",
                    [$tipo, $ahora, $comunicadoId, $usuario]);
            return ['ok' => $ok, 'mia' => $ok ? $tipo : $actual];
        }
        // Si dos toques llegan a la vez, la clave (comunicado, usuario) impide la segunda fila
        $ok = $conNombre
            ? IntranetAdmin::ejecutar(
                "INSERT INTO " . self::T_REACCIONES . " (comunicado_id, usuario, nombre_mostrado, tipo, creado_en) VALUES (?, ?, ?, ?, ?)",
                [$comunicadoId, $usuario, $nombre, $tipo, $ahora])
            : IntranetAdmin::ejecutar(
                "INSERT INTO " . self::T_REACCIONES . " (comunicado_id, usuario, tipo, creado_en) VALUES (?, ?, ?, ?)",
                [$comunicadoId, $usuario, $tipo, $ahora]);
        return ['ok' => $ok, 'mia' => $ok ? $tipo : null];
    }

    /**
     * Quiénes reaccionaron, agrupado por reacción (en el orden de REACCIONES), del más reciente al
     * más viejo: [tipo => [nombre, ...]]. Nombre: el guardado al reaccionar; nunca el usuario técnico.
     * null si la consulta falló.
     */
    public static function quienes(int $comunicadoId): ?array
    {
        $filas = IntranetAdmin::consultar(
            "SELECT r.tipo, r.usuario, " . (Intranet::reaccionConNombre() ? "r.nombre_mostrado" : "CAST(NULL AS NVARCHAR(1)) AS nombre_mostrado") . ", u.nombre_completo
               FROM " . self::T_REACCIONES . " r
              OUTER APPLY (SELECT TOP 1 nombre_completo FROM dbo.FP_SOF_USUARIOS WHERE username = r.usuario) u
              WHERE r.comunicado_id = ?
              ORDER BY COALESCE(r.modificado_en, r.creado_en) DESC", [$comunicadoId]);
        if ($filas === null) {
            return null;
        }
        $res = array_fill_keys(array_keys(self::REACCIONES), []);
        foreach ($filas as $f) {
            if (!isset($res[$f['tipo']])) {
                continue;
            }
            $tecnico = mb_strtolower(trim((string)$f['usuario']));
            $nombre = 'Usuario de XL';
            foreach ([$f['nombre_mostrado'], $f['nombre_completo']] as $cand) {
                $cand = trim((string)$cand);
                if ($cand !== '' && mb_strtolower($cand) !== $tecnico) {
                    $nombre = $cand;
                    break;
                }
            }
            $res[$f['tipo']][] = $nombre;
        }
        return $res;
    }

    /** ¿Existe el comunicado (cualquier estado)? Para que los responsables vean quiénes reaccionaron desde el listado. */
    public static function existeComunicado(int $id): bool
    {
        $f = IntranetAdmin::consultar("SELECT c.id FROM dbo.FP_INTRA_COMUNICADOS c WHERE c.id = ?" . Intranet::sinPlantillas('c'), [$id]);
        return !empty($f[0]['id']);
    }

    // ==================== Comentarios ====================

    /** Texto plano: saltos de línea uniformes, sin caracteres de control y sin más de una línea en blanco seguida. */
    public static function normalizarTexto($valor): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", (string)$valor);
        $t = preg_replace('/[^\P{Cc}\n]/u', '', $t) ?? '';  // caracteres de control (salvo el salto de línea; los emojis quedan)
        $t = preg_replace('/[ \t]+\n/u', "\n", $t) ?? $t;
        $t = preg_replace('/\n{3,}/u', "\n\n", $t) ?? $t;
        return trim($t);
    }

    /**
     * Publica un comentario. Devuelve ['ok' => true, 'comentario' => fila] o
     * ['ok' => false, 'error' => mensaje, 'codigo' => HTTP].
     */
    public static function comentar(int $comunicadoId, string $usuario, string $nombre, $texto): array
    {
        $texto = self::normalizarTexto($texto);
        if ($texto === '') {
            return ['ok' => false, 'error' => 'Escribí tu comentario.', 'codigo' => 422];
        }
        if (IntranetAdmin::largo($texto) > self::MAX_COMENTARIO) {
            return ['ok' => false, 'error' => 'El comentario puede tener hasta ' . self::MAX_COMENTARIO . ' caracteres.', 'codigo' => 422];
        }

        $ultimo = IntranetAdmin::consultar(
            "SELECT TOP 1 creado_en FROM " . self::T_COMENTARIOS . " WHERE usuario = ? ORDER BY creado_en DESC", [$usuario]);
        if ($ultimo === null) {
            return ['ok' => false, 'error' => 'No pudimos publicar tu comentario. Probá de nuevo.', 'codigo' => 500];
        }
        if (!empty($ultimo[0]['creado_en']) && time() - $ultimo[0]['creado_en']->getTimestamp() < self::SEG_ENTRE_COMENTARIOS) {
            return ['ok' => false, 'error' => 'Esperá unos segundos antes de volver a comentar.', 'codigo' => 429];
        }

        $filas = IntranetAdmin::consultar(
            "INSERT INTO " . self::T_COMENTARIOS . " (comunicado_id, usuario, nombre_mostrado, texto, creado_en)
             OUTPUT INSERTED.id
             VALUES (?, ?, ?, ?, ?)",
            [$comunicadoId, $usuario, mb_substr($nombre, 0, 150, 'UTF-8'), $texto, IntranetAdmin::ahora()]);
        $id = (int)($filas[0]['id'] ?? 0);
        $c = $id ? self::comentario($id) : null;
        if (!$c) {
            return ['ok' => false, 'error' => 'No pudimos publicar tu comentario. Probá de nuevo.', 'codigo' => 500];
        }
        return ['ok' => true, 'comentario' => $c];
    }

    /** Baja lógica de un comentario propio. */
    public static function borrar(int $id, int $comunicadoId, string $usuario): bool
    {
        $filas = IntranetAdmin::consultar(
            "UPDATE " . self::T_COMENTARIOS . " SET borrado = 1, borrado_en = ?
             OUTPUT INSERTED.id
              WHERE id = ? AND comunicado_id = ? AND usuario = ? AND borrado = 0",
            [IntranetAdmin::ahora(), $id, $comunicadoId, $usuario]);
        return !empty($filas[0]['id']);
    }

    /** Ocultar (o volver a mostrar) un comentario: solo responsables de carga, lo controla el endpoint. */
    public static function ocultar(int $id, int $comunicadoId, bool $ocultar, string $usuario, string $nombre): bool
    {
        $ahora = IntranetAdmin::ahora();
        $filas = $ocultar
            ? IntranetAdmin::consultar(
                "UPDATE " . self::T_COMENTARIOS . "
                    SET oculto = 1, oculto_por = ?, oculto_por_nombre = ?, oculto_en = ?
                 OUTPUT INSERTED.id
                  WHERE id = ? AND comunicado_id = ? AND borrado = 0",
                [$usuario, mb_substr($nombre, 0, 150, 'UTF-8'), $ahora, $id, $comunicadoId])
            : IntranetAdmin::consultar(
                "UPDATE " . self::T_COMENTARIOS . "
                    SET oculto = 0, mostrado_por = ?, mostrado_en = ?
                 OUTPUT INSERTED.id
                  WHERE id = ? AND comunicado_id = ? AND borrado = 0",
                [$usuario, $ahora, $id, $comunicadoId]);
        return !empty($filas[0]['id']);
    }
}
