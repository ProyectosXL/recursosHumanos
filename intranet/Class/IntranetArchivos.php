<?php
/**
 * Intranet XL - imágenes y PDF de los comunicados
 *
 * Dónde: carpeta INTRANET_ARCHIVOS del .env (ruta absoluta), FUERA de la carpeta pública del
 * sitio. Adentro:
 *   comunicados/<id>/<nombre generado>.<ext>   (imagen, su miniatura "_m" o PDF)
 *   temp/<lote>/...                           (subidos y todavía sin guardar)
 * Si la variable falta, la carpeta no existe, no se puede escribir o queda dentro de la carpeta
 * pública, carpeta() devuelve null y la carga muestra AVISO_SIN_CARPETA: el comunicado se puede
 * guardar igual, sin archivos.
 *
 * Registro: FP_INTRA_COMUNICADOS_ARCHIVOS (recursosHumanos/intranet/sql/09). Nunca se usa el nombre que trae el
 * usuario para guardar: solo se muestra.
 *
 * Flujo: el formulario de carga tiene un "lote" (32 hex). Cada archivo se sube apenas se elige
 * (recursosHumanos/intranet/archivo_subir.php -> subir()), se valida, se procesa y queda en temp/<lote> con
 * comunicado_id vacío. Al guardar el comunicado, vincular() mueve a comunicados/<id> lo que quedó
 * en el formulario, da de baja (lógica) y borra del disco lo que se quitó. Lo temporal que nunca
 * se guardó lo borra limpiarTemporales() a las 24 horas.
 *
 * Imágenes: tipo real JPG, PNG o WEBP (no la extensión); se giran según EXIF, se achican a
 * LADO_MAX y se recomprimen (WEBP calidad CALIDAD; si el servidor no tiene WEBP: JPG, o PNG para
 * los PNG, que conservan la transparencia). Al recomprimir se descartan los datos internos de la
 * foto (ubicación, cámara...). Además, una miniatura de LADO_MINIATURA.
 */

require_once __DIR__ . '/IntranetAdmin.php';

class IntranetArchivos
{
    const MAX_IMAGENES    = 6;
    const MAX_PDF         = 3;
    const PESO_MAX_IMAGEN = 5 * 1024 * 1024;
    const PESO_MAX_PDF    = 10 * 1024 * 1024;
    const LADO_MAX        = 1600;
    const LADO_MINIATURA  = 480;
    const CALIDAD         = 80;
    const PIXELES_MAX     = 40000000;   // más que esto no entra en memoria al procesarla
    const HORAS_TEMPORAL  = 24;
    const MAX_EPIGRAFE    = 150;
    const MAX_NOMBRE_PDF  = 120;

