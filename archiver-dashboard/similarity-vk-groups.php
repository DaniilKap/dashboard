<?php
use yii\grid\GridView;
use yii\helpers\Html;

/** @var $dataProvider yii\data\ArrayDataProvider */

$this->title = 'VK группы, попавшие в группы (без повторов)';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="box-tools">
            <?= Html::a('Сайты', ['/notary/archiver-dashboard/similarity-sites'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <div class="alert alert-info" style="margin-bottom:10px;">
            Используются единые правила нормализации URL: lower-case host, нормализация схемы, удаление trailing slash, очистка UTM.
        </div>
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-striped table-condensed'],
            'columns' => [
                [
                    'label' => 'VK group (normalized)',
                    'format' => 'raw',
                    'value' => fn($r) => Html::a(Html::encode($r['vk_group']), $r['vk_group'], ['target'=>'_blank']),
                ],
                [
                    'label' => 'Картинок',
                    'value' => fn($r) => $r['images'],
                ],
                [
                    'label' => 'Групп',
                    'value' => fn($r) => $r['groups'],
                ],
            ],
        ]); ?>
    </div>
</div>
