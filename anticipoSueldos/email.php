<?php

if($_SERVER['DOCUMENT_ROOT'] == ''){
    $_SERVER['DOCUMENT_ROOT'] = 'D:/htdocs';
}
        
require_once "Controller/emailController.php";

$mes = date('n');
$anio = date('Y');
$periodo_actual = "$mes-$anio";


iniciarEnvioAutomatico($periodo_actual);
