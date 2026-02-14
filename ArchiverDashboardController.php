<?php

namespace backend\modules\notary\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\filters\AccessControl;
use yii\httpclient\Client;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\db\Query;
use yii\data\ArrayDataProvider;
use backend\modules\notary\services\SimilarityGroupsImporter;
use backend\modules\notary\models\ArchiverSimilarityGroup;
use backend\modules\notary\models\ArchiverSimilarityImage;
use backend\modules\notary\models\ArchiverSimilarityDistance;

class ArchiverDashboardController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['@']],
                ],
            ],
        ];
    }

    private function apiBase(): string
    {
        // Лучше положить в params.php / module params
        return Yii::$app->params['archiverApiBase'] ?? 'http://185.189.167.89:8000';
    }

    private function apiRequest(string $method, string $path, array $query = []): array
    {
        $client = new Client([
            'baseUrl' => rtrim($this->apiBase(), '/'),
            'transport' => 'yii\httpclient\CurlTransport',
        ]);

        $req = $client->createRequest()
            ->setMethod($method)
            ->setUrl($path)
            ->setOptions([
                'timeout' => 30,
                'connectTimeout' => 10,
            ]);

        if (!empty($query)) {
            // Для GET уйдет как query string, для POST — как form data (норм для твоей доки)
            $req->setData($query);
        }

        // Если нужен токен:
        // $req->addHeaders(['Authorization' => 'Bearer ' . (Yii::$app->params['archiverApiToken'] ?? '')]);

        $resp = $req->send();
        if (!$resp->isOk) {
            return [
                '_error' => true,
                'statusCode' => $resp->statusCode,
                'body' => $resp->content,
            ];
        }

        $data = $resp->data;
        return is_array($data) ? $data : ['raw' => $resp->content];
    }
    public function actionSimilarityGroups()
    {
        $q = ArchiverSimilarityGroup::find();

        $type = Yii::$app->request->get('type'); // vm|other|mixed
        if ($type === 'vm') $q->andWhere(['>', 'vm_count', 0]);
        elseif ($type === 'other') $q->andWhere(['>', 'other_count', 0]);
        elseif ($type === 'mixed') $q->andWhere(['is_mixed' => 1]);

        $minSize = Yii::$app->request->get('min_size');
        if ($minSize !== null && $minSize !== '') $q->andWhere(['>=', 'image_count', (int)$minSize]);

        $sort = Yii::$app->request->get('sort', 'updated'); // updated|count|avg
        if ($sort === 'count') {
            $q->orderBy(['image_count' => SORT_DESC, 'group_id' => SORT_DESC]);
        } elseif ($sort === 'avg') {
            $q->orderBy([
                new \yii\db\Expression('avg_distance IS NULL ASC'),
                'avg_distance' => SORT_ASC,
                'group_id' => SORT_DESC,
            ]);
        } else {
            $q->orderBy(['updated_at' => SORT_DESC, 'group_id' => SORT_DESC]);
        }

        $dp = new ActiveDataProvider([
            'query' => $q,
            'pagination' => ['pageSize' => (int)Yii::$app->request->get('per_page', 50)],
        ]);

        return $this->render('similarity-groups', ['dataProvider' => $dp]);
    }


    // --- 3.3 LIST images ---
    public function actionSimilarityImages()
    {
        $q = ArchiverSimilarityImage::find()->alias('i')
            ->innerJoin(['g' => ArchiverSimilarityGroup::tableName()], 'g.group_id = i.group_id');

        $groupId = Yii::$app->request->get('group_id');
        if ($groupId) $q->andWhere(['i.group_id' => (int)$groupId]);

        $type = Yii::$app->request->get('type'); // vm|other
        if ($type) $q->andWhere(['i.image_type' => $type]);

        $domain = Yii::$app->request->get('domain');
        if ($domain) $q->andWhere(['like', 'i.discovery_domain', $domain]);

        $sort = Yii::$app->request->get('sort', 'distance'); // distance|created
        if ($sort === 'created') {
            $q->orderBy([
                new \yii\db\Expression('i.created_at_api IS NULL ASC'),
                'i.created_at_api' => SORT_DESC,
                'i.id' => SORT_DESC,
            ]);

        } else {
            $q->orderBy([
                new \yii\db\Expression('i.best_distance IS NULL ASC'),
                'i.best_distance' => SORT_ASC,
                'i.id' => SORT_ASC,
            ]);

        }

        $dp = new ActiveDataProvider([
            'query' => $q->select(['i.*', 'g.is_mixed', 'g.image_count', 'g.avg_distance']),
            'pagination' => ['pageSize' => (int)Yii::$app->request->get('per_page', 50)],
        ]);

        return $this->render('similarity-images', ['dataProvider' => $dp]);
    }



    /**
     * Надежный GET с query string + лог строки запроса
     */
    private function apiGet(string $path, array $query = []): array
    {
        $base = rtrim($this->apiBase(), '/');
        if ($base === '') {
            return ['_error' => true, 'message' => 'archiverApiBase is empty in Yii::$app->params'];
        }

        $url = $base . $path;
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        Yii::info('[ARCHIVER API] GET ' . $url, 'archiver');

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);

        $req = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->setOptions(['timeout' => 60, 'connectTimeout' => 15]);

        // если нужна авторизация — раскомментируй:
        // $token = Yii::$app->params['archiverApiToken'] ?? '';
        // if ($token !== '') $req->addHeaders(['Authorization' => 'Bearer ' . $token]);

        $resp = $req->send();

        if (!$resp->isOk) {
            Yii::error('[ARCHIVER API] HTTP ' . $resp->statusCode . ' ' . $resp->content, 'archiver');
            return ['_error' => true, 'statusCode' => $resp->statusCode, 'body' => $resp->content];
        }

        $data = $resp->data;
        return is_array($data) ? $data : ['raw' => $resp->content];
    }

    /**
     * UI page (кнопка Start/Stop, цикличный импорт)
     */
    public function actionSimilarityImport()
    {
        if (!Yii::$app->user->can('admin')) {
            throw new \yii\web\ForbiddenHttpException('Admins only');
        }

        $defaults = [
            'min_size' => 3,
            'image_type' => 'mixed', // vm|other|mixed|''(all)
            'limit' => 50,
            'offset' => 0,
            'with_details' => 0,
            'batch' => 'manual_' . date('Ymd_His'),
        ];

        $model = (object)array_merge($defaults, Yii::$app->request->get());

        return $this->render('similarity-import', [
            'model' => $model,
        ]);
    }
    public function actionSimilarityBigGroups()
    {


        $onlyTop = (int)Yii::$app->request->get('only_top', 1); // 1 = только топовые
        $minSize = (int)Yii::$app->request->get('min_size', 0); // например 10
        $perPage = (int)Yii::$app->request->get('per_page', 50);

        $q = ArchiverSimilarityGroup::find();

        if ($onlyTop === 1) {
            $q->andWhere(['is_top' => 1]);
        }

        if ($minSize > 0) {
            $q->andWhere(['>=', 'image_count', $minSize]);
        }

        $q->orderBy(['image_count' => SORT_DESC, 'group_id' => SORT_DESC]);

        $dp = new ActiveDataProvider([
            'query' => $q,
            'pagination' => ['pageSize' => $perPage],
        ]);

        return $this->render('similarity-big-groups', [
            'dataProvider' => $dp,
            'onlyTop' => $onlyTop,
            'minSize' => $minSize,
        ]);
    }
    public function actionSimilarityVkGroups()
    {


        $limit = max(10, (int)Yii::$app->request->get('limit', 10000));
        $t = '{{%archiver_similarity_image}}';

        // Нормализуем URL группы:
        // - убираем query и trailing slash
        // - оставляем https://vk.com/<slug>
        $norm = new Expression("
      LOWER(
        REGEXP_REPLACE(
          REGEXP_SUBSTR(found_at_url, 'https?://vk\\.com/[^\\?\\#\\s]+'),
          '/+$',
          ''
        )
      )
    ");

        $rows = (new \yii\db\Query())
            ->from($t)
            ->select([
                'vk_group' => $norm,
                'images' => new Expression("COUNT(*)"),
                'groups' => new Expression("COUNT(DISTINCT group_id)"),
            ])
            ->where(['like', 'found_at_url', 'vk.com/', false])
            ->andWhere(['not', ['found_at_url' => null]])
            ->groupBy($norm)
            ->orderBy(['images' => SORT_DESC])
            ->limit($limit)
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $u = (string)($r['vk_group'] ?? '');
            if ($u === '') continue;
            $out[] = [
                'vk_group' => $u,
                'images' => (int)$r['images'],
                'groups' => (int)$r['groups'],
            ];
        }

        $dp = new \yii\data\ArrayDataProvider([
            'allModels' => $out,
            'pagination' => ['pageSize' => 100],
            'sort' => false,
        ]);

        return $this->render('similarity-vk-groups', [
            'dataProvider' => $dp,
        ]);
    }

    public function actionSimilaritySites()
    {


        $type = (string)Yii::$app->request->get('type', 'found'); // found|source|both
        $limit = max(10, (int)Yii::$app->request->get('limit', 5000));

        // Базовая таблица
        $t = '{{%archiver_similarity_image}}';

        // host из URL (MySQL 8 REGEXP_SUBSTR)
        // https?://<host>/
        $hostExpr = function(string $col) {
            return new Expression("LOWER(REGEXP_SUBSTR($col, 'https?://[^/]+'))");
        };

        // Для "both" берём COALESCE(found_at_url, source_url)
        $urlExpr = ($type === 'source')
            ? new Expression("source_url")
            : (($type === 'both')
                ? new Expression("COALESCE(found_at_url, source_url)")
                : new Expression("found_at_url"));

        $host = new Expression("LOWER(REGEXP_SUBSTR($urlExpr, 'https?://[^/]+'))");

        $rows = (new Query())
            ->from($t)
            ->select([
                'host' => $host, // вернёт 'https://domain.tld'
                'images' => new Expression("COUNT(*)"),
                'groups' => new Expression("COUNT(DISTINCT group_id)"),
            ])
            ->where(new Expression("$urlExpr IS NOT NULL"))
            ->andWhere(new Expression("TRIM($urlExpr) <> ''"))
            ->groupBy($host)
            ->orderBy(['images' => SORT_DESC])
            ->limit($limit)
            ->all();

        // Приведём host к domain без схемы для красоты
        $out = [];
        foreach ($rows as $r) {
            $h = (string)($r['host'] ?? '');
            if ($h === '') continue;

            $domain = preg_replace('~^https?://~i', '', $h);
            $out[] = [
                'domain' => $domain,
                'host' => $h,
                'images' => (int)$r['images'],
                'groups' => (int)$r['groups'],
            ];
        }

        $dp = new ArrayDataProvider([
            'allModels' => $out,
            'pagination' => ['pageSize' => 100],
            'sort' => false,
        ]);

        return $this->render('similarity-sites', [
            'dataProvider' => $dp,
            'type' => $type,
        ]);
    }

    /**
     * API importer: 1 page -> DB (groups + images + distances)
     * GET /notary/archiver-dashboard/import-similarity-groups?min_size=3&image_type=mixed&limit=50&offset=0&with_details=0&batch=...
     */
    public function actionImportSimilarityGroups()
    {
        if (!Yii::$app->user->can('admin')) {
            throw new \yii\web\ForbiddenHttpException('Admins only');
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        $minSize = (int)Yii::$app->request->get('min_size', 2);
        $imageType = (string)Yii::$app->request->get('image_type', ''); // vm|other|mixed|''
        $limit = max(1, (int)Yii::$app->request->get('limit', 50));
        $offset = max(0, (int)Yii::$app->request->get('offset', 0));
        $withDetails = ((int)Yii::$app->request->get('with_details', 0) === 1);

        $batch = (string)Yii::$app->request->get('batch', '');
        if ($batch === '') $batch = 'manual_' . date('Ymd_His');

        $params = [
            'min_size' => $minSize,
            'limit' => $limit,
            'offset' => $offset,
        ];
        if ($imageType !== '') $params['image_type'] = $imageType;

        // ✅ 1) Реальный запрос к /api/v1/archiver/similarity_groups
        $data = $this->apiGet('/api/v1/archiver/similarity_groups', $params); //
        if (!empty($data['_error'])) {
            $data['ts'] = date('Y-m-d H:i:s');
            $data['filters'] = $params;
            return $data;
        }

        $now = time();
        $savedGroups = 0;
        $savedImages = 0;
        $savedDistances = 0;

        $groups = $data['groups'] ?? [];
        if (!is_array($groups)) $groups = [];

        foreach ($groups as $g) {
            $gid = (int)($g['group_id'] ?? 0);
            if ($gid <= 0) continue;

            // ✅ 2) upsert group
            $group = ArchiverSimilarityGroup::findOne(['group_id' => $gid]);
            if (!$group) {
                $group = new ArchiverSimilarityGroup();
                $group->group_id = $gid;
                $group->created_at = $now;
                $savedGroups++;
            }

            $group->created_at_api = $g['created_at'] ?? null;
            $group->image_count = (int)($g['image_count'] ?? 0);
            $group->is_mixed = !empty($g['is_mixed']) ? 1 : 0;

            $tc = $g['type_counts'] ?? [];
            $group->vm_count = (int)($tc['vm'] ?? 0);
            $group->other_count = (int)($tc['other'] ?? 0);

            $group->avg_distance = isset($g['avg_distance']) ? (float)$g['avg_distance'] : null;

            $group->import_batch = $batch;
            $group->updated_at = $now;
            $group->save(false);

            // ✅ 3) distances[] -> table + best_distance map
            $bestByImageId = [];
            $distances = $g['distances'] ?? [];
            if (is_array($distances)) {
                foreach ($distances as $d) {
                    $a = (int)($d['image_1_id'] ?? 0);
                    $b = (int)($d['image_2_id'] ?? 0);
                    $dist = isset($d['distance']) ? (float)$d['distance'] : null;
                    if ($a <= 0 || $b <= 0 || $dist === null || $a === $b) continue;

                    $recordedAt = $d['recorded_at'] ?? null;

                    // normalize pair
                    $i1 = min($a, $b);
                    $i2 = max($a, $b);

                    $pair = ArchiverSimilarityDistance::findOne([
                        'group_id' => $gid,
                        'image_1_id' => $i1,
                        'image_2_id' => $i2,
                    ]);

                    if (!$pair) {
                        $pair = new ArchiverSimilarityDistance();
                        $pair->group_id = $gid;
                        $pair->image_1_id = $i1;
                        $pair->image_2_id = $i2;
                        $pair->created_at = $now;
                        $savedDistances++;
                    }

                    $pair->distance = (float)$dist;
                    $pair->recorded_at_api = $recordedAt;
                    $pair->updated_at = $now;
                    $pair->save(false);

                    if (!isset($bestByImageId[$a]) || $dist < $bestByImageId[$a]) $bestByImageId[$a] = $dist;
                    if (!isset($bestByImageId[$b]) || $dist < $bestByImageId[$b]) $bestByImageId[$b] = $dist;
                }
            }

            // ✅ 4) images[] -> table
            $images = $g['images'] ?? [];
            if (!is_array($images)) $images = [];

            // (опционально) detail, если включишь with_details
            if ($withDetails) {
                $detail = $this->apiGet('/api/v1/archiver/similarity_groups/' . $gid, []); //
                if (empty($detail['_error'])) {
                    // stats на группу
                    $stats = $detail['statistics'] ?? null;
                    if (is_array($stats)) {
                        $group->min_distance = isset($stats['min_distance']) ? (float)$stats['min_distance'] : null;
                        $group->max_distance = isset($stats['max_distance']) ? (float)$stats['max_distance'] : null;
                        $group->avg_distance_stats = isset($stats['avg_distance']) ? (float)$stats['avg_distance'] : null;
                        $group->updated_at = $now;
                        $group->save(false);
                    }

                    // distances detail (может быть богаче)
                    if (is_array($detail['distances'] ?? null)) {
                        foreach ($detail['distances'] as $d) {
                            $a = (int)($d['image_1_id'] ?? 0);
                            $b = (int)($d['image_2_id'] ?? 0);
                            $dist = isset($d['distance']) ? (float)$d['distance'] : null;
                            if ($a <= 0 || $b <= 0 || $dist === null || $a === $b) continue;

                            $recordedAt = $d['recorded_at'] ?? null;

                            $i1 = min($a, $b);
                            $i2 = max($a, $b);

                            $pair = ArchiverSimilarityDistance::findOne([
                                'group_id' => $gid,
                                'image_1_id' => $i1,
                                'image_2_id' => $i2,
                            ]);

                            if (!$pair) {
                                $pair = new ArchiverSimilarityDistance();
                                $pair->group_id = $gid;
                                $pair->image_1_id = $i1;
                                $pair->image_2_id = $i2;
                                $pair->created_at = $now;
                                $savedDistances++;
                            }

                            $pair->distance = (float)$dist;
                            $pair->recorded_at_api = $recordedAt;
                            $pair->updated_at = $now;
                            $pair->save(false);

                            if (!isset($bestByImageId[$a]) || $dist < $bestByImageId[$a]) $bestByImageId[$a] = $dist;
                            if (!isset($bestByImageId[$b]) || $dist < $bestByImageId[$b]) $bestByImageId[$b] = $dist;
                        }
                    }

                    if (is_array($detail['images'] ?? null)) {
                        $images = $detail['images'];
                    }
                }
            }

            foreach ($images as $im) {
                $imageId = (int)($im['id'] ?? 0);
                if ($imageId <= 0) continue;

                $row = ArchiverSimilarityImage::findOne(['group_id' => $gid, 'image_id' => $imageId]);
                if (!$row) {
                    $row = new ArchiverSimilarityImage();
                    $row->group_id = $gid;
                    $row->image_id = $imageId;
                    $row->created_at = $now;
                    $savedImages++;
                }

                $type = (string)($im['image_type'] ?? 'other');
                if (!in_array($type, ['vm', 'other'], true)) $type = 'other';

                $row->image_type = $type;
                $row->filename = $im['filename'] ?? null;
                $row->image_hash = $im['image_hash'] ?? null;
                $row->milvus_id = isset($im['milvus_id']) ? (int)$im['milvus_id'] : null;
                $row->source_url = $im['source_url'] ?? null;
                $row->discovery_domain = $im['discovery_domain'] ?? null;
                $row->found_at_url = $im['found_at_url'] ?? null;

// если API не дал discovery_domain, попробуем вывести из found_at_url / source_url
                if (!$row->discovery_domain) {
                    $candidate = $row->found_at_url ?: $row->source_url;
                    if ($candidate) {
                        $host = parse_url($candidate, PHP_URL_HOST);
                        if ($host) $row->discovery_domain = $host;
                    }
                }




                $row->created_at_api = $im['created_at'] ?? null;
                $row->added_to_group_at = $im['added_to_group_at'] ?? null;

                $row->best_distance = isset($bestByImageId[$imageId]) ? (float)$bestByImageId[$imageId] : null;

                $row->updated_at = $now;
                $row->save(false);
            }
        }

        $apiOffset = (int)($data['offset'] ?? $offset);
        $apiReturned = (int)($data['returned_groups'] ?? count($groups));
        $apiTotal = $data['total_groups'] ?? null;
        $nextOffset = $apiOffset + $apiReturned;

        $done = false;
        if ($apiTotal !== null) {
            $done = ($nextOffset >= (int)$apiTotal);
        } else {
            $done = ($apiReturned < $limit);
        }

        return [
            'status' => 'ok',
            'ts' => date('Y-m-d H:i:s'),
            'filters' => $params,
            'batch' => $batch,
            'with_details' => $withDetails,

            'saved_groups' => $savedGroups,
            'saved_images' => $savedImages,
            'saved_distances' => $savedDistances,

            'api' => [
                'total_groups' => $apiTotal,
                'returned_groups' => $apiReturned,
                'limit' => $limit,
                'offset' => $apiOffset,
            ],

            'next_offset' => $nextOffset,
            'done' => $done,
        ];
    }

    // --- 3.4 LIST distances ---
    public function actionSimilarityDistances()
    {
        $q = ArchiverSimilarityDistance::find();

        $groupId = Yii::$app->request->get('group_id');
        if ($groupId) $q->andWhere(['group_id' => (int)$groupId]);

        $imageId = Yii::$app->request->get('image_id');
        if ($imageId) {
            $iid = (int)$imageId;
            $q->andWhere(['or', ['image_1_id' => $iid], ['image_2_id' => $iid]]);
        }

        $q->orderBy(['distance' => SORT_ASC, 'id' => SORT_ASC]);

        $dp = new ActiveDataProvider([
            'query' => $q,
            'pagination' => ['pageSize' => (int)Yii::$app->request->get('per_page', 100)],
        ]);

        return $this->render('similarity-distances', ['dataProvider' => $dp]);
    }
    public function actionIndex()
    {
        $taskId = Yii::$app->request->get('task_id');
        return $this->render('index', [
            'taskId' => $taskId,
        ]);
    }

    public function actionPartitionsStats()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/partitions/stats
        return $this->apiRequest('GET', '/api/v1/archiver/partitions/stats');
    }

    public function actionGpuStats()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/gpu_stats
        return $this->apiRequest('GET', '/api/v1/archiver/gpu_stats');
    }

    public function actionSkippedVkStats()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/skipped_vk_groups/stats
        return $this->apiRequest('GET', '/api/v1/archiver/skipped_vk_groups/stats');
    }

    public function actionStart()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $url = trim((string)Yii::$app->request->post('url', ''));
        if ($url === '') {
            throw new BadRequestHttpException('url is required');
        }

        // POST /api/v1/archiver/start_comparer?url=...
        return $this->apiRequest('POST', '/api/v1/archiver/start_comparer', ['url' => $url]);
    }

    public function actionResult($task_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/result/{task_id}
        return $this->apiRequest('GET', '/api/v1/archiver/result/' . $task_id);
    }

    public function actionStop($task_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // POST /api/v1/archiver/stop/{task_id}
        return $this->apiRequest('POST', '/api/v1/archiver/stop/' . $task_id);
    }

    public function actionError($task_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/error/{task_id}
        return $this->apiRequest('GET', '/api/v1/archiver/error/' . $task_id);
    }

    public function actionQueueStatus()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // GET /api/v1/archiver/queue_status
        return $this->apiRequest('GET', '/api/v1/archiver/queue_status');
    }


    public function actionRebuildTopFromExisting()
    {
        if (!Yii::$app->user->can('admin')) {
            throw new \yii\web\ForbiddenHttpException('Admins only');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $imageType = (string)Yii::$app->request->get('image_type', 'mixed'); // '', vm, other, mixed
        $limit = max(1, (int)Yii::$app->request->get('limit', 500));
        $reset = ((int)Yii::$app->request->get('reset', 1) === 1);

        // 1) Забираем TOP
        $params = ['limit' => $limit];
        if ($imageType !== '') $params['image_type'] = $imageType;

        $top = $this->apiGet('/api/v1/archiver/stats/top_images', $params); // :contentReference[oaicite:2]{index=2}
        if (!empty($top['_error'])) {
            return $top;
        }

        $items = $top['top_recurring_images'] ?? [];
        if (!is_array($items)) $items = [];

        $now = time();

        // 2) Сброс флагов
        $resetGroups = 0;
        $resetImages = 0;

        if ($reset) {
            $resetGroups = ArchiverSimilarityGroup::updateAll([
                'is_top' => 0,
                'top_occurrence_count' => null,
                'top_avg_similarity' => null,
                'updated_at' => $now,
            ]);

            $resetImages = ArchiverSimilarityImage::updateAll([
                'is_top_group' => 0,
                'updated_at' => $now,
            ]);
        }

        // 3) Собираем:
        // - group_id -> мета
        // - список URL (source_urls + sample_image.url)
        $topByGroup = [];
        $urls = [];

        foreach ($items as $it) {
            $gid = (int)($it['group_id'] ?? 0);
            if ($gid > 0) {
                $topByGroup[$gid] = [
                    'occurrence_count' => isset($it['occurrence_count']) ? (int)$it['occurrence_count'] : null,
                    'avg_similarity' => isset($it['avg_similarity']) ? (float)$it['avg_similarity'] : null,
                ];
            }

            // source_urls[]
            if (!empty($it['source_urls']) && is_array($it['source_urls'])) {
                foreach ($it['source_urls'] as $u) {
                    $u = $this->normalizeUrlForMatch((string)$u);
                    if ($u !== '') $urls[$u] = true;
                }
            }

            // sample_image.url
            $sampleUrl = $it['sample_image']['url'] ?? null; // :contentReference[oaicite:3]{index=3}
            if (is_string($sampleUrl)) {
                $u = $this->normalizeUrlForMatch($sampleUrl);
                if ($u !== '') $urls[$u] = true;
            }
        }

        $groupIds = array_keys($topByGroup);
        $urlList = array_keys($urls);

        // 4) Проставляем TOP в groups по group_id
        $markedGroups = 0;
        $markedGroupsIds = 0;

        if (!empty($groupIds)) {
            $markedGroups = ArchiverSimilarityGroup::updateAll([
                'is_top' => 1,
                'updated_at' => $now,
            ], ['in', 'group_id', $groupIds]);

            // occurrence_count / avg_similarity — точечно по group_id
            foreach ($topByGroup as $gid => $meta) {
                ArchiverSimilarityGroup::updateAll([
                    'top_occurrence_count' => $meta['occurrence_count'],
                    'top_avg_similarity' => $meta['avg_similarity'],
                    'updated_at' => $now,
                ], ['group_id' => (int)$gid]);
                $markedGroupsIds++;
            }
        }

        // 5) Проставляем TOP в images:
        // 5.1) по group_id (если группы уже в БД)
        $markedImagesByGroup = 0;
        if (!empty($groupIds)) {
            $markedImagesByGroup = ArchiverSimilarityImage::updateAll([
                'is_top_group' => 1,
                'updated_at' => $now,
            ], ['in', 'group_id', $groupIds]);
        }

        // 5.2) по URL (важный момент)
        $markedImagesByUrl = 0;
        if (!empty($urlList)) {
            // Сравниваем по LOWER(source_url) с LOWER(переданных URL),
            // потому что в top_images в примере есть "Https://..." :contentReference[oaicite:4]{index=4}
            $lowerUrls = array_map(static fn($s) => mb_strtolower($s, 'UTF-8'), $urlList);

            $markedImagesByUrl = ArchiverSimilarityImage::updateAll(
                ['is_top_group' => 1, 'updated_at' => $now],
                ['in', new Expression('LOWER(source_url)'), $lowerUrls]
            );
        }

        return [
            'status' => 'ok',
            'ts' => date('Y-m-d H:i:s'),
            'request' => [
                'image_type' => $imageType,
                'limit' => $limit,
                'reset' => $reset ? 1 : 0,
            ],
            'top' => [
                'items' => count($items),
                'unique_group_ids' => count($groupIds),
                'unique_urls' => count($urlList),
            ],
            'reset' => [
                'groups_rows' => $resetGroups,
                'images_rows' => $resetImages,
            ],
            'marked' => [
                'groups_rows_is_top' => $markedGroups,
                'groups_rows_updated_meta' => $markedGroupsIds,
                'images_rows_by_group_id' => $markedImagesByGroup,
                'images_rows_by_url' => $markedImagesByUrl,
            ],
        ];
    }

    /**
     * Нормализация URL под матчинг в БД:
     * - trim
     * - убрать #fragment
     * - НЕ трогаем query (бывает важен), но можно будет расширить
     */
    private function normalizeUrlForMatch(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';

        // remove fragment
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) $url = substr($url, 0, $hashPos);

        return $url;
    }
    public function actionSimilarityGroupsWithVm()
    {


        $gTable = ArchiverSimilarityGroup::tableName();
        $iTable = ArchiverSimilarityImage::tableName();

        // Подзапрос: "первая" VM-картинка в группе (по best_distance, затем id)
        $vmImageIdSql = new Expression("
        (SELECT i2.image_id
         FROM {$iTable} i2
         WHERE i2.group_id = g.group_id AND i2.image_type = 'vm'
         ORDER BY (i2.best_distance IS NULL) ASC, i2.best_distance ASC, i2.id ASC
         LIMIT 1)
    ");

        $vmSourceUrlSql = new Expression("
        (SELECT i3.filename
         FROM {$iTable} i3
         WHERE i3.group_id = g.group_id AND i3.image_type = 'vm'
         ORDER BY (i3.best_distance IS NULL) ASC, i3.best_distance ASC, i3.id ASC
         LIMIT 1)
    ");
        $LowDistantUrlSql = new Expression("
        (SELECT i3.best_distance
         FROM {$iTable} i3
         WHERE i3.group_id = g.group_id and best_distance>0
         ORDER BY (i3.best_distance IS NULL) ASC, i3.best_distance ASC, i3.id ASC
         LIMIT 1)
    ");

        $q = ArchiverSimilarityGroup::find()->alias('g')
            ->innerJoin(['i' => $iTable], 'i.group_id = g.group_id')
            ->andWhere(['i.image_type' => 'vm'])
            ->groupBy('g.group_id')
            ->select([
                'g.*',
                'vm_images' => new Expression("SUM(CASE WHEN i.image_type='vm' THEN 1 ELSE 0 END)"),
                'other_images' => new Expression("SUM(CASE WHEN i.image_type='other' THEN 1 ELSE 0 END)"),
                'vm_image_id' => $vmImageIdSql,
                'vm_source_url' => $vmSourceUrlSql,
                'low_dist' => $LowDistantUrlSql,
            ]);

        // фильтры (опционально)
        $minSize = Yii::$app->request->get('min_size');
        if ($minSize !== null && $minSize !== '') {
            $q->andWhere(['>=', 'g.image_count', (int)$minSize]);
        }

        $onlyTop = (int)Yii::$app->request->get('only_top', 0);
        if ($onlyTop === 1) {
            $q->andWhere(['g.is_top' => 1]);
        }

        // сортировки
        $sort = Yii::$app->request->get('sort', 'updated'); // updated|count|avg|top
        if ($sort === 'count') {
            $q->orderBy(['g.image_count' => SORT_DESC, 'g.group_id' => SORT_DESC]);
        } elseif ($sort === 'avg') {
            $q->orderBy([
                new Expression('g.avg_distance IS NULL ASC'),
                'g.avg_distance' => SORT_ASC,
                'g.group_id' => SORT_DESC,
            ]);
        } elseif ($sort === 'top') {
            $q->orderBy([
                new Expression('g.is_top = 0 ASC'),
                new Expression('g.top_occurrence_count IS NULL ASC'),
                'g.top_occurrence_count' => SORT_DESC,
                'g.group_id' => SORT_DESC,
            ]);
        } else {
            $q->orderBy(['low_dist' => SORT_ASC,]);
        }

        $dp = new ActiveDataProvider([
            'query' => $q->asArray(), // важно: чтобы были vm_image_id / vm_source_url
            'pagination' => ['pageSize' => (int)Yii::$app->request->get('per_page', 50)],
        ]);

        return $this->render('similarity-groups-with-vm', [
            'dataProvider' => $dp,
        ]);
    }
    public function actionSimilarityImageReport($image_id)
    {


        $imageId = (int)$image_id;

        $main = ArchiverSimilarityImage::find()
            ->where(['image_id' => $imageId])
            ->orderBy(['updated_at' => SORT_DESC])
            ->one();

        if (!$main) {
            throw new NotFoundHttpException('Image not found in DB: ' . $imageId);
        }

        $group = ArchiverSimilarityGroup::findOne(['group_id' => (int)$main->group_id]);

        // Остальные картинки этой группы + distance к выбранной
        // join на distances по нормализованной паре (min/max)
        $gid = (int)$main->group_id;

        $q = ArchiverSimilarityImage::find()->alias('i')
            ->where(['i.group_id' => $gid])
            ->andWhere(['<>', 'i.image_id', $imageId]);

        // LEFT JOIN distances:
        // d.image_1_id = LEAST(:main, i.image_id) AND d.image_2_id = GREATEST(:main, i.image_id)
        $q->leftJoin(['d' => '{{%archiver_similarity_distance}}'],
            'd.group_id = i.group_id AND d.image_1_id = LEAST(:mainId, i.image_id) AND d.image_2_id = GREATEST(:mainId, i.image_id)',
            [':mainId' => $imageId]
        );

        // Вычислим "день"
        $dayExpr = new Expression("DATE(COALESCE(i.added_to_group_at, i.created_at_api))");

        $rows = $q->select([
            'i.*',
            'pair_distance' => 'd.distance',
            'day' => $dayExpr,
        ])
            ->orderBy([
                new Expression('day IS NULL ASC'), // NULL (без даты) внизу
                'day' => SORT_DESC,
                new Expression('pair_distance IS NULL ASC'),
                'pair_distance' => SORT_ASC,
                'i.id' => SORT_ASC,
            ])
            ->asArray()
            ->all();

        // Группируем по дням
        $byDay = [];
        foreach ($rows as $r) {
            $day = $r['day'] ?: 'Без даты';
            if (!isset($byDay[$day])) $byDay[$day] = [];
            $byDay[$day][] = $r;
        }

        return $this->render('similarity-image-report', [
            'main' => $main,
            'group' => $group,
            'byDay' => $byDay,
        ]);
    }

}
