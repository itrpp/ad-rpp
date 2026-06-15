<?php

use yii\helpers\Html;

/** @var yii\data\Pagination $pagination */
/** @var int $maxButtonCount */

$maxButtonCount = isset($maxButtonCount) ? (int) $maxButtonCount : 10;
$pageCount = (int) $pagination->getPageCount();
$currentPage = (int) $pagination->getPage();

if ($pageCount <= 1) {
    return;
}

$blockSize = max(1, $maxButtonCount);
$prevBlockPage = max(0, $currentPage - $blockSize);
$nextBlockPage = min($pageCount - 1, $currentPage + $blockSize);

// แสดงเลขหน้าโดยให้หน้าปัจจุบันอยู่ตำแหน่งแรก (ซ้ายสุด) ของชุด
$beginPage = $currentPage;
$endPage = min($pageCount - 1, $beginPage + $blockSize - 1);
if (($endPage - $beginPage + 1) < $blockSize) {
    $beginPage = max(0, $endPage - $blockSize + 1);
}

$renderLink = static function (
    string $label,
    int $page,
    array $options = []
) use ($pagination, $currentPage): string {
    $isActive = !empty($options['active']);
    $isDisabled = !empty($options['disabled']);
    $title = (string) ($options['title'] ?? '');
    $extraClass = (string) ($options['class'] ?? '');

    $itemClass = 'page-item';
    if ($isActive) {
        $itemClass .= ' active';
    }
    if ($isDisabled) {
        $itemClass .= ' disabled';
    }
    if ($extraClass !== '') {
        $itemClass .= ' ' . $extraClass;
    }

    if ($isDisabled) {
        return '<li class="' . Html::encode($itemClass) . '"><span class="page-link" aria-disabled="true"'
            . ($title !== '' ? ' title="' . Html::encode($title) . '"' : '')
            . '>' . $label . '</span></li>';
    }

    $linkOptions = ['class' => 'page-link', 'encode' => false];
    if ($title !== '') {
        $linkOptions['title'] = $title;
    }
    if ($isActive) {
        $linkOptions['aria-current'] = 'page';
    }

    return '<li class="' . Html::encode($itemClass) . '">'
        . Html::a($label, $pagination->createUrl($page), $linkOptions)
        . '</li>';
};
?>
<ul class="pagination ou-user-pagination mb-0" aria-label="เปลี่ยนหน้ารายการผู้ใช้">
    <?= $renderLink(
        '<i class="fas fa-angle-double-left"></i>',
        0,
        [
            'disabled' => $currentPage <= 0,
            'title' => 'หน้าแรก',
            'class' => 'page-item-edge',
        ]
    ) ?>
    <?= $renderLink(
        '<span class="page-jump-symbol">&lt;</span>',
        $prevBlockPage,
        [
            'disabled' => $prevBlockPage >= $currentPage,
            'title' => 'ถอยหลัง ' . $blockSize . ' หน้า (ชุดก่อนหน้า)',
            'class' => 'page-item-block-jump',
        ]
    ) ?>

    <?php for ($page = $beginPage; $page <= $endPage; $page++): ?>
        <?= $renderLink(
            (string) ($page + 1),
            $page,
            [
                'active' => $page === $currentPage,
                'title' => 'ไปหน้า ' . ($page + 1),
                'class' => 'page-item-number',
            ]
        ) ?>
    <?php endfor; ?>

    <?= $renderLink(
        '<span class="page-jump-symbol">&gt;</span>',
        $nextBlockPage,
        [
            'disabled' => $nextBlockPage <= $currentPage,
            'title' => 'ไปข้างหน้า ' . $blockSize . ' หน้า (ชุดถัดไป)',
            'class' => 'page-item-block-jump',
        ]
    ) ?>
    <?= $renderLink(
        '<i class="fas fa-angle-double-right"></i>',
        $pageCount - 1,
        [
            'disabled' => $currentPage >= $pageCount - 1,
            'title' => 'หน้าสุดท้าย (หน้า ' . $pageCount . ')',
            'class' => 'page-item-edge',
        ]
    ) ?>
</ul>
<small class="text-muted ou-user-pagination-hint d-none d-lg-inline ms-2">
    กระโดดทีละ <?= (int) $blockSize ?> หน้าต่อชุด
</small>
