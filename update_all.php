<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем необходимые классы
require_once 'OzonApi.php';
require_once 'Database.php';

$db = new Database();
$ozonApi = new OzonApi();

// Функция для вывода сообщения в реальном времени
function logMessage($message, $type = 'info') {
    $color = $type === 'error' ? 'red' : ($type === 'success' ? 'green' : 'blue');
    echo "<div class='mb-2 p-2 bg-{$color}-100 border border-{$color}-400 text-{$color}-700 rounded'>";
    echo date('Y-m-d H:i:s') . " - " . htmlspecialchars($message);
    echo "</div>";
    flush();
    ob_flush();
}

// Включаем буферизацию вывода
ob_start();

try {
    // Проверяем время последнего обновления
    $lastUpdate = $db->getLastUpdateInfo();
    if ($lastUpdate) {
        $lastUpdateTime = strtotime($lastUpdate['last_update']);
        $currentTime = time();
        $timeDiff = $currentTime - $lastUpdateTime;
        
        if ($timeDiff < 60) {
            throw new Exception("Обновление доступно не чаще чем 1 раз в минуту. Следующее обновление будет доступно через " . (60 - $timeDiff) . " секунд.");
        }
    }

    logMessage("Начинаем обновление данных...");

    // Обновление товаров (как в index.php)
    logMessage("Получаем данные о товарах...");
    $products = $ozonApi->getProducts();
    if (empty($products)) {
        throw new Exception("Не удалось получить данные о товарах");
    }
    logMessage("Получено " . count($products) . " товаров");

    logMessage("Сохраняем данные о товарах...");
    $successCount = 0;
    foreach ($products as $product) {
        try {
            // Проверяем наличие обязательных полей
            if (!isset($product['id'])) {
                $product['id'] = null;
            }
            if (!isset($product['offer_id'])) {
                $product['offer_id'] = $product['sources'][0]['sku'] ?? null;
            }
            if (!isset($product['primary_image'])) {
                $product['primary_image'] = null;
            }
            if (!isset($product['images'])) {
                $product['images'] = null;
            }
            
            $db->saveProduct($product);
            $successCount++;
        } catch (Exception $e) {
            logMessage("Ошибка при сохранении товара: " . $e->getMessage(), 'error');
        }
    }
    logMessage("Успешно сохранено {$successCount} товаров", 'success');

    // Обновление продаж (как в sales.php)
    logMessage("Получаем данные о продажах...");
    $salesData = $ozonApi->getSalesData(); // Исправлено имя метода
    if (!empty($salesData)) {
        $db->saveSalesData($salesData);
        logMessage("Данные о продажах успешно сохранены", 'success');
    } else {
        logMessage("Нет новых данных о продажах");
    }

    // Обновление товаров в пути (как в transit.php)
    logMessage("Получаем данные о товарах в пути...");
    $transitData = $ozonApi->getStockOnWarehouses();
    if (!empty($transitData)) {
        $db->saveProductsInTransit($transitData);
        logMessage("Данные о товарах в пути успешно сохранены", 'success');
    } else {
        logMessage("Нет новых данных о товарах в пути");
    }

    // Обновление остатков (как в stock.php)
    logMessage("Получаем данные об остатках...");
    $stocks = $ozonApi->getWarehouseStocks();
    if (!empty($stocks)) {
        $db->saveStocks($stocks, $transitData);
        logMessage("Данные об остатках успешно сохранены", 'success');
    } else {
        logMessage("Нет новых данных об остатках");
    }

    // Обновляем информацию о последнем обновлении
    $db->saveUpdateInfo(count($products));
    logMessage("Все данные успешно обновлены!", 'success');

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'HTTP code: 429') !== false || strpos($e->getMessage(), 'rate limit') !== false) {
        logMessage("Обновление доступно не чаще чем 1 раз в минуту. Пожалуйста, подождите немного и попробуйте снова.", 'error');
    } else {
        logMessage("Произошла ошибка при обновлении данных: " . $e->getMessage(), 'error');
    }
}

// Выводим результат
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновление всех данных</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .log-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Обновление всех данных</h1>
        
        <!-- Кнопка обновления -->
        <div class="mb-4">
            <form method="post">
                <button type="submit" name="update_all" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Обновить все данные
                </button>
            </form>
        </div>

        <!-- Контейнер для логов -->
        <div class="log-container rounded shadow">
            <?php
            $output = ob_get_clean();
            echo $output;
            ?>
        </div>

        <!-- Ссылки на другие страницы -->
        <div class="mt-6">
            <a href="index.php" class="text-blue-500 hover:text-blue-700 mr-4">Товары</a>
            <a href="stock.php" class="text-blue-500 hover:text-blue-700 mr-4">Остатки</a>
            <a href="transit.php" class="text-blue-500 hover:text-blue-700 mr-4">Товары в пути</a>
            <a href="sales.php" class="text-blue-500 hover:text-blue-700">Продажи</a>
        </div>
    </div>
</body>
</html> 
