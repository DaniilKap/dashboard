<?php
use yii\grid\GridView;
use yii\helpers\Html;

/** @var $dataProvider yii\data\ArrayDataProvider */
/** @var $type string */

$this->title = 'Сайты, попавшие в группы (без повторов)';
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        <div class="box-tools">
            <?= Html::a('found_at_url', ['/notary/archiver-dashboard/similarity-sites', 'type' => 'found'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('source_url', ['/notary/archiver-dashboard/similarity-sites', 'type' => 'source'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('both', ['/notary/archiver-dashboard/similarity-sites', 'type' => 'both'], ['class' => 'btn btn-xs btn-default']) ?>
            <?= Html::a('VK группы', ['/notary/archiver-dashboard/similarity-vk-groups'], ['class' => 'btn btn-xs btn-default']) ?>
        </div>
    </div>

    <div class="box-body">
        <div class="alert alert-info" style="margin-bottom:10px;">
            Источник URL: <code><?= Html::encode($type) ?></code>. Агрегация по унифицированному discovery_domain/normalized_url
        </div>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-striped table-condensed'],
            'columns' => [
                [
                    'label' => 'Домен (normalized)',
                    'format' => 'raw',
                    'value' => fn($r) => Html::a(Html::encode($r['domain']), 'https://' . $r['domain'], ['target'=>'_blank']),
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
