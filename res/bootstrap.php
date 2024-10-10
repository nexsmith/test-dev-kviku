<?php

use Symfony\Component\Dotenv\Dotenv;

// Замеряем системные параметры в начале выполнения скрипта
define('APP_TIME_START', microtime(true));
define('APP_MEMORY_START', memory_get_usage());
// Базовая папка проекта
define('BASE_DIR', dirname(__DIR__));

require BASE_DIR . '/vendor/autoload.php';

// Параметры вынесены в .env в первую очередь чтобы не коммитить Bearer токен
$dotenv = new Dotenv();
$dotenv->load(BASE_DIR.'/.env');
