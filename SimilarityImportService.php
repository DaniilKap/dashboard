<?php

namespace backend\modules\notary\services;

use Yii;
use yii\httpclient\Client;
use backend\modules\notary\models\ArchiverSimilarityDistance;
use backend\modules\notary\models\ArchiverSimilarityGroup;
use backend\modules\notary\models\ArchiverSimilarityImage;

class SimilarityImportService
{
    private string $apiBase;

    public function __construct(string $apiBase)
    {
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function importPage(array $options): array
    {
        $now = time();

        $params = [
            'min_size' => (int)$options['min_size'],
            'limit' => (int)$options['limit'],
            'offset' => (int)$options['offset'],
        ];

        if (($options['image_type'] ?? '') !== '') {
            $params['image_type'] = (string)$options['image_type'];
        }

        $withDetails = (bool)($options['with_details'] ?? false);
        $batch = (string)($options['batch'] ?? ('manual_' . date('Ymd_His')));

        $result = [
            'status' => 'ok',
            'ts' => date('Y-m-d H:i:s'),
            'filters' => $params,
            'batch' => $batch,
            'with_details' => $withDetails,
            'saved_groups' => 0,
            'saved_images' => 0,
            'saved_distances' => 0,
            'errors' => [],
        ];

        $data = $this->fetchPage($params);
        if (!empty($data['_error'])) {
            $result['status'] = 'error';
            $result['errors'][] = $this->formatError(
                'api_page_fetch_failed',
                'load_page',
                null,
                (string)($data['message'] ?? ('HTTP ' . ($data['statusCode'] ?? 'unknown')))
            );
            $result['api_error'] = $data;
            return $result;
        }

        $groups = $data['groups'] ?? [];
        if (!is_array($groups)) {
            $groups = [];
        }

        foreach ($groups as $groupPayload) {
            $gid = (int)($groupPayload['group_id'] ?? 0);
            if ($gid <= 0) {
                $result['errors'][] = $this->formatError('invalid_group_id', 'upsert_groups', null, 'Missing or invalid group_id');
                continue;
            }

            try {
                $isNewGroup = ArchiverSimilarityGroup::find()->where(['group_id' => $gid])->exists() === false;
                $group = ArchiverSimilarityGroup::upsertFromApi($groupPayload, $batch, $now);
                if ($isNewGroup) {
                    $result['saved_groups']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = $this->formatError('group_upsert_failed', 'upsert_groups', $gid, $e->getMessage());
                continue;
            }

            $bestByImageId = [];
            $result['saved_distances'] += $this->upsertDistances(
                $gid,
                $groupPayload['distances'] ?? [],
                $bestByImageId,
                $result['errors'],
                $now,
                'upsert_distances'
            );

            $images = is_array($groupPayload['images'] ?? null) ? $groupPayload['images'] : [];

            if ($withDetails) {
                $detail = $this->fetchDetail($gid);
                if (!empty($detail['_error'])) {
                    $result['errors'][] = $this->formatError(
                        'detail_fetch_failed',
                        'optional_details',
                        $gid,
                        (string)($detail['message'] ?? ('HTTP ' . ($detail['statusCode'] ?? 'unknown')))
                    );
                } else {
                    try {
                        $group->applyStatsFromDetail($detail, $now);
                    } catch (\Throwable $e) {
                        $result['errors'][] = $this->formatError('group_stats_apply_failed', 'optional_details', $gid, $e->getMessage());
                    }

                    $result['saved_distances'] += $this->upsertDistances(
                        $gid,
                        $detail['distances'] ?? [],
                        $bestByImageId,
                        $result['errors'],
                        $now,
                        'optional_details'
                    );

                    if (is_array($detail['images'] ?? null)) {
                        $images = $detail['images'];
                    }
                }
            }

            $result['saved_images'] += $this->upsertImages($gid, $images, $bestByImageId, $result['errors'], $now);
        }

        $apiOffset = (int)($data['offset'] ?? $params['offset']);
        $apiReturned = (int)($data['returned_groups'] ?? count($groups));
        $apiTotal = $data['total_groups'] ?? null;
        $nextOffset = $apiOffset + $apiReturned;

        $done = $apiTotal !== null
            ? ($nextOffset >= (int)$apiTotal)
            : ($apiReturned < (int)$params['limit']);

        $result['api'] = [
            'total_groups' => $apiTotal,
            'returned_groups' => $apiReturned,
            'limit' => (int)$params['limit'],
            'offset' => $apiOffset,
        ];
        $result['next_offset'] = $nextOffset;
        $result['done'] = $done;

        if (!empty($result['errors'])) {
            $result['status'] = 'partial';
        }

        return $result;
    }

    private function upsertDistances(
        int $groupId,
        $distances,
        array &$bestByImageId,
        array &$errors,
        int $now,
        string $stage
    ): int {
        if (!is_array($distances)) {
            return 0;
        }

        $saved = 0;
        foreach ($distances as $distanceRow) {
            $a = (int)($distanceRow['image_1_id'] ?? 0);
            $b = (int)($distanceRow['image_2_id'] ?? 0);
            $dist = isset($distanceRow['distance']) ? (float)$distanceRow['distance'] : null;

            if ($a <= 0 || $b <= 0 || $a === $b || $dist === null) {
                continue;
            }

            try {
                $i1 = min($a, $b);
                $i2 = max($a, $b);
                $isNewPair = ArchiverSimilarityDistance::find()->where([
                    'group_id' => $groupId,
                    'image_1_id' => $i1,
                    'image_2_id' => $i2,
                ])->exists() === false;

                ArchiverSimilarityDistance::upsertPair(
                    $groupId,
                    $a,
                    $b,
                    $dist,
                    $distanceRow['recorded_at'] ?? null,
                    $now
                );

                if ($isNewPair) {
                    $saved++;
                }

                if (!isset($bestByImageId[$a]) || $dist < $bestByImageId[$a]) {
                    $bestByImageId[$a] = $dist;
                }
                if (!isset($bestByImageId[$b]) || $dist < $bestByImageId[$b]) {
                    $bestByImageId[$b] = $dist;
                }
            } catch (\Throwable $e) {
                $errors[] = $this->formatError('distance_upsert_failed', $stage, $groupId, $e->getMessage());
            }
        }

        return $saved;
    }

    private function upsertImages(int $groupId, $images, array $bestByImageId, array &$errors, int $now): int
    {
        if (!is_array($images)) {
            return 0;
        }

        $saved = 0;
        foreach ($images as $imagePayload) {
            $imageId = (int)($imagePayload['id'] ?? 0);
            if ($imageId <= 0) {
                continue;
            }

            try {
                $isNewImage = ArchiverSimilarityImage::find()->where([
                    'group_id' => $groupId,
                    'image_id' => $imageId,
                ])->exists() === false;

                ArchiverSimilarityImage::upsertFromApi(
                    $groupId,
                    $imagePayload,
                    isset($bestByImageId[$imageId]) ? (float)$bestByImageId[$imageId] : null,
                    $now
                );

                if ($isNewImage) {
                    $saved++;
                }
            } catch (\Throwable $e) {
                $errors[] = $this->formatError('image_upsert_failed', 'upsert_images', $groupId, $e->getMessage());
            }
        }

        return $saved;
    }

    private function fetchPage(array $params): array
    {
        return $this->apiGet('/api/v1/archiver/similarity_groups', $params);
    }

    private function fetchDetail(int $groupId): array
    {
        return $this->apiGet('/api/v1/archiver/similarity_groups/' . $groupId);
    }

    private function apiGet(string $path, array $query = []): array
    {
        if ($this->apiBase === '') {
            return ['_error' => true, 'message' => 'archiverApiBase is empty in Yii::$app->params'];
        }

        $url = $this->apiBase . $path;
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        Yii::info('[ARCHIVER API] GET ' . $url, 'archiver');

        $client = new Client(['transport' => 'yii\\httpclient\\CurlTransport']);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->setOptions(['timeout' => 60, 'connectTimeout' => 15])
            ->send();

        if (!$response->isOk) {
            Yii::error('[ARCHIVER API] HTTP ' . $response->statusCode . ' ' . $response->content, 'archiver');
            return [
                '_error' => true,
                'statusCode' => $response->statusCode,
                'body' => $response->content,
                'message' => 'API request failed',
            ];
        }

        return is_array($response->data) ? $response->data : ['raw' => $response->content];
    }

    private function formatError(string $code, string $stage, ?int $groupId, string $message): array
    {
        return [
            'code' => $code,
            'stage' => $stage,
            'group' => $groupId,
            'message' => $message,
        ];
    }
}
