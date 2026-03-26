<?php
require_once 'D:\htdocs\administracion\recursosHumanos\Class\Anticipo.php';
require_once 'D:\htdocs\administracion\recursosHumanos\Class\Email.php';

function iniciarEnvioAutomatico($periodoActual) {
    
    $anticipo = new Anticipo();
    $fechas = $anticipo->getFechasPorPeriodo($periodoActual);
    $emails = $anticipo->getEmails($periodoActual);

    $fechaHoy = date('Y-m-d');
    
    if ($fechaHoy == $fechas['fechaDesde']) {
        enviarEmailInicio($emails);
    }

    if ($fechaHoy == $fechas['fechaHasta']) {
        enviarEmailCierre($emails);
    }
    if($fechaHoy != $fechas['fechaDesde'] && $fechaHoy != $fechas['fechaHasta']){
        echo "No es fecha de envío de emails. Fecha actual: $fechaHoy\n";
        // dejar un log en un txt como registro de que se intento ejecutar el script en la ruta (C:\xampp\fulogs)
        file_put_contents('C:\xampp\fulogs\log.txt', "Intento de ejecución en fecha no válida: $fechaHoy\n", FILE_APPEND);
  
    }

}

function enviarEmailInicio($emails) {
    foreach ($emails as $email) {
       
        $emailObj = new Email(
            $email, 
            Email::getSubjectAnticipos(), 
            Email::getHtmlAnticipos()
        );


        $emailObj->sendEmail();
    }
}

function enviarEmailCierre($emails) {

    foreach ($emails as $email) {
        $emailObj = new Email(
            $email, 
            Email::getSubjectAnticiposCierre(), 
            Email::getHtmlAnticiposCierre()
        );

        $emailObj->sendEmail();
    }

}
?>
