<?php
require_once dirname(__DIR__).'/Database.php';

$db = new Database();

// добавляем тестовые данные
$db->addPromotion(['id'=>1,'name'=>'Test promo','type'=>'discount','is_participant'=>1,'date_from'=>'2024-01-01','date_to'=>'2024-12-31']);
$productId = $db->getProductIdByOfferId('test-offer');
if (!$productId) {
    // добавим продукт
    $db->saveProduct(['id'=>999,'offer_id'=>'test-offer','sources'=>[['sku'=>123]],'name'=>'Test Product']);
    $productId = $db->getProductIdByOfferId('test-offer');
}
$db->addPromotionProduct(1,$productId);

$before = $db->getPromotionProducts(1);
echo "Before remove: ".count($before)."\n";

$db->setPromotionRemoveFlag(1,1);
require dirname(__DIR__).'/promotions_cron.php';

$after = $db->getPromotionProducts(1);
echo "After remove: ".count($after)."\n";
