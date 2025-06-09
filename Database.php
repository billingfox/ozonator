<?php
/**
 * Класс для работы с SQLite базой данных
 */
class Database {
    private $db;

    /**
     * Конструктор класса
     * Создает подключение к базе данных и инициализирует таблицы
     */
    public function __construct() {
        try {
            // Определяем путь к базе данных
            $dbDir = '/var/www/html/db';
            $dbPath = $dbDir . '/ozon_products.db';
            
            // Создаем директорию, если её нет
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            // Устанавливаем права доступа
            chmod($dbDir, 0755);
            
            // Подключаемся к базе данных
            $this->db = new PDO('sqlite:' . $dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Создаем таблицу products, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER UNIQUE,
                    offer_id TEXT UNIQUE,
                    sku INTEGER,
                    name TEXT,
                    price REAL,
                    marketing_price REAL,
                    currency_code TEXT,
                    status TEXT,
                    status_name TEXT,
                    primary_image TEXT,
                    images TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Создаем таблицу warehouses, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS warehouses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Создаем таблицу stocks, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS stocks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER,
                    warehouse_id INTEGER,
                    valid_stock_count INTEGER DEFAULT 0,
                    waitingdocs_stock_count INTEGER DEFAULT 0,
                    expiring_stock_count INTEGER DEFAULT 0,
                    defect_stock_count INTEGER DEFAULT 0,
                    promised_amount INTEGER DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
                )
            ");
            
            // Создаем таблицу products_in_transit, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS products_in_transit (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER,
                    offer_id TEXT,
                    sku INTEGER,
                    warehouse_id INTEGER,
                    reserved_amount INTEGER DEFAULT 0,
                    promised_amount INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id),
                    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
                )
            ");
            
