<?php
require_once 'Database.php';

$db = new Database();

// Обработка ajax запроса на переключение флага
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promotion_id'])) {
    $id = (int)$_POST['promotion_id'];
    $flag = isset($_POST['remove']) && $_POST['remove'] === '1';
    $db->setPromotionRemoveFlag($id, $flag);
    echo 'ok';
    return;
}

$promotions = $db->getPromotions();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Акции</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function toggleRemove(id, cb) {
            const formData = new URLSearchParams();
            formData.append('promotion_id', id);
            formData.append('remove', cb.checked ? '1' : '0');
            fetch('promotions.php', {method:'POST', body:formData});
        }
    </script>
</head>
<body class="bg-gray-100">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Акции</h1>
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Акция</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Вы участник</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Начало</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Конец</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Удалять товары из акции</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($promo['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $promo['id']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($promo['type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= $promo['is_participant'] ? 'Да' : 'Нет'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($promo['date_from']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($promo['date_to']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <input type="checkbox" onchange="toggleRemove(<?= $promo['id']; ?>, this)" <?= $promo['remove_products'] ? 'checked' : ''; ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
