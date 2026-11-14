<?php
/**
 * Menú lateral de estructura: UNO solo para todas las pantallas (lo incluye partials/cabecera.php).
 *
 * No fuerza el login: los ítems de administración se dibujan sólo si ya hay una sesión del hub
 * con el permiso (Permisos nunca redirige). Así un local que entra desde XL Gestión ve las tres
 * consultas y nada más. Que el ítem no esté no protege nada: cada pantalla y cada endpoint de
 * administración vuelve a validar el permiso en el servidor.
 *
 * Espera $pagina['activo']: guia | contactos | internos | admin | internos_abm
 */
require_once __DIR__ . '/../Class/Permisos.php';

$menuActivo = isset($pagina['activo']) ? $pagina['activo'] : '';
$menuItems = array(
    array('guia', ESTRUCTURA_URL_GUIA, 'bi-signpost-2', 'Guía de Consultas', false),
    array('contactos', ESTRUCTURA_URL_CONTACTOS, 'bi-telephone', 'Contactos por área', false),
    array('internos', ESTRUCTURA_URL_INTERNOS, 'bi-people', 'Internos', false),
);
$menuAdmin = array();
if (Permisos::puedeAdministrar()) {
    $menuAdmin[] = array('admin', ESTRUCTURA_URL_ADMIN, 'bi-sliders', 'Administrar áreas', true);
}
if (Permisos::puedeAdministrarInternos()) {
    $menuAdmin[] = array('internos_abm', ESTRUCTURA_URL_INTERNOS_ABM, 'bi-person-lines-fill', 'Administrar internos', true);
}
?>
<nav class="est-menu" aria-label="Directorio">
    <a class="est-menu-volver" href="<?= htmlspecialchars(ESTRUCTURA_URL_XLGESTION) ?>">‹ Volver a XL Gestión</a>
    <div class="est-marca">
        <div class="est-marca-logo" aria-hidden="true">XL</div>
        <span class="est-marca-nombre">Directorio</span>
    </div>
    <?php foreach (array_merge($menuItems, $menuAdmin ? array(null) : array(), $menuAdmin) as $item): ?>
        <?php if ($item === null): ?>
            <div class="est-menu-sep" role="separator"></div>
        <?php else: ?>
            <a class="est-menu-item<?= $item[4] ? ' est-menu-item--admin' : '' ?>" href="<?= htmlspecialchars($item[1]) ?>"<?= $menuActivo === $item[0] ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= $item[2] ?>" aria-hidden="true"></i><?= htmlspecialchars($item[3]) ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
