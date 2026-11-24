<?php
$missing = array_filter(['curl','mbstring','openssl','dom','xml','xmlwriter','fileinfo','pdo_sqlite'], fn ($e) => !extension_loaded($e));
if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP 8.2 or later required.\n"); exit(1); }
foreach ($missing as $e) fwrite(STDERR, "Missing PHP extension: $e\n");
exit($missing ? 1 : 0);
