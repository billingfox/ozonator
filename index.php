<?php
// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Устанавливаем временную зону
date_default_timezone_set('Europe/Moscow');

// Подключаем класс OzonApi
require_once 'OzonApi.php';
require_once 'Database.php';

// Используем системную директорию для логов
$logFile = '/var/log/ozon_api.log';

// Инициализация классов
$ozonApi = new OzonApi();
$db = new Database();

// Обработка запроса на обновление
if (isset($_POST['update_products'])) {
    try {
        // Получаем список всех товаров
        $products = $ozonApi->getProducts();
        $productIds = array_column($products['result']['items'], 'product_id');
        
        // Получаем подробную информацию о товарах
        $productInfo = $ozonApi->getProductInfo($productIds);
        
        // Сохраняем информацию о последнем обновлении
        $db->saveUpdateInfo(count($productInfo['items']));
        
        // Сохраняем информацию о каждом товаре
        foreach ($productInfo['items'] as $product) {
            $db->saveProduct($product);
        }
        
        $successMessage = "Товары успешно обновлены! Обновлено " . count($productInfo['items']) . " товаров.";
    } catch (Exception $e) {
        $errorMessage = "Ошибка при обновлении товаров: " . $e->getMessage();
    }
}

// Получаем информацию о последнем обновлении
$lastUpdate = $db->getLastUpdateInfo();
$products = $db->getAllProducts();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами Ozon</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Управление товарами Ozon</h1>
        
        <!-- Кнопка обновления -->
        <div class="mb-8">
            <form method="post">
                <button type="submit" name="update_products" 
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Обновить товары
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

        <!-- Информация о последнем обновлении -->
        <?php if ($lastUpdate): ?>
            <div class="bg-white shadow rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">Последнее обновление</h2>
                <p>Дата: <?php echo date('d.m.Y H:i:s', strtotime($lastUpdate['last_update'])); ?></p>
                <p>Количество товаров: <?php echo $lastUpdate['total_products']; ?></p>
            </div>
        <?php endif; ?>

        <!-- Таблица товаров -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Изображение</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Артикул</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Маркетинговая цена</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <?php if (!empty($product['primary_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['primary_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="h-16 w-16 object-cover rounded">
                                <?php else: ?>
                                    <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center">
                                        <span class="text-gray-400">Нет фото</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $name = htmlspecialchars($product['name']);
                                echo mb_strlen($name) > 25 ? mb_substr($name, 0, 25) . '...' : $name;
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['offer_id']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['sku']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($product['marketing_price']); ?> 
                                <?php echo htmlspecialchars($product['currency_code']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $product['status'] === 'price_sent' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo htmlspecialchars($product['status_name']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 
