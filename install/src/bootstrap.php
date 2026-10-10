<?php

// Resolve env() exactly as the application does, without booting the CMS or DB.
require_once EVO_CORE_PATH . 'vendor/autoload.php';
if (is_readable(EVO_CORE_PATH . 'custom/.env')) {
    \Dotenv\Dotenv::createImmutable(EVO_CORE_PATH . 'custom')->load();
}
