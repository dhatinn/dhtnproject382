<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

// Strict Admin Security Check
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized access.'], 401);
}

$action = cleanInput($_GET['action'] ?? $_POST['action'] ?? '');

try {
    $db = getDb();
    
    // ACTION: Get Order Details
    if ($action === 'detail') {
        $orderCode = cleanInput($_GET['order_code'] ?? '');
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
        $stmt->execute([$orderCode]);
        $order = $stmt->fetch();
        
        if (!$order) {
            jsonResponse(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }
        
        $stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$order['id']]);
        $items = $stmtItems->fetchAll();
        
        foreach ($items as &$it) {
            $it['formatted_price'] = formatRupiah($it['price']);
            $it['formatted_subtotal'] = formatRupiah($it['subtotal']);
        }
        
        $order['formatted_subtotal'] = formatRupiah($order['subtotal']);
        $order['formatted_delivery_fee'] = formatRupiah($order['delivery_fee']);
        $order['formatted_total'] = formatRupiah($order['total_amount']);
        $order['items'] = $items;
        
        jsonResponse(['success' => true, 'data' => $order]);
    }
    
    // ACTION: Update Order Status
    if ($action === 'update_status') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: $_POST;
        
        $orderCode = cleanInput($payload['order_code'] ?? '');
        $newStatus = cleanInput($payload['order_status'] ?? '');
        $paymentStatus = cleanInput($payload['payment_status'] ?? '');
        
        $validStatuses = ['pending', 'confirmed', 'cooking', 'ready', 'completed', 'cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            jsonResponse(['success' => false, 'message' => 'Status pesanan tidak valid.'], 422);
        }
        
        $query = "UPDATE orders SET order_status = ?";
        $params = [$newStatus];
        
        if (!empty($paymentStatus)) {
            $query .= ", payment_status = ?";
            $params[] = $paymentStatus;
            if ($paymentStatus === 'paid') {
                $query .= ", paid_at = IFNULL(paid_at, NOW())";
            }
        }
        
        $query .= " WHERE order_code = ?";
        $params[] = $orderCode;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true, 
            'message' => 'Status pesanan berhasil diperbarui!',
            'order_code' => $orderCode,
            'new_status' => $newStatus
        ]);
    }
    
    // ACTION: Check New Orders for Live Audio Alert
    if ($action === 'check_new') {
        $lastId = (int)($_GET['last_id'] ?? 0);
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE id > ?");
        $stmt->execute([$lastId]);
        $newCount = (int)$stmt->fetchColumn();
        
        $stmtMax = $db->query("SELECT MAX(id) FROM orders");
        $maxId = (int)$stmtMax->fetchColumn();
        
        jsonResponse([
            'success' => true,
            'has_new' => $newCount > 0,
            'new_count' => $newCount,
            'max_id' => $maxId
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi tidak valid.'], 400);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Kesalahan server: ' . $e->getMessage()], 500);
}
