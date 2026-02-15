<?php
use yii\grid\GridView;
use yii\helpers\Html;

/** @var $dataProvider yii\data\ActiveDataProvider */
$this->title = 'Similarity Distances (DB)';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="box-tools">
            <?= Html::a('Groups', ['similarity-groups'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('Images', ['similarity-images'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                'group_id',
                'image_1_id',
                'image_2_id',
                'distance',
                [
                    'attribute' => 'recorded_at_api',
                    'value' => fn($m) => $m->recorded_at_api ?: '',
                ],
            ],
        ]); ?>
    </div>
</div>
