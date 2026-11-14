<?php
/**
 * "Quiénes reaccionaron": ÚNICA plantilla para la publicación (se despliega debajo de las
 * reacciones) y para la ventana del listado de carga. La arma recursosHumanos/intranet/interaccion.php.
 * Se usa con Intranet::plantilla('comunicado_quienes', ['quienes' => IntranetInteraccion::quienes($id)]).
 *
 * Agrupado por reacción, del más reciente al más viejo. En un local, el usuario compartido
 * aparece con el nombre del local.
 */
require_once dirname(__DIR__) . '/Class/IntranetInteraccion.php';

$grupos = array_filter($quienes ?? [], fn($nombres) => !empty($nombres));
?>
<?php if (!$grupos): ?>
    <div class="com-quienes-vacio">Todavía nadie reaccionó.</div>
<?php else: ?>
    <?php foreach ($grupos as $tipo => $nombres): [$nombreReaccion, $icono] = IntranetInteraccion::REACCIONES[$tipo]; ?>
    <div class="com-quienes-grupo com-quienes-<?= strtolower(Intranet::e($tipo)) ?>">
        <div class="com-quienes-titulo">
            <i class="fa-solid <?= $icono ?>" aria-hidden="true"></i> <?= Intranet::e($nombreReaccion) ?> <span class="com-quienes-n">(<?= count($nombres) ?>)</span>
        </div>
        <ul class="com-quienes-lista">
            <?php foreach ($nombres as $n): ?>
                <li><?= Intranet::e($n) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
