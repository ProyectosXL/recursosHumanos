
<?php
// Iniciar sesión para gestión de usuarios (si se implementa)
session_start();

// Incluir la clase Politica
require_once '../Class/Politica.php';

// Crear instancia de la clase
$politicaObj = new Politica();

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar que se haya proporcionado un ID de documento
    if (isset($_POST['documento_id']) && !empty($_POST['documento_id'])) {
        $documento_id = (int)$_POST['documento_id'];
        
        // Verificar que se haya enviado un archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            
            // Recopilar datos para la actualización
            $datos = [
                'version' => isset($_POST['version']) ? $_POST['version'] : '1.0',
                'descripcion' => isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
                'tags' => isset($_POST['tags']) ? $_POST['tags'] : ''
            ];
            
            // Actualizar el documento
            $resultado = $politicaObj->actualizarDocumento($documento_id, $datos, $_FILES['archivo']);
            
            // Redireccionar con mensaje de resultado
            if ($resultado['status'] === 'success') {
                header('Location: ../index.php?mensaje=success&texto=Documento actualizado correctamente&tab=update');
            } else {
                header('Location: ../index.php?mensaje=error&texto=' . urlencode($resultado['message']) . '&tab=update');
            }
            
        } else {
            // Error con el archivo
            $error = '';
            if (isset($_FILES['archivo'])) {
                switch ($_FILES['archivo']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = 'El archivo excede el tamaño máximo permitido';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = 'El archivo se cargó parcialmente';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error = 'No se seleccionó ningún archivo';
                        break;
                    default:
                        $error = 'Error desconocido al subir el archivo';
                }
            } else {
                $error = 'No se recibió el archivo';
            }
            
            header('Location: ../index.php?mensaje=error&texto=' . urlencode($error) . '&tab=update');
        }
        
    } else {
        // ID de documento no proporcionado
        header('Location: ../index.php?mensaje=error&texto=Documento no especificado&tab=update');
    }
    
} else {
    // No es una solicitud POST
    header('Location: ../index.php');
}
exit;