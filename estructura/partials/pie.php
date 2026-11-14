<?php
/**
 * Cierre de todas las pantallas de estructura: scripts comunes, rutas para el JS
 * (partials/config_js.php) y los scripts propios de la pantalla.
 *
 * Espera $pagina['js'] = [archivos de assets/] y, si hacen falta, $pagina['libs'] = ['jquery',
 * 'sweetalert', 'xlsx'] (Internos y el ABM ya usaban jQuery, SweetAlert2 y SheetJS; las pantallas
 * nuevas usan JS sin librerías y SheetJS sólo para la importación).
 */
$paginaJs = isset($pagina['js']) ? $pagina['js'] : array();
$paginaLibs = isset($pagina['libs']) ? $pagina['libs'] : array();
$libsUrl = array(
    'jquery'     => 'https://code.jquery.com/jquery-3.6.0.min.js',
    'sweetalert' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    // Misma versión de SheetJS que usaba Internos para exportar
    'xlsx'       => 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js',
    'filesaver'  => 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js',
);
?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($paginaLibs as $lib): ?>
<script src="<?= $libsUrl[$lib] ?>"></script>
<?php endforeach; ?>
<?php require __DIR__ . '/config_js.php'; ?>
<?php foreach ($paginaJs as $js): ?>
<script src="<?= ESTRUCTURA_URL_ASSETS ?>/<?= $js ?>?v=<?= (int)@filemtime(ESTRUCTURA_DIR . '/assets/' . $js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
