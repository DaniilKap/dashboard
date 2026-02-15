<?php

namespace backend\modules\notary\models;

use yii\db\ActiveRecord;

class ArchiverSimilarityTopImage extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%archiver_similarity_top_image}}';
    }

    public function rules()
    {
        return [
            [['canonical_image_key', 'occurrence_count', 'groups_count', 'vm_occurrence_count', 'recalculated_at'], 'required'],
            [['occurrence_count', 'groups_count', 'vm_occurrence_count', 'last_seen_at', 'recalculated_at'], 'integer'],
            [['avg_similarity'], 'number'],
            [['canonical_image_key'], 'string', 'max' => 512],
            [['canonical_image_key'], 'unique'],
        ];
    }
}
