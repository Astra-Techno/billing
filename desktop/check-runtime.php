<?php
foreach (['pdo_mysql','mbstring','openssl','fileinfo','gd','zip'] as $extension) {
    if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: $extension\n"); exit(1); }
}
echo "Bundled PHP extensions ready.\n";
