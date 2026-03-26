
<?php
// Iniciar sesión para gestión de usuarios (si se implementa)
session_start();

// Incluir la clase Politica
require_once '../Class/Politica.php';

// Crear instancia de la clase
$politicaObj = new Politica();

try {
    // Verificar que se ha proporcionado un ID
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        
        $documento_id = (int)$_GET['id'];
        
        // Obtener información del documento sin incrementar vista (parámetro en false)
        $documento = $politicaObj->obtenerPorId($documento_id, false);
        
        if ($documento) {
            // Registrar la descarga (esto incrementa el contador de descargas)
            $usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;
            $politicaObj->registrarAcceso($documento_id, 'descarga', $usuario_id);
            
            // Verificar que el archivo existe y que la ruta es correcta
            $ruta_archivo = $documento['ruta_archivo'];
            
            // Si la ruta no existe pero tenemos el nombre del archivo, intentar construir la ruta
            if (!file_exists($ruta_archivo) && isset($documento['archivo_nombre'])) {
                $ruta_alternativa = dirname(__FILE__, 2) . '/documentos/' . $documento['archivo_nombre'];
                if (file_exists($ruta_alternativa)) {
                    $ruta_archivo = $ruta_alternativa;
                }
            }
            
            if (file_exists($ruta_archivo)) {
                // Obtener el tamaño real del archivo
                $filesize = filesize($ruta_archivo);
                
                // Limpiar todos los búferes de salida
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Configurar cabeceras para la descarga
                header('Content-Description: File Transfer');
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $documento['archivo_nombre'] . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . $filesize);
                
                // Leer el archivo y enviarlo al navegador
                readfile($ruta_archivo);
                exit;
                
            } else {
                // El archivo no existe - registrar error
                error_log("Error en descarga: archivo no encontrado - Ruta: " . $ruta_archivo);
                header('Location: ../index.php?mensaje=error&texto=El archivo no existe en el servidor');
            }
            
        } else {
            // Documento no encontrado
            header('Location: ../index.php?mensaje=error&texto=Documento no encontrado');
        }
        
    } else {
        // ID no proporcionado
        header('Location: ../index.php?mensaje=error&texto=ID de documento no especificado');
    }
} catch (Exception $e) {
    // Registrar error
    error_log("Error en descarga: " . $e->getMessage());
    header('Location: ../index.php?mensaje=error&texto=Error al procesar la descarga: ' . $e->getMessage());
}
exit;