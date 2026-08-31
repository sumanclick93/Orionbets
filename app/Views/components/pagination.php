<?php if (($total ?? 0) > ($perPage ?? 12)):
    $page = (int) ($page ?? 1);
    $pages = (int) ceil($total / $perPage);
?>
<nav class="pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'is-active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
