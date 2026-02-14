<?php

use yii\helpers\Html;

/** @var $main \backend\modules\notary\models\ArchiverSimilarityImage */
/** @var $group \backend\modules\notary\models\ArchiverSimilarityGroup|null */
/** @var $byDay array */

$this->title = 'Отчёт по изображению #' . $main->image_id;

$imgUrl = $main->source_url ?: 'https://backend.vernimoe.ru/storage/right/'.$main->filename;
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
                            <th style="width:220px;"></th>
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
