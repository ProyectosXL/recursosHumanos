<?php
// exportar_excel_descuentos.php

// Incluimos la configuración y el autoloader de Composer
require_once 'config.php';
require_once ROOT_PATH . '../vendor/autoload.php';

require_once ROOT_PATH . 'Class/Cliente.php';

// Usamos las clases de la librería PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 1. OBTENER LOS FILTROS DE LA URL
$scope = $_GET['scope'] ?? 'empleados';
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Determinar el tipo de cliente basado en el scope
$tipoCliente = ($scope === 'directores') ? 'AS' : 'E';

try {
    // 2. EJECUTAR LA CONSULTA PARA OBTENER LOS DATOS
    $clienteModel = new Cliente();
    $resultados = $clienteModel->obtenerResumenDescuentos($tipoCliente, $fechaDesde, $fechaHasta);

    // 3. CREAR EL ARCHIVO EXCEL EN MEMORIA
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte de Descuentos');

    // 4. ESCRIBIR LOS ENCABEZADOS DE LAS COLUMNAS
    $sheet->setCellValue('A1', 'Código Cliente');
    $sheet->setCellValue('B1', 'Nombre');
    $sheet->setCellValue('C1', 'Cantidad de Bonificaciones');
    $sheet->setCellValue('D1', 'Total Bonificado ($)');
    $sheet->setCellValue('E1', 'Promedio Descuento (%)');

    // Aplicar estilo a los encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '343a40']]
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // 5. ESCRIBIR LOS DATOS EN EL EXCEL
    $row = 2; // Empezamos en la fila 2
    foreach ($resultados as $res) {
        $sheet->setCellValue('A' . $row, mb_convert_encoding($res->COD_CLIENT ?? '', 'UTF-8', 'ISO-8859-1'));
        $sheet->setCellValue('B' . $row, mb_convert_encoding($res->NOMBRE_CLI ?? '', 'UTF-8', 'ISO-8859-1'));
        $sheet->setCellValue('C' . $row, $res->CantidadBonificaciones ?? 0);
        $sheet->setCellValue('D' . $row, (float)($res->TotalBonificado ?? 0));
        $sheet->setCellValue('E' . $row, (float)($res->PromedioPorcentaje ?? 0));
        
        // Aplicar formato de moneda y porcentaje a las celdas
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('$ #,##0.00');
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('0.00"%"');

        $row++;
    }

    // Ajustar el ancho de las columnas automáticamente
    foreach (range('A', 'E') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. PREPARAR Y ENVIAR EL ARCHIVO AL NAVEGADOR PARA SU DESCARGA
    $writer = new Xlsx($spreadsheet);

    $fechaActual = date('Y-m-d');
    $fileName = "Reporte_Descuentos_{$scope}_{$fechaActual}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
    $writer->save('php://output');
    
    exit();

} catch (Exception $e) {
    // Manejo de errores
    die("Ocurrió un error al generar el reporte: " . $e->getMessage());
}