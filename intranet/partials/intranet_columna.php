<?php
/**
 * Columna "Intranet XL" del hub (a la derecha, solo desde 1200 px).
 * Requiere: $intra = Intranet::resumenHub();
 * Un bloque sin contenido no se muestra, salvo "Hoy", que siempre está.
 */
$e = [Intranet::class, 'e'];
$hoyFecha = Intranet::hoy();
$mesNombre = Intranet::mes((int)$hoyFecha->format('n'));
$tagTipo = ['FERIADO' => 'intra-tag-feriado', 'COMERCIAL' => 'intra-tag-comercial', 'OPERATIVA' => 'intra-tag-operativa'];
?>
<aside class="intra-col" aria-label="Intranet XL">
    <div class="intra-head">
        <h2 class="intra-head-title"><i class="fa-solid fa-newspaper"></i> Intranet XL</h2>
        <a href="<?= $e(Intranet::URL_INTRANET) ?>" class="intra-link">Ir a la Intranet <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>

    <!-- Hoy -->
    <section class="intra-hoy intra-aparece" style="--i: 0" aria-label="Hoy">
        <div class="intra-hoy-fecha">Hoy · <?= $e(Intranet::diaYFecha($hoyFecha)) ?></div>

        <?php if (!empty($intra['cumple_hoy'])): ?>
            <div class="intra-hoy-linea">
                <div class="intra-hoy-icono"><i class="fa-solid fa-cake-candles"></i></div>
                <div>
                    <?= count($intra['cumple_hoy']) === 1 ? 'Hoy cumple' : 'Hoy cumplen' ?>
                    <?php foreach ($intra['cumple_hoy'] as $p): ?>
                        <div><strong><?= $e($p['nombre']) ?></strong><?php if ($p['sector'] !== ''): ?> <span class="intra-hoy-sub">· <?= $e($p['sector']) ?></span><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!empty($intra['proximo_cumple'])): ?>
            <?php $pc = $intra['proximo_cumple']; ?>
            <div class="intra-hoy-linea">
                <div class="intra-hoy-icono"><i class="fa-solid fa-cake-candles"></i></div>
                <div>
                    Próximo cumpleaños:
                    <strong><?= $e(implode(', ', array_column($pc['personas'], 'nombre'))) ?></strong>,
                    <?= $e(Intranet::diaYFecha($pc['fecha'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($intra['aniv_hoy'])): ?>
            <div class="intra-hoy-linea">
                <div class="intra-hoy-icono"><i class="fa-solid fa-champagne-glasses"></i></div>
                <div>
                    <?php if (count($intra['aniv_hoy']) === 1): $a = $intra['aniv_hoy'][0]; ?>
                        Hoy cumple <?= Intranet::anios($a['anios']) ?> en XL:
                        <strong><?= $e($a['nombre']) ?></strong><?php if ($a['sector'] !== ''): ?> <span class="intra-hoy-sub">· <?= $e($a['sector']) ?></span><?php endif; ?>
                        <?php if ($a['es_hito']): ?><span class="intra-tag intra-tag-hito"><i class="fa-solid fa-medal"></i> <?= Intranet::anios($a['anios']) ?></span><?php endif; ?>
                    <?php else: ?>
                        Aniversarios de hoy en XL
                        <?php foreach ($intra['aniv_hoy'] as $a): ?>
                            <div>
                                <strong><?= $e($a['nombre']) ?></strong> <span class="intra-hoy-sub">· <?= Intranet::anios($a['anios']) ?></span>
                                <?php if ($a['es_hito']): ?><span class="intra-tag intra-tag-hito"><i class="fa-solid fa-medal"></i></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($intra['feriado'])): ?>
            <?php $fer = $intra['feriado']; $diasFer = Intranet::diasHasta($fer['desde']); ?>
            <div class="intra-hoy-linea">
                <div class="intra-hoy-icono"><i class="fa-solid fa-umbrella-beach"></i></div>
                <div>
                    <?php if ($diasFer <= 0): ?>
                        Hoy es feriado: <strong><?= $e($fer['titulo']) ?></strong>
                    <?php else: ?>
                        Próximo feriado <?= $diasFer === 1 ? 'mañana' : 'en ' . $diasFer . ' días' ?> · <?= $e(Intranet::diaYFecha($fer['desde'])) ?>
                        <div class="intra-hoy-sub"><?= $e($fer['titulo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($intra['cumple_mes'])): ?>
    <!-- Cumpleaños del mes -->
    <section class="intra-bloque intra-aparece" style="--i: 1" aria-label="Cumpleaños del mes">
        <h3 class="intra-bloque-titulo"><i class="fa-solid fa-cake-candles"></i> Cumpleaños de <?= $e($mesNombre) ?></h3>
        <ul class="intra-lista">
            <?php foreach ($intra['cumple_mes'] as $p): ?>
            <li class="intra-item">
                <?php if ($p['es_hoy']): ?>
                    <div class="intra-dia es-hoy">HOY</div>
                <?php else: ?>
                    <div class="intra-dia"><?= $e($p['fecha']->format('j')) ?></div>
                <?php endif; ?>
                <div class="intra-item-texto">
                    <div class="intra-item-titulo"><?= $e($p['nombre']) ?></div>
                    <?php if ($p['sector'] !== ''): ?><div class="intra-item-sub"><?= $e($p['sector']) ?></div><?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php $resto = $intra['cumple_mes_total'] - count($intra['cumple_mes']); ?>
        <?php if ($resto > 0): ?>
            <a href="<?= $e(Intranet::URL_INTRANET) ?>#cumpleanos" class="intra-mas">y <?= $resto ?> más</a>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($intra['proximas_fechas'])): ?>
    <!-- Próximas fechas -->
    <section class="intra-bloque intra-aparece" style="--i: 2" aria-label="Próximas fechas">
        <h3 class="intra-bloque-titulo"><i class="fa-solid fa-calendar-day"></i> Próximas fechas</h3>
        <ul class="intra-lista">
            <?php foreach ($intra['proximas_fechas'] as $f): ?>
            <li class="intra-item">
                <div class="intra-dia"><?= $e($f['desde']->format('j')) ?><small><?= $e(Intranet::mesCorto($f['desde'])) ?></small></div>
                <div class="intra-item-texto">
                    <div class="intra-item-titulo" title="<?= $e($f['titulo']) ?>"><?= $e($f['titulo']) ?></div>
                    <div class="intra-item-sub">
                        <span class="intra-tag <?= $tagTipo[$f['tipo']] ?? '' ?>"><?= $e(Intranet::tipoFecha($f['tipo'])) ?></span>
                        <?php if ($f['hasta'] > $f['desde']): ?> <?= $e(Intranet::rango($f['desde'], $f['hasta'])) ?><?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($intra['fijados']) || !empty($intra['ultimos'])): ?>
    <!-- Comunicados -->
    <section class="intra-aparece" style="--i: 3" aria-label="Comunicados">
        <h3 class="intra-bloque-titulo"><i class="fa-solid fa-bullhorn"></i> Comunicados</h3>
        <div class="d-flex flex-column gap-2">
            <?php foreach (array_merge($intra['fijados'], $intra['ultimos']) as $c): ?>
                <?= Intranet::plantilla('comunicado_resumen', ['c' => $c]) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($intra['aniversarios'])): ?>
    <!-- Equipo XL -->
    <section class="intra-bloque intra-aparece" style="--i: 4" aria-label="Equipo XL">
        <h3 class="intra-bloque-titulo"><i class="fa-solid fa-people-group"></i> Equipo XL</h3>
        <?php if (!empty($intra['aniversarios'])): ?>
            <div class="intra-sub-titulo">Próximos aniversarios</div>
            <ul class="intra-lista">
                <?php foreach ($intra['aniversarios'] as $p): ?>
                <li class="intra-item<?= $p['es_hito'] ? ' es-hito' : '' ?>">
                    <?php if ($p['es_hoy']): ?>
                        <div class="intra-dia es-hoy">HOY</div>
                    <?php else: ?>
                        <div class="intra-dia"><?= $e($p['fecha']->format('j')) ?><small><?= $e(Intranet::mesCorto($p['fecha'])) ?></small></div>
                    <?php endif; ?>
                    <div class="intra-item-texto">
                        <div class="intra-item-titulo"><?= $e($p['nombre']) ?></div>
                        <div class="intra-item-sub">
                            <?php if ($p['es_hito']): ?>
                                <span class="intra-tag intra-tag-hito"><i class="fa-solid fa-medal"></i> <?= $p['anios'] ?> años en XL</span>
                            <?php else: ?>
                                <?= $p['anios'] === 1 ? '1 año' : $p['anios'] . ' años' ?> en XL
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($intra['equipo_resto'] > 0): ?>
            <a href="<?= $e(Intranet::URL_INTRANET) ?>#equipo" class="intra-mas">y <?= (int)$intra['equipo_resto'] ?> más</a>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</aside>
