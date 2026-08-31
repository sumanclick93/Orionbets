<?php
$label = $label ?? 'Continue with Discord';
$class = trim((string) ($class ?? 'btn btn-discord'));
$next = intended_path((string) ($next ?? ''));
$href = url('/auth/discord' . ($next !== null ? ('?next=' . rawurlencode($next)) : ''));
?>
<a class="<?= e($class !== '' ? $class : 'btn btn-discord') ?>" data-auth="discord" href="<?= e($href) ?>">
    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M19.27 5.33A17.4 17.4 0 0 0 14.62 4c-.2.36-.43.85-.59 1.23a16.1 16.1 0 0 0-4.06 0A11.3 11.3 0 0 0 9.38 4 17.3 17.3 0 0 0 4.72 5.34C.96 10.96.05 16.44.33 21.87a17.5 17.4 0 0 0 5.28 2.67c.43-.58.81-1.2 1.14-1.84a11.4 11.4 0 0 1-1.8-.86c.15-.11.3-.22.44-.34a12.4 12.4 0 0 0 10.22 0c.15.12.3.23.44.34-.57.34-1.17.63-1.8.86.33.64.71 1.26 1.14 1.84a17.4 17.4 0 0 0 5.29-2.67c.33-6.3-.56-11.74-4.21-16.54ZM8.02 18.05c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.02 2.58-2.3 2.58Zm7.96 0c-1.26 0-2.3-1.16-2.3-2.58s1.02-2.58 2.3-2.58 2.33 1.17 2.3 2.58c0 1.42-1.03 2.58-2.3 2.58Z"/>
    </svg>
    <?= e($label) ?>
</a>
