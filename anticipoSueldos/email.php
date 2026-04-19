<?php

if(empty($_SERVER['DOCUMENT_ROOT'])){
    $_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
}
        
require_once "Controller/emailController.php";

$mes = date('n');
$anio = date('Y');
$periodo_actual = "$mes-$anio";


iniciarEnvioAutomatico($periodo_actual);
