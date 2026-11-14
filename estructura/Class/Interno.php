
<?php
/**
 * Estructura XL - Internos (Directorio de Personal)
 *
 * Migrado tal cual desde comercial/recepcion/Class/interno.php. Sólo cambian:
 *   - la conexión: la de recursosHumanos (Class/conexion.php, conectar('central')), porque la
 *     tabla RO_T_INTERNOS_PERSONAL está en la base central y este repo ya tiene su conexión;
 *   - la carpeta de fotos: sale de ESTRUCTURA_FOTOS_DIR (Class/rutas.php), no de una ruta relativa.
 * Las personas se siguen identificando por nombre_apellido, como hoy: el ABM no cambia.
 */
require_once __DIR__ . '/rutas.php';

class Interno {
    private $cid;
    private $cid_central;

    function __construct() {
        require_once ESTRUCTURA_RRHH_CLASS_DIR . '/conexion.php';
        $this->cid = new Conexion();
        $this->cid_central = $this->cid->conectar('central');
    }

    public function getConnection() {
        return $this->cid_central;
    }

    public function formatearNombreArchivo($nombre_apellido) {
        // Separar el nombre en palabras
        $palabras = explode(' ', trim($nombre_apellido));
        
        // Si hay menos de 2 palabras, retornar null
        if (count($palabras) < 2) {
            return null;
        }
    
        // Obtener el último nombre (apellido)
        $apellido = array_pop($palabras);
        // El resto son nombres
        $nombre = implode(' ', $palabras);
        
        // Intentar ambos formatos
        $formatos = [
            $apellido . ' ' . $nombre . '.jpg',  // Formato nuevo: "Apellido Nombre.jpg"
            str_replace(' ', '_', $nombre_apellido) . '.jpg'  // Formato anterior: "Nombre_Apellido.jpg"
        ];
        
        foreach ($formatos as $formato) {
            if (file_exists(ESTRUCTURA_FOTOS_DIR . '/' . $formato)) {
                return $formato;
            }
        }
        
        // Si no se encuentra ningún formato, retornar null
        return null;
    }
    

