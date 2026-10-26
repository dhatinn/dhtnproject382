<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = cleanInput($_GET['action'] ?? $_POST['action'] ?? 'status');
$orderCode = cleanInput($_GET['order_code'] ?? $_POST['order_code'] ?? '');

if (empty($orderCode)) {
    jsonResponse(['success' => false, 'message' => 'Kode pesanan (order_code) tidak disertakan.'], 400);
}

try {
    $db = getDb();
    
    // Fetch Order
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
    }
    
    // ACTION 1: Check Payment Status
    if ($action === 'status') {
        jsonResponse([
            'success' => true,
            'data' => [
                'order_code' => $order['order_code'],
                'customer_name' => $order['customer_name'],
                'total_amount' => (float)$order['total_amount'],
                'formatted_total' => formatRupiah($order['total_amount']),
                'payment_method' => $order['payment_method'],
                'payment_status' => $order['payment_status'],
                'order_status' => $order['order_status'],
                'paid_at' => $order['paid_at'],
                'is_paid' => $order['payment_status'] === 'paid'
            ]
        ]);
    }
    
    // ACTION 2: Simulate QRIS / DOKU Payment (Sandbox & Quick Testing)
    if ($action === 'simulate_pay') {
        if ($order['payment_status'] === 'paid') {
            jsonResponse([
                'success' => true, 
                'message' => 'Pesanan ini sudah berstatus lunas sebelumnya.',
                'already_paid' => true
            ]);
        }
        
        $ref = 'SIM-' . date('YmdHis') . '-' . rand(1000, 9999);
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                paid_at = NOW(), 
                order_status = 'cooking',
                payment_ref = ?
            WHERE order_code = ?
        ");
        $updateStmt->execute([$ref, $orderCode]);
        
        // Log payment
        $logStmt = $db->prepare("
            INSERT INTO payment_logs (order_code, provider, event_type, raw_payload, ip_address)
            VALUES (?, 'SIMULATION_QRIS', 'PAYMENT_SUCCESS', ?, ?)
        ");
        $payloadJson = json_encode([
            'simulated' => true,
            'ref' => $ref,
            'amount' => $order['total_amount'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $logStmt->execute([$orderCode, $payloadJson, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Pembayaran berhasil dikonfirmasi! Pesanan segera disiapkan.',
            'data' => [
                'order_code' => $orderCode,
                'payment_status' => 'paid',
                'payment_ref' => $ref,
                'order_status' => 'cooking'
            ]
        ]);
    }
    
    // ACTION 3: Create DOKU Checkout Token / URL (Production & Sandbox API)
    if ($action === 'doku_checkout') {
        $clientId = getSetting('doku_client_id');
        $sharedKey = getSetting('doku_shared_key');
        $mode = getSetting('doku_mode', 'sandbox');
        
        // Return simulated DOKU checkout page or real endpoint
        $checkoutData = [
            'order_code' => $orderCode,
            'amount' => (int)$order['total_amount'],
            'client_id' => $clientId,
            'mode' => $mode,
            'checkout_url' => "payment_gateway.php?code=" . urlencode($orderCode)
        ];
        
        jsonResponse([
            'success' => true,
            'message' => 'DOKU Checkout siap.',
            'data' => $checkoutData
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi pembayaran tidak dikenali.'], 400);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
}
