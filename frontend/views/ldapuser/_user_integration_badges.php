<?php
use yii\helpers\Html;
use common\models\LdapUser;

/** @var array $user */
/** @var string|null $type 'gtw'|'ephis'|null */
$type = $type ?? null;

$getVal = static function (array $user, string $key): string {
    $keyLower = strtolower($key);
    foreach ($user as $k => $v) {
        if (strtolower((string)$k) === $keyLower) {
            if (is_array($v)) {
                return trim((string)($v[0] ?? ''));
            }
            return trim((string)$v);
        }
    }
    return '';
};

$isNumericCode = static function (string $value): bool {
    return $value !== '' && preg_match('/^\d+$/', $value) === 1;
};

$gtwCode = LdapUser::normalizeGtwCode($getVal($user, 'countrycode'));
$ephisCode = trim($getVal($user, 'physicaldeliveryofficename'));
$hasGtw = $isNumericCode($gtwCode);
$hasEphis = $isNumericCode($ephisCode);

if ($type === 'gtw') {
    if (!$hasGtw) {
        return;
    }
    ?>
    <span class="badge user-badge-gtw" title="เลขรหัส GTW: <?= Html::encode($gtwCode) ?>">GTW</span>
    <?php
    return;
}

if ($type === 'ephis') {
    if (!$hasEphis) {
        return;
    }
    ?>
    <span class="badge user-badge-ephis" title="เลขรหัส E-phis: <?= Html::encode($ephisCode) ?>">e-phis</span>
    <?php
    return;
}

if (!$hasGtw && !$hasEphis) {
    return;
}
?>
<div class="user-integration-badges d-flex flex-wrap gap-1 justify-content-start">
    <?php if ($hasGtw): ?>
        <span class="badge user-badge-gtw" title="เลขรหัส GTW: <?= Html::encode($gtwCode) ?>">GTW</span>
    <?php endif; ?>
    <?php if ($hasEphis): ?>
        <span class="badge user-badge-ephis" title="เลขรหัส E-phis: <?= Html::encode($ephisCode) ?>">e-phis</span>
    <?php endif; ?>
</div>
