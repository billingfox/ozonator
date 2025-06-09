<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем необходимые классы
require_once 'OzonApi.php';
require_once 'Database.php';

$db = new Database();
$ozonApi = new OzonApi();

// Обработка запроса на обновление
if (isset($_POST['update_stocks'])) {
    try {
        // Проверяем время последнего обновления
        $lastUpdate = $db->getLastUpdateInfo();
        if ($lastUpdate) {
            $lastUpdateTime = strtotime($lastUpdate['last_update']);
            $currentTime = time();
            $timeDiff = $currentTime - $lastUpdateTime;
            
            if ($timeDiff < 60) { // 60 секунд = 1 минута
                $errorMessage = "Обновление доступно не чаще чем 1 раз в минуту. Следующее обновление будет доступно через " . (60 - $timeDiff) . " секунд.";
                throw new Exception($errorMessage);
            }
        }

        // Получаем данные о товарах в пути
        $transitData = $ozonApi->getStockOnWarehouses();
        
        // Проверяем, что данные не пустые и имеют правильную структуру
        if (empty($transitData) || !is_array($transitData)) {
            $errorMessage = "Не удалось получить данные о товарах в пути или данные имеют неверный формат";
            throw new Exception($errorMessage);
        }
        
        // Проверяем наличие необходимых полей в данных
        $requiredFields = ['offer_id', 'warehouse_name', 'reserved_amount', 'promised_amount'];
        foreach ($transitData as $item) {
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    $errorMessage = "В данных отсутствует обязательное поле: {$field}";
                    throw new Exception($errorMessage);
                }
            }
        }
        
        // Сохраняем данные в базу
        $db->saveProductsInTransit($transitData);
        
        // Обновляем информацию о последнем обновлении
        $db->saveUpdateInfo(count($transitData));
        
        $successMessage = "Данные успешно обновлены!";
    } catch (Exception $e) {
        // Проверяем, содержит ли сообщение об ошибке код 429
        if (strpos($e->getMessage(), 'HTTP code: 429') !== false || strpos($e->getMessage(), 'rate limit') !== false) {
            $errorMessage = "Обновление доступно не чаще чем 1 раз в минуту. Пожалуйста, подождите немного и попробуйте снова.";
        } else {
            $errorMessage = "Произошла ошибка при обновлении данных: " . $e->getMessage();
        }
    }
}

// Получаем данные из базы
try {
    $stocks = $db->getProductsInTransit();
} catch (Exception $e) {
    $errorMessage = "Ошибка при получении данных из базы: " . $e->getMessage();
    $stocks = [];
}

// Формируем список складов
$warehouses = [];
foreach ($stocks as $stock) {
    if (!in_array($stock['warehouse_name'], $warehouses)) {
        $warehouses[] = $stock['warehouse_name'];
    }
}
sort($warehouses);

// Группируем товары по артикулу
$groupedStocks = [];
foreach ($stocks as $stock) {
    $groupedStocks[$stock['offer_id']]['primary_image'] = $stock['primary_image'];
    $groupedStocks[$stock['offer_id']]['name'] = $stock['name'];
    $groupedStocks[$stock['offer_id']]['offer_id'] = $stock['offer_id'];
    $groupedStocks[$stock['offer_id']]['warehouses'][$stock['warehouse_name']] = [
        'reserved' => $stock['reserved_amount'] ?? 0,
        'promised' => $stock['promised_amount'] ?? 0,
        'total' => ($stock['reserved_amount'] ?? 0) + ($stock['promised_amount'] ?? 0)
    ];
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Товары в пути</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Товары в пути</h1>
        
        <!-- Кнопка обновления -->
        <div class="mb-4">
            <form method="post">
                <button type="submit" name="update_stocks" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Обновить данные
                </button>
            </form>
        </div>

        <!-- Сообщения об успехе/ошибке -->
        <?php if (isset($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>

        <!-- Таблица товаров -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="sticky left-0 bg-gray-50 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase z-10">Изображение</th>
                            <th class="sticky left-[120px] bg-gray-50 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase z-10">Артикул</th>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= htmlspecialchars($warehouse) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($groupedStocks as $stock): ?>
                        <tr>
                            <td class="sticky left-0 bg-white px-6 py-4 z-10">
                                <?php if (!empty($stock['primary_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($stock['primary_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($stock['name']); ?>"
                                         class="h-16 w-16 object-cover rounded"
                                         onerror="this.src='data:image/svg+xml;charset=UTF-8,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%23e5e7eb\'%3e%3cpath d=\'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z\'/%3e%3cpath d=\'M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z\'/%3e%3c/svg%3e';">
                                <?php elseif (!empty($stock['images']) && is_array($stock['images']) && !empty($stock['images'][0])): ?>
                                    <img src="<?php echo htmlspecialchars($stock['images'][0]); ?>" 
                                         alt="<?php echo htmlspecialchars($stock['name']); ?>"
                                         class="h-16 w-16 object-cover rounded"
                                         onerror="this.src='data:image/svg+xml;charset=UTF-8,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%23e5e7eb\'%3e%3cpath d=\'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z\'/%3e%3cpath d=\'M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z\'/%3e%3c/svg%3e';">
                                <?php else: ?>
                                    <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center">
                                        <span class="text-gray-400">Нет фото</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="sticky left-[120px] bg-white px-6 py-4 whitespace-nowrap z-10"><?= htmlspecialchars($stock['offer_id']) ?></td>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if (isset($stock['warehouses'][$warehouse])): ?>
                                        <div class="flex flex-col">
                                            <span class="text-sm text-gray-500">Резерв: <?= $stock['warehouses'][$warehouse]['reserved'] ?></span>
                                            <span class="text-sm text-gray-500">Обещан: <?= $stock['warehouses'][$warehouse]['promised'] ?></span>
                                            <span class="font-bold">Всего: <?= $stock['warehouses'][$warehouse]['total'] ?></span>
                                        </div>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 
