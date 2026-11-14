<?php
/**
 * Panel "Importar listado" de Administrar áreas (lo maneja assets/admin_importar.js).
 * Para la carga inicial y los cambios masivos. Referencia: docs/maquetas/Areas.dc.html.
 *
 * El navegador sólo lee el Excel (SheetJS, la misma librería que ya usa Internos) y manda las
 * filas: la validación y la resolución las hace el servidor (Class/Importacion.php). Nada se
 * guarda hasta "Confirmar importación", y se guarda todo o nada.
 */
require_once __DIR__ . '/../Class/Importacion.php';
?>
        <section id="importar" class="est-panel adm-importar" hidden aria-labelledby="impTitulo">
            <div>
                <h2 id="impTitulo" class="adm-subtitulo">Importar listado</h2>
                <p class="adm-ayuda mb-0">
                    Para la carga inicial o cambios masivos. Una fila por tema; los datos del área se repiten en cada fila.
                    Agrega y actualiza: <strong>nunca borra ni desactiva</strong>, y una celda vacía no borra un dato
                    (para borrar un dato, editá la ficha). Las áreas y los temas se reconocen por su nombre, sin
                    importar mayúsculas ni tildes. En Referente va el nombre como figura en Internos, o la palabra <strong>Equipo</strong>.
                </p>
            </div>

            <div class="adm-pasos">
                <div class="adm-paso">
                    <strong>0. Descargar la plantilla</strong>
                    Trae las áreas y los temas actuales, para editarla y volver a subirla.
                    <div class="mt-2">
                        <button type="button" class="est-btn est-btn--chico" id="descargarPlantilla">
                            <i class="bi bi-download" aria-hidden="true"></i> Descargar plantilla
                        </button>
                    </div>
                </div>
                <div class="adm-paso">
                    <strong>1. Subir el archivo</strong>
                    <label for="archivoImportar" class="d-block mb-1">Elegí el Excel (.xlsx):</label>
                    <input type="file" class="form-control form-control-sm" id="archivoImportar"
                           accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                </div>
                <div class="adm-paso">
                    <strong>2. Revisar antes de guardar</strong>
                    Muestra qué áreas y temas se crean o cambian, y avisa si un nombre no está en Internos.
                </div>
                <div class="adm-paso">
                    <strong>3. Confirmar</strong>
                    Recién ahí se guarda (todo o nada) y se ve en la Guía de Consultas y en Contactos por área.
                </div>
            </div>

            <p class="form-text mb-0">Columnas: <?= htmlspecialchars(implode(', ', Importacion::COLUMNAS)) ?>.</p>

            <div id="impPrevia" class="adm-previa" aria-live="polite"></div>

            <div class="adm-botones">
                <button type="button" class="est-btn est-btn--primario" id="confirmarImportacion" disabled>Confirmar importación</button>
                <button type="button" class="est-btn" id="cancelarImportacion">Cancelar</button>
            </div>
        </section>
