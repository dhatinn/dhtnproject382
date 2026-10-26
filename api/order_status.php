<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$orderCode = cleanInput($_GET['code'] ?? '');

if (empty($orderCode)) {
    jsonResponse(['success' => false, 'message' => 'Kode pesanan diperlukan.'], 400);
}

try {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
    }
    
    // Fetch items
    $stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll();
    
    foreach ($items as &$it) {
        $it['formatted_price'] = formatRupiah($it['price']);
        $it['formatted_subtotal'] = formatRupiah($it['subtotal']);
    }
    
    // Timeline steps definition
    $steps = [
        ['key' => 'pending', 'label' => 'Pesanan Dibuat', 'desc' => 'Menunggu penyelesaian pembayaran', 'done' => true],
        ['key' => 'paid', 'label' => 'Pembayaran Terverifikasi', 'desc' => 'Pembayaran via ' . strtoupper($order['payment_method']) . ' terkonfirmasi', 'done' => $order['payment_status'] === 'paid'],
        ['key' => 'cooking', 'label' => 'Diracik di Dapur Bundo', 'desc' => 'Koki sedang meracik bumbu & lauk hangat', 'done' => in_array($order['order_status'], ['cooking', 'ready', 'completed'])],
        ['key' => 'ready', 'label' => $order['order_type'] === 'dine_in' ? 'Disajikan ke Meja ' . $order['table_number'] : 'Siap Diantar Kurir', 'desc' => $order['order_type'] === 'dine_in' ? 'Pelayan mengantar pesanan ke meja Anda' : 'Pesanan dibawa kurir menuju alamat Anda', 'done' => in_array($order['order_status'], ['ready', 'completed'])],
        ['key' => 'completed', 'label' => 'Pesanan Selesai', 'desc' => 'Selamat menikmati sajian khas Harapan Bundo!', 'done' => $order['order_status'] === 'completed']
    ];
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $order['id'],
            'order_code' => $order['order_code'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'order_type' => $order['order_type'],
            'table_number' => $order['table_number'],
            'delivery_address' => $order['delivery_address'],
            'subtotal' => (float)$order['subtotal'],
            'formatted_subtotal' => formatRupiah($order['subtotal']),
            'delivery_fee' => (float)$order['delivery_fee'],
            'formatted_delivery_fee' => formatRupiah($order['delivery_fee']),
            'total_amount' => (float)$order['total_amount'],
            'formatted_total' => formatRupiah($order['total_amount']),
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'payment_ref' => $order['payment_ref'],
            'qris_raw_string' => $order['qris_raw_string'],
            'order_status' => $order['order_status'],
            'created_at' => $order['created_at'],
            'items' => $items,
            'timeline' => $steps
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Gagal memuat status: ' . $e->getMessage()], 500);
}
