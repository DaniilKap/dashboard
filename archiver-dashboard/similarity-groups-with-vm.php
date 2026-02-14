<?php
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Наши изображения на сайтайх находящийся в Реестре туроператор';
?>

<style>
    .vm-thumb {
        width:400px; height:auto; object-fit:cover;
        border-radius:6px; border:1px solid #e5e5e5;
        background:#fafafa;
    }
    .clickable-row { cursor:pointer; }
</style>

<div class="box box-primary">


    <div class="box-body">
        <div class="alert alert-info" style="margin-bottom:10px;">
            Клик по строке открывает осмотр группы похожих фото в которых есть наше.
        </div>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-striped table-condensed'],
            'rowOptions' => function($row) {
                $vmImageId = (int)($row['vm_image_id'] ?? 0);
                if ($vmImageId <= 0) return [];

                $url = Url::to(['/notary/archiver-dashboard/similarity-image-report', 'image_id' => $vmImageId]);
                return [
                    'class' => 'clickable-row',
                    'onclick' => "window.open(" . json_encode($url) . ",'_blank');",
                    'title' => 'Открыть отчёт по VM картинке #' . $vmImageId,
                ];
            },
            'columns' => [
                [
                    'label' => 'Наше изображение',
                    'format' => 'raw',
                    'contentOptions' => ['style' => 'width:500px;'],
                    'value' => function($row) {
                        $vmImageId = (int)($row['vm_image_id'] ?? 0);

                        $src='https://backend.vernimoe.ru/storage/right/'.str_replace('.','(SMALL).',(string)($row['vm_source_url'] ?? ''));
                        $reportUrl = Url::to(['/notary/archiver-dashboard/similarity-image-report', 'image_id' => $vmImageId]);
                        if(isset($row['image_type']) and $row['image_type']=='vm'){
                            return $row;
                        }
                        if ($vmImageId <= 0 || $src === '') {
                            return '<span class="text-muted">нет</span>';
                        }


                        return Html::a(
                            Html::img($src, ['class' => 'vm-thumb', 'loading' => 'lazy']),
                            $reportUrl,
                            ['data-pjax' => 0, 'onclick' => 'event.stopPropagation();', 'title' => 'Report #' . $vmImageId]
                        );
                    }
                ],
                [
                    'label' => 'Всего фото',
                    'value' => fn($row) => (int)($row['image_count'] ?? 0),
                ],
                [
                    'label' => 'Сходство (меньше-лучше)',
                    'value' => function($row){
                    return $row['low_dist'] === null ? '' : (string)$row['low_dist'];

                    },
                ],
                [
                    'label' => 'Обновлено',
                    'value' => fn($row) => !empty($row['updated_at']) ? date('Y-m-d H:i:s', (int)$row['updated_at']) : '',
                ],
            ],
        ]); ?>
    </div>
</div>
