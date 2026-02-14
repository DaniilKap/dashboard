<?php

namespace backend\modules\notary\models;

use yii\db\ActiveRecord;

class ArchiverSimilarityDistance extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%archiver_similarity_distance}}';
    }

    public function rules()
    {
        return [
            [['group_id', 'image_1_id', 'image_2_id', 'distance', 'created_at', 'updated_at'], 'required'],
            [['group_id', 'image_1_id', 'image_2_id', 'created_at', 'updated_at'], 'integer'],
            [['distance'], 'number'],
            [['recorded_at_api'], 'safe'],
            [['group_id', 'image_1_id', 'image_2_id'], 'unique', 'targetAttribute' => ['group_id', 'image_1_id', 'image_2_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'group_id' => 'Group',
            'image_1_id' => 'Image 1',
            'image_2_id' => 'Image 2',
            'distance' => 'Distance',
            'recorded_at_api' => 'Recorded at (API)',
        ];
    }

    public function getGroup()
    {
        return $this->hasOne(ArchiverSimilarityGroup::class, ['group_id' => 'group_id']);
    }

    public static function upsertPair(int $groupId, int $a, int $b, float $distance, ?string $recordedAt, int $now): ?self
    {
        if ($a <= 0 || $b <= 0 || $a === $b) return null;

        $i1 = min($a, $b);
        $i2 = max($a, $b);

        $row = static::findOne(['group_id' => $groupId, 'image_1_id' => $i1, 'image_2_id' => $i2]);
        if (!$row) {
            $row = new static();
            $row->group_id = $groupId;
            $row->image_1_id = $i1;
            $row->image_2_id = $i2;
            $row->created_at = $now;
        }

        $row->distance = $distance;
        $row->recorded_at_api = $recordedAt;
        $row->updated_at = $now;
        $row->save(false);

        return $row;
    }
}
