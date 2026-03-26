
<?php

class Politica
{
    private $cid;
    private $cid_central;
    private $upload_dir; // Directorio donde se guardarán los archivos

    function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/Class/Conexion.php';
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
     * Obtiene todos los documentos (políticas y procedimientos)
     */
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT p.*, s.nombre as sector_nombre, s.icono 
                FROM Politicas_Procedimientos p 
                JOIN Sectores s ON p.sector_id = s.id 
                WHERE p.estado = 'activo'";
        
        // Aplicar filtros si existen
        if (isset($filtros['tipo']) && !empty($filtros['tipo'])) {
            $sql .= " AND p.tipo = '".$filtros['tipo']."'";
        }
        
        if (isset($filtros['sector_id']) && !empty($filtros['sector_id'])) {
            $sql .= " AND p.sector_id = ".$filtros['sector_id'];
        }
        
        if (isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $sql .= " AND (p.titulo LIKE '%$busqueda%' OR p.descripcion LIKE '%$busqueda%' OR p.tags LIKE '%$busqueda%')";
        }
        
        $sql .= " ORDER BY p.fecha_actualizacion DESC";
        
        return $this->retornarArray($sql);
    }
    
    /**
     * Obtiene un documento específico por su ID
     * @param int $id ID del documento
     * @param bool $incrementarVista Si es true, incrementa el contador de vistas
     * @return array|bool El documento encontrado o false
     */
    public function obtenerPorId($id, $incrementarVista = false) {
        $sql = "SELECT p.*, s.nombre as sector_nombre, s.icono 
                FROM Politicas_Procedimientos p 
                JOIN Sectores s ON p.sector_id = s.id 
                WHERE p.id = $id";
        
        $resultado = $this->retornarArray($sql);
        
        if (count($resultado) > 0) {
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
     * Actualiza un documento existente
     */
    public function actualizarDocumento($id, $datos, $archivo = null) {
        // Validar existencia del documento
        $documento = $this->obtenerPorId($id);
        if (!$documento) {
            return [
                'status' => 'error',
                'message' => 'El documento no existe'
            ];
        }
        
        // Iniciar la consulta SQL para actualización
        $sql = "UPDATE Politicas_Procedimientos SET 
                fecha_actualizacion = GETDATE()";
        
        // Actualizar campos si están presentes
        if (isset($datos['version']) && !empty($datos['version'])) {
            $sql .= ", version = '".$this->escaparTexto($datos['version'])."'";
        }
        
        if (isset($datos['descripcion'])) {
            $sql .= ", descripcion = '".$this->escaparTexto($datos['descripcion'])."'";
        }
        
        if (isset($datos['tags'])) {
            $sql .= ", tags = '".$this->escaparTexto($datos['tags'])."'";
        }
        
        // Si hay un archivo nuevo, procesarlo
        if ($archivo && !empty($archivo['name'])) {
            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            
            // Comprobar que es un PDF
            if (strtolower($extension) !== 'pdf') {
                return [
                    'status' => 'error',
                    'message' => 'Solo se permiten archivos PDF'
                ];
            }
            
            // Generar nuevo nombre y ruta
            $titulo = $documento['titulo']; // Mantener el título original
            $nuevo_nombre = $this->generarNombreArchivo($titulo, $extension);
            $ruta_completa = $this->upload_dir . $nuevo_nombre;
            
            // Mover el archivo nuevo
            if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                // Eliminar archivo anterior si existe
                if (file_exists($documento['ruta_archivo'])) {
                    unlink($documento['ruta_archivo']);
                }
                
                // Actualizar campos en la base de datos
                $sql .= ", archivo_nombre = '$nuevo_nombre', ruta_archivo = '$ruta_completa'";
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Error al subir el archivo'
                ];
            }
        }
        
        // Completar la consulta
        $sql .= " WHERE id = $id";
        
        // Ejecutar la actualización
        $resultado = $this->ejecutarSQL($sql);
        
        // Comprobar si se actualizó correctamente
        if ($resultado['status'] === 'success') {
            // Opcional: Registrar la actualización en un historial
            $this->registrarHistorialVersion($id, $datos['version'] ?? '1.0', $datos['descripcion'] ?? '');
            
            // ========== INTEGRACIÓN RAG: Notificar documento actualizado ==========
            // Esta integración no afecta el funcionamiento normal del sistema
            try {
                require_once dirname(__FILE__) . '/RagWebhook.php';
                $ragWebhook = new RagWebhook();
                
                // Obtener datos completos actualizados del documento
                $documento_actualizado = $this->obtenerPorId($id);
                if ($documento_actualizado) {
                    $ragWebhook->notificarDocumentoActualizado($id, $documento_actualizado);
                }
            } catch (Exception $e) {
                // Si hay error en el webhook, no afecta la operación principal
                error_log("Error en webhook RAG (actualización): " . $e->getMessage());
            }
            // ======================================================================
        }
        
        return $resultado;
    }
    
    /**
     * Elimina un documento (marcándolo como inactivo)
     */
    public function eliminarDocumento($id) {
        $sql = "UPDATE Politicas_Procedimientos SET 
                estado = 'inactivo', 
                fecha_actualizacion = GETDATE() 
                WHERE id = $id";
        
        $resultado = $this->ejecutarSQL($sql);
        
        // ========== INTEGRACIÓN RAG: Notificar documento eliminado ==========
        if ($resultado['status'] === 'success') {
            try {
                require_once dirname(__FILE__) . '/RagWebhook.php';
                $ragWebhook = new RagWebhook();
                $ragWebhook->notificarDocumentoEliminado($id);
            } catch (Exception $e) {
                error_log("Error en webhook RAG (eliminación): " . $e->getMessage());
            }
        }
        // ====================================================================
        
        return $resultado;
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
        $sql = "SELECT * FROM Sectores WHERE id = $id";
        $resultado = $this->retornarArray($sql);
        
        if (count($resultado) > 0) {
            return $resultado[0];
        }
        
        return false;
    }
    
    /**
     * Guarda un nuevo sector
     */
    public function guardarSector($datos) {
        if (empty($datos['nombre'])) {
            return [
                'status' => 'error',
                'message' => 'El nombre del sector es obligatorio'
            ];
        }
        
        $descripcion = isset($datos['descripcion']) ? $datos['descripcion'] : '';
        $icono = isset($datos['icono']) ? $datos['icono'] : 'fa-folder';
        
        $sql = "INSERT INTO Sectores (nombre, descripcion, icono) 
                VALUES ('".$this->escaparTexto($datos['nombre'])."', 
                        '".$this->escaparTexto($descripcion)."', 
                        '$icono')";
        
        return $this->ejecutarSQL($sql);
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
     * Guarda un nuevo término en el glosario
     */
    public function guardarTerminoGlosario($datos) {
        if (empty($datos['termino']) || empty($datos['definicion'])) {
            return [
                'status' => 'error',
                'message' => 'El término y la definición son obligatorios'
            ];
        }
        
        $sector_id = isset($datos['sector_id']) && !empty($datos['sector_id']) ? $datos['sector_id'] : "NULL";
        
        $sql = "INSERT INTO Glosario (termino, definicion, sector_id) 
                VALUES ('".$this->escaparTexto($datos['termino'])."', 
                        '".$this->escaparTexto($datos['definicion'])."', 
                        $sector_id)";
        
        return $this->ejecutarSQL($sql);
    }
    
    /**
     * Registra acceso a un documento
     */
    public function registrarAcceso($documento_id, $tipo_acceso, $usuario_id = null) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $usuario_id = $usuario_id ? $usuario_id : "NULL";
        
        $sql = "INSERT INTO Accesos_Documentos (documento_id, usuario_id, tipo_acceso, ip_acceso) 
                VALUES ($documento_id, $usuario_id, '$tipo_acceso', '$ip')";
        
        $this->ejecutarSQL($sql);
        
        // Incrementar contador específico
        $this->incrementarContador($documento_id, $tipo_acceso);
    }
    
    /**
     * Incrementa el contador de vistas o descargas
     */
    private function incrementarContador($documento_id, $tipo) {
        $campo = $tipo === 'descarga' ? 'descargas' : 'vistas';
        
        $sql = "UPDATE Politicas_Procedimientos 
                SET $campo = $campo + 1 
                WHERE id = $documento_id";
        
        $this->ejecutarSQL($sql);
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
     * Registra una entrada en el historial de versiones
     */
    private function registrarHistorialVersion($documento_id, $version, $descripcion = '') {
        $usuario = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Sistema';
        
        $sql = "INSERT INTO Historial_Versiones (documento_id, version, descripcion, usuario, fecha) 
                VALUES ($documento_id, '".$this->escaparTexto($version)."', 
                '".$this->escaparTexto($descripcion)."', '".$this->escaparTexto($usuario)."', GETDATE())";
        
        return $this->ejecutarSQL($sql);
    }

    /**
     * Obtiene el historial de versiones de un documento
     */
    public function obtenerHistorialVersiones($documento_id) {
        $sql = "SELECT id, documento_id, version, descripcion, fecha, usuario 
                FROM Historial_Versiones 
                WHERE documento_id = $documento_id 
                ORDER BY fecha DESC";
        
        $historial = $this->retornarArray($sql);
        
        // Formatear fechas
        foreach ($historial as &$version) {
            if (isset($version['fecha'])) {
                if ($version['fecha'] instanceof DateTime) {
                    // Si es un objeto DateTime, formatearlo directamente
                    $version['fecha'] = $version['fecha']->format('d/m/Y H:i');
                } elseif (is_object($version['fecha']) && isset($version['fecha']->date)) {
                    // Si es un objeto con propiedad 'date', convertirlo
                    $version['fecha'] = date('d/m/Y H:i', strtotime($version['fecha']->date));
                } elseif (is_string($version['fecha'])) {
                    // Si ya es una cadena, formatearla normalmente
                    $version['fecha'] = date('d/m/Y H:i', strtotime($version['fecha']));
                }
            }
        }
        
        return $historial;
    }

}