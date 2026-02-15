<?php
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var $dataProvider yii\data\ActiveDataProvider */
/** @var $onlyTop int */
/** @var $minSize int */

$this->title = 'Группы изображений которые встречались чаще всего';
?>

<style>
    .badge-top { background:#2e7d32; color:#fff; padding:3px 8px; border-radius:10px; font-size:12px; }
    .badge-notop { background:#9e9e9e; color:#fff; padding:3px 8px; border-radius:10px; font-size:12px; }
</style>

<div class="box box-primary">


    <div class="box-body">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-striped table-condensed'],
            'columns' => [
                [
                    'attribute' => 'group_id',
                    'label' => 'Группа',
                    'format' => 'raw',
                    'value' => function($m){
                        return Html::a(
                            Html::encode($m->group_id),
                            ['/notary/archiver-dashboard/similarity-images', 'group_id' => $m->group_id],
                            ['data-pjax' => 0, 'title' => 'Открыть картинки группы']
                        );
                    }
                ],
                [
                    'attribute' => 'image_count',
                    'label' => 'Размер',
                ],
                [
                    'attribute' => 'avg_distance',
                    'label' => 'Среднее сходство',
                    'value' => fn($m) => $m->avg_distance === null ? '' : (string)$m->avg_distance,
                ],

                [
                    'label' => 'Updated',
                    'label' => 'Обновлено',
                    'value' => fn($m) => date('Y-m-d H:i:s', (int)$m->updated_at),
                ],

            ],
        ]); ?>
    </div>
</div>
