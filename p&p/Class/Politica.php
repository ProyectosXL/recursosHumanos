
<?php

class Politica
{
    private $cid;
    private $cid_central;
    private $upload_dir; // Directorio donde se guardarán los archivos

    const TAMANIO_MAXIMO_PDF = 10485760; // 10 MB

    // Días en que un documento muestra la marca "Nuevo" o "Actualizado"
    const DIAS_NOVEDAD = 15;

    function __construct()
    {
        require_once __DIR__ . '/../../Class/conexion.php';
        $this->cid = new Conexion();
        $this->cid_central = $this->cid->conectar('central');
        
        // Definir la carpeta de almacenamiento de documentos
        $this->upload_dir = dirname(__FILE__, 2) . '/documentos/';
        
        // Crear el directorio si no existe
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }

    /**
     * Ejecuta una consulta y retorna un array con los resultados
     */
    private function retornarArray($sqlEnviado, $db = 'central'){
        $sql = $sqlEnviado;
        $cid_central = $this->cid->conectar($db);
        $stmt = sqlsrv_query($cid_central, $sql);
        
        if ($stmt === false) {
            return false;
        }
        
        $rows = array();
        while($v = sqlsrv_fetch_array($stmt)) {
            $rows[] = $v;
        }
        
        return $rows;
    }
    
    /**
     * Ejecuta una consulta que no devuelve resultados (INSERT, UPDATE, DELETE)
     */
    private function ejecutarSQL($sqlEnviado, $db = 'central') {
        $sql = $sqlEnviado;
        $cid_central = $this->cid->conectar($db);
        $stmt = sqlsrv_query($cid_central, $sql);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            return [
                'status' => 'error',
                'message' => $errors[0]['message'],
                'code' => $errors[0]['code']
            ];
        }
        
