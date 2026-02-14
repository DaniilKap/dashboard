<?php

namespace backend\modules\notary\models;

use yii\db\ActiveRecord;

class ArchiverSimilarityGroup extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%archiver_similarity_group}}';
    }

    public function rules()
    {
        return [
            [['group_id', 'created_at', 'updated_at'], 'required'],
            [['group_id', 'image_count', 'is_mixed', 'vm_count', 'other_count', 'created_at', 'updated_at'], 'integer'],
            [['created_at_api'], 'safe'],
            [['import_batch'], 'string', 'max' => 64],
            [['avg_distance', 'min_distance', 'max_distance', 'avg_distance_stats'], 'number'],
            [['group_id'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'group_id' => 'Group ID',
            'created_at_api' => 'Created (API)',
            'image_count' => 'Images',
            'is_mixed' => 'Mixed',
            'vm_count' => 'VM',
            'other_count' => 'Other',
            'avg_distance' => 'Avg distance (list)',
            'min_distance' => 'Min distance',
            'max_distance' => 'Max distance',
            'avg_distance_stats' => 'Avg distance (stats)',
            'import_batch' => 'Batch',
            'updated_at' => 'Updated',
        ];
    }

    public function getImages()
    {
        return $this->hasMany(ArchiverSimilarityImage::class, ['group_id' => 'group_id']);
    }


    public function getDistances()
    {
        return $this->hasMany(ArchiverSimilarityDistance::class, ['group_id' => 'group_id']);
    }

    public static function upsertFromApi(array $g, string $batch, int $now): self
    {
        $gid = (int)($g['group_id'] ?? 0);
        $m = static::findOne(['group_id' => $gid]);
        if (!$m) {
            $m = new static();
            $m->group_id = $gid;
            $m->created_at = $now;
        }

        $m->created_at_api = $g['created_at'] ?? null;
        $m->image_count = (int)($g['image_count'] ?? 0);
        $m->is_mixed = !empty($g['is_mixed']) ? 1 : 0;

        $tc = $g['type_counts'] ?? [];
        $m->vm_count = (int)($tc['vm'] ?? 0);
        $m->other_count = (int)($tc['other'] ?? 0);

        $m->avg_distance = isset($g['avg_distance']) ? (float)$g['avg_distance'] : null;

        $m->import_batch = $batch;
        $m->updated_at = $now;

        $m->save(false);
        return $m;
    }

    public function applyStatsFromDetail(array $detail, int $now): void
    {
        $stats = $detail['statistics'] ?? null;
        if (!is_array($stats)) return;

        $this->min_distance = isset($stats['min_distance']) ? (float)$stats['min_distance'] : null;
        $this->max_distance = isset($stats['max_distance']) ? (float)$stats['max_distance'] : null;
        $this->avg_distance_stats = isset($stats['avg_distance']) ? (float)$stats['avg_distance'] : null;
        $this->updated_at = $now;
        $this->save(false);
    }
}
