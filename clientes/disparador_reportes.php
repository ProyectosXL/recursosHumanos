<?php
// disparador_reportes.php

function ejecutarReportesMensuales() {
    $archivoRegistro = __DIR__ . '/ultimo_reporte.txt';
    $mesAnioActual = date('Y-m');
    $diaActual = date('j'); // Día del mes sin ceros iniciales (1-31)

    // Solo procedemos si es el primer día del mes.
    // OJO: Si nadie visita la web el día 1, esto no se ejecutará hasta la primera visita del día 2, 3, etc.
    if ($diaActual < 1) { // Puedes cambiar esto a 2 o 3 para dar un margen de días.
        //return; // Descomentar esta línea si quieres que SÓLO se ejecute el día 1.
    }

    // Leemos cuándo se envió el último reporte
    $ultimoReporteEnviado = file_exists($archivoRegistro) ? file_get_contents($archivoRegistro) : null;

    // Si el reporte de este mes ya fue enviado, no hacemos nada.
    if ($ultimoReporteEnviado === $mesAnioActual) {
        return;
    }

    // --- Si llegamos aquí, es hora de enviar los reportes ---

    // Incluir y ejecutar el script de reporte de empleados
    // Usamos @ para suprimir la salida de "echo" y evitar que se muestre en la página del usuario.
    @include_once __DIR__ . '/enviar_reporte_excedidos.php';

    // Incluir y ejecutar el script de reporte de referidos
    @include_once __DIR__ . '/enviar_reporte_referidos.php';
    
    // Actualizamos el archivo de registro con el mes y año actual.
    file_put_contents($archivoRegistro, $mesAnioActual);
}

// Llamamos a la función
ejecutarReportesMensuales();