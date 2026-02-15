<?php

use yii\grid\GridView;
use yii\helpers\Html;

$this->title = 'Top Images';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="pull-right">
            <?= Html::a('Similarity Import', ['similarity-import'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('Groups', ['similarity-groups'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <form class="form-inline" method="get" style="margin-bottom:10px;">
            <label style="margin-right:6px;">type</label>
            <select name="type" class="form-control" style="width:120px; margin-right:8px;">
                <?php $type = Yii::$app->request->get('type', ''); ?>
                <option value="" <?= $type === '' ? 'selected' : '' ?>>all</option>
                <option value="vm" <?= $type === 'vm' ? 'selected' : '' ?>>vm</option>
                <option value="other" <?= $type === 'other' ? 'selected' : '' ?>>other</option>
                <option value="mixed" <?= $type === 'mixed' ? 'selected' : '' ?>>mixed</option>
            </select>

            <label style="margin-right:6px;">period_from</label>
            <input type="date" class="form-control" name="period_from" value="<?= Html::encode(Yii::$app->request->get('period_from', '')) ?>" style="margin-right:8px;">

            <label style="margin-right:6px;">period_to</label>
            <input type="date" class="form-control" name="period_to" value="<?= Html::encode(Yii::$app->request->get('period_to', '')) ?>" style="margin-right:8px;">

            <label style="margin-right:6px;">min_occurrences</label>
            <input type="number" class="form-control" min="1" name="min_occurrences" value="<?= Html::encode(Yii::$app->request->get('min_occurrences', '')) ?>" style="width:100px; margin-right:8px;">

            <label style="margin-right:6px;">sort</label>
            <?php $sort = Yii::$app->request->get('sort', 'occurrence_desc'); ?>
            <select name="sort" class="form-control" style="width:170px; margin-right:8px;">
                <option value="occurrence_desc" <?= $sort === 'occurrence_desc' ? 'selected' : '' ?>>occurrence_count desc</option>
                <option value="occurrence_asc" <?= $sort === 'occurrence_asc' ? 'selected' : '' ?>>occurrence_count asc</option>
            </select>

            <button class="btn btn-primary">Apply</button>
        </form>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                'canonical_image_key',
                'occurrence_count',
                'groups_count',
                'vm_occurrence_count',
                [
                    'attribute' => 'avg_similarity',
                    'format' => ['decimal', 6],
                ],
                [
                    'attribute' => 'last_seen_at',
                    'value' => static fn($m) => $m->last_seen_at ? date('Y-m-d H:i:s', (int)$m->last_seen_at) : null,
                ],
                [
                    'attribute' => 'recalculated_at',
                    'value' => static fn($m) => $m->recalculated_at ? date('Y-m-d H:i:s', (int)$m->recalculated_at) : null,
                ],
            ],
        ]) ?>
    </div>
</div>
