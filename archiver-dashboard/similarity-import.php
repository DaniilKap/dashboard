<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var $model object */

$this->title = 'Similarity groups import (loop)';
$importUrl = Url::to(['/notary/archiver-dashboard/import-similarity-groups']);
$rebuildTopUrl = Url::to(['/notary/archiver-dashboard/rebuild-top-from-existing']);

?>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><?= Html::encode($this->title) ?></h3>
        </div>

        <div class="box-body">

            <form id="simImportForm" class="form-inline" onsubmit="return false;" style="margin-bottom:10px;">
                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">min_size</label>
                    <input class="form-control" name="min_size" value="<?= Html::encode($model->min_size) ?>" style="width:90px;">
                </div>

                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">image_type</label>
                    <select class="form-control" name="image_type" style="width:120px;">
                        <option value="" <?= $model->image_type === '' ? 'selected' : '' ?>>all</option>
                        <option value="vm" <?= $model->image_type === 'vm' ? 'selected' : '' ?>>vm</option>
                        <option value="other" <?= $model->image_type === 'other' ? 'selected' : '' ?>>other</option>
                        <option value="mixed" <?= $model->image_type === 'mixed' ? 'selected' : '' ?>>mixed</option>
                    </select>
                </div>

                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">limit</label>
                    <select class="form-control" name="limit" style="width:110px;">
                        <?php foreach ([20, 50, 100, 200] as $v): ?>
                            <option value="<?= $v ?>" <?= ((int)$model->limit === $v) ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">offset</label>
                    <input class="form-control" name="offset" value="<?= Html::encode($model->offset) ?>" style="width:110px;">
                </div>

                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">with_details</label>
                    <select class="form-control" name="with_details" style="width:90px;">
                        <option value="0" <?= ((int)$model->with_details === 0) ? 'selected' : '' ?>>0</option>
                        <option value="1" <?= ((int)$model->with_details === 1) ? 'selected' : '' ?>>1</option>
                    </select>
                </div>

                <div class="form-group" style="margin-right:8px;">
                    <label style="margin-right:6px;">batch</label>
                    <input class="form-control" name="batch" value="<?= Html::encode($model->batch) ?>" style="width:220px;">
                </div>

                <button id="btnStart" class="btn btn-success">Start full export</button>
                <button id="btnRebuildTop" class="btn btn-primary" style="margin-left:6px;">
                    Rebuild TOP (DB only)
                </button>
                <button id="btnStop" class="btn btn-danger" disabled style="margin-left:6px;">Stop</button>
            </form>

            <div class="well well-sm" style="margin-bottom:10px;">
                <b>Status:</b> <span id="stStatus">idle</span>
                &nbsp;|&nbsp; <b>Offset:</b> <span id="stOffset">—</span>
                &nbsp;|&nbsp; <b>Total groups:</b> <span id="stTotal">—</span>
                &nbsp;|&nbsp; <b>Saved groups:</b> <span id="stG">0</span>
                &nbsp;|&nbsp; <b>Saved images:</b> <span id="stI">0</span>
                &nbsp;|&nbsp; <b>Saved distances:</b> <span id="stD">0</span>
                &nbsp;|&nbsp; <b>Requests:</b> <span id="stReq">0</span>
                &nbsp;|&nbsp; <b>Last:</b> <span id="stLast">—</span>
            </div>

            <div id="simLog" style="white-space:pre-wrap; max-height:360px; overflow:auto; border:1px solid #ddd; padding:10px; border-radius:4px; background:#fafafa;"></div>
            <div id="topResult" style="white-space:pre-wrap; margin-bottom:10px;"></div>

        </div>
    </div>

