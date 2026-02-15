<?php

namespace backend\modules\notary\services;

use backend\modules\notary\models\ArchiverSimilarityGroup;
use backend\modules\notary\models\ArchiverSimilarityImage;
use backend\modules\notary\models\ArchiverSimilarityTopImage;
use Yii;
use yii\db\Expression;
use yii\db\Query;

class ArchiverSimilarityTopImageRebuilder
{
    public static function canonicalKeySql(string $alias = 'i'): string
    {
        return "CASE\n"
            . " WHEN NULLIF(TRIM({$alias}.image_hash), '') IS NOT NULL THEN CONCAT('hash:', LOWER(TRIM({$alias}.image_hash)))\n"
            . " WHEN NULLIF(TRIM({$alias}.source_url), '') IS NOT NULL THEN CONCAT('url:', SUBSTRING_INDEX(LOWER(TRIM({$alias}.source_url)), '#', 1))\n"
            . " ELSE NULL\n"
            . 'END';
    }

    public function rebuild(bool $reset = true): array
    {
        $db = Yii::$app->db;
        $now = time();

        if ($reset) {
            $db->createCommand()->delete(ArchiverSimilarityTopImage::tableName())->execute();
        }

        $canonicalSql = self::canonicalKeySql('i');
        $canonicalExpr = new Expression($canonicalSql);

        $rows = (new Query())
            ->from(['i' => ArchiverSimilarityImage::tableName()])
            ->leftJoin(['g' => ArchiverSimilarityGroup::tableName()], 'g.group_id = i.group_id')
            ->select([
                'canonical_image_key' => $canonicalExpr,
                'occurrence_count' => new Expression('COUNT(*)'),
                'groups_count' => new Expression('COUNT(DISTINCT i.group_id)'),
                'vm_occurrence_count' => new Expression("SUM(CASE WHEN i.image_type='vm' THEN 1 ELSE 0 END)"),
                'last_seen_at' => new Expression('MAX(COALESCE(i.updated_at, i.created_at))'),
                'avg_similarity' => new Expression('AVG(COALESCE(i.best_distance, g.avg_distance_stats, g.avg_distance))'),
            ])
            ->where(new Expression("{$canonicalSql} IS NOT NULL"))
            ->groupBy([$canonicalExpr])
            ->orderBy(['occurrence_count' => SORT_DESC])
            ->all($db);

        if (empty($rows)) {
            return [
                'rows_inserted' => 0,
                'recalculated_at' => $now,
            ];
        }

        $insertRows = [];
        foreach ($rows as $row) {
            $insertRows[] = [
                $row['canonical_image_key'],
                (int)$row['occurrence_count'],
                (int)$row['groups_count'],
                (int)$row['vm_occurrence_count'],
                $row['last_seen_at'] !== null ? (int)$row['last_seen_at'] : null,
                $row['avg_similarity'] !== null ? (float)$row['avg_similarity'] : null,
                $now,
            ];
        }

        $db->createCommand()->batchInsert(
            ArchiverSimilarityTopImage::tableName(),
            ['canonical_image_key', 'occurrence_count', 'groups_count', 'vm_occurrence_count', 'last_seen_at', 'avg_similarity', 'recalculated_at'],
            $insertRows
        )->execute();

        return [
            'rows_inserted' => count($insertRows),
            'recalculated_at' => $now,
        ];
    }
}
