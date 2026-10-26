<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode request tidak diizinkan.'], 405);
}

// Read raw JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    jsonResponse(['success' => false, 'message' => 'Format data pesanan tidak valid.'], 400);
}

// Sanitize & Validate inputs
$customerName = cleanInput($data['customer_name'] ?? '');
$customerPhone = cleanInput($data['customer_phone'] ?? '');
$orderType = cleanInput($data['order_type'] ?? 'dine_in');
$tableNumber = cleanInput($data['table_number'] ?? '');
$deliveryAddress = cleanInput($data['delivery_address'] ?? '');
$deliveryNotes = cleanInput($data['delivery_notes'] ?? '');
$paymentMethod = cleanInput($data['payment_method'] ?? 'qris');
$notes = cleanInput($data['notes'] ?? '');
$cartItems = $data['items'] ?? [];

if (empty($customerName)) {
    jsonResponse(['success' => false, 'message' => 'Nama pemesan wajib diisi.'], 422);
}

if (empty($customerPhone) || strlen($customerPhone) < 8) {
    jsonResponse(['success' => false, 'message' => 'Nomor WhatsApp aktif wajib diisi (minimal 8 digit).'], 422);
}

if (!in_array($orderType, ['dine_in', 'delivery', 'takeaway'])) {
    jsonResponse(['success' => false, 'message' => 'Jenis pesanan tidak valid.'], 422);
}

if ($orderType === 'dine_in' && empty($tableNumber)) {
    jsonResponse(['success' => false, 'message' => 'Nomor meja wajib dipilih untuk pesanan Dine-in.'], 422);
}

if ($orderType === 'delivery' && empty($deliveryAddress)) {
    jsonResponse(['success' => false, 'message' => 'Alamat pengantaran wajib diisi untuk pesanan Delivery.'], 422);
}

if (empty($cartItems) || !is_array($cartItems)) {
    jsonResponse(['success' => false, 'message' => 'Keranjang pesanan masih kosong.'], 422);
}

try {
    $db = getDb();
    $db->beginTransaction();

    // Collect Menu IDs and preserve each distinct cart line entry
    $menuIds = [];
    $validCartEntries = [];
    foreach ($cartItems as $item) {
        $id = (int)($item['menu_id'] ?? $item['id'] ?? 0);
        $qty = (int)($item['quantity'] ?? $item['qty'] ?? 1);
        if ($id > 0 && $qty > 0) {
            $menuIds[] = $id;
            $validCartEntries[] = [
                'id' => $id,
                'qty' => $qty,
                'notes' => cleanInput($item['notes'] ?? ''),
                'addon_price' => isset($item['addon_price']) ? (float)$item['addon_price'] : 0
            ];
        }
    }

    if (empty($menuIds)) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Item dalam keranjang tidak valid.'], 422);
    }

    $uniqueMenuIds = array_values(array_unique($menuIds));
    // Fetch verified menu prices from database (CRITICAL SECURITY: Prevents client price tampering)
    $placeholders = implode(',', array_fill(0, count($uniqueMenuIds), '?'));
    $stmt = $db->prepare("SELECT id, name, price, promo_price, is_available FROM menu_items WHERE id IN ($placeholders)");
    $stmt->execute($uniqueMenuIds);
    $dbMenus = $stmt->fetchAll();

    if (empty($dbMenus)) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => 'Menu yang dipilih tidak ditemukan.'], 404);
    }

    $dbMenuMap = [];
    foreach ($dbMenus as $m) {
        $dbMenuMap[$m['id']] = $m;
    }

    $subtotal = 0;
    $orderItemsToInsert = [];

    foreach ($validCartEntries as $entry) {
        $mid = $entry['id'];
        $menu = $dbMenuMap[$mid] ?? null;
        if (!$menu) continue;

        if (!$menu['is_available']) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => "Mohon maaf, menu '{$menu['name']}' saat ini sedang habis."], 400);
        }

        $basePrice = (!empty($menu['promo_price']) && $menu['promo_price'] > 0) ? (float)$menu['promo_price'] : (float)$menu['price'];
        // Allow verified add-on increments up to 30,000 max per item
        $addonPrice = max(0, min(30000, (float)$entry['addon_price']));
        $price = $basePrice + $addonPrice;
        $qty = $entry['qty'];
        $itemSubtotal = $price * $qty;
        $subtotal += $itemSubtotal;

        $orderItemsToInsert[] = [
            'menu_id' => $mid,
            'name' => $menu['name'],
            'price' => $price,
            'qty' => $qty,
            'subtotal' => $itemSubtotal,
            'notes' => $entry['notes']
        ];
    }

    // Delivery fee calculation
    $deliveryFee = 0;
    if ($orderType === 'delivery') {
        $deliveryFee = (float)getSetting('delivery_flat_fee', '10000');
    }

    // Tax calculation
    $taxPercentage = (float)getSetting('tax_percentage', '0');
    $taxAmount = ($subtotal * $taxPercentage) / 100;

    $totalAmount = $subtotal + $deliveryFee + $taxAmount;
    $orderCode = generateOrderCode();

    // Generate Standard Indonesian QRIS string
    $qrisPayload = generateQrisPayload($orderCode, $totalAmount);

    // Insert Order
    $stmtOrder = $db->prepare("
        INSERT INTO `orders` (
            `order_code`, `customer_name`, `customer_phone`, `order_type`, 
            `table_number`, `delivery_address`, `delivery_notes`, 
            `subtotal`, `tax_amount`, `delivery_fee`, `total_amount`, 
            `payment_method`, `payment_status`, `qris_raw_string`, `order_status`, `notes`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, 'pending', ?)
    ");
    
    $stmtOrder->execute([
        $orderCode, $customerName, $customerPhone, $orderType,
        $tableNumber, $deliveryAddress, $deliveryNotes,
        $subtotal, $taxAmount, $deliveryFee, $totalAmount,
        $paymentMethod, $qrisPayload, $notes
    ]);

    $orderId = (int)$db->lastInsertId();

    // Insert Order Items
    $stmtItems = $db->prepare("
        INSERT INTO `order_items` (`order_id`, `menu_id`, `menu_name`, `price`, `quantity`, `subtotal`, `notes`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($orderItemsToInsert as $oi) {
        $stmtItems->execute([
            $orderId,
            $oi['menu_id'],
            $oi['name'],
            $oi['price'],
            $oi['qty'],
            $oi['subtotal'],
            $oi['notes']
        ]);
        
        // Increment total_sold in menu_items
        $db->prepare("UPDATE menu_items SET total_sold = total_sold + ? WHERE id = ?")->execute([$oi['qty'], $oi['menu_id']]);
    }

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Pesanan berhasil dibuat!',
        'data' => [
            'order_id' => $orderId,
            'order_code' => $orderCode,
            'customer_name' => $customerName,
            'order_type' => $orderType,
            'subtotal' => $subtotal,
            'formatted_subtotal' => formatRupiah($subtotal),
            'delivery_fee' => $deliveryFee,
            'formatted_delivery_fee' => formatRupiah($deliveryFee),
            'total_amount' => $totalAmount,
            'formatted_total' => formatRupiah($totalAmount),
            'payment_method' => $paymentMethod,
            'payment_status' => 'unpaid',
            'qris_payload' => $qrisPayload,
            'track_url' => 'track.php?code=' . urlencode($orderCode)
        ]
    ], 201);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Gagal memproses pesanan: ' . $e->getMessage()], 500);
}