    const TIPOS = ['IMAGEN', 'PDF'];
    const MIMES_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];

    const AVISO_SIN_CARPETA = 'No se pueden subir archivos: falta configurar la carpeta';

    const TABLA = 'dbo.FP_INTRA_COMUNICADOS_ARCHIVOS';

    private static $carpeta = false; // false = todavía no se calculó

    // ==================== Carpeta ====================

    /** Ruta absoluta de la carpeta de archivos (con "/"), o null si no se puede usar. */
    public static function carpeta(): ?string
    {
        if (self::$carpeta === false) {
            self::$carpeta = self::resolverCarpeta();
        }
        return self::$carpeta;
    }

    /** Se puede subir y mostrar archivos: carpeta lista y recursosHumanos/intranet/sql/09 corrido. */
    public static function disponible(): bool
    {
        return self::carpeta() !== null && Intranet::tieneArchivos();
    }

    private static function resolverCarpeta(): ?string
    {
        $conf = ConexionApps::valor('INTRANET_ARCHIVOS');
        if ($conf === null) {
            return null;
        }
        $real = realpath($conf);
        if ($real === false || !is_dir($real)) {
            // Ojo: una letra de unidad de red mapeada (V:, W:...) no existe para el servicio de Apache
            error_log('IntranetArchivos: la carpeta INTRANET_ARCHIVOS no existe en este servidor: ' . $conf
                . ' (usar una ruta local del servidor o una ruta de red \\\\servidor\\recurso, no una unidad mapeada).');
            return null;
        }
        $ruta = self::normalizar($real);

        foreach (self::carpetasPublicas() as $publica) {
            if (self::estaDentro($ruta, $publica)) {
                error_log('IntranetArchivos: INTRANET_ARCHIVOS está dentro de la carpeta pública del sitio (' . $publica . '). No se usa.');
                return null;
            }
        }

        foreach (['comunicados', 'temp'] as $sub) {
            if (!is_dir("$ruta/$sub") && !@mkdir("$ruta/$sub", 0750, true)) {
                error_log("IntranetArchivos: no se pudo crear $ruta/$sub (permisos de escritura).");
                return null;
            }
        }

        // Prueba real de escritura: is_writable() no es confiable en Windows
        $prueba = "$ruta/temp/.prueba_" . bin2hex(random_bytes(4));
        if (@file_put_contents($prueba, 'ok') === false) {
            error_log("IntranetArchivos: sin permiso de escritura en $ruta.");
            return null;
        }
        @unlink($prueba);

        return $ruta;
    }

    /** Raíz pública del sitio (DOCUMENT_ROOT) y la carpeta que contiene la app. */
    private static function carpetasPublicas(): array
    {
        $lista = [];
        foreach ([$_SERVER['DOCUMENT_ROOT'] ?? '', dirname(__DIR__, 3)] as $c) {
            $r = $c !== '' ? realpath($c) : false;
            if ($r !== false) {
                $lista[] = self::normalizar($r);
            }
        }
        return array_unique($lista);
    }

    private static function normalizar(string $ruta): string
    {
        return rtrim(str_replace('\\', '/', $ruta), '/');
    }

    /** ¿$ruta es $base o está dentro? Sin distinguir mayúsculas (en Windows da igual; en Linux, peca de prudente). */
    private static function estaDentro(string $ruta, string $base): bool
    {
        $ruta = strtolower($ruta) . '/';
        $base = strtolower($base) . '/';
        return strncmp($ruta, $base, strlen($base)) === 0;
    }

    /** Ruta absoluta de un archivo guardado, verificando que no se salga de la carpeta. */
    public static function rutaAbsoluta(?string $relativa): ?string
    {
        $base = self::carpeta();
        if ($base === null || $relativa === null || $relativa === '' || strpos($relativa, '..') !== false) {
            return null;
        }
        $real = realpath($base . '/' . ltrim($relativa, '/\\'));
        if ($real === false || !is_file($real) || !self::estaDentro(self::normalizar($real), $base)) {
            return null;
        }
        return $real;
    }

    // ==================== Utilidades ====================

    /** El lote que manda el formulario, si es válido (32 hex). */
    public static function loteValido($valor): ?string
    {
        $valor = strtolower(trim((string)$valor));
        return preg_match('/^[a-f0-9]{32}$/', $valor) ? $valor : null;
    }

    public static function loteNuevo(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** "310 KB", "2,4 MB" */
    public static function peso(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
        }
        return max(1, (int)round($bytes / 1024)) . ' KB';
    }

    private static function mimeReal(string $ruta): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string)$finfo->file($ruta);
    }

    /** Nombre original, solo para mostrar: sin ruta ni caracteres de control. */
    private static function nombreOriginal(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre) ?? '';
        $nombre = trim($nombre);
        return mb_substr($nombre !== '' ? $nombre : 'archivo', 0, 255, 'UTF-8');
    }

    private static function error(string $mensaje): array
    {
        return ['ok' => false, 'error' => $mensaje];
    }

    private static function borrarDelDisco(array $fila): void
    {
        foreach ([$fila['ruta_relativa'] ?? null, $fila['ruta_miniatura'] ?? null] as $rel) {
            $ruta = self::rutaAbsoluta($rel);
            if ($ruta !== null) {
                @unlink($ruta);
            }
        }
    }

    // ==================== Lectura ====================

    private static function selectArchivos(): string
    {
        return "SELECT id, comunicado_id, lote, tipo, orden, es_portada, epigrafe, nombre, nombre_original,
                       ruta_relativa, ruta_miniatura, mime, peso_original, peso_final, ancho, alto,
                       activo, creado_por, creado_en
                  FROM " . self::TABLA;
    }

    public static function archivo(int $id): ?array
    {
        $filas = IntranetAdmin::consultar(self::selectArchivos() . " WHERE id = ? AND activo = 1", [$id]);
        return $filas[0] ?? null;
    }

    /** Archivos activos de un comunicado: imágenes y después PDF, cada grupo en su orden. */
    public static function delComunicado(int $comunicadoId): array
    {
        if (!self::disponible()) {
            return [];
        }
        return IntranetAdmin::consultar(self::selectArchivos() . "
             WHERE comunicado_id = ? AND activo = 1
             ORDER BY CASE tipo WHEN 'IMAGEN' THEN 0 ELSE 1 END, orden, id", [$comunicadoId]) ?? [];
    }

    /**
     * Imágenes y PDF para mostrar, de varios comunicados a la vez:
     * [comunicado_id => ['imagenes' => [...], 'pdfs' => [...]]]. Vacío si no hay archivos disponibles.
     */
    public static function paraMostrar(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || !self::disponible()) {
            return [];
        }
        $filas = IntranetAdmin::consultar(
            "SELECT id, comunicado_id, tipo, orden, es_portada, epigrafe, nombre, nombre_original, peso_final, ancho, alto
               FROM " . self::TABLA . "
              WHERE activo = 1 AND comunicado_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
              ORDER BY comunicado_id, CASE tipo WHEN 'IMAGEN' THEN 0 ELSE 1 END, orden, id", $ids) ?? [];

        $res = [];
        foreach ($filas as $f) {
            $cid = (int)$f['comunicado_id'];
            $res[$cid] = $res[$cid] ?? ['imagenes' => [], 'pdfs' => []];
            $res[$cid][$f['tipo'] === 'IMAGEN' ? 'imagenes' : 'pdfs'][] = self::filaParaMostrar($f);
        }
        return $res;
    }

    /** Lo mismo que paraMostrar, para los archivos de un formulario (vista previa, sin guardar). */
    public static function itemsParaMostrar(array $items): array
    {
        $res = ['imagenes' => [], 'pdfs' => []];
        foreach ($items as $f) {
            $res[$f['tipo'] === 'IMAGEN' ? 'imagenes' : 'pdfs'][] = self::filaParaMostrar($f);
        }
        return $res;
    }

    private static function filaParaMostrar(array $f): array
    {
        $id = (int)$f['id'];
        $base = Intranet::URL_ARCHIVO . '?id=' . $id;
        if ($f['tipo'] === 'IMAGEN') {
            return [
                'id' => $id, 'epigrafe' => trim((string)($f['epigrafe'] ?? '')), 'es_portada' => !empty($f['es_portada']),
                'ancho' => (int)$f['ancho'], 'alto' => (int)$f['alto'],
                'url' => $base, 'url_mini' => $base . '&m=1',
            ];
        }
        $nombre = trim((string)($f['nombre'] ?? ''));
        return [
            'id' => $id, 'nombre' => $nombre !== '' ? $nombre : pathinfo((string)$f['nombre_original'], PATHINFO_FILENAME),
            'peso' => self::peso((int)$f['peso_final']),
            'url' => $base, 'url_descargar' => $base . '&descargar=1',
        ];
    }

    /**
     * Copia (en disco y en la base) los archivos activos de un comunicado a otro, con nombres nuevos:
     * borrar el original no afecta a la copia. false si alguno no se pudo copiar.
     */
    public static function copiar(int $origenId, int $destinoId, string $usuario): bool
    {
        if (!self::disponible()) {
            return true;
        }
        return self::copiarFilas(self::delComunicado($origenId), $destinoId, null, $usuario)['ok'];
    }

    /**
     * Copia los archivos de un comunicado (o plantilla) al lote de un formulario que todavía no se
     * guardó: quedan como recién subidos (temp/<lote>, sin comunicado, de este usuario). Si el
     * formulario se cancela, limpiarTemporales() los borra a las 24 horas; si se guarda,
     * vincular() los pasa al comunicado nuevo. Devuelve las filas copiadas (para el formulario).
     */
    public static function copiarALote(int $origenId, string $lote, string $usuario): array
    {
        if (!self::disponible() || self::loteValido($lote) === null) {
            return [];
        }
        $r = self::copiarFilas(self::delComunicado($origenId), null, $lote, $usuario);
        if (!$r['ok']) {
            error_log("IntranetArchivos::copiarALote: no se copiaron todos los archivos de $origenId al lote $lote");
        }
        return $r['filas'];
    }

    /** Copia física y registro. Destino: un comunicado ($destinoId) o un lote temporal ($lote). */
    private static function copiarFilas(array $filas, ?int $destinoId, ?string $lote, string $usuario): array
    {
        if (!$filas) {
            return ['ok' => true, 'filas' => []];
        }
        $carpeta = self::carpeta();
        $dir = $destinoId ? "comunicados/$destinoId" : "temp/$lote";
        if (!is_dir("$carpeta/$dir") && !@mkdir("$carpeta/$dir", 0750, true)) {
            return ['ok' => false, 'filas' => []];
        }

        $ok = true;
        $nuevas = [];
        foreach ($filas as $f) {
            $base = bin2hex(random_bytes(16));
            $ext = pathinfo($f['ruta_relativa'], PATHINFO_EXTENSION);
            $rutas = ['ruta_relativa' => "$dir/$base.$ext", 'ruta_miniatura' => null];
            if (!empty($f['ruta_miniatura'])) {
                $rutas['ruta_miniatura'] = "$dir/{$base}_m." . pathinfo($f['ruta_miniatura'], PATHINFO_EXTENSION);
            }
            $copiados = [];
            $fallo = false;
            foreach (['ruta_relativa', 'ruta_miniatura'] as $col) {
                if ($rutas[$col] === null) {
                    continue;
                }
                $origen = self::rutaAbsoluta($f[$col]);
                if ($origen === null || !@copy($origen, "$carpeta/{$rutas[$col]}")) {
                    $fallo = true;
                    break;
                }
                $copiados[] = "$carpeta/{$rutas[$col]}";
            }
            if ($fallo) {
                foreach ($copiados as $c) {
                    @unlink($c); // no dejar la imagen sin su miniatura
                }
                $ok = false;
                continue;
            }
            $guardado = IntranetAdmin::consultar(
                "INSERT INTO " . self::TABLA . "
                    (comunicado_id, lote, tipo, orden, es_portada, epigrafe, nombre, nombre_original, ruta_relativa, ruta_miniatura,
                     mime, peso_original, peso_final, ancho, alto, creado_por, creado_en)
                 OUTPUT INSERTED.id
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$destinoId, $destinoId ? null : $lote, $f['tipo'], (int)$f['orden'], (int)$f['es_portada'], $f['epigrafe'], $f['nombre'],
                 $f['nombre_original'], $rutas['ruta_relativa'], $rutas['ruta_miniatura'], $f['mime'],
                 (int)$f['peso_original'], (int)$f['peso_final'], $f['ancho'], $f['alto'], $usuario, IntranetAdmin::ahora()]);
            if (empty($guardado[0]['id'])) {
                foreach ($copiados as $c) {
                    @unlink($c);
                }
                $ok = false;
                continue;
            }
            $nuevas[] = array_merge($f, $rutas, [
                'id' => (int)$guardado[0]['id'], 'comunicado_id' => $destinoId, 'lote' => $destinoId ? null : $lote,
            ]);
        }
        return ['ok' => $ok, 'filas' => $nuevas];
    }

    /** Lo que puede quedar en un formulario: los del comunicado + los subidos en este lote por este usuario. */
    private static function candidatos(?int $comunicadoId, string $lote, string $usuario): array
    {
        $sql = self::selectArchivos() . " WHERE activo = 1 AND ((comunicado_id IS NULL AND lote = ? AND creado_por = ?)";
        $params = [$lote, $usuario];
        if ($comunicadoId) {
            $sql .= " OR comunicado_id = ?";
            $params[] = $comunicadoId;
        }
        $sql .= ")";
        $porId = [];
        foreach (IntranetAdmin::consultar($sql, $params) ?? [] as $f) {
            $porId[(int)$f['id']] = $f;
        }
        return $porId;
    }

    /**
     * ¿El usuario logueado puede ver este archivo? Todos: si es de un comunicado activo, publicado
     * y vigente hoy. Quien publica comunicados: además borradores, programados, vencidos e
     * inactivos, y los recién subidos.
     */
    public static function puedeVer(array $archivo): bool
    {
        // Quien publica o administra plantillas: todo (también las imágenes de las plantillas)
        if (Intranet::puedePublicarComunicados() || Intranet::puedeAdministrarPlantillas()) {
            return true;
        }
        if (empty($archivo['comunicado_id'])) {
            return false;
        }
        $colEstado = Intranet::tieneV2() ? "c.estado" : "'PUBLICADO' AS estado";
        $filas = IntranetAdmin::consultar(
            "SELECT c.activo, c.vigente_desde, c.vigente_hasta, $colEstado
               FROM dbo.FP_INTRA_COMUNICADOS c WHERE c.id = ?" . Intranet::sinPlantillas('c'), [(int)$archivo['comunicado_id']]);
        $c = $filas[0] ?? null;
        if (!$c || empty($c['activo']) || $c['estado'] !== 'PUBLICADO') {
            return false;
        }
        $hoy = Intranet::hoy();
        return $c['vigente_desde'] <= $hoy && (!$c['vigente_hasta'] || $c['vigente_hasta'] >= $hoy);
    }

    /** Datos de un archivo para la pantalla de carga (JSON). */
    public static function paraCliente(array $f): array
    {
        $id = (int)$f['id'];
        $esImagen = $f['tipo'] === 'IMAGEN';
        $orig = (int)$f['peso_original'];
        $final = (int)$f['peso_final'];
        return [
            'id'              => $id,
            'tipo'            => $f['tipo'],
            'nombre_original' => (string)$f['nombre_original'],
            'nombre'          => (string)($f['nombre'] ?? ''),
            'epigrafe'        => (string)($f['epigrafe'] ?? ''),
            'es_portada'      => !empty($f['es_portada']),
            'ancho'           => $f['ancho'] !== null ? (int)$f['ancho'] : null,
            'alto'            => $f['alto'] !== null ? (int)$f['alto'] : null,
            'pesos'           => $esImagen ? self::peso($orig) . ' → ' . self::peso($final) : self::peso($final),
            'url'             => Intranet::URL_ARCHIVO . '?id=' . $id,
            'url_mini'        => $esImagen ? Intranet::URL_ARCHIVO . '?id=' . $id . '&m=1' : null,
        ];
    }

    // ==================== Subida ====================

    /**
     * Valida, procesa y guarda en temp/<lote> un archivo recién elegido.
     * $ocupados: cuántos de ese tipo tiene ya el formulario (el tope final se controla al guardar).
     * Devuelve ['ok' => true, 'archivo' => paraCliente] o ['ok' => false, 'error' => motivo].
     */
    public static function subir(array $f, string $tipo, string $lote, int $ocupados, string $usuario): array
    {
        if (!self::disponible()) {
            return self::error(self::AVISO_SIN_CARPETA . '.');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return self::error('Tipo de archivo no válido.');
        }

        $max = $tipo === 'IMAGEN' ? self::MAX_IMAGENES : self::MAX_PDF;
        if ($ocupados >= $max) {
            return self::error($tipo === 'IMAGEN' ? 'Máximo ' . self::MAX_IMAGENES . ' imágenes.' : 'Máximo ' . self::MAX_PDF . ' PDF.');
        }

        $pesoMax = $tipo === 'IMAGEN' ? self::PESO_MAX_IMAGEN : self::PESO_MAX_PDF;
        $codigo = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return self::error('Pesa más de ' . self::peso($pesoMax) . '.');
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($codigo !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            return self::error('No se pudo recibir el archivo. Probá de nuevo.');
        }
        $peso = (int)filesize($tmp);
        if ($peso > $pesoMax) {
            return self::error('Pesa ' . self::peso($peso) . ': el máximo es ' . self::peso($pesoMax) . '.');
        }

        $dirLote = self::carpeta() . '/temp/' . $lote;
        if (!is_dir($dirLote) && !@mkdir($dirLote, 0750, true)) {
            return self::error('No se pudo guardar el archivo. Avisale a Sistemas.');
        }

        $nombreOriginal = self::nombreOriginal((string)($f['name'] ?? ''));
        return $tipo === 'IMAGEN'
            ? self::subirImagen($tmp, $peso, $nombreOriginal, $lote, $usuario)
            : self::subirPdf($tmp, $peso, $nombreOriginal, $lote, $usuario);
    }

    private static function subirImagen(string $tmp, int $peso, string $nombreOriginal, string $lote, string $usuario): array
    {
        $mime = self::mimeReal($tmp);
        if (!in_array($mime, self::MIMES_IMAGEN, true)) {
            return self::error('Formato no permitido: subí JPG, PNG o WEBP.');
        }
        $info = @getimagesize($tmp);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return self::error('El archivo no es una imagen válida.');
        }
        if ($info[0] * $info[1] > self::PIXELES_MAX) {
            return self::error('La imagen tiene demasiada resolución (más de 40 megapíxeles).');
        }

        $img = self::abrirImagen($tmp, $mime);
        if (!$img) {
            return self::error('No se pudo leer la imagen. Probá guardarla de nuevo como JPG.');
        }
        if ($mime === 'image/jpeg') {
            $img = self::orientar($img, $tmp);
        }

        [$ext, $mimeSalida] = self::formatoSalida($mime);
        $base = bin2hex(random_bytes(16));
        $rel = "temp/$lote/$base.$ext";
        $relMini = "temp/$lote/{$base}_m.$ext";
        $carpeta = self::carpeta();

        $medidas = self::guardarEscalada($img, "$carpeta/$rel", self::LADO_MAX, $ext);
        $mini = $medidas ? self::guardarEscalada($img, "$carpeta/$relMini", self::LADO_MINIATURA, $ext) : null;
        imagedestroy($img);
        if (!$medidas || !$mini) {
            @unlink("$carpeta/$rel");
            @unlink("$carpeta/$relMini");
            return self::error('No se pudo procesar la imagen. Probá de nuevo.');
        }

        clearstatcache();
        $fila = [
            'tipo' => 'IMAGEN', 'nombre' => null, 'nombre_original' => $nombreOriginal,
            'ruta_relativa' => $rel, 'ruta_miniatura' => $relMini, 'mime' => $mimeSalida,
            'peso_original' => $peso, 'peso_final' => (int)filesize("$carpeta/$rel"),
            'ancho' => $medidas[0], 'alto' => $medidas[1],
        ];
        return self::registrar($fila, $lote, $usuario);
    }

    private static function subirPdf(string $tmp, int $peso, string $nombreOriginal, string $lote, string $usuario): array
    {
        $cabecera = (string)@file_get_contents($tmp, false, null, 0, 5);
        if (self::mimeReal($tmp) !== 'application/pdf' || $cabecera !== '%PDF-') {
            return self::error('El archivo no es un PDF válido.');
        }

        $rel = "temp/$lote/" . bin2hex(random_bytes(16)) . '.pdf';
        $destino = self::carpeta() . '/' . $rel;
        if (!@move_uploaded_file($tmp, $destino)) {
            return self::error('No se pudo guardar el archivo. Avisale a Sistemas.');
        }

        $nombre = trim(pathinfo($nombreOriginal, PATHINFO_FILENAME));
        $fila = [
            'tipo' => 'PDF',
            'nombre' => mb_substr($nombre !== '' ? $nombre : 'Documento', 0, self::MAX_NOMBRE_PDF, 'UTF-8'),
            'nombre_original' => $nombreOriginal,
            'ruta_relativa' => $rel, 'ruta_miniatura' => null, 'mime' => 'application/pdf',
            'peso_original' => $peso, 'peso_final' => $peso, 'ancho' => null, 'alto' => null,
        ];
        return self::registrar($fila, $lote, $usuario);
    }

    private static function registrar(array $fila, string $lote, string $usuario): array
    {
        $filas = IntranetAdmin::consultar(
            "INSERT INTO " . self::TABLA . "
                (comunicado_id, lote, tipo, orden, es_portada, nombre, nombre_original, ruta_relativa, ruta_miniatura,
                 mime, peso_original, peso_final, ancho, alto, creado_por, creado_en)
             OUTPUT INSERTED.id
             VALUES (NULL, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$lote, $fila['tipo'], $fila['nombre'], $fila['nombre_original'], $fila['ruta_relativa'], $fila['ruta_miniatura'],
             $fila['mime'], $fila['peso_original'], $fila['peso_final'], $fila['ancho'], $fila['alto'], $usuario, IntranetAdmin::ahora()]);
        if (empty($filas[0]['id'])) {
            self::borrarDelDisco($fila);
            return self::error('No se pudo registrar el archivo. Probá de nuevo en unos minutos.');
        }
        $fila['id'] = (int)$filas[0]['id'];
        $fila['es_portada'] = 0;
        $fila['epigrafe'] = null;
        return ['ok' => true, 'archivo' => self::paraCliente($fila)];
    }

    // ==================== Procesamiento de imágenes ====================

    private static function abrirImagen(string $ruta, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($ruta) ?: null;
            case 'image/png':  return @imagecreatefrompng($ruta) ?: null;
            case 'image/webp': return function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($ruta) ?: null) : null;
        }
        return null;
    }

    /** Gira o espeja según la orientación EXIF de la cámara (fotos de celular). */
    private static function orientar($img, string $ruta)
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($ruta);
        $o = (int)($exif['Orientation'] ?? 1);
        // imagerotate gira en sentido antihorario
        switch ($o) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 3: $img = self::rotar($img, 180); break;
            case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
            case 5: $img = self::rotar($img, -90); imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 6: $img = self::rotar($img, -90); break;
            case 7: $img = self::rotar($img, 90); imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 8: $img = self::rotar($img, 90); break;
        }
        return $img;
    }

    private static function rotar($img, int $grados)
    {
        $rotada = imagerotate($img, $grados, 0);
        if ($rotada) {
            imagedestroy($img);
            return $rotada;
        }
        return $img;
    }

    /** [extensión, mime] del archivo guardado: WEBP si el servidor puede; si no, PNG para PNG y JPG para el resto. */
    private static function formatoSalida(string $mimeOrigen): array
    {
        if (function_exists('imagewebp') && (imagetypes() & IMG_WEBP)) {
            return ['webp', 'image/webp'];
        }
        return $mimeOrigen === 'image/png' ? ['png', 'image/png'] : ['jpg', 'image/jpeg'];
    }

    /**
     * Guarda una copia que entra en $lado x $lado (sin agrandar), siempre recodificada en color
     * real: eso descarta los datos internos de la foto. Devuelve [ancho, alto] o null.
     */
    private static function guardarEscalada($img, string $destino, int $lado, string $ext): ?array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, $lado / max($w, $h));
        $nw = max(1, (int)round($w * $escala));
        $nh = max(1, (int)round($h * $escala));

        $lienzo = imagecreatetruecolor($nw, $nh);
        if (!$lienzo) {
            return null;
        }
        if ($ext === 'jpg') {
            // JPG no tiene transparencia: fondo blanco
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        } else {
            imagealphablending($lienzo, false);
            imagesavealpha($lienzo, true);
            imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        }
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        switch ($ext) {
            case 'webp': $ok = imagewebp($lienzo, $destino, self::CALIDAD); break;
            case 'png':  $ok = imagepng($lienzo, $destino, 9); break;
            default:     imageinterlace($lienzo, true); $ok = imagejpeg($lienzo, $destino, 82);
        }
        imagedestroy($lienzo);
        return $ok ? [$nw, $nh] : null;
    }

    // ==================== Formulario y guardado ====================

    /**
     * Archivos que vienen en el formulario ($post = $_POST['archivos']: lista ordenada de
     * [id, epigrafe, nombre, portada]). Solo se aceptan los del comunicado y los subidos en este
     * lote por este usuario. Devuelve [lista lista para guardar y volver a mostrar, errores].
     */
    public static function delFormulario($post, string $lote, ?int $comunicadoId, string $usuario): array
    {
        if (!self::disponible()) {
            return [[], []];
        }
        $candidatos = self::candidatos($comunicadoId, $lote, $usuario);
        $items = [];
        $vistos = [];
        $err = [];

        foreach (is_array($post) ? $post : [] as $p) {
            $id = (int)($p['id'] ?? 0);
            if (!isset($candidatos[$id]) || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $f = $candidatos[$id];
            if ($f['tipo'] === 'IMAGEN') {
                $f['epigrafe'] = IntranetAdmin::texto($p['epigrafe'] ?? $f['epigrafe'] ?? '');
                $f['es_portada'] = !empty($p['portada']) ? 1 : 0;
                if (IntranetAdmin::largo($f['epigrafe']) > self::MAX_EPIGRAFE) {
                    $err['archivos'] = 'El epígrafe de cada imagen puede tener hasta ' . self::MAX_EPIGRAFE . ' caracteres.';
                }
            } else {
                $f['nombre'] = IntranetAdmin::texto($p['nombre'] ?? '');
                $f['es_portada'] = 0;
                if ($f['nombre'] === '') {
                    $err['archivos'] = 'Poné un nombre para cada PDF.';
                } elseif (IntranetAdmin::largo($f['nombre']) > self::MAX_NOMBRE_PDF) {
                    $err['archivos'] = 'El nombre de cada PDF puede tener hasta ' . self::MAX_NOMBRE_PDF . ' caracteres.';
                }
            }
            $items[] = $f;
        }

        $imagenes = array_keys(array_filter($items, fn($f) => $f['tipo'] === 'IMAGEN'));
        $pdfs = array_filter($items, fn($f) => $f['tipo'] === 'PDF');
        if (count($imagenes) > self::MAX_IMAGENES) {
            $err['archivos'] = 'Máximo ' . self::MAX_IMAGENES . ' imágenes.';
        }
        if (count($pdfs) > self::MAX_PDF) {
            $err['archivos'] = 'Máximo ' . self::MAX_PDF . ' PDF.';
        }

        // Una sola portada: la primera marcada o, si no hay, la primera imagen
        $portada = null;
        foreach ($imagenes as $i) {
            if ($portada === null && $items[$i]['es_portada']) {
                $portada = $i;
            }
        }
        $portada = $portada ?? ($imagenes[0] ?? null);
        foreach ($imagenes as $i) {
            $items[$i]['es_portada'] = $i === $portada ? 1 : 0;
        }

        // Orden dentro de cada tipo, según la posición en el formulario
        $orden = ['IMAGEN' => 0, 'PDF' => 0];
        foreach ($items as &$f) {
            $f['orden'] = ++$orden[$f['tipo']];
        }
        unset($f);

        return [$items, $err];
    }

    /**
     * Deja el comunicado con exactamente estos archivos ($items de delFormulario): mueve a su carpeta
     * los recién subidos, actualiza orden, portada, epígrafe y nombre, da de baja (y borra del disco)
     * los que se quitaron y elimina los temporales del lote que no se usaron. false si algo falló.
     */
    public static function vincular(int $comunicadoId, string $lote, array $items, string $usuario): bool
    {
        if (!self::disponible()) {
            return true; // sin carpeta no se tocan los archivos que ya tenga
        }
        $carpeta = self::carpeta();
        $dir = "comunicados/$comunicadoId";
        if (!is_dir("$carpeta/$dir") && !@mkdir("$carpeta/$dir", 0750, true)) {
            error_log("IntranetArchivos: no se pudo crear $carpeta/$dir");
            return false;
        }

        $candidatos = self::candidatos($comunicadoId, $lote, $usuario);
        $ok = true;
        $quedan = [];
        $ahora = IntranetAdmin::ahora();

        foreach ($items as $f) {
            $id = (int)$f['id'];
            if (!isset($candidatos[$id])) {
                continue;
            }
            $actual = $candidatos[$id];
            $quedan[$id] = true;
            $epigrafe = $f['tipo'] === 'IMAGEN' && ($f['epigrafe'] ?? '') !== '' ? $f['epigrafe'] : null;
            $nombre = $f['tipo'] === 'PDF' ? $f['nombre'] : null;

            if (empty($actual['comunicado_id'])) {
                // Recién subido: pasa de temp/<lote> a comunicados/<id>
                $movidos = [];
                $rutas = [];
                foreach (['ruta_relativa', 'ruta_miniatura'] as $col) {
                    $rutas[$col] = null;
                    if (empty($actual[$col])) {
                        continue;
                    }
                    $nueva = $dir . '/' . basename($actual[$col]);
                    $origen = self::rutaAbsoluta($actual[$col]);
                    if ($origen === null || !@rename($origen, "$carpeta/$nueva")) {
                        $rutas = null;
                        break;
                    }
                    $movidos[] = [$origen, "$carpeta/$nueva"];
                    $rutas[$col] = $nueva;
                }
                $guardado = $rutas !== null && IntranetAdmin::ejecutar(
                    "UPDATE " . self::TABLA . "
                        SET comunicado_id = ?, lote = NULL, ruta_relativa = ?, ruta_miniatura = ?,
                            orden = ?, es_portada = ?, epigrafe = ?, nombre = ?
                      WHERE id = ?",
                    [$comunicadoId, $rutas['ruta_relativa'], $rutas['ruta_miniatura'],
                     (int)$f['orden'], (int)$f['es_portada'], $epigrafe, $nombre, $id]);
                if (!$guardado) {
                    foreach ($movidos as [$de, $a]) {
                        @rename($a, $de); // vuelve a temp: se puede reintentar guardando de nuevo
                    }
                    $ok = false;
                }
                continue;
            }

            // Ya era del comunicado: solo si cambió algo (así modificado_* registra cambios reales)
            $cambio = (int)$actual['orden'] !== (int)$f['orden']
                || (int)$actual['es_portada'] !== (int)$f['es_portada']
                || (string)($actual['epigrafe'] ?? '') !== (string)($epigrafe ?? '')
                || (string)($actual['nombre'] ?? '') !== (string)($nombre ?? '');
            if ($cambio) {
                $ok = IntranetAdmin::ejecutar(
                    "UPDATE " . self::TABLA . "
                        SET orden = ?, es_portada = ?, epigrafe = ?, nombre = ?, modificado_por = ?, modificado_en = ?
                      WHERE id = ?",
                    [(int)$f['orden'], (int)$f['es_portada'], $epigrafe, $nombre, $usuario, $ahora, $id]) && $ok;
            }
        }

        // Lo que no quedó en el formulario
        foreach ($candidatos as $id => $actual) {
            if (isset($quedan[$id])) {
                continue;
            }
            if (!empty($actual['comunicado_id'])) {
                // Era del comunicado: baja lógica y se borra del disco
                if (IntranetAdmin::ejecutar(
                    "UPDATE " . self::TABLA . " SET activo = 0, modificado_por = ?, modificado_en = ? WHERE id = ?",
                    [$usuario, $ahora, $id])) {
                    self::borrarDelDisco($actual);
                } else {
                    $ok = false;
                }
            } elseif (IntranetAdmin::ejecutar("DELETE FROM " . self::TABLA . " WHERE id = ? AND comunicado_id IS NULL", [$id])) {
                // Temporal que se quitó antes de guardar: nunca formó parte del comunicado
                self::borrarDelDisco($actual);
            }
        }

        @rmdir("$carpeta/temp/$lote"); // solo si quedó vacía
        return $ok;
    }

    // ==================== Limpieza ====================

    /**
     * Borra lo subido que nunca se guardó (más de HORAS_TEMPORAL horas). Corre como mucho una vez por
     * hora: la llama la pantalla de carga, así no hace falta una tarea programada.
     */
    public static function limpiarTemporales(): void
    {
        if (!self::disponible()) {
            return;
        }
        $temp = self::carpeta() . '/temp';
        $marca = "$temp/.ultima_limpieza";
        if (is_file($marca) && time() - (int)@filemtime($marca) < 3600) {
            return;
        }
        @touch($marca);

        $limite = (new DateTimeImmutable('-' . self::HORAS_TEMPORAL . ' hours'))->format('Y-m-d\TH:i:s');
        $viejos = IntranetAdmin::consultar(self::selectArchivos() . " WHERE comunicado_id IS NULL AND creado_en < ?", [$limite]);
        foreach ($viejos ?? [] as $f) {
            if (IntranetAdmin::ejecutar("DELETE FROM " . self::TABLA . " WHERE id = ? AND comunicado_id IS NULL", [(int)$f['id']])) {
                self::borrarDelDisco($f);
            }
        }

        // Carpetas de lotes vacías o abandonadas (archivos sin registro)
        $enUso = [];
        foreach (IntranetAdmin::consultar("SELECT DISTINCT lote FROM " . self::TABLA . " WHERE comunicado_id IS NULL") ?? [] as $f) {
            $enUso[trim((string)$f['lote'])] = true;
        }
        $corte = time() - self::HORAS_TEMPORAL * 3600;
        foreach (glob("$temp/*", GLOB_ONLYDIR) ?: [] as $dirLote) {
            if (isset($enUso[basename($dirLote)]) || (int)@filemtime($dirLote) > $corte) {
                continue;
            }
            foreach (glob("$dirLote/*") ?: [] as $archivo) {
                @unlink($archivo);
            }
            @rmdir($dirLote);
        }
    }
}
