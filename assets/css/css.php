
<?php

// DEFINIR SERVER
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname($_SERVER['PHP_SELF']);

define('BASE_URL', $protocol.'://'. $host .'/administracion');

// RECORRER TODOS LOS ARCHIVOS CSS

// PRIMERO LAS LIBRERIAS

$folderPath2 = __DIR__.'/libs' ;
$files = scandir($folderPath2);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    // VALIDA QUE SEAN ARCHIVOS CSS
    if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {
        echo '<link rel="stylesheet" href='.BASE_URL.'/assets/css/libs/'.$file.'>';
    }
}





// SEGUNDO LOS CSS MANUALES

$folderPath = __DIR__ ;
$files = scandir($folderPath);


$archivoActual = str_replace('.php', '.css', basename($_SERVER['PHP_SELF']));

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    // VALIDA QUE SEAN ARCHIVOS CSS
    if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {

        if($file == $archivoActual){

            echo '<link rel="stylesheet" href='.BASE_URL.'/assets/css/'.$file.'>';
            
        }
    }
}


?>


<!-- CDN SI O SI -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css" >


<!-- ICONO DE XL -->
<link rel="icon" type="image/jpg" href="<?php echo BASE_URL.'/assets/images/logo.jpg';?>">

