<?php

namespace backend\modules\notary\commands;

use yii\console\Controller;
use yii\db\Query;
use backend\modules\notary\models\ArchiverSimilarityImage;
use backend\modules\notary\services\SimilarityUrlNormalizer;

class SimilarityMaintenanceController extends Controller
{
    /**
     * Backfill normalized_url and discovery_domain for existing rows.
     *
     * Usage:
     * php yii notary/similarity-maintenance/backfill-url-fields --batchSize=1000 --stripQuery=0 --stripUtm=1
     */
    public function actionBackfillUrlFields(int $batchSize = 1000, int $stripQuery = 0, int $stripUtm = 1): int
    {
        $normalizer = new SimilarityUrlNormalizer();
        $now = time();

        $updated = 0;
        $processed = 0;

        foreach ((new Query())
                     ->from(ArchiverSimilarityImage::tableName())
                     ->select(['id', 'source_url', 'found_at_url', 'discovery_domain', 'normalized_url'])
                     ->batch($batchSize) as $batch) {
            foreach ($batch as $row) {
                $processed++;

                $source = (string)($row['source_url'] ?? '');
                $found = (string)($row['found_at_url'] ?? '');
                $candidate = $source !== '' ? $source : $found;

                $normalized = $normalizer->normalizeUrlForMatch($candidate, $stripQuery === 1, $stripUtm === 1) ?: null;

                $domain = $normalizer->extractDomain($row['discovery_domain'] ?? null);
                if ($domain === null) {
                    $domain = $normalizer->extractDomain($candidate);
                }

                $changes = [];
                if (($row['discovery_domain'] ?? null) !== $domain) {
                    $changes['discovery_domain'] = $domain;
                }
                if (($row['normalized_url'] ?? null) !== $normalized) {
                    $changes['normalized_url'] = $normalized;
                }

                if ($changes === []) {
                    continue;
                }

                $changes['updated_at'] = $now;
                ArchiverSimilarityImage::updateAll($changes, ['id' => (int)$row['id']]);
                $updated++;
            }
        }

        $this->stdout("Processed: {$processed}\n");
        $this->stdout("Updated: {$updated}\n");

        return self::EXIT_CODE_NORMAL;
    }
}
