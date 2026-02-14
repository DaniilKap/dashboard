<?php
use yii\helpers\Url;

$this->title = 'Archiver Dashboard (Live)';

$startUrl = Url::to(['start']);
$resultUrl = Url::to(['result', 'task_id' => '___TASK___']);
$stopUrl = Url::to(['stop', 'task_id' => '___TASK___']);
$errorUrl = Url::to(['error', 'task_id' => '___TASK___']);

$queueUrl = Url::to(['queue-status']);
$partitionsUrl = Url::to(['partitions-stats']);
$gpuUrl = Url::to(['gpu-stats']);
$skippedVkStatsUrl = Url::to(['skipped-vk-stats']);

$initialTaskId = $taskId ? json_encode($taskId) : 'null';
$csrf = Yii::$app->request->csrfToken;
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Live</h3>
    </div>

    <div class="box-body">


        <div class="row" style="margin-bottom: 10px;">
            <div class="col-md-6">
                <div><b>Task:</b> <span id="task-id">—</span></div>
                <div><b>Status:</b> <span id="task-status">—</span></div>
                <div><b>Processed:</b> <span id="task-processed">—</span></div>
                <div><b>Elapsed:</b> <span id="task-elapsed">—</span></div>
            </div>
            <div class="col-md-6">
                <div><b>Queue (short):</b> <span id="queue-summary">—</span></div>

                <div style="margin-top: 8px;">
                    <label>Частота обновления</label>
                    <select id="refresh-interval" class="form-control" style="max-width: 240px; display:inline-block;">
                        <option value="0">выкл</option>
                        <option value="2000">2 сек</option>
                        <option value="5000">5 сек</option>
                        <option value="10000" selected>10 сек</option>
                        <option value="15000">15 сек</option>
                        <option value="30000">30 сек</option>
                    </select>
                </div>
            </div>
        </div>

        <hr/>

        <div class="row">
            <div class="col-md-7">
                <h4 style="margin-top:0;">Task result (preview)</h4>
                <pre id="result-json" style="max-height: 420px; overflow:auto; background:#111; color:#eaeaea; padding:12px;">{}</pre>
            </div>
            <div class="col-md-5">
                <h4 style="margin-top:0;">### 4. Ошибки задачи</h4>
                <pre id="error-json" style="max-height: 420px; overflow:auto; background:#2b0f0f; color:#ffeaea; padding:12px;">[]</pre>
            </div>
        </div>

        <div class="row" style="margin-top: 10px;">
            <div class="col-md-6">
                <h4 style="margin-top:0;">### 6. Статус очередей</h4>
                <pre id="queue-json" style="max-height: 380px; overflow:auto; background:#0f1d2b; color:#e6f2ff; padding:12px;">{}</pre>
            </div>
            <div class="col-md-6">
                <h4 style="margin-top:0;">Статистика партиций Milvus</h4>
                <pre id="partitions-json" style="max-height: 380px; overflow:auto; background:#0f2b18; color:#eafff0; padding:12px;">{}</pre>
            </div>
        </div>

        <div class="row" style="margin-top: 10px;">
            <div class="col-md-6">
                <h4 style="margin-top:0;">Статистика GPU</h4>
                <pre id="gpu-json" style="max-height: 320px; overflow:auto; background:#25220f; color:#fff7d6; padding:12px;">{}</pre>
            </div>
            <div class="col-md-6">
                <h4 style="margin-top:0;">Статистика пропущенных VK групп</h4>
                <pre id="skippedvk-json" style="max-height: 320px; overflow:auto; background:#1d0f2b; color:#f3e6ff; padding:12px;">{}</pre>
            </div>
        </div>

    </div>
</div>

<script>
    (() => {
        const startUrl = <?= json_encode($startUrl) ?>;
        const resultUrlTpl = <?= json_encode($resultUrl) ?>;
        const stopUrlTpl = <?= json_encode($stopUrl) ?>;
        const errorUrlTpl = <?= json_encode($errorUrl) ?>;

        const queueUrl = <?= json_encode($queueUrl) ?>;
        const partitionsUrl = <?= json_encode($partitionsUrl) ?>;
        const gpuUrl = <?= json_encode($gpuUrl) ?>;
        const skippedVkStatsUrl = <?= json_encode($skippedVkStatsUrl) ?>;

        const csrf = <?= json_encode($csrf) ?>;

        let taskId = <?= $initialTaskId ?>;
        let timer = null;
        let inflight = false;

        const el = (id) => document.getElementById(id);
        const tpl = (urlTpl, id) => urlTpl.replace('___TASK___', encodeURIComponent(id));
        const pretty = (obj) => { try { return JSON.stringify(obj, null, 2); } catch(e) { return String(obj); } };

        function setTaskUI(meta) {
            el('task-id').textContent = meta.task_id || taskId || '—';
            el('task-status').textContent = meta.status || '—';
            el('task-processed').textContent = meta.processed_images ?? '—';
            el('task-elapsed').textContent = meta.elapsed_time ?? '—';
        }

        function setQueueSummary(q) {
            if (!q || q._error) { el('queue-summary').textContent = 'error'; return; }
            el('queue-summary').textContent =
                `user_queue=${q.user_queue_size}, admin_queue=${q.admin_queue_size}, active_total=${q.total_active_tasks}`;
        }

        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(body)
            });
            return await res.json();
        }

        async function getJson(url) {
            const res = await fetch(url, { method: 'GET' });
            return await res.json();
        }

        async function refreshSystemBlocks() {
            // queue
            const q = await getJson(queueUrl);
            el('queue-json').textContent = pretty(q);
            setQueueSummary(q);

            // partitions
            const p = await getJson(partitionsUrl);
            el('partitions-json').textContent = pretty(p);

            // gpu
            const g = await getJson(gpuUrl);
            el('gpu-json').textContent = pretty(g);

            // skipped vk stats
            const s = await getJson(skippedVkStatsUrl);
            el('skippedvk-json').textContent = pretty(s);
        }

        async function refreshTaskBlocks() {
            if (!taskId) return;

            const r = await getJson(tpl(resultUrlTpl, taskId));
            el('result-json').textContent = pretty(r);

            if (r && !r._error) {
                setTaskUI({ task_id: taskId, ...r });
                const st = String(r.status || '').toLowerCase();
                el('btn-stop').disabled = (st !== 'running');
            }

            const e = await getJson(tpl(errorUrlTpl, taskId));
            el('error-json').textContent = pretty(e);
        }

        async function refreshAll() {
            if (inflight) return;
            inflight = true;
            try {
                await refreshSystemBlocks();
                await refreshTaskBlocks();
            } catch (err) {
                el('result-json').textContent = 'Client error: ' + String(err);
            } finally {
                inflight = false;
            }
        }

        function setIntervalMs(ms) {
            if (timer) clearInterval(timer);
            timer = null;
            if (ms > 0) timer = setInterval(refreshAll, ms);
        }

        el('refresh-interval').addEventListener('change', () => {
            setIntervalMs(parseInt(el('refresh-interval').value, 10) || 0);
        });

        

        // init
        refreshAll();
        // ✅ дефолт 10 сек
        setIntervalMs(parseInt(el('refresh-interval').value, 10) || 0);
    })();
</script>
