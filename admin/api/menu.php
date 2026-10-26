<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized access.'], 401);
}

$action = cleanInput($_GET['action'] ?? $_POST['action'] ?? '');

try {
    $db = getDb();
    
    // ACTION 1: Toggle Availability (Stock on/off)
    if ($action === 'toggle_availability') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE menu_items SET is_available = IF(is_available = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$id]);
        
        $fetchStmt = $db->prepare("SELECT is_available, name FROM menu_items WHERE id = ?");
        $fetchStmt->execute([$id]);
        $row = $fetchStmt->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Status stok menu ' . $row['name'] . ' berhasil diubah.',
            'is_available' => (int)$row['is_available']
        ]);
    }
    
    // ACTION 2: Delete Menu Item
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse(['success' => true, 'message' => 'Menu berhasil dihapus.']);
    }
    
    // ACTION 3: Save / Update Menu Item
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = cleanInput($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $promoPrice = !empty($_POST['promo_price']) ? (float)$_POST['promo_price'] : null;
        $spicyLevel = (int)($_POST['spicy_level'] ?? 1);
        $description = cleanInput($_POST['description'] ?? '');
        $imageUrl = cleanInput($_POST['image_url'] ?? '');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        
        if (empty($name) || $price <= 0 || $categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Nama, kategori, dan harga wajib diisi dengan benar.'], 422);
        }
        
        // Handle file upload if provided
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowedTypes)) {
                jsonResponse(['success' => false, 'message' => 'Format gambar harus JPEG, PNG, atau WEBP.'], 422);
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                jsonResponse(['success' => false, 'message' => 'Ukuran gambar maksimal 5MB.'], 422);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = 'menu_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../assets/images/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $destPath = $uploadDir . $newFilename;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $imageUrl = 'assets/images/' . $newFilename;
            }
        }
        
        if (empty($imageUrl)) {
            // Default placeholder if none provided
            $imageUrl = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80';
        }
        
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        
        if ($id > 0) {
            // Update
            $stmt = $db->prepare("
                UPDATE menu_items 
                SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, 
                    promo_price = ?, spicy_level = ?, image_url = ?, is_available = ?, is_featured = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $categoryId, $name, $slug, $description, $price, 
                $promoPrice, $spicyLevel, $imageUrl, $isAvailable, $isFeatured, $id
            ]);
            $msg = 'Menu berhasil diperbarui!';
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO menu_items (category_id, name, slug, description, price, promo_price, spicy_level, image_url, is_available, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $categoryId, $name, $slug, $description, $price, 
                $promoPrice, $spicyLevel, $imageUrl, $isAvailable, $isFeatured
            ]);
            $id = (int)$db->lastInsertId();
            $msg = 'Menu baru berhasil ditambahkan!';
        }
        
        jsonResponse(['success' => true, 'message' => $msg, 'id' => $id]);
    }
    
    // ACTION 4: Get Single Item Data for Edit Modal
    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $menu = $stmt->fetch();
        if (!$menu) {
            jsonResponse(['success' => false, 'message' => 'Menu tidak ditemukan.'], 404);
        }
        jsonResponse(['success' => true, 'data' => $menu]);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi tidak valid.'], 400);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Kesalahan server: ' . $e->getMessage()], 500);
}