<?php
$js = <<<JS
(function(){
  var importUrl = '{$importUrl}';
  var form = document.getElementById('simImportForm');

  var btnStart = document.getElementById('btnStart');
  var btnStop  = document.getElementById('btnStop');

  var stStatus = document.getElementById('stStatus');
  var stOffset = document.getElementById('stOffset');
  var stTotal  = document.getElementById('stTotal');
  var stG = document.getElementById('stG');
  var stI = document.getElementById('stI');
  var stD = document.getElementById('stD');
  var stReq = document.getElementById('stReq');
  var stLast = document.getElementById('stLast');

  var log = document.getElementById('simLog');

  var running = false;
  var reqCount = 0;
  var totalG = 0, totalI = 0, totalD = 0;
var rebuildTopUrl = '$rebuildTopUrl';
var btnRebuildTop = document.getElementById('btnRebuildTop');
var topResult = document.getElementById('topResult');

btnRebuildTop.addEventListener('click', async function(){
  if (running) return; // чтобы не мешать циклу импорта

  topResult.textContent = 'Rebuild TOP...';
  try {
    // берем image_type и limit из формы (limit — reuse)
    var params = getParams();
    // reset=1 по дефолту
    if (!params.get('reset')) params.append('reset', '1');

    var url = rebuildTopUrl + '?' + params.toString();
    var r = await fetch(url, { credentials: 'same-origin' });
    var data = await r.json();
    topResult.textContent = JSON.stringify(data, null, 2);
  } catch(e) {
    topResult.textContent = 'ERROR: ' + (e && e.message ? e.message : String(e));
  }
});

  
  
  
  
  function setRunning(v){
    running = v;
    btnStart.disabled = v;
    btnStop.disabled = !v;
    stStatus.textContent = v ? 'running' : 'idle';
  }

  function appendLog(obj){
    var s = (typeof obj === 'string') ? obj : JSON.stringify(obj, null, 2);
    log.textContent = (s + "\\n\\n") + log.textContent; // latest on top
  }

  function getParams(){
    var fd = new FormData(form);
    var params = new URLSearchParams();
    fd.forEach(function(v,k){ params.append(k, v); });
    return params;
  }

  async function step(){
    if (!running) return;

    var offsetInput = form.querySelector('input[name="offset"]');
    stOffset.textContent = offsetInput ? offsetInput.value : '—';

    reqCount += 1;
    stReq.textContent = String(reqCount);

    try{
      var url = importUrl + '?' + getParams().toString();
      var r = await fetch(url, { credentials: 'same-origin' });
      var data = await r.json();

      stLast.textContent = data.ts || new Date().toISOString();

      if (data.status !== 'ok') {
        appendLog(data);
        setRunning(false);
        return;
      }

      totalG += parseInt(data.saved_groups, 10) || 0;
      totalI += parseInt(data.saved_images, 10) || 0;
      totalD += parseInt(data.saved_distances, 10) || 0;

      stG.textContent = String(totalG);
      stI.textContent = String(totalI);
      stD.textContent = String(totalD);

      if (data.api && typeof data.api.total_groups !== 'undefined' && data.api.total_groups !== null) {
        stTotal.textContent = String(data.api.total_groups);
      }

      appendLog(data);

      // move offset
      if (typeof data.next_offset !== 'undefined' && data.next_offset !== null) {
        if (offsetInput) offsetInput.value = String(data.next_offset);
        stOffset.textContent = String(data.next_offset);
      }

      if (data.done === true) {
        appendLog("DONE: reached end");
        setRunning(false);
        return;
      }

      // safety: if returned_groups is 0 — stop
      if (data.api && parseInt(data.api.returned_groups,10) === 0) {
        appendLog("STOP: returned_groups=0");
        setRunning(false);
        return;
      }

      // next tick (не долбим слишком жестко)
      if (running) setTimeout(step, 150);
    } catch(e) {
      appendLog("ERROR: " + (e && e.message ? e.message : String(e)));
      setRunning(false);
    }
  }

  btnStart.addEventListener('click', function(){
    if (running) return;

    // reset counters for a new full run
    reqCount = 0; totalG = 0; totalI = 0; totalD = 0;
    stReq.textContent = "0"; stG.textContent="0"; stI.textContent="0"; stD.textContent="0";
    log.textContent = "";

    setRunning(true);
    step();
  });

  btnStop.addEventListener('click', function(){
    setRunning(false);
    appendLog("STOP: user requested");
  });
})();
JS;

$this->registerJs($js);
