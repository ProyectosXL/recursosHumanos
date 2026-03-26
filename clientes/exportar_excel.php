<?php
// exportar_excel.php

// Incluimos la configuración y el autoloader de Composer
require_once 'config.php';
require_once ROOT_PATH . '../vendor/autoload.php'; // Esencial para cargar PhpSpreadsheet

require_once ROOT_PATH . 'Class/Cliente.php';

// Usamos las clases de la librería PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 1. OBTENER LOS MISMOS FILTROS QUE USA consulta.php
$filtroTipo = $_GET['tipo'] ?? '';
$filtroEstado = $_GET['estado'] ?? '';

try {
    // 2. EJECUTAR LA MISMA CONSULTA PARA OBTENER LOS DATOS FILTRADOS
    $clienteModel = new Cliente();
    $filtros = ['tipo' => $filtroTipo, 'estado' => $filtroEstado];
    $clientes = $clienteModel->consultarClientes($filtros);

    // 3. CREAR EL ARCHIVO EXCEL EN MEMORIA
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Clientes');

    // 4. ESCRIBIR LOS ENCABEZADOS DE LAS COLUMNAS
    $sheet->setCellValue('A1', 'Código');
    $sheet->setCellValue('B1', 'Nombre');
    $sheet->setCellValue('C1', 'DNI / CUIT');
    $sheet->setCellValue('D1', '% Descuento');
    $sheet->setCellValue('E1', 'Fecha de Alta');
    $sheet->setCellValue('F1', 'Estado');

    // Aplicar estilo a los encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '343a40']]
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

    // 5. ESCRIBIR LOS DATOS DE LOS CLIENTES EN EL EXCEL
    $row = 2; // Empezamos en la fila 2
    foreach ($clientes as $cliente) {
        $sheet->setCellValue('A' . $row, $cliente->Codigo);
        $sheet->setCellValue('B' . $row, $cliente->Nombre);
        $sheet->setCellValue('C' . $row, $cliente->Cuit);
        $sheet->setCellValue('D' . $row, number_format((float)($cliente->PorcentajeDescuento ?? 0), 2) . '%');
        $sheet->setCellValue('E' . $row, ($cliente->FechaAlta ?? null) ? date('d/m/Y', strtotime($cliente->FechaAlta)) : 'N/A');
        $sheet->setCellValue('F' . $row, $cliente->Habilitado ? 'Habilitado' : 'Inhabilitado');
        $row++;
    }

    // Ajustar el ancho de las columnas automáticamente
    foreach (range('A', 'F') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. PREPARAR Y ENVIAR EL ARCHIVO AL NAVEGADOR PARA SU DESCARGA
    $writer = new Xlsx($spreadsheet);

    $fechaActual = date('Y-m-d');
    $fileName = "Reporte_Clientes_{$fechaActual}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
    $writer->save('php://output');
    
    exit();

} catch (Exception $e) {
    // Manejo de errores
    die("Ocurrió un error al generar el reporte: " . $e->getMessage());
}