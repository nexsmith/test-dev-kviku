<?php

use app\res\App;

require dirname(__DIR__) . '/res/bootstrap.php';

$app = new App();
$app->handle();
