<?php
// Acceso: login del hub + nivel Ver
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::PAGINA);

// Incluir la clase Politica
require_once __DIR__ . '/../Class/Politica.php';

// Crear instancia de la clase
$politicaObj = new Politica();

try {
    // Verificar que se ha proporcionado un ID
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        
        $documento_id = (int)$_GET['id'];
        
        // Obtener información del documento sin incrementar vista (parámetro en false)
        // Los documentos dados de baja solo los ve Admin
        $documento = $politicaObj->obtenerPorId($documento_id, false, !AccesoDocuGest::esAdmin());
        
        if ($documento) {
            // Registrar la descarga (esto incrementa el contador de descargas)
            $politicaObj->registrarAcceso($documento_id, 'descarga', AccesoDocuGest::usuarioId());
            
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