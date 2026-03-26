<?php
require_once "../Class/Horario.php";
$horario = new Horario();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];
    
    if ($newPassword === $confirmPassword) {

        $result = $horario->updatePassword($newPassword, $_GET['nroLegajo']);

        echo "<p style='color: green; text-align: center;'>Contraseña actualizada con éxito.</p>";

    } else {

        echo "<p style='color: red; text-align: center;'>Las contraseñas no coinciden. Inténtalo de nuevo.</p>";
    }
}

$empleado = $horario->traerVendedores($_GET['nroLegajo']);
$empleado = $empleado[0];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Contraseña</title>
    
    <?php require_once "../assets/css/css.php"; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        .password-container {
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 50%;
            background-color: #f9f9f9;
            text-align: center;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <h2>Cambiar Contraseña de <?= $empleado['APELLIDO'] ?> <?= $empleado['NOMBRE'] ?></h2>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="oldPassword">Contraseña Actual</label>
                <input type="text" name="oldPassword" id="oldPassword" class="form-control" value="<?= $empleado['CONTRASEÑA'] ?>" disabled>
            </div>
            <div class="form-group">
                <label for="newPassword">Nueva Contraseña</label>
                <input type="password" name="newPassword" id="newPassword" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirmar Contraseña</label>
                <input type="password" name="confirmPassword" id="confirmPassword" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
            <a href="editarEmpleado.php?nroLegajo=<?= $empleado['NRO_LEGAJO'] ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>

    <?php require_once "../assets/js/js.php"; ?>
</body>
</html>