            // Создаем таблицу update_info, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS update_info (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    last_update DATETIME,
                    total_products INTEGER
                )
            ");

            // Создаем таблицу sales, если она не существует
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sales (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER,
                    offer_id TEXT,
                    sku INTEGER,
                    cluster_to TEXT,
                    sales_count INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )
            ");

            // Таблица акций
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS promotions (
                    id INTEGER PRIMARY KEY,
                    name TEXT,
                    type TEXT,
                    is_participant INTEGER DEFAULT 0,
                    date_from TEXT,
                    date_to TEXT,
                    remove_products INTEGER DEFAULT 0
                )
            ");

            // Таблица товаров в акциях
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS promotion_products (
                    promotion_id INTEGER,
                    product_id INTEGER,
                    PRIMARY KEY (promotion_id, product_id),
                    FOREIGN KEY (promotion_id) REFERENCES promotions(id),
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )
            ");
            
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    /**
     * Сохранение информации о товаре
     * 
     * @param array $product Данные товара
     */
    public function saveProduct($product) {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO products 
            (product_id, offer_id, sku, name, price, marketing_price, currency_code, status, status_name, primary_image, images) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $primaryImage = is_array($product['primary_image']) ? ($product['primary_image'][0] ?? null) : $product['primary_image'];
        $images = is_array($product['images']) ? json_encode($product['images']) : $product['images'];
        
        $stmt->execute([
            $product['id'] ?? null,
            $product['offer_id'] ?? null,
            $product['sources'][0]['sku'] ?? null,
            $product['name'] ?? null,
            $product['price'] ?? '0',
            $product['marketing_price'] ?? '0',
            $product['currency_code'] ?? 'RUB',
            $product['statuses']['status'] ?? null,
            $product['statuses']['status_name'] ?? null,
            $primaryImage,
            $images
        ]);
    }

    /**
     * Сохранение информации об остатках
     * 
     * @param array $stocks Данные об остатках
     * @param array $transitData Данные о товарах в пути
     */
    public function saveStocks($stocks, $transitData = []) {
        try {
            $this->db->beginTransaction();

            // Подготавливаем запросы
            $productStmt = $this->db->prepare("INSERT OR IGNORE INTO products (offer_id, sku, name, primary_image) VALUES (?, ?, ?, ?)");
            $warehouseStmt = $this->db->prepare("INSERT OR IGNORE INTO warehouses (name) VALUES (?)");
            $stockStmt = $this->db->prepare("INSERT OR REPLACE INTO stocks (product_id, warehouse_id, valid_stock_count, waitingdocs_stock_count, expiring_stock_count, defect_stock_count, promised_amount) 
                VALUES (
                    (SELECT id FROM products WHERE offer_id = ?),
                    (SELECT id FROM warehouses WHERE name = ?),
                    ?, ?, ?, ?, ?
                )");

            // Создаем индекс для быстрого поиска данных о товарах в пути
            $transitIndex = [];
            foreach ($transitData as $item) {
                $key = $item['sku'] . '_' . $item['warehouse_name'];
                $transitIndex[$key] = $item;
            }

            foreach ($stocks['items'] as $item) {
                // Сохраняем товар
                $productStmt->execute([
                    $item['offer_id'] ?? null,
                    $item['sku'] ?? null,
                    $item['name'] ?? null,
                    $item['primary_image'] ?? null
                ]);
                
                // Сохраняем склад
                $warehouseStmt->execute([$item['warehouse_name'] ?? null]);
                
                // Находим соответствующие данные о товарах в пути
                $key = ($item['sku'] ?? '') . '_' . ($item['warehouse_name'] ?? '');
                $transitItem = $transitIndex[$key] ?? null;
                
                // Сохраняем остатки
                $stockStmt->execute([
                    $item['offer_id'] ?? null,
                    $item['warehouse_name'] ?? null,
                    $item['valid_stock_count'] ?? 0,
                    $item['waitingdocs_stock_count'] ?? 0,
                    $item['expiring_stock_count'] ?? 0,
                    $item['defect_stock_count'] ?? 0,
                    $transitItem['promised_amount'] ?? 0
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Получение информации о последнем обновлении
     * 
     * @return array Информация о последнем обновлении
     */
    public function getLastUpdateInfo() {
        $result = $this->db->query('SELECT * FROM update_info ORDER BY id DESC LIMIT 1');
        return $result->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Получение списка всех товаров
     * 
     * @return array Список товаров
     */
    public function getAllProducts() {
        $products = [];
        $result = $this->db->query('SELECT * FROM products ORDER BY id DESC');
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $products[] = $row;
        }
        return $products;
    }

    /**
     * Получение остатков товара
     * 
     * @return array Список остатков
     */
    public function getStocks() {
        $stmt = $this->db->query("
            SELECT 
                p.id,
                p.offer_id,
                p.sku,
                p.name as product_name,
                p.primary_image,
                p.images,
                w.name as warehouse_name,
                s.valid_stock_count,
                s.waitingdocs_stock_count,
                s.expiring_stock_count,
                s.defect_stock_count
            FROM stocks s
            JOIN products p ON s.product_id = p.id
            JOIN warehouses w ON s.warehouse_id = w.id
            ORDER BY p.offer_id, w.name
        ");
        
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Декодируем JSON для images
        foreach ($stocks as &$stock) {
            if (!empty($stock['images'])) {
                $stock['images'] = json_decode($stock['images'], true) ?? [];
            } else {
                $stock['images'] = [];
            }
        }
        
        return $stocks;
    }

    public function saveUpdateInfo($totalProducts) {
        $stmt = $this->db->prepare("INSERT INTO update_info (last_update, total_products) VALUES (datetime('now'), ?)");
        $stmt->execute([$totalProducts]);
    }

    /**
     * Сохранение информации о товарах в пути
     * 
     * @param array $products Данные о товарах в пути
     */
    public function saveProductsInTransit($products) {
        if (empty($products)) {
            throw new Exception("Нет данных для сохранения");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Подготавливаем запросы
            $productStmt = $this->db->prepare("INSERT OR IGNORE INTO products (offer_id, sku, name, primary_image) VALUES (?, ?, ?, ?)");
            $warehouseStmt = $this->db->prepare("INSERT OR IGNORE INTO warehouses (name) VALUES (?)");
            $transitStmt = $this->db->prepare("INSERT OR REPLACE INTO products_in_transit 
                (product_id, offer_id, sku, warehouse_id, reserved_amount, promised_amount) 
                VALUES (
                    (SELECT id FROM products WHERE offer_id = ?),
                    ?,
                    ?,
                    (SELECT id FROM warehouses WHERE name = ?),
                    ?,
                    ?
                )");
            
            $insertedCount = 0;
            foreach ($products as $product) {
                if (empty($product['offer_id']) || empty($product['warehouse_name'])) {
                    continue;
                }
                
                // Сохраняем товар
                $productStmt->execute([
                    $product['offer_id'],
                    $product['sku'],
                    $product['name'],
                    $product['primary_image'] ?? null
                ]);
                
                // Сохраняем склад
                $warehouseStmt->execute([$product['warehouse_name']]);
                
                // Сохраняем данные о товаре в пути
                $transitStmt->execute([
                    $product['offer_id'],
                    $product['offer_id'],
                    $product['sku'],
                    $product['warehouse_name'],
                    $product['reserved_amount'],
                    $product['promised_amount']
                ]);
                
                $insertedCount++;
            }
            
            $this->db->commit();
            
            if ($insertedCount === 0) {
                throw new Exception("Не удалось сохранить данные о товарах в пути: нет подходящих записей");
            }
            
            return $insertedCount;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Получение списка товаров в пути
     * 
     * @return array Список товаров в пути
     */
    public function getProductsInTransit() {
        $stmt = $this->db->query("
            SELECT 
                t.id,
                t.offer_id,
                t.sku,
                t.reserved_amount,
                t.promised_amount,
                t.created_at,
                p.name,
                p.primary_image,
                w.name as warehouse_name
            FROM products_in_transit t
            LEFT JOIN products p ON t.offer_id = p.offer_id
            LEFT JOIN warehouses w ON t.warehouse_id = w.id
            WHERE t.reserved_amount > 0 OR t.promised_amount > 0
            ORDER BY t.offer_id, w.name
        ");
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($products)) {
            throw new Exception("Нет данных о товарах в пути в базе данных");
        }
        
        return $products;
    }

    /**
     * Сохраняет данные о продажах в базу данных
     * @param array $salesData Массив с данными о продажах
     */
    public function saveSalesData($salesData) {
        if (empty($salesData)) {
            throw new Exception("Нет данных для сохранения");
        }
        
        $this->db->beginTransaction();
        
        try {
            // Очищаем старые данные
            $this->db->exec("DELETE FROM sales");
            
            // Подготавливаем запросы
            $productStmt = $this->db->prepare("INSERT OR IGNORE INTO products (offer_id, sku) VALUES (?, ?)");
            $salesStmt = $this->db->prepare("
                INSERT INTO sales (
                    product_id, 
                    offer_id, 
                    sku, 
                    cluster_to, 
                    sales_count, 
                    created_at
                ) VALUES (
                    (SELECT id FROM products WHERE offer_id = ?), 
                    ?, 
                    ?, 
                    ?, 
                    ?, 
                    ?
                )
            ");
            
            $insertedCount = 0;
            foreach ($salesData as $sale) {
                if (empty($sale['offer_id']) || empty($sale['sku']) || empty($sale['cluster_to'])) {
                    continue;
                }
                
                // Сначала создаем запись о товаре, если её нет
                $productStmt->execute([
                    $sale['offer_id'],
                    $sale['sku']
                ]);
                
                // Затем сохраняем данные о продаже
                $salesStmt->execute([
                    $sale['offer_id'],
                    $sale['offer_id'],
                    $sale['sku'],
                    $sale['cluster_to'],
                    $sale['sales_count'],
                    date('Y-m-d H:i:s')
                ]);
                
                $insertedCount++;
            }
            
            $this->db->commit();
            
            if ($insertedCount === 0) {
                throw new Exception("Не удалось сохранить данные о продажах: нет подходящих записей");
            }
            
            return $insertedCount;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Получает данные о продажах из базы данных
     * @return array Массив с данными о продажах
     */
    public function getSalesData() {
        $query = "
            SELECT 
                p.primary_image,
                s.offer_id,
                s.sku,
                s.cluster_to,
                SUM(s.sales_count) as sales_count
            FROM sales s
            JOIN products p ON s.product_id = p.id
            GROUP BY s.offer_id, s.sku, s.cluster_to
            ORDER BY s.offer_id, s.sku, s.cluster_to
        ";
        
        $stmt = $this->db->query($query);
        $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($salesData)) {
            throw new Exception("Нет данных о продажах в базе данных");
        }
        
        return $salesData;
    }

    public function getProductIdByOfferId($offerId) {
        $stmt = $this->db->prepare("SELECT id FROM products WHERE offer_id = ?");
        $stmt->execute([$offerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }

    /**
     * Возвращает список акций
     * @return array
     */
    public function getPromotions() {
        $stmt = $this->db->query("SELECT * FROM promotions ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setPromotionRemoveFlag($promotionId, $flag) {
        $stmt = $this->db->prepare("UPDATE promotions SET remove_products = ? WHERE id = ?");
        $stmt->execute([$flag ? 1 : 0, $promotionId]);
    }

    public function getPromotionsToRemove() {
        $stmt = $this->db->query("SELECT * FROM promotions WHERE remove_products = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPromotionProducts($promotionId) {
        $stmt = $this->db->prepare(
            "SELECT p.* FROM promotion_products pp JOIN products p ON pp.product_id = p.id WHERE pp.promotion_id = ?"
        );
        $stmt->execute([$promotionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeProductsFromPromotion($promotionId) {
        $stmt = $this->db->prepare("DELETE FROM promotion_products WHERE promotion_id = ?");
        return $stmt->execute([$promotionId]);
    }

    public function addPromotion($data) {
        $stmt = $this->db->prepare(
            "INSERT OR REPLACE INTO promotions (id, name, type, is_participant, date_from, date_to, remove_products) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['id'],
            $data['name'],
            $data['type'],
            $data['is_participant'] ?? 0,
            $data['date_from'] ?? null,
            $data['date_to'] ?? null,
            $data['remove_products'] ?? 0
        ]);
    }

    public function addPromotionProduct($promotionId, $productId) {
        $stmt = $this->db->prepare(
            "INSERT OR IGNORE INTO promotion_products (promotion_id, product_id) VALUES (?, ?)"
        );
        $stmt->execute([$promotionId, $productId]);
    }
} 
