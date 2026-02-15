<?php

namespace backend\modules\notary\models;

use yii\db\ActiveRecord;

class ArchiverSimilarityImage extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%archiver_similarity_image}}';
    }

    public function rules()
    {
        return [
            [['group_id', 'image_id', 'image_type', 'created_at', 'updated_at'], 'required'],
            [['group_id', 'image_id', 'milvus_id', 'created_at', 'updated_at'], 'integer'],
            [['filename', 'source_url','found_at_url'], 'string'],
            [['image_hash'], 'string', 'max' => 128],
            [['discovery_domain'], 'string', 'max' => 255],
            [['image_type'], 'string', 'max' => 16],
            [['image_type'], 'in', 'range' => ['vm', 'other']],
            [['created_at_api', 'added_to_group_at'], 'safe'],
            [['best_distance'], 'number'],
            [['group_id', 'image_id'], 'unique', 'targetAttribute' => ['group_id', 'image_id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'group_id' => 'Group',
            'image_id' => 'Image ID',
            'image_type' => 'Type',
            'best_distance' => 'Best distance',
            'discovery_domain' => 'Domain',
            'source_url' => 'Source URL',
            'created_at_api' => 'Created (API)',
        ];
    }

    public function getGroup()
    {
        return $this->hasOne(ArchiverSimilarityGroup::class, ['group_id' => 'group_id']);
    }
    public function getRight()
    {
        return $this->hasOne(RightObject::class, ['file' => 'filename']);
    }

    public static function upsertFromApi(int $groupId, array $im, ?float $bestDistance, int $now): ?self
    {
        $imageId = (int)($im['id'] ?? 0);
        if (!$imageId) return null;

        $row = static::findOne(['group_id' => $groupId, 'image_id' => $imageId]);
        if (!$row) {
            $row = new static();
            $row->group_id = $groupId;
            $row->image_id = $imageId;
            $row->created_at = $now;
        }

        $row->image_type = (string)($im['image_type'] ?? 'other');
        if (!in_array($row->image_type, ['vm', 'other'], true)) $row->image_type = 'other';

        $row->filename = $im['filename'] ?? null;
        $row->image_hash = $im['image_hash'] ?? null;
        $row->milvus_id = isset($im['milvus_id']) ? (int)$im['milvus_id'] : null;
        $row->source_url = $im['source_url'] ?? null;
        $row->found_at_url = $im['found_at_url'] ?? null;
        $row->discovery_domain = $im['discovery_domain'] ?? null;

        if (!$row->discovery_domain) {
            $candidate = $row->found_at_url ?: $row->source_url;
            if ($candidate) {
                $host = parse_url($candidate, PHP_URL_HOST);
                if ($host) {
                    $row->discovery_domain = $host;
                }
            }
        }

        $row->created_at_api = $im['created_at'] ?? null;
        $row->added_to_group_at = $im['added_to_group_at'] ?? null;

        $row->best_distance = $bestDistance;

        $row->updated_at = $now;
        $row->save(false);

        return $row;
    }
}
