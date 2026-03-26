<?php
// enviar_reporte_excedidos.php

// Cargar configuración y clases
require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

// Cargar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require ROOT_PATH . '../vendor/autoload.php';

// 1. DETERMINAR FECHAS Y LÍMITE
$fechaPrimerDiaMesAnterior = date('Y-m-01', strtotime('first day of last month'));
$fechaUltimoDiaMesAnterior = date('Y-m-t', strtotime('last day of last month'));
$mesAnterior = date('n', strtotime('last month')); // Mes en número (1-12)

// Límite de bonificaciones
$limite = ($mesAnterior == 10 || $mesAnterior == 12) ? 5 : 3;

// 2. OBTENER DATOS DE LA BASE DE DATOS
try {
    $clienteModel = new Cliente();
    $empleadosExcedidos = $clienteModel->obtenerEmpleadosExcedidos($fechaPrimerDiaMesAnterior, $fechaUltimoDiaMesAnterior, $limite);
} catch (Exception $e) {
    // Si hay un error de base de datos, lo registramos y salimos
    error_log("Error al obtener empleados excedidos: " . $e->getMessage());
    die("Error de base de datos.");
}

// 3. CONSTRUIR EL CUERPO DEL CORREO EN HTML
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain.1252');
$nombreMes = strftime('%B de %Y', strtotime($fechaPrimerDiaMesAnterior)); // Nombre del mes en español
$htmlBody = "
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; }
        th { background-color: #343a40; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Reporte de Empleados con Límite de Bonificaciones Excedido</h1>
    <p>Este reporte corresponde al mes de <strong>" . ucwords($nombreMes) . "</strong>.</p>
    <p>El límite de bonificaciones para este mes fue de <strong>{$limite}</strong> artículos.</p>
";

if (empty($empleadosExcedidos)) {
    $htmlBody .= "<p><strong>¡Buenas noticias!</strong> Ningún empleado excedió el límite de bonificaciones durante este período.</p>";
} else {
    $htmlBody .= "
    <table>
        <thead>
            <tr>
                <th>Código Cliente</th>
                <th>Nombre</th>
                <th style='text-align:center;'>Cant. Bonificaciones</th>
                <th style='text-align:right;'>Total Bonificado ($)</th>
                <th style='text-align:right;'>Promedio Descuento (%)</th>
            </tr>
        </thead>
        <tbody>";

    foreach ($empleadosExcedidos as $empleado) {
        $nombre = htmlspecialchars(mb_convert_encoding($empleado->NOMBRE_CLI ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8');
        $totalBonificado = number_format((float)($empleado->TotalBonificado ?? 0), 2, ',', '.');
        $promedioPorcentaje = number_format((float)($empleado->PromedioPorcentaje ?? 0), 2, ',', '.');
        
        $htmlBody .= "
            <tr>
                <td>{$empleado->COD_CLIENT}</td>
                <td>{$nombre}</td>
                <td style='text-align:center;'>{$empleado->CantidadBonificaciones}</td>
                <td style='text-align:right;'>{$totalBonificado}</td>
                <td style='text-align:right;'>{$promedioPorcentaje}%</td>
            </tr>";
    }

    $htmlBody .= "</tbody></table>";
}

$htmlBody .= "
    <p style='font-size: 0.8em; color: #777;'>Este es un correo generado automáticamente. Por favor, no responda a este mensaje.</p>
</body>
</html>";

// 4. ENVIAR EL CORREO
$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->CharSet    = 'UTF-8';

// Destinatarios
$mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
// $mail->addAddress(MAIL_TO_ADDRESS); // ESTA LÍNEA SE COMENTA O ELIMINA

// Bucle para añadir múltiples destinatarios
$destinatarios = explode(',', MAIL_TO_ADDRESS);
foreach ($destinatarios as $destinatario) {
    if (!empty(trim($destinatario))) {
        $mail->addAddress(trim($destinatario));
    }
}

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = 'Reporte Mensual - Empleados con Límite Excedido (' . ucwords($nombreMes) . ')';
    $mail->Body    = $htmlBody;

    $mail->send();
    echo 'El correo de reporte ha sido enviado exitosamente.';
} catch (Exception $e) {
    echo "El mensaje no pudo ser enviado. Error de Mailer: {$mail->ErrorInfo}";
    error_log("Error al enviar correo de reporte: " . $mail->ErrorInfo);
}

?>