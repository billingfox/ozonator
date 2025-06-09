<?php
/**
 * Класс для работы с API OZON
 * 
 * Предоставляет методы для взаимодействия с API маркетплейса OZON
 * Использует учетные данные из .env файла для аутентификации
 */
class OzonApi {
    /**
     * @var string Идентификатор клиента OZON
     */
    private $clientId;
    
    /**
     * @var string API ключ для аутентификации
     */
    private $apiKey;
    
    /**
     * @var string Базовый URL API OZON
     */
    private $apiUrl = 'https://api-seller.ozon.ru';

    /**
     * Конструктор класса
     * 
     * Загружает учетные данные из .env файла
     * Проверяет наличие необходимых параметров
     * 
     * @throws Exception Если не найдены учетные данные
     */
    public function __construct() {
        // Загрузка конфигурации из .env файла
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $this->clientId = $env['OZON_CLIENT_ID'] ?? '';
            $this->apiKey = $env['OZON_API_KEY'] ?? '';
        }

        if (empty($this->clientId) || empty($this->apiKey)) {
            throw new Exception('OZON API credentials not found in .env file');
        }
    }

    /**
     * Получение списка товаров
     * 
     * Метод для получения списка всех товаров с возможностью фильтрации
     * 
     * @param array $filter Массив параметров фильтрации:
     *                      - offer_id: массив идентификаторов товаров
     *                      - product_id: массив внутренних идентификаторов
     *                      - visibility: видимость товаров (ALL, VISIBLE, INVISIBLE)
     * @param string $lastId Идентификатор последнего значения на странице
     *                      Используется для пагинации
     * @param int $limit Количество значений на странице (1-1000)
     * 
     * @return array Ответ от API в формате:
     *               [
     *                   'result' => [
     *                       'items' => [...], // Массив товаров
     *                       'total' => int,   // Общее количество товаров
     *                       'last_id' => string // Идентификатор для пагинации
     *                   ]
     *               ]
     * 
     * @throws Exception При ошибках запроса или обработки ответа
     */
    public function getProducts($filter = [], $lastId = '', $limit = 100) {
        $endpoint = '/v3/product/list';
        $url = $this->apiUrl . $endpoint;

        // Формируем правильный формат запроса
        $data = [
            'filter' => [
                'visibility' => 'ALL' // По умолчанию показываем все товары
            ],
            'last_id' => $lastId,
            'limit' => $limit
        ];

        // Добавляем пользовательские фильтры, если они переданы
        if (!empty($filter)) {
            $data['filter'] = array_merge($data['filter'], $filter);
        }

        // Формируем заголовки запроса
        $headers = [
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        // Инициализируем cURL запрос
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Выполняем запрос
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Проверяем ошибки cURL
        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }

        // Декодируем JSON ответ
        $result = json_decode($response, true);
        
        // Проверяем HTTP код ответа
        if ($httpCode !== 200) {
            throw new Exception('API error: ' . ($result['message'] ?? 'Unknown error') . "\nResponse: " . $response);
        }

        return $result;
    }

    /**
     * Получение подробной информации о товарах
     * 
     * Метод для получения детальной информации о товарах по их идентификаторам
     * 
     * @param array $productIds Массив идентификаторов товаров (product_id)
     * @param array $offerIds Массив артикулов товаров (offer_id)
     * @param array $skus Массив SKU товаров
     * 
     * @return array Ответ от API в формате:
     *               [
     *                   'items' => [
     *                       [
     *                           'id' => int,           // Идентификатор товара
     *                           'name' => string,      // Название товара
     *                           'offer_id' => string,  // Артикул
     *                           'price' => string,     // Цена
     *                           'old_price' => string, // Старая цена
     *                           'statuses' => [...],   // Статусы товара
     *                           'stocks' => [...],     // Остатки
     *                           'images' => [...],     // Изображения
     *                           // и другие поля
     *                       ]
     *                   ]
     *               ]
     * 
     * @throws Exception При ошибках запроса или обработки ответа
     */
    public function getProductInfo($productIds = [], $offerIds = [], $skus = []) {
        $endpoint = '/v3/product/info/list';
        $url = $this->apiUrl . $endpoint;

        // Формируем данные запроса
        $data = [];
        
        if (!empty($productIds)) {
            $data['product_id'] = $productIds;
        }
        
        if (!empty($offerIds)) {
            $data['offer_id'] = $offerIds;
        }
        
        if (!empty($skus)) {
            $data['sku'] = $skus;
        }

        // Проверяем, что передан хотя бы один тип идентификаторов
        if (empty($data)) {
            throw new Exception('At least one of productIds, offerIds or skus must be provided');
        }

        // Формируем заголовки запроса
        $headers = [
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        // Инициализируем cURL запрос
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Выполняем запрос
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Проверяем ошибки cURL
        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }

        // Декодируем JSON ответ
        $result = json_decode($response, true);
        
        // Проверяем HTTP код ответа
        if ($httpCode !== 200) {
            throw new Exception('API error: ' . ($result['message'] ?? 'Unknown error') . "\nResponse: " . $response);
        }

        return $result;
    }

    /**
     * Получает информацию об остатках товаров
     * @param array $productIds Массив product_id
     * @param array $offerIds Массив offer_id
     * @param string $cursor Указатель для пагинации
     * @param int $limit Количество значений на странице (1-1000)
     * @return array Массив с информацией об остатках
     * @throws Exception при ошибке запроса
     */
    public function getFboStocks($productIds = [], $offerIds = [], $cursor = '', $limit = 1000) {
        if (empty($productIds) && empty($offerIds)) {
            throw new Exception("At least one of productIds or offerIds must be provided");
        }

        $data = [
            'cursor' => $cursor,
            'limit' => $limit,
            'filter' => [
                'visibility' => 'ALL',
                'with_quant' => [
                    'created' => true,
                    'exists' => true
                ]
            ]
        ];

        if (!empty($productIds)) {
            $data['filter']['product_id'] = $productIds;
        }
        if (!empty($offerIds)) {
            $data['filter']['offer_id'] = $offerIds;
        }

        $headers = [
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init($this->apiUrl . '/v4/product/info/stocks');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API request failed with code $httpCode. Response: $response");
        }

        return json_decode($response, true);
    }

    /**
     * Получение информации об остатках товаров по складам
     * 
     * @return array Ответ от API в формате:
     *               [
     *                   'items' => [
     *                       [
     *                           'offer_id' => string,
     *                           'sku' => int,
     *                           'name' => string,
     *                           'warehouse_name' => string,
     *                           'valid_stock_count' => int,
     *                           'defect_stock_count' => int,
     *                           'expiring_stock_count' => int,
     *                           'waitingdocs_stock_count' => int
     *                       ]
     *                   ]
     *               ]
     * @throws Exception При ошибках запроса или обработки ответа
     */
    public function getWarehouseStocks() {
        $endpoint = '/v1/analytics/manage/stocks';
        $url = $this->apiUrl . $endpoint;

        $data = [
            'filter' => [
                'stock_types' => ['STOCK_TYPE_VALID']
            ],
            'limit' => 1000,
            'offset' => 0
        ];

        $headers = [
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Curl error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('API request failed with HTTP code: ' . $httpCode . '. Response: ' . $response);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Получение списка товаров в пути на склады Ozon (FBO)
     * 
     * @return array Данные о товарах в пути
     */
    public function getProductsInTransit() {
        try {
            // Шаг 1: Получаем список товаров в пути
            $payload = [
                "filter" => [
                    "delivery_method_id" => [],
                    "provider_id" => [],
                    "status" => "awaiting_deliver",
                    "warehouse_id" => []
                ],
                "limit" => 100,
                "offset" => 0
            ];
            
            $response = $this->makeRequest('/v2/posting/fbs/list', 'POST', $payload);
            
            if (!isset($response['result']) || !is_array($response['result'])) {
                throw new Exception("Неверный формат ответа API: отсутствует result");
            }
            
            // Преобразуем данные в нужный формат
            $items = [];
            foreach ($response['result'] as $posting) {
                if (!isset($posting['products'][0])) {
                    continue;
                }
                
                $product = $posting['products'][0];
                $analytics = $posting['analytics_data'] ?? [];
                
                $items[] = [
                    'offer_id' => $product['offer_id'] ?? '',
                    'sku' => $product['sku'] ?? '',
                    'name' => $product['name'] ?? '',
                    'warehouse_name' => $posting['delivery_method']['warehouse'] ?? '',
                    'waitingdocs_stock_count' => $product['quantity'] ?? 0,
                    'expiring_stock_count' => 0,
                    'primary_image' => $product['primary_image'] ?? ''
                ];
            }
            
            if (empty($items)) {
                throw new Exception("Нет данных о товарах в пути");
            }
            
            return $items;
        } catch (Exception $e) {
            error_log("Ошибка при получении данных о товарах в пути: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получение отчета по остаткам и товарам в перемещении по складам Ozon
     * 
     * @param int $limit Количество ответов на странице
     * @param int $offset Количество пропускаемых элементов
     * @param string $warehouseType Тип склада (ALL, EXPRESS_DARK_STORE, NOT_EXPRESS_DARK_STORE)
     * @return array Данные об остатках
     */
    public function getStockOnWarehouses($limit = 1000, $offset = 0, $warehouseType = 'ALL') {
        $url = "{$this->apiUrl}/v2/analytics/stock_on_warehouses";
        
        $data = [
            'limit' => $limit,
            'offset' => $offset,
            'warehouse_type' => $warehouseType
        ];
        
        try {
            $response = $this->makeRequest($url, 'POST', $data);
            
            if (!isset($response['result']['rows']) || !is_array($response['result']['rows'])) {
                throw new Exception("Неверный формат ответа от API: отсутствует или пустой массив rows");
            }
            
            $result = [];
            foreach ($response['result']['rows'] as $row) {
                // Проверяем наличие обязательных полей
                if (empty($row['item_code'])) {
                    continue;
                }
                
                if (empty($row['warehouse_name'])) {
                    continue;
                }
                
                // Проверяем и преобразуем числовые значения
                $reservedAmount = isset($row['reserved_amount']) ? (int)$row['reserved_amount'] : 0;
                $promisedAmount = isset($row['promised_amount']) ? (int)$row['promised_amount'] : 0;
                
                // Пропускаем записи, где нет товаров в пути
                if ($reservedAmount <= 0 && $promisedAmount <= 0) {
                    continue;
                }
                
                $result[] = [
                    'offer_id' => (string)$row['item_code'],
                    'sku' => isset($row['sku']) ? (string)$row['sku'] : null,
                    'name' => isset($row['item_name']) ? (string)$row['item_name'] : '',
                    'warehouse_name' => (string)$row['warehouse_name'],
                    'reserved_amount' => $reservedAmount,
                    'promised_amount' => $promisedAmount
                ];
            }
            
            if (empty($result)) {
                throw new Exception("Нет данных о товарах в пути в ответе API");
            }
            
            return $result;
        } catch (Exception $e) {
            throw new Exception("Ошибка при получении данных о товарах в пути: " . $e->getMessage());
        }
    }

    /**
     * Получает данные о продажах за последние 30 дней
     * @return array Массив с данными о продажах
     */
    public function getSalesData() {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $thirtyDaysAgo = (clone $now)->modify('-30 days');
        
        $log = [];
        $log[] = "=== Начало получения данных о продажах ===";
        $log[] = "Период: с " . $thirtyDaysAgo->format('Y-m-d\TH:i:s.v\Z') . " по " . $now->format('Y-m-d\TH:i:s.v\Z');
        
        // Создаем отчет
        $payload = [
            "filter" => [
                "processed_at_from" => $thirtyDaysAgo->format('Y-m-d\TH:i:s.v\Z'),
                "processed_at_to" => $now->format('Y-m-d\TH:i:s.v\Z'),
                "delivery_schema" => ["fbo"],
                "status" => "delivered"
            ],
            "language" => "DEFAULT"
        ];
        
        try {
            $log[] = "=== Шаг 1: Создание отчета ===";
            $log[] = "URL: " . $this->apiUrl . '/v1/report/postings/create';
            $log[] = "Payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Создаем отчет
            $response = $this->makeRequest('/v1/report/postings/create', 'POST', $payload);
            $log[] = "Ответ сервера на создание отчета:";
            $log[] = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (!isset($response['result']['code'])) {
                $log[] = "Ошибка: отсутствует код отчета в ответе API";
                throw new Exception("Неверный формат ответа API при создании отчета");
            }
            
            $reportCode = $response['result']['code'];
            $log[] = "Получен код отчета: " . $reportCode;
            
            // Ждем готовности отчета
            $log[] = "=== Шаг 2: Ожидание готовности отчета ===";
            $fileUrl = $this->waitForReport($reportCode);
            $log[] = "Получен URL файла отчета: " . $fileUrl;
            
            // Скачиваем CSV файл
            $log[] = "=== Шаг 3: Скачивание файла отчета ===";
            $csvContent = $this->downloadFile($fileUrl);
            $log[] = "Размер скачанного файла: " . strlen($csvContent) . " байт";
            
            // Парсим CSV и получаем номера отправлений
            $log[] = "=== Шаг 4: Парсинг CSV файла ===";
            $postingNumbers = $this->parsePostingNumbers($csvContent);
            $log[] = "Найдено отправлений: " . count($postingNumbers);
            
            // Получаем детальную информацию по каждому отправлению
            $log[] = "=== Шаг 5: Получение детальной информации по отправлениям ===";
            $salesData = [];
            foreach ($postingNumbers as $number) {
                try {
                    $log[] = "Обработка отправления: " . $number;
                    $postingInfo = $this->getPostingInfo($number);
                    
                    if ($postingInfo && isset($postingInfo['products'][0])) {
                        $product = $postingInfo['products'][0];
                        $financial = $postingInfo['financial_data'] ?? [];
                        
                        if (!isset($financial['cluster_to'])) {
                            $log[] = "Предупреждение: отсутствует cluster_to для отправления " . $number;
                            continue;
                        }
                        
                        $salesData[] = [
                            'offer_id' => $product['offer_id'] ?? '',
                            'sku' => $product['sku'] ?? 0,
                            'cluster_to' => $financial['cluster_to'],
                            'sales_count' => $product['quantity'] ?? 0,
                            'processed_at' => $postingInfo['in_process_at'] ?? date('Y-m-d H:i:s')
                        ];
                    }
                } catch (Exception $e) {
                    $log[] = "Ошибка при получении информации по отправлению {$number}: " . $e->getMessage();
                    continue;
                }
            }
            
            if (empty($salesData)) {
                throw new Exception("Не удалось получить данные о продажах");
            }
            
            $log[] = "=== Шаг 6: Сохранение данных о продажах ===";
            $log[] = "Всего записей: " . count($salesData);
            
            // Сохраняем логи в сессию
            $_SESSION['api_log'] = implode("\n", $log);
            
            return $salesData;
        } catch (Exception $e) {
            $log[] = "Ошибка: " . $e->getMessage();
            $_SESSION['api_log'] = implode("\n", $log);
            throw $e;
        }
    }
    
    /**
     * Ожидает готовности отчета
     */
    private function waitForReport($code, $maxAttempts = 20) {
        $url = '/v1/report/info';
        $payload = ["code" => $code];
        
        $log = [];
        $log[] = "=== Ожидание готовности отчета ===";
        $log[] = "Код отчета: " . $code;
        $log[] = "Максимальное время ожидания: " . ($maxAttempts * 3) . " секунд";
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $log[] = "Попытка #" . ($i + 1) . " из " . $maxAttempts;
                $response = $this->makeRequest($url, 'POST', $payload);
                
                if (!isset($response['result'])) {
                    $error = "Неверный формат ответа API: отсутствует result";
                    $log[] = $error;
                    $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                    throw new Exception($error);
                }
                
                $status = $response['result']['status'] ?? 'unknown';
                $log[] = "Статус отчета: " . $status;
                
                if ($status === 'success' && isset($response['result']['file'])) {
                    $log[] = "Отчет готов!";
                    $log[] = "URL файла: " . $response['result']['file'];
                    $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                    return $response['result']['file'];
                }
                
                if ($status === 'failed') {
                    $error = "Ошибка при генерации отчета: " . ($response['result']['error'] ?? 'Неизвестная ошибка');
                    $log[] = $error;
                    $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                    throw new Exception($error);
                }
                
                if ($status === 'processing' || $status === 'waiting') {
                    $log[] = "Отчет в процессе генерации (статус: {$status}), ожидание 3 секунды...";
                    $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                    sleep(3);
                    continue;
                }
                
                // Если статус неизвестен или неожиданный
                $error = "Неожиданный статус отчета: " . $status;
                $log[] = $error;
                $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                throw new Exception($error);
                
            } catch (Exception $e) {
                $log[] = "Ошибка при проверке статуса отчета: " . $e->getMessage();
                $_SESSION['api_log'] .= "\n" . implode("\n", $log);
                throw $e;
            }
        }
        
        $error = 'Отчет не был готов в течение ' . ($maxAttempts * 3) . ' секунд';
        $log[] = $error;
        $_SESSION['api_log'] .= "\n" . implode("\n", $log);
        throw new Exception($error);
    }
    
    /**
     * Скачивает файл по URL
     */
    private function downloadFile($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Ошибка при скачивании файла: HTTP код ' . $httpCode);
        }
        
        // Используем системную временную директорию
        $tempDir = sys_get_temp_dir();
        $filename = $tempDir . '/sales_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        if (file_put_contents($filename, $content) === false) {
            throw new Exception('Ошибка при сохранении файла: ' . error_get_last()['message']);
        }
        
        return $content;
    }
    
    /**
     * Парсит CSV и извлекает номера отправлений
     */
    private function parsePostingNumbers($csvContent) {
        $log = [];
        $log[] = "=== Начало парсинга CSV файла ===";
        $log[] = "Первые 100 символов CSV: " . substr($csvContent, 0, 100);
        
        $lines = explode("\n", $csvContent);
        $postingNumbers = [];
        $headerFound = false;
        $postingNumberIndex = -1;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $columns = str_getcsv($line, ';');
            
            // Ищем заголовок
            if (!$headerFound) {
                foreach ($columns as $index => $column) {
                    if (strpos($column, 'Номер отправления') !== false) {
                        $postingNumberIndex = $index;
                        $headerFound = true;
                        $log[] = "Найден столбец с номерами отправлений: " . $index;
                        break;
                    }
                }
                continue;
            }
            
            // Если нашли заголовок и есть номер отправления
            if ($postingNumberIndex !== -1 && isset($columns[$postingNumberIndex])) {
                $number = trim($columns[$postingNumberIndex], '"');
                if (!empty($number) && $number !== 'Номер отправления') {
                    $postingNumbers[] = $number;
                    $log[] = "Найден номер отправления: " . $number;
                }
            }
        }
        
        $log[] = "Всего найдено номеров отправлений: " . count($postingNumbers);
        $log[] = "Список номеров: " . json_encode($postingNumbers, JSON_UNESCAPED_UNICODE);
        
        // Сохраняем логи в сессию
        $_SESSION['api_log'] .= "\n" . implode("\n", $log);
        
        return $postingNumbers;
    }
    
    /**
     * Получает информацию по отправлению
     */
    private function getPostingInfo($postingNumber) {
        $payload = [
            "posting_number" => $postingNumber,
            "translit" => true,
            "with" => [
                "analytics_data" => true,
                "financial_data" => true
            ]
        ];
        
        try {
            $response = $this->makeRequest('/v2/posting/fbo/get', 'POST', $payload);
            if (!isset($response['result'])) {
                throw new Exception("Неверный формат ответа API");
            }
            
            $result = $response['result'];
            if (!isset($result['financial_data']['cluster_to'])) {
                throw new Exception("Отсутствует cluster_to в ответе API");
            }
            
            // Добавляем cluster_to в analytics_data для совместимости
            $result['analytics_data']['cluster_to'] = $result['financial_data']['cluster_to'];
            
            return $result;
        } catch (Exception $e) {
            error_log("Ошибка при получении информации по отправлению {$postingNumber}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Выполнение HTTP-запроса к API Ozon
     * 
     * @param string $url URL для запроса
     * @param string $method HTTP метод (GET, POST)
     * @param array $data Данные для отправки
     * @return array Ответ от API
     */
    public function makeRequest($url, $method = 'POST', $data = []) {
        // Добавляем базовый URL API, если передан относительный путь
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = $this->apiUrl . $url;
        }
        
        $log = [];
        $log[] = "=== Отправка запроса к API Ozon ===";
        $log[] = "URL: " . $url;
        $log[] = "Method: " . $method;
        $log[] = "Data: " . (is_array($data) ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $data);
        
        $ch = curl_init($url);
        
        $headers = [
            'Client-Id: ' . $this->clientId,
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $log[] = "Headers: " . json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        $log[] = "=== Ответ от API Ozon ===";
        $log[] = "HTTP код: " . $httpCode;
        $log[] = "Ответ: " . (is_array($response) ? json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $response);
        if ($error) {
            $log[] = "Ошибка cURL: " . $error;
        }
        
        curl_close($ch);
        
        // Сохраняем логи в сессию
        $_SESSION['api_log'] = implode("\n", $log);
        
        if ($error) {
            throw new Exception('Ошибка cURL: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Ошибка API: HTTP код {$httpCode}, ответ: " . (is_array($response) ? json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $response));
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка декодирования JSON: ' . json_last_error_msg());
        }
        
        return $result;
    }
} 
