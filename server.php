<?php
// server.php

// Serve the requested resource as-is if it exists
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// Otherwise, include the index.php file
require_once __DIR__ . '/index.php';
