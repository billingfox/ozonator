<?php
require_once 'Database.php';

$db = new Database();

$promotions = $db->getPromotionsToRemove();
foreach ($promotions as $promo) {
    $before = count($db->getPromotionProducts($promo['id']));
    $db->removeProductsFromPromotion($promo['id']);
    $after = count($db->getPromotionProducts($promo['id']));
    echo "Promotion {$promo['id']} - removed " . ($before - $after) . " products\n";
}
