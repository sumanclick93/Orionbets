<?php
$total = (int) ($total ?? 0);
$perPageRaw = (string) ($perPage ?? '10');
$perPageNum = strtolower($perPageRaw) === 'all' ? 10000 : max(1, (int) $perPageRaw);
$page = max(1, (int) ($page ?? 1));
$pages = (int) ceil($total / $perPageNum);
$start = $total > 0 ? (($page - 1) * $perPageNum) + 1 : 0;
$end = min($total, $page * $perPageNum);
?>
<?php if ($total > 0): ?>
<div class="datatable-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-top:1.25rem; padding-top:0.75rem; border-top:1px solid var(--color-border);">
    <div class="datatable-info muted" style="font-size:0.85rem;">
        Showing <?= number_format($start) ?> to <?= number_format($end) ?> of <?= number_format($total) ?> entries
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination" style="margin-top:0;">
        <?php if ($page > 1): ?>
            <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>" aria-label="Previous">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?php if ($i == 1 || $i == $pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <a class="<?= $i === $page ? 'is-active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
            <?php elseif (($i == 2 && $page > 4) || ($i == $pages - 1 && $page < $pages - 3)): ?>
                <span class="pagination-ellipsis muted" style="padding:0 0.3rem; align-self:center;">...</span>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
            <a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>" aria-label="Next">Next &raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>
