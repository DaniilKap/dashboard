<?php
use yii\grid\GridView;
use yii\helpers\Html;

/** @var $dataProvider yii\data\ActiveDataProvider */
$this->title = 'Similarity Images (DB)';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="box-tools">
            <?= Html::a('Groups', ['similarity-groups'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('Distances', ['similarity-distances'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                'group_id',
                'image_id',
                'image_type',
                [
                    'attribute' => 'best_distance',
                    'value' => fn($m) => $m->best_distance === null ? '' : (string)$m->best_distance,
                ],
                'discovery_domain',
                [
                    'label' => 'Source',
                    'format' => 'raw',
                    'value' => fn($m) => $m->source_url ? Html::a('open', $m->source_url, ['target' => '_blank']) : '',
                ],
                [
                    'attribute' => 'created_at_api',
                    'value' => fn($m) => $m->created_at_api ?: '',
                ],
            ],
        ]); ?>
    </div>
</div>
