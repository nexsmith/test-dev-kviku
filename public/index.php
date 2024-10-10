<?php

use app\res\App;

require dirname(__DIR__) . '/res/bootstrap.php';

$app = new App();
$app->handle();


/*




try {
    $response = $httpClient->get(API_URL_GET, [
        'headers' => ['Authorization' => 'Bearer ' . BEARER_TOKEN],
    ]);
    $statusCode = (int)$response->getStatusCode();

    if ($statusCode !== 200) {
        echo "Некорректный код ответа сервера при GET запросе: " . $statusCode;
        die;
    }
} catch (GuzzleException $e) {
    echo "Ошибка GET запроса к сервису: " . $e->getMessage();
    die;
}

echo $response->getBody();*/
