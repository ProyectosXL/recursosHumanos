<?php

class Email {
    private $to;
    private $subject;
    private $htmlContent;
    private $apiUrl;

    public function __construct($to, $subject, $htmlContent) {
        $this->to = $to;
        $this->subject = $subject;
        $this->htmlContent = $htmlContent;
        $this->apiUrl = "https://app.xl.com.ar/api/email/";
    }

 
    public function getEmailData() {
        return [
            "to" => $this->to,
            "subject" => $this->subject,
            "html" => $this->htmlContent,
            "emailType" => "Anticipos",
        ];
    }


    public function sendEmail() {
        $data = json_encode($this->getEmailData());

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Content-Length: " . strlen($data)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            echo "Email enviado con éxito a: " . $this->to . "\n";
        } else {
            echo "Error al enviar email a: " . $this->to . " | Código HTTP: $httpCode\n";
            echo "Respuesta de la API: $response\n";
        }
    }

    // Métodos estáticos para obtener templates de email
    public static function getSubjectAnticipos() {
        $mes = date('F');
        $anio = date('Y');
        return "Apertura: Período de Anticipos de Sueldo - $mes $anio";
    }

    public static function getSubjectAnticiposCierre() {
        $mes = date('F');
        $anio = date('Y');
        return "ÚLTIMO DÍA: Cierre Solicitudes de Anticipos - $mes $anio";
    }

    public static function getHtmlAnticipos() {
        return "<!DOCTYPE html> <html lang='es'> 
        <head>
            <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>
                        Apertura del Período de Anticipos
                    </title>
                    <style> 
                        body 
                        { 
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            line-height: 1.6;
                             color: #333;
                            background-color: #f9f9f9;
                            margin: 0; padding: 0; 
                        } 
                        .email-container {
                            max-width: 600px;
                            margin: 0 auto;
                            background-color: #ffffff;
                            border-radius: 8px;
                            overflow: hidden;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                        } 
                        .email-header {
                            background-color: #3498db;
                            color: white;
                            padding: 20px;
                            text-align: center;
                        } 
                        .email-body { 
                            padding: 30px;
                        } 
                        .email-footer { 
                            background-color: #f5f5f5;
                            padding: 15px;
                            text-align: center;
                            font-size: 14px;
                            color: #777;
                        } 
                        .btn-primary { 
                            background-color: #3498db;
                            border-color: #3498db;
                            padding: 10px 20px;
                            font-weight: 500;
                            border-radius: 4px;
                            text-decoration: none;
                            display: inline-block;
                            margin-top: 15px;
                            color: white;
                        } 
                        .btn-primary:hover { 
                            background-color: #2980b9;
                            border-color: #2980b9;
                        } 
                        .highlight {
                            background-color: #f8f4e5; padding: 15px; border-left: 4px solid #3498db; margin: 20px 0; border-radius: 4px; } .icon { font-size: 48px; margin-bottom: 15px; text-align: center; } @media only screen and (max-width: 600px) { .email-body { padding: 20px; } } </style> </head> <body> <div class='email-container'> <div class='email-header'> <h1>Solicitud de Anticipos</h1> </div> <div class='email-body'> <div class='text-center mb-4'> <div class='icon'>📅</div> <h2>¡Período de Solicitud Abierto!</h2> </div> <p>Buen día,</p> <div class='highlight'> <p>Les comunicamos que ya se encuentra <strong>habilitado el período para solicitar anticipos de sueldo</strong>.</p> </div> <div class='mt-4'> <p>Recuerde que:</p> <ul> <li>Debe completar todos los campos requeridos</li> <li>La aprobación está sujeta a revisión</li> <li>Consulte las políticas de anticipos vigentes</li> </ul> </div> <p>Si tiene alguna consulta, no dude en contactar al departamento de Recursos Humanos.</p> <p>Gracias!<br>Saludos.-</p> </div> <div class='email-footer'> <p>Este es un correo automático. Por favor, no responda a este mensaje.</p> <p>&copy; 2025 Portal de Anticipos</p> </div> </div> </body> </html>";
    }

    public static function getHtmlAnticiposCierre() {
        return "<!DOCTYPE html> <html lang='es'> <head> <meta charset='UTF-8'> <meta name='viewport' content='width=device-width, initial-scale=1.0'> <title>Cierre del Período de Anticipos</title> <link href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css' rel='stylesheet'> <style> body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f9f9f9; margin: 0; padding: 0; } .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); } .email-header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; } .email-body { padding: 30px; } .email-footer { background-color: #f5f5f5; padding: 15px; text-align: center; font-size: 14px; color: #777; } .btn-danger { background-color: #e74c3c; border-color: #e74c3c; padding: 10px 20px; font-weight: 500; border-radius: 4px; text-decoration: none; display: inline-block; margin-top: 15px; color: white; } .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; } .alert { background-color: #fdecea; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0; border-radius: 4px; } .icon { font-size: 48px; margin-bottom: 15px; } .countdown { font-size: 24px; font-weight: bold; color: #e74c3c; margin: 15px 0; } @media only screen and (max-width: 600px) { .email-body { padding: 20px; } } </style> </head> <body> <div class='email-container'> <div class='email-header'> <h1>Solicitud de Anticipos</h1> </div> <div class='email-body'> <div class='text-center mb-4'> <div class='icon'>⏰</div> <h2>¡Último Día para Solicitar!</h2> </div> <p>Buen día,</p> <div class='alert'> <p>Les comunicamos que <strong>hoy es la fecha límite para solicitar anticipos de sueldo</strong>.</p> </div> <div class='text-center'> <div class='countdown'>ÚLTIMO DÍA</div> </div> <div class='mt-4'> <p>Recuerde que:</p> <ul> <li>Las solicitudes se cierran a las 15:00 hrs.</li> <li>No se procesarán solicitudes fuera del plazo establecido</li> </ul> </div> <p>Si tiene alguna consulta, no dude en contactar al departamento de Recursos Humanos.</p> <p>Gracias!<br>Saludos.-</p> </div> <div class='email-footer'> <p>Este es un correo automático. Por favor, no responda a este mensaje.</p> <p>&copy; 2025 Portal de Anticipos</p> </div> </div> <script src='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js'></script> <script> document.addEventListener('DOMContentLoaded', function() { const emailContainer = document.querySelector('.email-container'); emailContainer.style.opacity = '0'; emailContainer.style.transform = 'translateY(20px)'; emailContainer.style.transition = 'opacity 0.5s ease, transform 0.5s ease'; setTimeout(function() { emailContainer.style.opacity = '1'; emailContainer.style.transform = 'translateY(0)'; }, 100); const countdown = document.querySelector('.countdown'); setInterval(function() { countdown.style.opacity = (countdown.style.opacity === '0.6' ? '1' : '0.6'); }, 800); }); </script> </body> </html>";
    }
}
?>
