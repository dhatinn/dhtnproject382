<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDb();
    
    // Check if requesting categories
    if (isset($_GET['categories'])) {
        $stmt = $db->prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $categories]);
    }
    
    // Build query for menu items
    $query = "SELECT m.*, c.name AS category_name, c.slug AS category_slug 
              FROM menu_items m
              JOIN categories c ON m.category_id = c.id
              WHERE 1=1";
    $params = [];
    
    // Filter by Category
    if (!empty($_GET['category'])) {
        $cat = cleanInput($_GET['category']);
        if (is_numeric($cat)) {
            $query .= " AND m.category_id = ?";
            $params[] = (int)$cat;
        } else {
            $query .= " AND c.slug = ?";
            $params[] = $cat;
        }
    }
    
    // Filter by Featured
    if (isset($_GET['featured']) && $_GET['featured'] == '1') {
        $query .= " AND m.is_featured = 1";
    }
    
    // Search Query
    if (!empty($_GET['q'])) {
        $search = '%' . cleanInput($_GET['q']) . '%';
        $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }
    
    // Availability filter (default show only available for public, or all if admin)
    if (!isset($_GET['all'])) {
        $query .= " AND m.is_available = 1";
    }
    
    $query .= " ORDER BY m.is_featured DESC, m.total_sold DESC, m.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    // Format prices & labels
    foreach ($items as &$item) {
        $item['formatted_price'] = formatRupiah($item['price']);
        $item['final_price'] = !empty($item['promo_price']) && $item['promo_price'] > 0 ? (float)$item['promo_price'] : (float)$item['price'];
        $item['formatted_final_price'] = formatRupiah($item['final_price']);
        $item['has_promo'] = !empty($item['promo_price']) && $item['promo_price'] > 0;
        if ($item['has_promo']) {
            $item['formatted_promo_price'] = formatRupiah($item['promo_price']);
            $item['discount_percent'] = round((($item['price'] - $item['promo_price']) / $item['price']) * 100);
        }
    }
    
    jsonResponse([
        'success' => true,
        'count' => count($items),
        'data' => $items
    ]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Gagal mengambil data menu: ' . $e->getMessage()], 500);
}
