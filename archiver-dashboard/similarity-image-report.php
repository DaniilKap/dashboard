<?php

use yii\helpers\Html;

/** @var $main \backend\modules\notary\models\ArchiverSimilarityImage */
/** @var $group \backend\modules\notary\models\ArchiverSimilarityGroup|null */
/** @var $byDay array */
/** @var $filters array */

$this->title = 'Отчёт по изображению #' . $main->image_id;

$imgUrl = $main->source_url ?: 'https://backend.vernimoe.ru/storage/right/'.$main->filename;
$minDistance = $filters['min_distance'] ?? '';
$maxDistance = $filters['max_distance'] ?? '16';
?>

<div class="box box-primary">
    <div class="box-header with-border">
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-sm-4">
                <?php if ($imgUrl): ?>
                    <a href="<?= Html::encode($imgUrl) ?>" target="_blank">
                        <img src="<?= Html::encode($imgUrl) ?>" style="max-width:100%; border-radius:6px; border:1px solid #ddd;">
                    </a>
                <?php else: ?>
                    <div class="alert alert-warning">Нет source_url у этой записи</div>
                <?php endif; ?>
            </div>

            <div class="col-sm-8">
                <table class="table table-condensed">

                    <tr><th>Домены</th><td><?= Html::encode($main->discovery_domain) ?></td></tr>
                    <tr><th>Лучшее сходство</th><td><?= $main->best_distance === null ? '' : Html::encode($main->best_distance) ?></td></tr>
                    <tr><th><?php if($main->image_type=='vm' and isset($main->right->id)){ ?><a target="_blank" href="https://backend.vernimoe.ru/notary/ru/right/update?id=<?= $main->right->id ?>" >Открыть обьект права</a> <?php }?> </th></tr>
                </table>

            </div>
        </div>

        <hr>

        <form method="get" class="form-inline" style="margin-bottom: 15px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <input type="hidden" name="image_id" value="<?= (int)$main->image_id ?>">

            <div class="form-group">
                <label for="min-distance" style="display:block;">Сходство от</label>
                <input id="min-distance" type="number" step="0.01" min="0" name="min_distance" value="<?= Html::encode($minDistance) ?>" class="form-control" placeholder="например 0">
            </div>

            <div class="form-group">
                <label for="max-distance" style="display:block;">Сходство до</label>
                <input id="max-distance" type="number" step="0.01" min="0" name="max_distance" value="<?= Html::encode($maxDistance) ?>" class="form-control" placeholder="по умолчанию 16">
            </div>

            <button type="submit" class="btn btn-primary">Применить фильтр</button>
            <a href="?image_id=<?= (int)$main->image_id ?>&max_distance=16" class="btn btn-default">Сбросить</a>
        </form>

        <h4 style="margin-top:0;">Остальные фото группы (по дням)</h4>
        <div style="max-height: 600px;
    overflow-x: auto;">
        <?php if (empty($byDay)): ?>
            <div class="alert alert-info">В группе нет других изображений.</div>
        <?php else: ?>
            <?php foreach ($byDay as $day => $items): ?>
                <h4 style="margin-top:18px;"><?= Html::encode($day) ?> <small>(<?= count($items) ?>)</small></h4>

                <div class="table-responsive" style="font-size: x-large">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th style="width:220px;">Найденная</th>
                            <?php if ($main->image_type === 'vm'): ?>
                                <th style="width:600px;">Пара (основная VM + найденная)</th>
                            <?php endif; ?>
                            <th>Сходство коэф (меньше-лучше)</th>
                            <th>Домен</th>
                            <th>url</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $r): ?>
                            <?php $u = $r['source_url'] ?? null; ?>
                            <tr>
                                <td>
                                    <?php if ($u): ?>
                                        <a href="<?= Html::encode($u) ?>" target="_blank">
                                            <img src="<?= Html::encode($u) ?>" style="width:400px; height:auto; border-radius:4px; border:1px solid #eee;">
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>


                                <?php if ($main->image_type === 'vm'): ?>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <div style="text-align:center;">
                                                <div style="font-size:12px; color:#666; margin-bottom:4px;">VM (основная)</div>
                                                <?php if ($imgUrl): ?>
                                                    <a href="<?= Html::encode($imgUrl) ?>" target="_blank">
                                                        <img src="<?= Html::encode($imgUrl) ?>" style="width:180px; height:auto; border-radius:4px; border:1px solid #eee;">
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size:18px; color:#777;">↔</div>
                                            <div style="text-align:center;">
                                                <div style="font-size:12px; color:#666; margin-bottom:4px;">Найденная</div>
                                                <?php if ($u): ?>
                                                    <a href="<?= Html::encode($u) ?>" target="_blank">
                                                        <img src="<?= Html::encode($u) ?>" style="width:180px; height:auto; border-radius:4px; border:1px solid #eee;">
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td><?= $r['pair_distance'] === null ? '' : Html::encode($r['pair_distance']) ?></td>
                                <td><?= Html::encode($r['discovery_domain'] ?? '') ?></td>
                                <td><?= $u ? Html::a($r['found_at_url'], $r['found_at_url'], ['target' => '_blank']) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
