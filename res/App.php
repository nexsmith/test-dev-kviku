<?php

namespace app\res;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\StreamWrapper as GuzzleStreamWrapper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonMachine\Items as JsonMachineItems;
use Psr\Http\Message\ResponseInterface;
use Exception;

class App
{
    // Подключение к API
    protected ?string $apiGetUrl;
    protected ?string $apiPostUrl;
    protected ?string $authBearerToken;
    // Заголовки
    protected ?int $awaitCreditDays;
    protected ?float $awaitCreditPercentPerDay;
    protected ?int $awaitElementNumber;
    protected ?string $xUserEmail;
    // Входящие данные пользователя
    protected ?string $userEmail;
    protected ?string $userIp;
    protected ?string $userFirstName;
    protected ?string $userLastName;
    protected ?string $userCurrency;
    protected ?float $userAmount;
    // Время выполнения
    protected ?float $getRequestRuntime;
    protected ?float $jsonStreamHandlingRuntime;
    protected ?float $postRequestRuntime;

    public function __construct()
    {
        $this->apiGetUrl = $_ENV['API_GET_URL'];
        $this->apiPostUrl = $_ENV['API_POST_URL'];
        $this->authBearerToken = $_ENV['AUTH_BEARER_TOKEN'];
    }

    public function handle(): void
    {
        // Получаем данные из внешнего API
        $response = $this->getIncomingData();
        // Получаем из ответа и проставляем заголовки
        $this->getIncomingHeaders($response);
        // Получаем строку с данными нужного нам пользователя из потока
        $this->getCurrentDataFromStream($response);
        // Производим расчет формулой сложного процента роста
        $creditTotal = $this->calculateCreditTotal();
        // Формируем ответ
        $result = $this->generateResultData($creditTotal);
        // Отправляем ответ
        $response = $this->sendResultData($result);
        // Отладочная информация
        $this->renderDebugData($response, $creditTotal);
    }

    /**
     * @return ResponseInterface
     * @throws Exception
     */
    protected function getIncomingData(): ResponseInterface
    {
        $runtimeStart = microtime(true);
        // todo: сертификат самоподписной, поэтому verify = false
        // todo: при наличии сертификата можно сделать 'verify' => '/path/to/self-signed/cert.pem'
        $httpClient = new GuzzleHttpClient(['verify' => false]);

        try {
            $response = $httpClient->get($this->apiGetUrl, [
                'headers' => ['Authorization' => 'Bearer ' . $this->authBearerToken],
            ]);
            $statusCode = (int)$response->getStatusCode();

            if ($statusCode !== 200) {
                throw new Exception("Некорректный код ответа сервера при GET запросе: " . $statusCode);
            }
        } catch (GuzzleException $e) {
            throw new Exception("Ошибка GET запроса к сервису: " . $e->getMessage());
        }
        $this->getRequestRuntime = microtime(true) - $runtimeStart;

        return $response;
    }

    protected function getIncomingHeaders(ResponseInterface $response): void
    {
        $this->awaitCreditDays = (int)$response->getHeader('Await-Credit-Days')[0];
        $this->awaitCreditPercentPerDay = (float)$response->getHeader('Await-Credit-Percent-Per-Day')[0];
        $this->awaitElementNumber = (int)$response->getHeader('Await-Element-Number')[0];
        $this->xUserEmail = (string)$response->getHeader('Await-Element-Number')[0];
    }

    protected function getCurrentDataFromStream(ResponseInterface $response): void
    {
        $runtimeStart = microtime(true);
        // Преобразуем стрим Guzzle в обычный php стрим
        $phpStream = GuzzleStreamWrapper::getResource($response->getBody());
        $streamOptions = ['pointer' => ['/' . $this->awaitElementNumber]];

        foreach (JsonMachineItems::fromStream($phpStream, $streamOptions) as $key => $value) {

            switch ($key) {
                case 'email':
                    $this->userEmail = (string)$value;
                    break;
                case 'ip':
                    $this->userIp = (string)$value;
                    break;
                case 'firstName':
                    $this->userFirstName = (string)$value;
                    break;
                case 'lastName':
                    $this->userLastName = (string)$value;
                    break;
                case 'credit':
                    $this->userCurrency = (string)$value->currency;
                    $this->userAmount = (float)$value->amount;
                    break;
            }
        }
        $this->jsonStreamHandlingRuntime = microtime(true) - $runtimeStart;
    }

    protected function calculateCreditTotal(): float
    {
        $result = $this->userAmount * pow((1 + ($this->awaitCreditPercentPerDay / 100)), $this->awaitCreditDays);
//        echo "pure result: " . $result . "<br/><br/>";
//        $result = round($result, 2);
        // todo: в ТЗ указано округление до двух знаков запятой, но POST запрос принимает значение только
        // todo: отсекая остальные знаки (после двух), а не округляя их
        $result = floor($result * 100) / 100;

        return $result;
    }

    protected function generateResultData(float $creditTotal): array
    {
        return [
            'email' => $this->userEmail,
            'ip' => $this->userIp,
            'firstName' => $this->userFirstName,
            'lastName' => $this->userLastName,
            'credit' => [
                'total' => $creditTotal,
            ]
        ];
    }

    protected function sendResultData(array $result): ResponseInterface
    {
        $runtimeStart = microtime(true);
        $httpClient = new GuzzleHttpClient(['verify' => false]);

        try {
            $response = $httpClient->post($this->apiGetUrl, [
                'headers' => ['Authorization' => 'Bearer ' . $this->authBearerToken],
                RequestOptions::JSON => $result,
            ]);
            $statusCode = (int)$response->getStatusCode();

            if ($statusCode !== 200) {
                throw new Exception("Некорректный код ответа сервера при POST запросе: " . $statusCode);
            }
        } catch (GuzzleException $e) {
            throw new Exception("Ошибка POST запроса к сервису: " . $e->getMessage());
        }
        $this->postRequestRuntime = microtime(true) - $runtimeStart;

        return $response;
    }

    protected function renderDebugData(ResponseInterface $response, float $creditTotal)
    {
        // Отладка
        echo "Total 0 (amount): " . $this->userAmount . "<br/>";
        echo "Credit percent: " . $this->awaitCreditPercentPerDay . "<br/>";
        echo "N: " . $this->awaitCreditDays . "<br/>";
        echo "Total n: " . $creditTotal;
        echo "<br/><br/>";
        echo "Memory: " . $this->formattedSize(memory_get_usage() - APP_MEMORY_START) . "<br/>";
        echo "Total memory: " . $this->formattedSize(memory_get_usage()) . "<br/>";
        echo "Runtime GET request: " . $this->getRequestRuntime . " сек. <br/>";
        echo "Runtime handling JSON stream: " . $this->jsonStreamHandlingRuntime . " сек. <br/>";
        echo "Runtime POST request: " . $this->postRequestRuntime . " сек. <br/>";
        echo "Runtime total: " . microtime(true) - APP_TIME_START. " сек. <br/>";

        echo "<br/>";
        echo "<pre>";
        echo $response->getBody();
        echo "</pre>";
    }

    protected function formattedSize(int $size): string
    {
        if ($size < 1024) {
            return "{$size} bytes";
        } elseif ($size < 1048576) {
            $size_kb = round($size / 1024);
            return "{$size_kb} KB";
        } else {
            $size_mb = round($size / 1048576, 1);
            return "{$size_mb} MB";
        }
    }
}
