<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'OzonApi.php';
require_once 'Database.php';

session_start();

$db = new Database();
$api = new OzonApi();

// Обработка POST запроса на обновление данных
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    try {
        $salesData = $api->getSalesData();
        $db->saveSalesData($salesData);
        $_SESSION['message'] = 'Данные успешно обновлены';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка при обновлении данных: ' . $e->getMessage();
    }
}

// Получаем данные о продажах из базы данных
$salesData = $db->getSalesData();

// Группируем данные по SKU и складу
$groupedSales = [];
foreach ($salesData as $sale) {
    if (!isset($sale['sku']) || !isset($sale['cluster_to']) || !isset($sale['sales_count'])) {
        continue;
    }
    
    $sku = $sale['sku'];
    $cluster = $sale['cluster_to'];
    
    if (!isset($groupedSales[$sku])) {
        $groupedSales[$sku] = [];
    }
    
    if (!isset($groupedSales[$sku][$cluster])) {
        $groupedSales[$sku][$cluster] = 0;
    }
    
    $groupedSales[$sku][$cluster] += $sale['sales_count'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Продажи за последние 30 дней</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container {
            background-color: #f8f9fa;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
        .error-details {
            background-color: #fff3f3;
            color: #dc3545;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
        .table-responsive {
            overflow-x: auto;
            max-width: 100%;
        }
        .table {
            width: auto;
            min-width: 100%;
        }
        th, td {
            white-space: nowrap;
            padding: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Продажи за последние 30 дней</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="mb-4">
            <button type="submit" name="update" class="btn btn-primary">Обновить данные</button>
        </form>
        
        <?php if (isset($_SESSION['api_log'])): ?>
            <div class="log-container">
                <h3>Логи API:</h3>
                <?php echo nl2br(htmlspecialchars($_SESSION['api_log'])); unset($_SESSION['api_log']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($groupedSales)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="position: sticky; left: 0; background: white; z-index: 1;">Изображение</th>
                            <th style="position: sticky; left: 100px; background: white; z-index: 1;">Артикул</th>
                            <?php
                            // Получаем уникальные кластеры
                            $clusters = [];
                            foreach ($salesData as $sale) {
                                if (!in_array($sale['cluster_to'], $clusters)) {
                                    $clusters[] = $sale['cluster_to'];
                                }
                            }
                            sort($clusters);
                            
                            foreach ($clusters as $cluster) {
                                echo "<th>{$cluster}</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Группируем данные по артикулу
                        $groupedData = [];
                        foreach ($salesData as $sale) {
                            $offerId = $sale['offer_id'];
                            if (!isset($groupedData[$offerId])) {
                                $groupedData[$offerId] = [
                                    'sku' => $sale['sku'],
                                    'primary_image' => $sale['primary_image'],
                                    'clusters' => []
                                ];
                            }
                            $groupedData[$offerId]['clusters'][$sale['cluster_to']] = $sale['sales_count'];
                        }
                        
                        foreach ($groupedData as $offerId => $data) {
                            echo "<tr>";
                            echo "<td style='position: sticky; left: 0; background: white; z-index: 1;'>";
                            if (!empty($data['primary_image'])) {
                                echo "<img src='{$data['primary_image']}' alt='{$data['sku']}' style='max-width: 100px; max-height: 100px;'>";
                            }
                            echo "</td>";
                            echo "<td style='position: sticky; left: 100px; background: white; z-index: 1;'>{$data['sku']}</td>";
                            
                            foreach ($clusters as $cluster) {
                                $count = $data['clusters'][$cluster] ?? 0;
                                echo "<td>{$count}</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Нет данных о продажах за последние 30 дней
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
