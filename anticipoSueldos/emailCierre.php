
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre del Período de Anticipos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
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
            background-color: #e74c3c;
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
        .btn-danger {
            background-color: #e74c3c;
            border-color: #e74c3c;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        .alert {
            background-color: #fdecea;
            padding: 15px;
            border-left: 4px solid #e74c3c;
            margin: 20px 0;
            border-radius: 4px;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .countdown {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            margin: 15px 0;
        }
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Solicitud de Anticipos</h1>
        </div>
        <div class="email-body">
            <div class="text-center mb-4">
                <div class="icon">⏰</div>
                <h2>¡Último Día para Solicitar!</h2>
            </div>
            
            <p>Buen día,</p>
            
            <div class="alert">
                <p>Les comunicamos que <strong>hoy es la fecha límite para solicitar anticipos de sueldo</strong>.</p>
            </div>
            
            <div class="text-center">
                <div class="countdown">ÚLTIMO DÍA</div>
            </div>
            
            <div class="mt-4">
                <p>Recuerde que:</p>
                <ul>
                    <li>Las solicitudes se cierran a las 15:00 hrs.</li>
                    <li>No se procesarán solicitudes fuera del plazo establecido</li>
                </ul>
            </div>
            
            <p>Si tiene alguna consulta, no dude en contactar al departamento de Recursos Humanos.</p>
            
            <p>Gracias!<br>Saludos.-</p>
        </div>
        <div class="email-footer">
            <p>Este es un correo automático. Por favor, no responda a este mensaje.</p>
            <p>&copy; 2025 Portal de Anticipos</p>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Añadir animación de urgencia para el correo de cierre
        document.addEventListener('DOMContentLoaded', function() {
            const emailContainer = document.querySelector('.email-container');
            emailContainer.style.opacity = '0';
            emailContainer.style.transform = 'translateY(20px)';
            emailContainer.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            
            setTimeout(function() {
                emailContainer.style.opacity = '1';
                emailContainer.style.transform = 'translateY(0)';
            }, 100);
            
            // Animar el texto de último día
            const countdown = document.querySelector('.countdown');
            setInterval(function() {
                countdown.style.opacity = (countdown.style.opacity === '0.6' ? '1' : '0.6');
            }, 800);
        });
    </script>
</body>
</html>