        return [
            'status' => 'success'
        ];
    }

    /**
     * Consulta parametrizada que devuelve filas asociativas (o false si falla)
     */
    private function consultar($sql, array $params = []) {
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        if ($stmt === false) {
            error_log('Politica::consultar: ' . print_r(sqlsrv_errors(), true));
            return false;
        }
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Ejecución parametrizada (INSERT / UPDATE / DELETE)
     */
    private function ejecutar($sql, array $params = []) {
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log('Politica::ejecutar: ' . print_r($errors, true));
            return [
                'status' => 'error',
                'message' => $errors[0]['message'] ?? 'Error en la base de datos',
                'code' => $errors[0]['code'] ?? null
            ];
        }
        return [
            'status' => 'success',
            'filas' => sqlsrv_rows_affected($stmt)
        ];
    }

    /**
     * Usuario del hub que hace la operación (o 'Sistema' si no hay sesión, p.ej. por consola)
     */
    private function usuarioActual() {
        return class_exists('AccesoDocuGest') ? AccesoDocuGest::usuario() : 'Sistema';
    }

    private function usuarioActualId() {
        return class_exists('AccesoDocuGest') ? AccesoDocuGest::usuarioId() : null;
    }

    /**
     * Obtiene todos los documentos (políticas y procedimientos)
     */
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT p.*, s.nombre as sector_nombre, s.icono
                FROM Politicas_Procedimientos p
                JOIN Sectores s ON p.sector_id = s.id
                WHERE p.estado = 'activo'";
        $params = [];

        // Aplicar filtros si existen
        if (!empty($filtros['tipo'])) {
            $sql .= " AND p.tipo = ?";
            $params[] = (string)$filtros['tipo'];
        }

        if (!empty($filtros['sector_id'])) {
            $sql .= " AND p.sector_id = ?";
            $params[] = (int)$filtros['sector_id'];
        }

        if (!empty($filtros['busqueda'])) {
            $like = '%' . $filtros['busqueda'] . '%';
            $sql .= " AND (p.titulo LIKE ? OR p.descripcion LIKE ? OR p.tags LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        // Novedades primero: fecha de la última versión o del alta (no fecha_actualizacion,
        // que también cambia con bajas y ediciones de datos)
        $sql .= " ORDER BY COALESCE(p.fecha_ultima_version, p.fecha_creacion, p.fecha_actualizacion) DESC, p.id DESC";

        return $this->consultar($sql, $params) ?: [];
    }

    /**
     * Marca de novedad de un documento:
     *  'actualizado' si se le subió una versión nueva hace DIAS_NOVEDAD días o menos,
     *  'nuevo' si se dio de alta hace DIAS_NOVEDAD días o menos, '' si no.
     * Cuenta desde la fecha de alta o de la última versión (no desde fecha_actualizacion).
     */
    public static function marcaNovedad($documento, $ahora = null) {
        $ahora = $ahora ?? time();
        $limite = $ahora - self::DIAS_NOVEDAD * 86400;

        $alta = self::aTimestamp($documento['fecha_creacion'] ?? null);
        $version = self::aTimestamp($documento['fecha_ultima_version'] ?? null);

        // Versión nueva: la fecha de la última versión es posterior al alta (más de 1 minuto)
        if ($version !== null && $version >= $limite && ($alta === null || $version - $alta > 60)) {
            return 'actualizado';
        }
        if ($alta !== null && $alta >= $limite) {
            return 'nuevo';
        }
        return '';
    }

    private static function aTimestamp($valor) {
        if ($valor instanceof DateTimeInterface) {
            return $valor->getTimestamp();
        }
        if (is_string($valor) && trim($valor) !== '') {
            $ts = strtotime($valor);
            return $ts === false ? null : $ts;
        }
        return null;
    }
    
    /**
     * Obtiene un documento específico por su ID
     * @param int $id ID del documento
     * @param bool $incrementarVista Si es true, incrementa el contador de vistas
     * @param bool $soloActivos Si es true, no devuelve documentos dados de baja
     * @return array|bool El documento encontrado o false
     */
    public function obtenerPorId($id, $incrementarVista = false, $soloActivos = false) {
        $sql = "SELECT p.*, s.nombre as sector_nombre, s.icono
                FROM Politicas_Procedimientos p
                JOIN Sectores s ON p.sector_id = s.id
                WHERE p.id = ?";
        if ($soloActivos) {
            $sql .= " AND p.estado = 'activo'";
        }

        $resultado = $this->consultar($sql, [(int)$id]);

        if ($resultado && count($resultado) > 0) {
            // Incrementar contador de vistas solo si se solicita explícitamente
            if ($incrementarVista) {
                $this->incrementarContador($id, 'vistas');
            }
            return $resultado[0];
        }
        
        return false;
    }
    
    /**
     * Guarda un nuevo documento en la base de datos y en el sistema de archivos
     */
    public function guardarDocumento($datos, $archivo) {
        // Validar datos mínimos
        if (empty($datos['titulo']) || empty($datos['sector_id']) || empty($archivo['name'])) {
            return [
                'status' => 'error',
                'message' => 'Faltan datos obligatorios'
            ];
        }
        
        // Procesar el archivo
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nuevo_nombre = $this->generarNombreArchivo($datos['titulo'], $extension);
        $ruta_completa = $this->upload_dir . $nuevo_nombre;
        
        // Comprobar que es un PDF
        if (strtolower($extension) !== 'pdf') {
            return [
                'status' => 'error',
                'message' => 'Solo se permiten archivos PDF'
            ];
        }
        
        // Mover el archivo al directorio de almacenamiento
        if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
            return [
                'status' => 'error',
                'message' => 'Error al subir el archivo'
            ];
        }
        
        // Preparar campos para la base de datos
        $tipo = isset($datos['tipo']) ? $datos['tipo'] : 'politica';
        $descripcion = isset($datos['descripcion']) ? $datos['descripcion'] : '';
        $tags = isset($datos['tags']) ? $datos['tags'] : '';
        $creado_por = isset($datos['creado_por']) ? $datos['creado_por'] : 'Sistema';
        
        // Insertar en la base de datos
        $sql = "INSERT INTO Politicas_Procedimientos (
                    tipo, titulo, descripcion, sector_id, 
                    archivo_nombre, ruta_archivo, tags, creado_por
                ) VALUES (
                    '$tipo', '".$this->escaparTexto($datos['titulo'])."', 
                    '".$this->escaparTexto($descripcion)."', ".$datos['sector_id'].", 
                    '$nuevo_nombre', '$ruta_completa', 
                    '".$this->escaparTexto($tags)."', '$creado_por'
                )";
        
        $resultado = $this->ejecutarSQL($sql);
        
        if ($resultado['status'] === 'success') {
            // Obtener el ID del documento recién insertado
            $sql_id = "SELECT MAX(id) as id FROM Politicas_Procedimientos";
            $id_resultado = $this->retornarArray($sql_id);
            
            if ($id_resultado && count($id_resultado) > 0) {
                $resultado['id'] = $id_resultado[0]['id'];
                
                // ========== INTEGRACIÓN RAG: Notificar nuevo documento ==========
                // Esta integración no afecta el funcionamiento normal del sistema
                try {
                    require_once dirname(__FILE__) . '/RagWebhook.php';
                    $ragWebhook = new RagWebhook();
                    
                    // Preparar datos completos del documento para el webhook
                    $documento_completo = $this->obtenerPorId($resultado['id']);
                    if ($documento_completo) {
                        $ragWebhook->notificarDocumentoNuevo($documento_completo);
                    }
                } catch (Exception $e) {
                    // Si hay error en el webhook, no afecta la operación principal
                    // El documento ya fue guardado correctamente
                    error_log("Error en webhook RAG: " . $e->getMessage());
                }
                // ================================================================
                
                // ========== AUTO-GENERACIÓN DE TAGS Y GLOSARIO (HUGGING FACE API) ==========
                // Sistema automático usando Hugging Face Space API
                // PROCESO ASÍNCRONO: Se ejecuta después de retornar respuesta al usuario
                // para evitar timeouts (Space puede tardar 60s en despertar)
                
                // Cerrar la conexión y liberar el buffer para enviar respuesta inmediata
                if (function_exists('fastcgi_finish_request')) {
                    // PHP-FPM: envía respuesta y continúa procesando
                    fastcgi_finish_request();
                } else {
                    // Apache/CGI: simular comportamiento similar
                    @ob_end_clean();
                    ignore_user_abort(true);
                    set_time_limit(0);
                }
                
                try {
                    require_once dirname(__FILE__) . '/TagsGlosarioAPI.php';
                    
                    // Inicializar API client
                    $api = new TagsGlosarioAPI();
                    
                    $documento_id = $resultado['id'];
                    
                    // 1. GENERAR TAGS AUTOMÁTICAMENTE
                    // Combinar tags del usuario (si existen) con tags generadas por IA
                    error_log("TagsGlosario: Generando tags para documento $documento_id...");
                    
                    // Separar tags del usuario (si las hay)
                    $tags_usuario = [];
                    if (!empty($tags)) {
                        // Limpiar y normalizar tags del usuario
                        $tags_usuario = array_map('trim', explode(',', $tags));
                        $tags_usuario = array_filter($tags_usuario); // Eliminar vacíos
                        $tags_usuario = array_map('strtolower', $tags_usuario); // Normalizar a minúsculas para comparación
                        error_log("TagsGlosario: Usuario proporcionó " . count($tags_usuario) . " tags manuales");
                    }
                    
                    // Generar tags con IA
                    $tags_ia = $api->generarTags($ruta_completa, 10);
                    
                    if (count($tags_ia) > 0) {
                        // Normalizar tags de IA para comparación (minúsculas)
                        $tags_ia_lower = array_map('strtolower', $tags_ia);
                        
                        // Filtrar tags de IA que ya están en las tags del usuario
                        $tags_ia_filtradas = [];
                        foreach ($tags_ia as $index => $tag) {
                            $tag_lower = $tags_ia_lower[$index];
                            // Solo agregar si no está en las tags del usuario
                            if (!in_array($tag_lower, $tags_usuario)) {
                                $tags_ia_filtradas[] = $tag;
                            }
                        }
                        
                        // Combinar: primero las del usuario (originales, sin normalizar), luego las de IA
                        $tags_originales_usuario = [];
                        if (!empty($tags)) {
                            $tags_originales_usuario = array_map('trim', explode(',', $tags));
                            $tags_originales_usuario = array_filter($tags_originales_usuario);
                        }
                        
                        $tags_finales = array_merge($tags_originales_usuario, $tags_ia_filtradas);
                        $tags_string = implode(', ', $tags_finales);
                        
                        // Actualizar tags en la BD
                        $sql_update = "UPDATE Politicas_Procedimientos 
                                       SET tags = '".$this->escaparTexto($tags_string)."'
                                       WHERE id = $documento_id";
                        $this->ejecutarSQL($sql_update);
                        
                        $count_usuario = count($tags_originales_usuario);
                        $count_ia = count($tags_ia_filtradas);
                        error_log("TagsGlosario: Tags finales para documento $documento_id - Usuario: $count_usuario, IA (nuevos): $count_ia, Total: " . count($tags_finales));
                    } else {
                        // Si no se generaron tags de IA pero hay tags del usuario, ya están guardadas
                        if (!empty($tags)) {
                            error_log("TagsGlosario: No se generaron tags de IA, se mantienen solo las del usuario");
                        } else {
                            error_log("TagsGlosario: No se generaron tags para documento $documento_id");
                        }
                    }
                    
                    // 2. DETECTAR TÉRMINOS PARA GLOSARIO
                    
                    // 2. DETECTAR TÉRMINOS PARA GLOSARIO
                    error_log("TagsGlosario: Detectando términos para glosario (documento $documento_id)...");
                    
                    // Obtener glosario existente para evitar duplicados
                    $sql_glosario_existente = "SELECT termino FROM Glosario";
                    $glosario_result = $this->retornarArray($sql_glosario_existente);
                    $glosario_existente = [];
                    if ($glosario_result) {
                        foreach ($glosario_result as $row) {
                            $glosario_existente[] = $row['termino'];
                        }
                    }
                    
                    // Detectar nuevos términos
                    $terminos_detectados = $api->detectarTerminosGlosario($ruta_completa, $glosario_existente);
                    
                    if (count($terminos_detectados) > 0) {
                        $terminos_insertados = 0;
                        
                        foreach ($terminos_detectados as $termino_obj) {
                            // Verificación adicional de duplicados (case-insensitive)
                            $sql_check = "SELECT COUNT(*) as count FROM Glosario 
                                         WHERE LOWER(termino) = LOWER('".$this->escaparTexto($termino_obj['termino'])."')";
                            $check_result = $this->retornarArray($sql_check);
                            
                            if ($check_result && $check_result[0]['count'] == 0) {
                                // Insertar en tabla Glosario
                                $definicion = isset($termino_obj['definicion']) ? $termino_obj['definicion'] : '';
                                
                                $sql_glosario = "INSERT INTO Glosario (termino, definicion, sector_id, fecha_creacion)
                                                 VALUES (
                                                     '".$this->escaparTexto($termino_obj['termino'])."',
                                                     '".$this->escaparTexto($definicion)."',
                                                     {$datos['sector_id']},
                                                     GETDATE()
                                                 )";
                                $this->ejecutarSQL($sql_glosario);
                                $terminos_insertados++;
                            }
                        }
                        
                        if ($terminos_insertados > 0) {
                            error_log("TagsGlosario: $terminos_insertados nuevos términos agregados al glosario desde documento $documento_id");
                        } else {
                            error_log("TagsGlosario: No se insertaron nuevos términos (ya existían en el glosario)");
                        }
                    } else {
                        error_log("TagsGlosario: No se detectaron términos para glosario en documento $documento_id");
                    }
                    
                } catch (Exception $e) {
                    // Si falla la API, no afecta el guardado del documento
                    error_log("TagsGlosario: Error en auto-generación: " . $e->getMessage());
                }
                // ========================================================
            }
        }
        
        return $resultado;
    }
    
    /**
     * Sube una versión nueva del PDF de un documento.
     *
     * - El PDF anterior NO se borra: se mueve a documentos/versiones/{id}/v{version_anterior}_{archivo}.
     *   Esa carpeta está bloqueada por documentos/.htaccess y ningún endpoint la sirve.
     * - El id del documento no cambia (enlaces y contadores siguen funcionando).
     * - Se registra en Historial_Versiones: versión, motivo, usuario y ruta del PDF archivado.
     * - El servicio de IA reindexa el documento: index-base64 borra los fragmentos
     *   anteriores del mismo document_id antes de cargar los nuevos (no duplica).
     *
     * @param int   $id      Id del documento
     * @param array $datos   ['version' => '1.1', 'motivo' => '...', 'tags' => '...']
     * @param array $archivo Elemento de $_FILES con el PDF nuevo (obligatorio)
     */
    public function actualizarDocumento($id, $datos, $archivo = null) {
        $id = (int)$id;
        $documento = $this->obtenerPorId($id, false, true);
        if (!$documento) {
            return ['status' => 'error', 'message' => 'El documento no existe o está dado de baja'];
        }

        // ---- Validaciones ----
        $motivo = trim((string)($datos['motivo'] ?? ''));
        if ($motivo === '') {
            return ['status' => 'error', 'message' => 'Contá el motivo del cambio'];
        }

        $versionActual = trim((string)($documento['version'] ?? '')) ?: '1.0';
        $versionNueva = trim((string)($datos['version'] ?? ''));
        if (!self::versionValida($versionNueva)) {
            return ['status' => 'error', 'message' => 'La versión tiene que ser un número, por ejemplo 1.1'];
        }
        if (self::versionValida($versionActual) && self::compararVersiones($versionNueva, $versionActual) <= 0) {
            return ['status' => 'error', 'message' => "La versión nueva tiene que ser mayor que la actual ($versionActual)"];
        }

        $validacionArchivo = $this->validarPdfSubido($archivo);
        if ($validacionArchivo !== true) {
            return ['status' => 'error', 'message' => $validacionArchivo];
        }

        // ---- Archivos ----
        $rutaVigente = $this->rutaArchivoVigente($documento);

        $nuevo_nombre = $this->generarNombreArchivo($documento['titulo'], 'pdf');
        // Nunca pisar un archivo existente (por ejemplo el vigente, si coincide el segundo del nombre)
        if (file_exists($this->upload_dir . $nuevo_nombre)) {
            $nuevo_nombre = pathinfo($nuevo_nombre, PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(3)) . '.pdf';
        }
        $ruta_nueva = $this->upload_dir . $nuevo_nombre;
        if (!move_uploaded_file($archivo['tmp_name'], $ruta_nueva)) {
            return ['status' => 'error', 'message' => 'No se pudo guardar el archivo nuevo'];
        }

        // Archivar el PDF anterior (nunca se borra)
        $ruta_archivada = null;
        if ($rutaVigente !== null) {
            $dirVersiones = $this->upload_dir . 'versiones/' . $id . '/';
            if (!is_dir($dirVersiones) && !mkdir($dirVersiones, 0755, true)) {
                @unlink($ruta_nueva);
                return ['status' => 'error', 'message' => 'No se pudo crear la carpeta de versiones anteriores'];
            }
            $prefijo = 'v' . preg_replace('/[^0-9.]/', '', $versionActual) . '_';
            $ruta_archivada = $dirVersiones . $prefijo . basename($rutaVigente);
            if (file_exists($ruta_archivada)) {
                $ruta_archivada = $dirVersiones . $prefijo . time() . '_' . basename($rutaVigente);
            }
            if (!rename($rutaVigente, $ruta_archivada)) {
                @unlink($ruta_nueva);
                return ['status' => 'error', 'message' => 'No se pudo guardar la versión anterior. No se hizo ningún cambio.'];
            }
        } else {
            error_log("actualizarDocumento: el documento $id no tenía PDF en disco; no hay versión anterior para archivar");
        }

        // ---- Base de datos ----
        $tags = array_key_exists('tags', $datos) ? trim((string)$datos['tags']) : (string)($documento['tags'] ?? '');

        $resultado = $this->ejecutar(
            "UPDATE Politicas_Procedimientos
                SET version = ?, archivo_nombre = ?, ruta_archivo = ?, tags = ?,
                    fecha_actualizacion = GETDATE(), fecha_ultima_version = GETDATE()
              WHERE id = ?",
            [$versionNueva, $nuevo_nombre, $ruta_nueva, $tags, $id]
        );

        if ($resultado['status'] !== 'success') {
            // Dejar los archivos como estaban
            if ($ruta_archivada !== null) {
                @rename($ruta_archivada, $rutaVigente);
            }
            @unlink($ruta_nueva);
            return ['status' => 'error', 'message' => 'No se pudo actualizar el documento. No se hizo ningún cambio.'];
        }

        $this->registrarHistorial($id, 'version', $versionNueva, $motivo, $versionActual, $ruta_archivada);

        // ---- Servicio de IA: reindexar solo la versión vigente ----
        $this->notificarRag('actualizado', $id);

        return ['status' => 'success', 'version' => $versionNueva, 'tipo' => $documento['tipo']];
    }

    /**
     * Edita los datos de un documento sin cambiar el PDF ni la versión.
     * Queda en el historial como "Edición de datos". Si cambian título, tipo o sector,
     * se avisa al servicio de IA para que actualice los metadatos (reindexa el PDF vigente).
     *
     * @param array $datos ['titulo', 'tipo', 'sector_id', 'descripcion', 'tags']
     */
    public function editarDatosDocumento($id, $datos) {
        $id = (int)$id;
        $documento = $this->obtenerPorId($id, false, true);
        if (!$documento) {
            return ['status' => 'error', 'message' => 'El documento no existe o está dado de baja'];
        }

        $nuevo = [
            'titulo'      => trim((string)($datos['titulo'] ?? '')),
            'tipo'        => (string)($datos['tipo'] ?? ''),
            'sector_id'   => (int)($datos['sector_id'] ?? 0),
            'descripcion' => trim((string)($datos['descripcion'] ?? '')),
            'tags'        => trim((string)($datos['tags'] ?? '')),
        ];

        if ($nuevo['titulo'] === '') {
            return ['status' => 'error', 'message' => 'El título es obligatorio'];
        }
        if (!in_array($nuevo['tipo'], ['politica', 'procedimiento'], true)) {
            return ['status' => 'error', 'message' => 'Elegí el tipo de documento'];
        }
        if ($nuevo['sector_id'] <= 0 || !$this->obtenerSectorPorId($nuevo['sector_id'])) {
            return ['status' => 'error', 'message' => 'Elegí un sector válido'];
        }

        $etiquetas = [
            'titulo'      => 'título',
            'tipo'        => 'tipo',
            'sector_id'   => 'sector',
            'descripcion' => 'descripción',
            'tags'        => 'etiquetas',
        ];
        $cambios = [];
        foreach ($etiquetas as $campo => $texto) {
            $anterior = $campo === 'sector_id' ? (int)$documento[$campo] : trim((string)($documento[$campo] ?? ''));
            if ($anterior !== $nuevo[$campo]) {
                $cambios[] = $texto;
            }
        }
        if (empty($cambios)) {
            return ['status' => 'error', 'message' => 'No hay cambios para guardar'];
        }

        // No toca version ni fecha_ultima_version
        $resultado = $this->ejecutar(
            "UPDATE Politicas_Procedimientos
                SET titulo = ?, tipo = ?, sector_id = ?, descripcion = ?, tags = ?,
                    fecha_actualizacion = GETDATE()
              WHERE id = ?",
            [$nuevo['titulo'], $nuevo['tipo'], $nuevo['sector_id'], $nuevo['descripcion'], $nuevo['tags'], $id]
        );

        if ($resultado['status'] !== 'success') {
            return ['status' => 'error', 'message' => 'No se pudieron guardar los cambios'];
        }

        $version = trim((string)($documento['version'] ?? '')) ?: '1.0';
        $this->registrarHistorial($id, 'edicion', $version, 'Cambió: ' . implode(', ', $cambios));

        // Título, tipo o sector forman parte de los metadatos del servicio de IA
        if (array_intersect($cambios, ['título', 'tipo', 'sector'])) {
            $this->notificarRag('actualizado', $id);
        }

        return ['status' => 'success', 'tipo' => $nuevo['tipo']];
    }

    /**
     * Ruta en disco del PDF vigente de un documento, o null si no existe.
     * Nunca devuelve rutas de documentos/versiones/.
     */
    public function rutaArchivoVigente($documento) {
        $candidatas = [];
        if (!empty($documento['ruta_archivo'])) {
            $candidatas[] = $documento['ruta_archivo'];
        }
        if (!empty($documento['archivo_nombre'])) {
            $candidatas[] = $this->upload_dir . basename($documento['archivo_nombre']);
        }
        $dirVersiones = realpath($this->upload_dir . 'versiones');
        foreach ($candidatas as $ruta) {
            $real = realpath($ruta);
            if ($real === false || !is_file($real)) {
                continue;
            }
            if ($dirVersiones !== false && strpos($real, $dirVersiones . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }
            return $real;
        }
        return null;
    }

    /**
     * Valida el PDF subido. Devuelve true o el mensaje de error para el usuario.
     */
    private function validarPdfSubido($archivo) {
        if (!$archivo || !isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE || empty($archivo['name'])) {
            return 'Elegí el PDF nuevo';
        }
        if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'El archivo supera el tamaño máximo (10 MB)';
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            return 'El archivo no se pudo subir. Probá de nuevo.';
        }
        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            return 'Solo se permiten archivos PDF';
        }
        if ($archivo['size'] > self::TAMANIO_MAXIMO_PDF) {
            return 'El archivo supera el tamaño máximo (10 MB)';
        }
        $cabecera = @file_get_contents($archivo['tmp_name'], false, null, 0, 5);
        if ($cabecera !== '%PDF-') {
            return 'El archivo no es un PDF válido';
        }
        return true;
    }

    /**
     * Compara versiones número por número (1 == 1.0, 1.10 > 1.9). Devuelve -1, 0 o 1.
     */
    public static function compararVersiones($a, $b) {
        $pa = array_map('intval', explode('.', (string)$a));
        $pb = array_map('intval', explode('.', (string)$b));
        $largo = max(count($pa), count($pb));
        for ($i = 0; $i < $largo; $i++) {
            $x = $pa[$i] ?? 0;
            $y = $pb[$i] ?? 0;
            if ($x !== $y) {
                return $x > $y ? 1 : -1;
            }
        }
        return 0;
    }

    /** Versión con formato numérico: 1, 1.1, 2.0.3 */
    public static function versionValida($version) {
        return is_string($version) && preg_match('/^\d{1,4}(\.\d{1,4}){0,3}$/', $version) === 1;
    }

    /**
     * Avisa al servicio de IA. No corta la operación si falla.
     * $evento: 'actualizado' (reindexa el PDF vigente con sus metadatos) | 'eliminado'
     */
    private function notificarRag($evento, $id) {
        try {
            require_once dirname(__FILE__) . '/RagWebhook.php';
            $ragWebhook = new RagWebhook();
            if ($evento === 'eliminado') {
                $r = $ragWebhook->notificarDocumentoEliminado($id);
            } else {
                $documento = $this->obtenerPorId($id);
                if (!$documento) {
                    return;
                }
                // RagWebhook lee el PDF desde ruta_archivo
                $ruta = $this->rutaArchivoVigente($documento);
                if ($ruta !== null) {
                    $documento['ruta_archivo'] = $ruta;
                }
                $r = $ragWebhook->notificarDocumentoActualizado($id, $documento);
            }
            if (empty($r['success'])) {
                error_log("Webhook RAG ($evento) documento $id: " . ($r['message'] ?? 'sin detalle'));
            }
        } catch (Throwable $e) {
            error_log("Error en webhook RAG ($evento): " . $e->getMessage());
        }
    }

    /**
     * Da de baja un documento (estado = 'inactivo'). No borra ningún archivo.
     * Registra fecha, usuario y motivo, y avisa al servicio de IA para que lo saque del índice.
     */
    public function eliminarDocumento($id, $motivo = '') {
        $id = (int)$id;
        $motivo = trim((string)$motivo);
        if ($motivo === '') {
            return ['status' => 'error', 'message' => 'Contá el motivo de la baja'];
        }

        $documento = $this->obtenerPorId($id, false, true);
        if (!$documento) {
            return ['status' => 'error', 'message' => 'El documento no existe o ya está dado de baja'];
        }

        $resultado = $this->ejecutar(
            "UPDATE Politicas_Procedimientos
                SET estado = 'inactivo', fecha_actualizacion = GETDATE(),
                    fecha_baja = GETDATE(), usuario_baja = ?, motivo_baja = ?
              WHERE id = ? AND estado = 'activo'",
            [$this->usuarioActual(), mb_substr($motivo, 0, 500), $id]
        );

        if ($resultado['status'] === 'success') {
            $version = trim((string)($documento['version'] ?? '')) ?: '1.0';
            $this->registrarHistorial($id, 'baja', $version, $motivo);
            $this->notificarRag('eliminado', $id);
        }

        return $resultado;
    }

    /**
     * Vuelve a activar un documento dado de baja y avisa al servicio de IA para que lo indexe.
     */
    public function reactivarDocumento($id) {
        $id = (int)$id;
        $documento = $this->obtenerPorId($id);
        if (!$documento) {
            return ['status' => 'error', 'message' => 'El documento no existe'];
        }
        if ($documento['estado'] === 'activo') {
            return ['status' => 'error', 'message' => 'El documento ya está activo'];
        }
        if ($this->rutaArchivoVigente($documento) === null) {
            return ['status' => 'error', 'message' => 'No se encontró el PDF del documento en el servidor'];
        }

        $resultado = $this->ejecutar(
            "UPDATE Politicas_Procedimientos
                SET estado = 'activo', fecha_actualizacion = GETDATE(),
                    fecha_baja = NULL, usuario_baja = NULL, motivo_baja = NULL
              WHERE id = ?",
            [$id]
        );

        if ($resultado['status'] === 'success') {
            $version = trim((string)($documento['version'] ?? '')) ?: '1.0';
            $this->registrarHistorial($id, 'reactivacion', $version, 'Documento reactivado');
            $this->notificarRag('actualizado', $id);
        }

        return $resultado;
    }

    /**
     * Documentos dados de baja (Configuración, solo Admin)
     */
    public function obtenerDadosDeBaja() {
        $filas = $this->consultar(
            "SELECT p.id, p.titulo, p.tipo, p.version, s.nombre AS sector_nombre,
                    COALESCE(p.fecha_baja, p.fecha_actualizacion) AS fecha_baja,
                    p.usuario_baja, p.motivo_baja
               FROM Politicas_Procedimientos p
               JOIN Sectores s ON p.sector_id = s.id
              WHERE p.estado <> 'activo'
              ORDER BY COALESCE(p.fecha_baja, p.fecha_actualizacion) DESC"
        );
        return $filas ?: [];
    }

    /**
     * Elimina físicamente un documento (tanto registro como archivo)
     */
    public function eliminarDefinitivamente($id) {
        // Primero obtener la información del documento
        $documento = $this->obtenerPorId($id);
        if (!$documento) {
            return [
                'status' => 'error',
                'message' => 'El documento no existe'
            ];
        }
        
        // Eliminar archivo físico si existe
        if (file_exists($documento['ruta_archivo'])) {
            unlink($documento['ruta_archivo']);
        }
        
        // Eliminar registro de la base de datos
        $sql = "DELETE FROM Politicas_Procedimientos WHERE id = $id";
        
        $resultado = $this->ejecutarSQL($sql);
        
        // ========== INTEGRACIÓN RAG: Notificar documento eliminado ==========
        if ($resultado['status'] === 'success') {
            try {
                require_once dirname(__FILE__) . '/RagWebhook.php';
                $ragWebhook = new RagWebhook();
                $ragWebhook->notificarDocumentoEliminado($id);
            } catch (Exception $e) {
                error_log("Error en webhook RAG (eliminación definitiva): " . $e->getMessage());
            }
        }
        // ====================================================================
        
        return $resultado;
    }
    
    /**
     * Obtiene todos los sectores
     */
    public function obtenerSectores() {
        $sql = "SELECT * FROM Sectores ORDER BY nombre";
        return $this->retornarArray($sql);
    }
    
    /**
     * Obtiene un sector por su ID
     */
    public function obtenerSectorPorId($id) {
        $resultado = $this->consultar("SELECT * FROM Sectores WHERE id = ?", [(int)$id]);
        return ($resultado && count($resultado) > 0) ? $resultado[0] : false;
    }

    /**
     * Valida y normaliza los datos de un sector. Devuelve [datos, error].
     */
    private function normalizarSector($datos, $idExcluir = null) {
        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($nombre === '') {
            return [null, 'El nombre del sector es obligatorio'];
        }
        if (mb_strlen($nombre) > 100) {
            return [null, 'El nombre del sector es demasiado largo'];
        }
        $icono = trim((string)($datos['icono'] ?? ''));
        if (!preg_match('/^fa-[a-z0-9-]{1,40}$/', $icono)) {
            $icono = 'fa-folder';
        }

        $sql = "SELECT COUNT(*) AS cantidad FROM Sectores WHERE LOWER(LTRIM(RTRIM(nombre))) = LOWER(?)";
        $params = [$nombre];
        if ($idExcluir !== null) {
            $sql .= " AND id <> ?";
            $params[] = (int)$idExcluir;
        }
        $repetido = $this->consultar($sql, $params);
        if ($repetido && (int)$repetido[0]['cantidad'] > 0) {
            return [null, 'Ya existe un sector con ese nombre'];
        }

        return [[
            'nombre'      => $nombre,
            'descripcion' => trim((string)($datos['descripcion'] ?? '')),
            'icono'       => $icono,
        ], null];
    }

    /**
     * Guarda un nuevo sector
     */
    public function guardarSector($datos) {
        list($sector, $error) = $this->normalizarSector($datos);
        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }
        return $this->ejecutar(
            "INSERT INTO Sectores (nombre, descripcion, icono) VALUES (?, ?, ?)",
            [$sector['nombre'], $sector['descripcion'], $sector['icono']]
        );
    }

    /**
     * Modifica un sector existente
     */
    public function actualizarSector($id, $datos) {
        $id = (int)$id;
        if (!$this->obtenerSectorPorId($id)) {
            return ['status' => 'error', 'message' => 'El sector no existe'];
        }
        list($sector, $error) = $this->normalizarSector($datos, $id);
        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }
        return $this->ejecutar(
            "UPDATE Sectores SET nombre = ?, descripcion = ?, icono = ? WHERE id = ?",
            [$sector['nombre'], $sector['descripcion'], $sector['icono'], $id]
        );
    }

    /**
     * Cantidad de documentos de un sector: ['activos' => n, 'inactivos' => n]
     */
    public function contarDocumentosSector($id) {
        $resultado = $this->consultar(
            "SELECT SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS activos,
                    SUM(CASE WHEN estado <> 'activo' THEN 1 ELSE 0 END) AS inactivos
               FROM Politicas_Procedimientos
              WHERE sector_id = ?",
            [(int)$id]
        );
        return [
            'activos'   => (int)($resultado[0]['activos'] ?? 0),
            'inactivos' => (int)($resultado[0]['inactivos'] ?? 0),
        ];
    }

    /**
     * Elimina un sector sin documentos.
     * - Con documentos activos: no se puede (dice cuántos tiene).
     * - Con documentos dados de baja: tampoco, porque al reactivarlos quedarían sin sector.
     * - Los términos del glosario de ese sector quedan "sin sector específico".
     */
    public function eliminarSector($id) {
        $id = (int)$id;
        if (!$this->obtenerSectorPorId($id)) {
            return ['status' => 'error', 'message' => 'El sector no existe'];
        }

        $cantidad = $this->contarDocumentosSector($id);
        if ($cantidad['activos'] > 0) {
            $n = $cantidad['activos'];
            return ['status' => 'error', 'message' => "No se puede eliminar: el sector tiene $n documento" . ($n === 1 ? '' : 's') . ' activo' . ($n === 1 ? '' : 's') . '.'];
        }
        if ($cantidad['inactivos'] > 0) {
            $n = $cantidad['inactivos'];
            return ['status' => 'error', 'message' => "No se puede eliminar: el sector tiene $n documento" . ($n === 1 ? '' : 's') . ' dado' . ($n === 1 ? '' : 's') . ' de baja.'];
        }

        $this->ejecutar("UPDATE Glosario SET sector_id = NULL WHERE sector_id = ?", [$id]);
        return $this->ejecutar("DELETE FROM Sectores WHERE id = ?", [$id]);
    }

    /**
     * Documentos activos por sector (para estadísticas)
     */
    public function obtenerEstadisticasSectores() {
        $filas = $this->consultar(
            "SELECT s.id, s.nombre, s.icono, COUNT(p.id) AS documentos
               FROM Sectores s
               LEFT JOIN Politicas_Procedimientos p ON p.sector_id = s.id AND p.estado = 'activo'
              GROUP BY s.id, s.nombre, s.icono
              ORDER BY s.nombre"
        );
        return $filas ?: [];
    }

    /**
     * Obtiene términos del glosario
     */
    public function obtenerGlosario($filtro = '') {
        $sql = "SELECT g.*, s.nombre as sector_nombre
                FROM Glosario g
                LEFT JOIN Sectores s ON g.sector_id = s.id";

        if (!empty($filtro)) {
            $sql .= " WHERE g.termino LIKE '%$filtro%' OR g.definicion LIKE '%$filtro%'";
        }

        $sql .= " ORDER BY g.termino";

        return $this->retornarArray($sql);
    }

    /**
     * Obtiene un término del glosario por su ID
     */
    public function obtenerTerminoPorId($id) {
        $resultado = $this->consultar(
            "SELECT g.id, g.termino, g.definicion, g.sector_id, s.nombre AS sector_nombre
               FROM Glosario g
               LEFT JOIN Sectores s ON g.sector_id = s.id
              WHERE g.id = ?",
            [(int)$id]
        );
        return ($resultado && count($resultado) > 0) ? $resultado[0] : false;
    }

    /**
     * Valida y normaliza un término del glosario. Devuelve [datos, error].
     */
    private function normalizarTermino($datos, $idExcluir = null) {
        $termino = trim((string)($datos['termino'] ?? ''));
        $definicion = trim((string)($datos['definicion'] ?? ''));
        if ($termino === '' || $definicion === '') {
            return [null, 'El término y la definición son obligatorios'];
        }

        $sectorId = (int)($datos['sector_id'] ?? 0);
        if ($sectorId > 0 && !$this->obtenerSectorPorId($sectorId)) {
            return [null, 'El sector elegido no existe'];
        }

        $sql = "SELECT COUNT(*) AS cantidad FROM Glosario WHERE LOWER(LTRIM(RTRIM(termino))) = LOWER(?)";
        $params = [$termino];
        if ($idExcluir !== null) {
            $sql .= " AND id <> ?";
            $params[] = (int)$idExcluir;
        }
        $repetido = $this->consultar($sql, $params);
        if ($repetido && (int)$repetido[0]['cantidad'] > 0) {
            return [null, 'Ese término ya está en el glosario'];
        }

        return [[
            'termino'    => $termino,
            'definicion' => $definicion,
            'sector_id'  => $sectorId > 0 ? $sectorId : null,
        ], null];
    }

    /**
     * Guarda un nuevo término en el glosario
     */
    public function guardarTerminoGlosario($datos) {
        list($t, $error) = $this->normalizarTermino($datos);
        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }
        return $this->ejecutar(
            "INSERT INTO Glosario (termino, definicion, sector_id, fecha_creacion) VALUES (?, ?, ?, GETDATE())",
            [$t['termino'], $t['definicion'], $t['sector_id']]
        );
    }

    /**
     * Modifica un término del glosario
     */
    public function actualizarTerminoGlosario($id, $datos) {
        $id = (int)$id;
        if (!$this->obtenerTerminoPorId($id)) {
            return ['status' => 'error', 'message' => 'El término no existe'];
        }
        list($t, $error) = $this->normalizarTermino($datos, $id);
        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }
        return $this->ejecutar(
            "UPDATE Glosario SET termino = ?, definicion = ?, sector_id = ? WHERE id = ?",
            [$t['termino'], $t['definicion'], $t['sector_id'], $id]
        );
    }

    /**
     * Elimina un término del glosario
     */
    public function eliminarTerminoGlosario($id) {
        $id = (int)$id;
        if (!$this->obtenerTerminoPorId($id)) {
            return ['status' => 'error', 'message' => 'El término no existe'];
        }
        return $this->ejecutar("DELETE FROM Glosario WHERE id = ?", [$id]);
    }

    /**
     * Registra acceso a un documento
     */
    public function registrarAcceso($documento_id, $tipo_acceso, $usuario_id = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $tipo_acceso = $tipo_acceso === 'descarga' ? 'descarga' : 'vista';
        if ($usuario_id === null) {
            $usuario_id = $this->usuarioActualId();
        }

        $sql = "INSERT INTO Accesos_Documentos (documento_id, usuario_id, tipo_acceso, ip_acceso)
                VALUES (?, ?, ?, ?)";

        $this->ejecutar($sql, [(int)$documento_id, $usuario_id, $tipo_acceso, $ip]);

        // Incrementar contador específico
        $this->incrementarContador($documento_id, $tipo_acceso);
    }

    /**
     * Incrementa el contador de vistas o descargas
     * (no toca fecha_actualizacion ni fecha_ultima_version)
     */
    private function incrementarContador($documento_id, $tipo) {
        $campo = $tipo === 'descarga' ? 'descargas' : 'vistas';

        $sql = "UPDATE Politicas_Procedimientos
                SET $campo = $campo + 1
                WHERE id = ?";

        $this->ejecutar($sql, [(int)$documento_id]);
    }
    
    /**
     * Obtiene estadísticas de uso
     */
    public function obtenerEstadisticas() {
        $estadisticas = [];
        
        // Total de documentos
        $sql = "SELECT COUNT(*) as total FROM Politicas_Procedimientos WHERE estado = 'activo'";
        $resultado = $this->retornarArray($sql);
        $estadisticas['total_documentos'] = $resultado[0]['total'];
        
        // Obtener específicamente la cantidad de políticas
        $sql = "SELECT COUNT(*) as cantidad 
        FROM Politicas_Procedimientos 
        WHERE estado = 'activo' AND tipo = 'politica'";
        $resultado = $this->retornarArray($sql);
        $estadisticas['total_politicas'] = $resultado[0]['cantidad'];

        // Obtener específicamente la cantidad de procedimientos
        $sql = "SELECT COUNT(*) as cantidad 
            FROM Politicas_Procedimientos 
            WHERE estado = 'activo' AND tipo = 'procedimiento'";
        $resultado = $this->retornarArray($sql);
        $estadisticas['total_procedimientos'] = $resultado[0]['cantidad'];
        
        // Documentos por sector
        $sql = "SELECT s.nombre, COUNT(*) as cantidad 
                FROM Politicas_Procedimientos p
                JOIN Sectores s ON p.sector_id = s.id
                WHERE p.estado = 'activo' 
                GROUP BY s.nombre";
        $estadisticas['por_sector'] = $this->retornarArray($sql);
        
        // Documentos más vistos
        $sql = "SELECT TOP 5 id, titulo, vistas 
                FROM Politicas_Procedimientos 
                WHERE estado = 'activo' 
                ORDER BY vistas DESC";
        $estadisticas['mas_vistos'] = $this->retornarArray($sql);
        
        // Documentos más descargados
        $sql = "SELECT TOP 5 id, titulo, descargas 
                FROM Politicas_Procedimientos 
                WHERE estado = 'activo' 
                ORDER BY descargas DESC";
        $estadisticas['mas_descargados'] = $this->retornarArray($sql);
        
        return $estadisticas;
    }
    
    /**
     * Busca documentos por contenido
     * Nota: Esta es una implementación básica. Para búsqueda avanzada en PDFs,
     * se necesitaría una herramienta de indexación de contenido como Elasticsearch
     */
    public function buscarPorContenido($texto) {
        // En una implementación real, aquí se utilizaría un índice de búsqueda
        // Para este ejemplo, sólo buscamos en metadatos
        $sql = "SELECT p.*, s.nombre as sector_nombre 
                FROM Politicas_Procedimientos p 
                JOIN Sectores s ON p.sector_id = s.id 
                WHERE p.estado = 'activo' 
                AND (p.titulo LIKE '%$texto%' 
                    OR p.descripcion LIKE '%$texto%' 
                    OR p.tags LIKE '%$texto%')";
        
        return $this->retornarArray($sql);
    }
    
    /**
     * Método auxiliar para generar un nombre de archivo único
     */
    private function generarNombreArchivo($titulo, $extension) {
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($titulo));
        $timestamp = time();
        return $base . '-' . $timestamp . '.' . $extension;
    }
    
    /**
     * Método auxiliar para escapar texto para SQL
     */
    private function escaparTexto($texto) {
        return str_replace("'", "''", $texto);
    }

    /**
     * Registra un cambio en Historial_Versiones.
     * $tipo: 'version' | 'edicion' | 'baja' | 'reactivacion'
     */
    private function registrarHistorial($documento_id, $tipo, $version, $descripcion = '', $versionAnterior = null, $rutaArchivoAnterior = null) {
        return $this->ejecutar(
            "INSERT INTO Historial_Versiones
                (documento_id, version, descripcion, usuario, fecha, tipo_cambio, version_anterior, ruta_archivo_anterior, usuario_id)
             VALUES (?, ?, ?, ?, GETDATE(), ?, ?, ?, ?)",
            [
                (int)$documento_id,
                (string)$version,
                (string)$descripcion,
                $this->usuarioActual(),
                $tipo,
                $versionAnterior,
                $rutaArchivoAnterior,
                $this->usuarioActualId()
            ]
        );
    }

    /**
     * Historial de un documento para mostrar en pantalla.
     * No devuelve la ruta de los PDF archivados: no se pueden ver ni descargar desde la app.
     */
    public function obtenerHistorialVersiones($documento_id) {
        $historial = $this->consultar(
            "SELECT id, documento_id, version, descripcion, fecha, usuario,
                    ISNULL(tipo_cambio, 'version') AS tipo_cambio
               FROM Historial_Versiones
              WHERE documento_id = ?
              ORDER BY fecha DESC, id DESC",
            [(int)$documento_id]
        );
        if (!$historial) {
            return [];
        }

        $etiquetas = [
            'version'      => 'Nueva versión',
            'edicion'      => 'Edición de datos',
            'baja'         => 'Baja',
            'reactivacion' => 'Reactivación',
        ];

        foreach ($historial as &$fila) {
            $fila['fecha'] = self::formatearFecha($fila['fecha'], 'd/m/Y H:i');
            $fila['tipo_texto'] = $etiquetas[$fila['tipo_cambio']] ?? 'Cambio';
        }
        unset($fila);

        return $historial;
    }

    /**
     * Formatea una fecha de SQL Server (DateTime o string). '' si no hay fecha.
     */
    public static function formatearFecha($valor, $formato = 'd/m/Y') {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format($formato);
        }
        if (is_string($valor) && trim($valor) !== '') {
            $ts = strtotime($valor);
            return $ts ? date($formato, $ts) : '';
        }
        return '';
    }

}
