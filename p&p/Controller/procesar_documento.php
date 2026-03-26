
<?php
// Iniciar sesión para gestión de usuarios (si se implementa)
session_start();

// Incluir la clase Politica
require_once '../Class/Politica.php';

// Crear instancia de la clase
$politicaObj = new Politica();

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificar que se haya enviado un archivo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        
        // Recopilar datos del formulario
        $datos = [
            'titulo' => isset($_POST['titulo']) ? $_POST['titulo'] : '',
            'tipo' => isset($_POST['tipo']) ? $_POST['tipo'] : 'politica',
            'sector_id' => isset($_POST['sector_id']) ? $_POST['sector_id'] : '',
            'descripcion' => isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
            'tags' => isset($_POST['tags']) ? $_POST['tags'] : '',
            'creado_por' => isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Sistema'
        ];
        
        // Guardar el documento
        $resultado = $politicaObj->guardarDocumento($datos, $_FILES['archivo']);
        
        // Redireccionar con mensaje de resultado
        if ($resultado['status'] === 'success') {
            // Usar ruta relativa en lugar de absoluta para la redirección
            // Agregar parámetro para mostrar modal de tags automáticos
            header('Location: ../index.php?mensaje=success&texto=Documento guardado correctamente&auto_tags=1');
        } else {
            header('Location: ../index.php?mensaje=error&texto=' . urlencode($resultado['message']));
        }
        
    } else {
        // Error con el archivo
        $error = '';
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
        
        header('Location: ../index.php?mensaje=error&texto=' . urlencode($error));
    }
    
} else {
    // No es una solicitud POST
    header('Location: ../index.php');
}
exit;