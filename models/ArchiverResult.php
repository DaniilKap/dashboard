<?php

namespace backend\modules\notary\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $archiver_task_id
 * @property string|null $found_at_url
 * @property string|null $image_url
 * @property string|null $status
 * @property string|null $similar_filename
 * @property float|null $distance
 *
 * @property ArchiverTask $task
 */
class ArchiverResult extends ActiveRecord
{
    public static function tableName()
    {
        return 'archiver_result';
    }

    public function rules()
    {
        return [
            [['archiver_task_id'], 'required'],
            [['archiver_task_id'], 'integer'],
            [['found_at_url', 'image_url'], 'string'],
            [['status'], 'string', 'max' => 64],
            [['similar_filename'], 'string', 'max' => 255],
            [['distance'], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'archiver_task_id' => 'ID задачи',
            'found_at_url' => 'Найдено на странице',
            'image_url' => 'URL изображения',
            'status' => 'Статус изображения',
            'similar_filename' => 'Похожее имя файла',
            'distance' => 'Степень похожести',
        ];
    }

    public function getTask()
    {
        return $this->hasOne(ArchiverTask::class, ['id' => 'archiver_task_id']);
    }
    public function getRight()
    {
        return $this->hasOne(RightObject::class, ['file' => 'similar_filename']);
    }
}