    public function getAllInternos() {
        try {
            // Primero actualizamos los registros que tienen INACTIVO en NULL
            $updateNullQuery = "UPDATE RO_T_INTERNOS_PERSONAL 
                               SET INACTIVO = 0 
                               WHERE INACTIVO IS NULL";
            sqlsrv_query($this->cid_central, $updateNullQuery);
    
            $query = "SELECT 
                        interno,
                        nombre_apellido,
                        sector,
                        mail,
                        celular,
                        ISNULL(INACTIVO, 0) as inactivo,
                        FECHA_MODIF
                     FROM RO_T_INTERNOS_PERSONAL 
                     ORDER BY nombre_apellido";
            
            $stmt = sqlsrv_query($this->cid_central, $query);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
    
            $results = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $results[] = array(
                    'interno' => trim($row['interno'] ?? ''),
                    'nombre_apellido' => trim($row['nombre_apellido'] ?? ''),
                    'sector' => trim($row['sector'] ?? ''),
                    'mail' => trim($row['mail'] ?? ''),
                    'celular' => trim($row['celular'] ?? ''),
                    'inactivo' => $row['inactivo'],
                    'fecha_modif' => $row['FECHA_MODIF'] ? $row['FECHA_MODIF']->format('Y-m-d H:i:s') : null
                );
            }
    
            return $results;
        } catch (Exception $e) {
            error_log("Error en getAllInternos: " . $e->getMessage());
            return array();
        }
    }
    
    public function getFotoPath($nombre_apellido) {
        if (empty($nombre_apellido)) return null;
        
        // Limpiar y formatear el nombre
        $nombre_limpio = trim($nombre_apellido);
        $foto = str_replace(' ', '_', $nombre_limpio) . '.jpg';
        $foto_path = ESTRUCTURA_FOTOS_DIR . '/' . $foto;
        
        error_log("Verificando foto para: " . $nombre_limpio);
        error_log("Ruta de la foto: " . $foto_path);
        
        // Verificar si existe la foto
        if (file_exists($foto_path)) {
            error_log("Foto encontrada: " . $foto);
            return $foto;
        }
        
        error_log("Foto no encontrada para: " . $nombre_limpio);
        return null;
    }

    public function getSectores() {
        try {
            $query = "SELECT DISTINCT sector 
                      FROM RO_T_INTERNOS_PERSONAL 

                      WHERE sector IS NOT NULL";

        if (isset($_SESSION['entorno']) && $_SESSION['entorno'] == 'sistemas') {
            $query .= " AND sector != 'Direccion'";
        }

        $query .= " ORDER BY sector";

            
            $stmt = sqlsrv_query($this->cid_central, $query);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
    
            $sectores = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $sectores[] = trim($row['sector']);
            }
    
            return $sectores;
    
        } catch (Exception $e) {
            error_log("Error en getSectores: " . $e->getMessage());
            return [];
        }
    }

    public function buscarInternos($busqueda, $sector = null) {
        try {
            $params = array();
            $query = "SELECT 
                        ISNULL(nombre_apellido, '') as nombre_apellido,
                        ISNULL(sector, '') as sector,
                        ISNULL(mail, '') as mail,
                        ISNULL(celular, '') as celular,
                        ISNULL(interno, '') as interno,
                        ISNULL(INACTIVO, 0) as inactivo,
                        FECHA_MODIF
                      FROM RO_T_INTERNOS_PERSONAL 
                      WHERE nombre_apellido LIKE ? AND INACTIVO = 0";
            $params[] = '%' . $busqueda . '%';
            
        
           if (isset($_SESSION['entorno']) && $_SESSION['entorno'] === 'sucursal') {
              $query .= " AND sector != 'Direccion'";
           }

            if ($sector) {
                $query .= " AND sector = ?";
                $params[] = $sector;
            }
            
            $query .= " ORDER BY nombre_apellido";
            
            $stmt = sqlsrv_query($this->cid_central, $query, $params);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
    
            $results = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Asegurarnos de que los valores nulos se conviertan a cadenas vacías
                $results[] = array(
                    'nombre_apellido' => trim($row['nombre_apellido']) ?: 'Sin Nombre',
                    'sector' => trim($row['sector']) ?: 'Sin Sector',
                    'mail' => trim($row['mail']) ?: '',
                    'celular' => trim($row['celular']) ?: '',
                    'interno' => trim($row['interno']) ?: '',
                    'inactivo' => $row['inactivo'],
                    'fecha_modif' => $row['FECHA_MODIF'] ? $row['FECHA_MODIF']->format('Y-m-d H:i:s') : null
                );
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error en buscarInternos: " . $e->getMessage());
            return array();
        }
    }

    public function getEmpleadosPorSector($sector) {
        try {
            $query = "SELECT COUNT(*) as count FROM RO_T_INTERNOS_PERSONAL WHERE sector = ? AND inactivo = 0";
            $params = array($sector);
            
            $stmt = sqlsrv_query($this->cid_central, $query, $params);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }

            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            return $row['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error en getEmpleadosPorSector: " . $e->getMessage());
            return 0;
        }
    }

    public function guardarEmpleado($datos) {
        try {
            error_log("Datos recibidos: " . print_r($datos, true));
    
            // Validar datos requeridos
            if (empty($datos['nombre_apellido']) || empty($datos['sector'])) {
                throw new Exception("Nombre y sector son requeridos");
            }
    
            // Limpiar y preparar datos
            $nombre_apellido = trim($datos['nombre_apellido']);
            $sector = trim($datos['sector']);
            $mail = !empty($datos['mail']) ? trim($datos['mail']) : null;
            $celular = !empty($datos['celular']) ? trim($datos['celular']) : null;
            $interno = !empty($datos['interno']) ? trim($datos['interno']) : null;
            $modoEdicion = isset($datos['modoEdicion']) && $datos['modoEdicion'] === '1';
            $nombreOriginal = !empty($datos['nombreApellidoOriginal']) ? trim($datos['nombreApellidoOriginal']) : null;
    
            // Debug log
            error_log("Ejecutando en modo: " . ($modoEdicion ? "EDICIÓN" : "NUEVO"));
            error_log("Nombre original: " . $nombreOriginal);
            error_log("Nombre actual: " . $nombre_apellido);
    
            // Para modo edición
            if ($modoEdicion) {
                $query = "UPDATE RO_T_INTERNOS_PERSONAL 
                         SET sector = ?, 
                             mail = ?, 
                             celular = ?,
                             interno = ?
                         WHERE nombre_apellido = ?";
                $params = array($sector, $mail, $celular, $interno, $nombreOriginal);
    
                error_log("Query de actualización: " . $query);
                error_log("Parámetros: " . print_r($params, true));
            } 
            // Para nuevo registro
            else {
                // Verificar duplicado solo para nuevos registros
                $checkQuery = "SELECT COUNT(*) as count FROM RO_T_INTERNOS_PERSONAL WHERE nombre_apellido = ?";
                $checkStmt = sqlsrv_query($this->cid_central, $checkQuery, array($nombre_apellido));
                
                if ($checkStmt === false) {
                    throw new Exception("Error al verificar duplicados: " . print_r(sqlsrv_errors(), true));
                }
                
                $row = sqlsrv_fetch_array($checkStmt);
                if ($row['count'] > 0) {
                    throw new Exception("Ya existe un empleado con este nombre y apellido");
                }
    
                $query = "INSERT INTO RO_T_INTERNOS_PERSONAL 
                         (interno, nombre_apellido, sector, mail, celular, inactivo, fecha_modif) 
                         VALUES (?, ?, ?, ?, ?, 0, GETDATE())";
                $params = array($interno, $nombre_apellido, $sector, $mail, $celular);
            }
    
            // Ejecutar la consulta
            $stmt = sqlsrv_query($this->cid_central, $query, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error SQL: " . print_r($errors, true));
                throw new Exception("Error en la consulta: " . ($errors[0]['message'] ?? 'Error desconocido'));
            }
    
            // Verificar que la operación afectó filas
            $rowsAffected = sqlsrv_rows_affected($stmt);
            error_log("Filas afectadas: " . $rowsAffected);
            
            if ($rowsAffected === false || $rowsAffected === 0) {
                throw new Exception("No se realizó ningún cambio en el registro");
            }
    
            return [
                'success' => true,
                'message' => $modoEdicion ? 'Registro actualizado exitosamente' : 'Registro guardado exitosamente'
            ];
    
        } catch (Exception $e) {
            error_log("Error en guardarEmpleado: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function toggleStatus($nombre_apellido, $status) {
        try {
            // Primero actualizamos los registros que tienen INACTIVO en NULL
            $updateNullQuery = "UPDATE RO_T_INTERNOS_PERSONAL 
                               SET INACTIVO = 0 
                               WHERE INACTIVO IS NULL";
            
            sqlsrv_query($this->cid_central, $updateNullQuery);
    
            // Ahora actualizamos el registro específico
            $query = "UPDATE RO_T_INTERNOS_PERSONAL 
                     SET INACTIVO = ?, 
                         FECHA_MODIF = GETDATE()
                     WHERE NOMBRE_APELLIDO = ?";
            
            $params = array($status, $nombre_apellido);
            
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            
            $stmt = sqlsrv_query($this->cid_central, $query, $params);
            
            if ($stmt === false) {
                throw new Exception("Error al actualizar el estado: " . print_r(sqlsrv_errors(), true));
            }
    
            // Verificar si se actualizó algún registro
            $rowsAffected = sqlsrv_rows_affected($stmt);
            if ($rowsAffected === false || $rowsAffected === 0) {
                throw new Exception("No se encontró el empleado o no se pudo actualizar");
            }
    
            return [
                'success' => true,
                'message' => 'Estado actualizado correctamente'
            ];
    
        } catch (Exception $e) {
            error_log("Error en toggleStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getEmpleado($nombre_apellido) {
        try {
            $query = "SELECT 
                        interno,
                        nombre_apellido,
                        sector,
                        mail,
                        celular,
                        ISNULL(INACTIVO, 0) as inactivo
                     FROM RO_T_INTERNOS_PERSONAL 
                     WHERE nombre_apellido = ?";
            
            $params = array($nombre_apellido);
            $stmt = sqlsrv_query($this->cid_central, $query, $params);
            
            if ($stmt === false) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
    
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($row) {
                return [
                    'success' => true,
                    'data' => [
                        'interno' => trim($row['interno'] ?? ''),
                        'nombre_apellido' => trim($row['nombre_apellido'] ?? ''),
                        'sector' => trim($row['sector'] ?? ''),
                        'mail' => trim($row['mail'] ?? ''),
                        'celular' => trim($row['celular'] ?? ''),
                        'inactivo' => $row['inactivo']
                    ]
                ];
            }
    
            throw new Exception("Empleado no encontrado");
    
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
}
