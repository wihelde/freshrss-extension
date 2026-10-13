<?php

declare(strict_types=1);

// Match FreshRSS's web-server rewrites, including redirects to /i/.
unset($_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
