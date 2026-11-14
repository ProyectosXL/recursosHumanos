<?php
/**
 * Un comentario de un comunicado: ÚNICA plantilla para la intranet (al cargar la página) y para la
 * respuesta de recursosHumanos/intranet/interaccion.php (al comentar, ocultar o volver a mostrar).
 * Se usa con Intranet::plantilla('comunicado_comentario', [...]).
 *
 * Requiere:
 *   $k        fila de FP_INTRA_COMENTARIOS (IntranetInteraccion::comentarios / comentario)
 * Opcional:
 *   $moderar  bool: quien mira es responsable de carga (ve "Ocultar" / "Mostrar de nuevo")
 *   $yo       username de quien mira (ve "Borrar" en los suyos)
 *   $plegado  bool: queda escondido hasta "Ver todos los comentarios"
 *
 * El texto es plano: se escapa todo y los saltos de línea se respetan con CSS (pre-line).
 */
$moderar = !empty($moderar);
$yo = (string)($yo ?? '');
$plegado = !empty($plegado);
$oculto = !empty($k['oculto']);
$creado = $k['creado_en'] instanceof DateTimeInterface ? $k['creado_en'] : new DateTimeImmutable();
$esMio = $yo !== '' && mb_strtolower((string)$k['usuario']) === mb_strtolower($yo);
?>
<li class="com-comentario<?= $oculto ? ' es-oculto' : '' ?>" data-comentario="<?= (int)$k['id'] ?>"<?= $plegado ? ' data-plegado hidden' : '' ?>>
    <div class="intra-avatar com-comentario-avatar" aria-hidden="true"><?= Intranet::e(Intranet::iniciales((string)$k['nombre_mostrado'])) ?></div>
    <div class="com-comentario-cuerpo">
        <div class="com-comentario-cabecera">
            <span class="com-comentario-nombre"><?= Intranet::e($k['nombre_mostrado']) ?></span>
            <time class="com-comentario-cuando" datetime="<?= Intranet::e($creado->format('c')) ?>" title="<?= Intranet::e($creado->format('d/m/Y H:i')) ?>"><?= Intranet::e(Intranet::haceRato($creado)) ?></time>
        </div>
        <div class="com-comentario-texto"><?= Intranet::e($k['texto']) ?></div>
        <?php if ($oculto): ?>
            <div class="com-comentario-oculto">
                <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
                Oculto<?= ($k['oculto_por_nombre'] ?? '') !== '' ? ' por ' . Intranet::e($k['oculto_por_nombre']) : '' ?><?= $k['oculto_en'] instanceof DateTimeInterface ? ' el ' . Intranet::e(Intranet::ddmm($k['oculto_en'])) : '' ?>
            </div>
        <?php endif; ?>
        <?php if ($esMio || $moderar): ?>
            <div class="com-comentario-acciones">
                <?php if ($esMio): ?>
                    <button type="button" class="com-comentario-accion" data-accion-comentario="borrar"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Borrar</button>
                <?php endif; ?>
                <?php if ($moderar && $oculto): ?>
                    <button type="button" class="com-comentario-accion" data-accion-comentario="mostrar"><i class="fa-regular fa-eye" aria-hidden="true"></i> Mostrar de nuevo</button>
                <?php elseif ($moderar): ?>
                    <button type="button" class="com-comentario-accion" data-accion-comentario="ocultar"><i class="fa-regular fa-eye-slash" aria-hidden="true"></i> Ocultar</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</li>
