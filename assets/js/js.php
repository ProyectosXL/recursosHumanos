<!-- CDN SI O SI -->
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
// EL SERVER YA VIENE DECLARADO DEL ARCHIVO CSS.PHP

// RECORRER TODOS LOS ARCHIVOS JS

// PRIMERO LAS LIBRERIAS

$folderPath2 = __DIR__.'/libs' ;
$files = scandir($folderPath2);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    // VALIDA QUE SEAN ARCHIVOS JS
    if (pathinfo($file, PATHINFO_EXTENSION) === 'js') {
        echo '<script src="'.BASE_URL.'/assets/js/libs/'.$file.'"></script>';
    }
}

// SEGUNDO LOS ARCHIVOS MANUALES

$folderPath = __DIR__ ;
$files = scandir($folderPath);

$archivoActual = str_replace('.php', '.js', basename($_SERVER['PHP_SELF']));

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }
    // VALIDA QUE SEAN ARCHIVOS JS
    if (pathinfo($file, PATHINFO_EXTENSION) === 'js') {

        if($file == $archivoActual){

            echo '<script src="'.BASE_URL.'/assets/js/'.$file.'"></script>';
            
        }
    }
}




?>




