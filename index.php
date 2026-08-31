<?php

declare(strict_types=1);

/**
 * Fallback front controller when the host document root is the project folder.
 * Pretty URLs and CSS still need the root .htaccess rewrite into /public.
 */
require __DIR__ . '/public/index.php';
