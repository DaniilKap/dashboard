<?php
use yii\grid\GridView;
use yii\helpers\Html;

/** @var $dataProvider yii\data\ActiveDataProvider */
$this->title = 'Similarity Groups (DB)';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="box-tools">
            <?= Html::a('Images', ['similarity-images'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('Distances', ['similarity-distances'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                'group_id',
                'image_count',
                [
                    'attribute' => 'is_mixed',
                    'value' => fn($m) => $m->is_mixed ? '1' : '0',
                ],
                'vm_count',
                'other_count',
                [
                    'attribute' => 'avg_distance',
                    'value' => fn($m) => $m->avg_distance === null ? '' : (string)$m->avg_distance,
                ],
                [
                    'attribute' => 'import_batch',
                ],
                [
                    'label' => 'Updated',
                    'value' => fn($m) => date('Y-m-d H:i:s', (int)$m->updated_at),
                ],
                [
                    'label' => 'Open',
                    'format' => 'raw',
                    'value' => function($m){
                        return
                            Html::a('images', ['similarity-images', 'group_id' => $m->group_id], ['class' => 'btn btn-xs btn-default']) . ' ' .
                            Html::a('dist', ['similarity-distances', 'group_id' => $m->group_id], ['class' => 'btn btn-xs btn-default']);
                    }
                ],
            ],
        ]); ?>
    </div>
</div>
