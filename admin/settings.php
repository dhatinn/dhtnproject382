<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();

$db = getDb();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settingsToUpdate = [
        'restaurant_name' => cleanInput($_POST['restaurant_name'] ?? ''),
        'tagline' => cleanInput($_POST['tagline'] ?? ''),
        'address' => cleanInput($_POST['address'] ?? ''),
        'whatsapp_number' => cleanInput($_POST['whatsapp_number'] ?? ''),
        'doku_mode' => cleanInput($_POST['doku_mode'] ?? 'sandbox'),
        'doku_client_id' => cleanInput($_POST['doku_client_id'] ?? ''),
        'doku_shared_key' => cleanInput($_POST['doku_shared_key'] ?? ''),
        'qris_merchant_name' => cleanInput($_POST['qris_merchant_name'] ?? ''),
        'qris_nmid' => cleanInput($_POST['qris_nmid'] ?? ''),
        'delivery_flat_fee' => cleanInput($_POST['delivery_flat_fee'] ?? '10000'),
        'tax_percentage' => cleanInput($_POST['tax_percentage'] ?? '0')
    ];

    try {
        $stmt = $db->prepare("
            INSERT INTO restaurant_settings (setting_key, setting_value) 
            VALUES (:k, :v) 
            ON DUPLICATE KEY UPDATE setting_value = :v
        ");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([':k' => $key, ':v' => $val]);
        }
        $msg = 'Pengaturan berhasil disimpan!';
    } catch (Exception $e) {
        $err = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
    }
}

// Fetch all settings
$allSettings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM restaurant_settings")->fetchAll();
foreach ($rows as $r) {
    $allSettings[$r['setting_key']] = $r['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Restoran & DOKU - RM Padang Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/GooeyNav.css">
</head>
<body>

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <div class="brand-info">
                <h2>Harapan Bundo</h2>
                <p><span class="brand-status-dot"></span> <span>Panel Manajemen Resto</span></p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <span class="nav-label">Navigasi Utama</span>
            <a href="index.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard Bento</span>
            </a>
            <a href="orders.php" class="nav-link">
                <i class="fa-solid fa-receipt"></i>
                <span>Kelola Pesanan</span>
            </a>
            <a href="menu.php" class="nav-link">
                <i class="fa-solid fa-bowl-food"></i>
                <span>Manajemen Menu</span>
            </a>

            <span class="nav-label">Pengaturan Resto</span>
            <a href="settings.php" class="nav-link active">
                <i class="fa-solid fa-gear"></i>
                <span>Pengaturan & DOKU</span>
            </a>
            <a href="../index.php" target="_blank" class="nav-link">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Lihat Web Menu</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                    <div class="user-role"><i class="fa-solid fa-shield-halved"></i> <span>Administrator</span></div>
                </div>
                <a href="logout.php" class="logout-btn" title="Keluar">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <main class="admin-main">
        <header class="top-header">
            <div class="header-title">
                <h1>Pengaturan Restoran &amp; Integrasi DOKU</h1>
                <p>Konfigurasi identitas restoran, tarif layanan, dan payment gateway</p>
            </div>
            <div class="header-actions">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" title="Buka Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </header>

        <div class="page-container" style="max-width: 900px;">
            <?php if (!empty($msg)): ?>
                <div style="background: var(--status-completed-bg); color: var(--status-completed-text); padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($err)): ?>
                <div style="background: var(--status-cancelled-bg); color: var(--status-cancelled-text); padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span><?= htmlspecialchars($err) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Resto Info -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-store"></i>
                            <span>Profil & Kontak Rumah Makan</span>
                        </div>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Nama Restoran</label>
                                <input type="text" name="restaurant_name" value="<?= htmlspecialchars($allSettings['restaurant_name'] ?? 'RM Padang Harapan Bundo') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" required>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Nomor WhatsApp Resto (Untuk Notifikasi)</label>
                                <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($allSettings['whatsapp_number'] ?? '6281234567890') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" required>
                            </div>

                            <div style="grid-column: span 2;">
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Slogan / Tagline</label>
                                <input type="text" name="tagline" value="<?= htmlspecialchars($allSettings['tagline'] ?? '') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                            </div>

                            <div style="grid-column: span 2;">
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Alamat Fisik Restoran</label>
                                <textarea name="address" rows="2" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);"><?= htmlspecialchars($allSettings['address'] ?? '') ?></textarea>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Biaya Ongkir Flat (Rp)</label>
                                <input type="number" name="delivery_flat_fee" value="<?= htmlspecialchars($allSettings['delivery_flat_fee'] ?? '10000') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Pajak PB1 / Resto (%)</label>
                                <input type="number" name="tax_percentage" value="<?= htmlspecialchars($allSettings['tax_percentage'] ?? '0') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Integration Settings -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>Integrasi QRIS & DOKU Payment Gateway</span>
                        </div>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Mode DOKU</label>
                                <select name="doku_mode" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                                    <option value="sandbox" <?= ($allSettings['doku_mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Pengujian / Demo)</option>
                                    <option value="production" <?= ($allSettings['doku_mode'] ?? '') === 'production' ? 'selected' : '' ?>>Production (Live / Siap Transaksi Nyata)</option>
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Nama Merchant QRIS</label>
                                <input type="text" name="qris_merchant_name" value="<?= htmlspecialchars($allSettings['qris_merchant_name'] ?? 'RM PADANG HARAPAN BUNDO') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">DOKU Client ID (Mall ID)</label>
                                <input type="text" name="doku_client_id" value="<?= htmlspecialchars($allSettings['doku_client_id'] ?? '') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="MALL-ID-...">
                            </div>

                            <div>
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">DOKU Shared Key (Secret Key)</label>
                                <input type="text" name="doku_shared_key" value="<?= htmlspecialchars($allSettings['doku_shared_key'] ?? '') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="SEC-KEY-...">
                            </div>

                            <div style="grid-column: span 2;">
                                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">National Merchant ID (NMID) QRIS</label>
                                <input type="text" name="qris_nmid" value="<?= htmlspecialchars($allSettings['qris_nmid'] ?? 'ID1020039281720') ?>" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                                <small style="color: var(--text-muted); display: block; margin-top: 4px;">NMID resmi yang tertera pada sertifikat QRIS Bank Indonesia / DOKU merchant Anda.</small>
                            </div>

                            <div style="grid-column: span 2; background: rgba(255, 255, 255, 0.035); border: 1px dashed rgba(255, 255, 255, 0.12); padding: 16px; border-radius: 12px; font-size: 0.84rem; color: var(--text-secondary);">
                                <strong style="color: var(--text-primary);"><i class="fa-solid fa-circle-info" style="color: var(--gold);"></i> Webhook Notification URL untuk Dashboard DOKU:</strong><br>
                                <code style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 255, 255, 0.1); color: #38BDF8; padding: 6px 12px; border-radius: 8px; display: inline-block; margin-top: 8px; font-family: monospace; font-size: 0.85rem;">http://localhost/restorant/api/doku_notify.php</code>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 0.95rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Semua Pengaturan
                </button>
            </form>
        </div>
    </main>
    <script src="../assets/js/GooeyNav.js"></script>
</body>
</html>
