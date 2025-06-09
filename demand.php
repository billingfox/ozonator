<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Database.php';

$db = new Database();

// Получаем данные из базы
$stocks = $db->getStocks();
$sales = [];
try {
    $sales = $db->getSalesData();
} catch (Exception $e) {
    // если нет данных о продажах, оставляем пустым
}
$transit = [];
try {
    $transit = $db->getProductsInTransit();
} catch (Exception $e) {
    // если нет данных о товарах в пути, оставляем пустым
}

// Формируем список складов
$warehouses = [];
foreach ($stocks as $s) {
    $warehouses[$s['warehouse_name']] = true;
}
foreach ($transit as $t) {
    $warehouses[$t['warehouse_name']] = true;
}
ksort($warehouses);
$warehouseList = array_keys($warehouses);

// Индексируем данные
$result = [];
foreach ($stocks as $item) {
    $offer = $item['offer_id'];
    if (!isset($result[$offer])) {
        $result[$offer] = [
            'name' => $item['product_name'],
            'image' => $item['primary_image'],
            'warehouses' => []
        ];
    }
    $result[$offer]['warehouses'][$item['warehouse_name']]['stock'] = (int)$item['valid_stock_count'];
}
foreach ($sales as $sale) {
    $offer = $sale['offer_id'];
    $cluster = $sale['cluster_to'];
    if (!isset($result[$offer])) {
        $result[$offer] = [
            'name' => '',
            'image' => '',
            'warehouses' => []
        ];
    }
    $result[$offer]['warehouses'][$cluster]['sales'] = (int)$sale['sales_count'];
}
foreach ($transit as $t) {
    $offer = $t['offer_id'];
    $warehouse = $t['warehouse_name'];
    if (!isset($result[$offer])) {
        $result[$offer] = [
            'name' => '',
            'image' => '',
            'warehouses' => []
        ];
    }
    $qty = (int)$t['reserved_amount'] + (int)$t['promised_amount'];
    $result[$offer]['warehouses'][$warehouse]['transit'] = $qty;
}

// Рассчитываем потребность
foreach ($result as $offer => &$data) {
    foreach ($warehouseList as $wh) {
        $stock = $data['warehouses'][$wh]['stock'] ?? 0;
        $salesCount = $data['warehouses'][$wh]['sales'] ?? 0;
        $transitQty = $data['warehouses'][$wh]['transit'] ?? 0;
        $order = ($salesCount * 2) - $stock - $transitQty;
        if ($order < 0) {
            $order = 0;
        }
        $data['warehouses'][$wh] = [
            'stock' => $stock,
            'sales' => $salesCount,
            'order' => $order
        ];
    }
}
unset($data);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Потребность по складам</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Потребность по складам</h1>
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Изображение</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Артикул</th>
<?php foreach ($warehouseList as $wh): ?>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase"><?= htmlspecialchars($wh) ?></th>
<?php endforeach; ?>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
<?php foreach ($result as $offerId => $data): ?>
            <tr>
                <td class="px-6 py-4">
<?php if (!empty($data['image'])): ?>
                    <img src="<?= htmlspecialchars($data['image']) ?>" alt="" class="h-16 w-16 object-cover rounded">
<?php else: ?>
                    <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center"><span class="text-gray-400">Нет фото</span></div>
<?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($offerId) ?></td>
<?php foreach ($warehouseList as $wh): $info = $data['warehouses'][$wh]; ?>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <?= $info['stock'] ?>/<?= $info['sales'] ?>/<?= $info['order'] ?>
                </td>
<?php endforeach; ?>
            </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
