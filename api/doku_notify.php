<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Read raw notification payload
$rawPayload = file_get_contents('php://input');
$headers = getallheaders();

$requestId = $headers['Request-Id'] ?? $headers['request-id'] ?? ('REQ-' . time());
$signature = $headers['Signature'] ?? $headers['signature'] ?? '';
$timestamp = $headers['Request-Timestamp'] ?? $headers['request-timestamp'] ?? date('c');

$data = json_decode($rawPayload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

try {
    $db = getDb();
    
    // Extract transaction info (Supports both DOKU Checkout v2 and QRIS notification)
    $orderCode = cleanInput($data['order']['invoice_number'] ?? $data['order_code'] ?? '');
    $transactionStatus = $data['transaction']['status'] ?? $data['status'] ?? '';
    
    // Log incoming webhook notification
    $stmtLog = $db->prepare("
        INSERT INTO payment_logs (order_code, provider, event_type, raw_payload, ip_address)
        VALUES (?, 'DOKU_WEBHOOK', ?, ?, ?)
    ");
    $stmtLog->execute([
        $orderCode, 
        $transactionStatus ?: 'UNKNOWN', 
        $rawPayload, 
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    if (!empty($orderCode)) {
        if (strtoupper($transactionStatus) === 'SUCCESS' || strtoupper($transactionStatus) === 'PAID') {
            $stmtUpdate = $db->prepare("
                UPDATE orders 
                SET payment_status = 'paid', 
                    paid_at = NOW(), 
                    order_status = 'cooking',
                    payment_ref = ?
                WHERE order_code = ? AND payment_status != 'paid'
            ");
            $ref = $data['transaction']['reference_id'] ?? ('DOKU-' . time());
            $stmtUpdate->execute([$ref, $orderCode]);
        }
    }

    // Acknowledge DOKU server
    http_response_code(200);
    echo json_encode([
        'responseCode' => '2000000',
        'responseMessage' => 'Notification received successfully'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
