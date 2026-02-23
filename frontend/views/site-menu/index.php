<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $models common\models\SiteServiceMenu[] */
/* @var $pagination yii\data\Pagination */

$this->title = 'จัดการเมนูระบบงาน';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-th-list me-2"></i><?= Html::encode($this->title) ?></h3>
        <?= Html::a('<i class="fas fa-plus me-1"></i>เพิ่มรายการ', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">รายการที่แสดงด้านล่างจะปรากฏในบล็อก "ระบบงานที่เกี่ยวข้องในโรงพยาบาล" บนหน้าแรก เรียงตามลำดับ และสามารถเปิด/ปิดการแสดงผลได้</p>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px" class="text-center">ลำดับ</th>
                        <th>ชื่อรายการ</th>
                        <th>ลิงก์</th>
                        <th style="width:90px" class="text-center">แสดงผล</th>
                        <th style="width:90px" class="text-center">เฉพาะ Admin</th>
                        <th style="width:200px" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการ — <?= Html::a('เพิ่มรายการเมนู', ['create']) ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($models as $m): ?>
                    <tr class="<?= $m->is_visible ? '' : 'table-secondary' ?>">
                        <td class="text-center">
                            <?= Html::a('<i class="fas fa-chevron-up"></i>', ['move-up', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'เลื่อนขึ้น']) ?>
                            <?= Html::a('<i class="fas fa-chevron-down"></i>', ['move-down', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-secondary', 'title' => 'เลื่อนลง']) ?>
                            <span class="d-inline-block mt-1"><?= (int)$m->sort_order ?></span>
                        </td>
                        <td>
                            <strong><?= Html::encode($m->title) ?></strong>
                            <?php if ($m->description): ?>
                                <br><small class="text-muted"><?= Html::encode($m->description) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-break"><?= Html::encode($m->url) ?></small></td>
                        <td class="text-center">
                            <?= Html::button($m->is_visible ? 'เปิด' : 'ปิด', [
                                'class' => 'btn btn-sm toggle-visibility ' . ($m->is_visible ? 'btn-success' : 'btn-outline-secondary'),
                                'data-id' => $m->id,
                                'data-url' => Url::to(['toggle', 'id' => $m->id]),
                            ]) ?>
                        </td>
                        <td class="text-center"><?= $m->admin_only ? '<span class="badge bg-warning text-dark">ใช่</span>' : '<span class="badge bg-secondary">ไม่</span>' ?></td>
                        <td class="text-center">
                            <?= Html::a('<i class="fas fa-edit"></i> แก้ไข', ['update', 'id' => $m->id], ['class' => 'btn btn-sm btn-primary']) ?>
                            <?= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $m->id], [
                                'class' => 'btn btn-sm btn-danger',
                                'data-method' => 'post',
                                'data-confirm' => 'ต้องการลบรายการนี้หรือไม่?',
                            ]) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($pagination) && $pagination->totalCount > $pagination->limit): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                แสดง <?= $pagination->offset + 1 ?>–<?= min($pagination->offset + $pagination->limit, $pagination->totalCount) ?>
                จาก <?= $pagination->totalCount ?> รายการ
            </small>
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
                'maxButtonCount' => 5,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$this->registerJs(<<<JS
(function(){
    var csrfParam = '$csrfParam';
    var csrfToken = '$csrfToken';
    document.querySelectorAll('.toggle-visibility').forEach(function(btn){
        btn.addEventListener('click', function(){
            var url = this.getAttribute('data-url');
            var row = this.closest('tr');
            fetch(url, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: csrfParam + '=' + encodeURIComponent(csrfToken) })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d.success) {
                        if (d.is_visible) {
                            btn.textContent = 'เปิด';
                            btn.className = 'btn btn-sm toggle-visibility btn-success';
                            row.classList.remove('table-secondary');
                        } else {
                            btn.textContent = 'ปิด';
                            btn.className = 'btn btn-sm toggle-visibility btn-outline-secondary';
                            row.classList.add('table-secondary');
                        }
                    }
                });
        });
    });
})();
JS
);
?>
<style>
.text-break { word-break: break-all; }
</style>
