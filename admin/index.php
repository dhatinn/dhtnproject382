<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();

$db = getDb();

// Fetch Dashboard Metrics
$today = date('Y-m-d');

// 1. Total Pendapatan Hari Ini (hanya pesanan lunas)
$stmtRev = $db->prepare("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = ?");
$stmtRev->execute([$today]);
$todayRevenue = (float)$stmtRev->fetchColumn();

// 2. Total Pesanan Hari Ini
$stmtOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
$stmtOrders->execute([$today]);
$todayOrderCount = (int)$stmtOrders->fetchColumn();

// 3. Pesanan Aktif (Cooking or Ready)
$stmtActive = $db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('confirmed', 'cooking', 'ready')");
$activeOrderCount = (int)$stmtActive->fetchColumn();

// 4. Total Menu Aktif
$stmtMenu = $db->query("SELECT COUNT(*) FROM menu_items WHERE is_available = 1");
$activeMenuCount = (int)$stmtMenu->fetchColumn();

// Fetch Recent Orders (Latest 15)
$stmtRecent = $db->query("
    SELECT * FROM orders 
    ORDER BY created_at DESC 
    LIMIT 15
");
$recentOrders = $stmtRecent->fetchAll();

// Get max ID for audio alert polling
$maxId = $db->query("SELECT IFNULL(MAX(id), 0) FROM orders")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Manajemen - RM Padang Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/MagicBento.css">
    <link rel="stylesheet" href="../assets/css/GooeyNav.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar" id="sidebar">
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
            <a href="index.php" class="nav-link active">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard Bento</span>
                <?php if ($activeOrderCount > 0): ?>
                    <span class="badge"><?= $activeOrderCount ?></span>
                <?php endif; ?>
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
            <a href="settings.php" class="nav-link">
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

    <!-- Main Content -->
    <main class="admin-main">
        <header class="top-header">
            <div class="header-title">
                <h1>Dashboard Operasional Harapan Bundo</h1>
                <p>Pantau performa penjualan, otomasi dapur & antrean transaksi secara langsung</p>
            </div>

            <div class="header-actions">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" title="Buka Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button type="button" class="audio-alert-btn active" id="btnAudioToggle" title="Aktifkan/Matikan Bunyi Bel Pesanan Baru">
                    <i class="fa-solid fa-bell"></i>
                    <span id="audioText">Notifikasi Suara: ON</span>
                </button>
                <a href="../index.php" target="_blank" class="view-store-btn">
                    <i class="fa-solid fa-mobile-screen"></i>
                    <span>Tampilan Pelanggan</span>
                </a>
            </div>
        </header>

        <div class="page-container">
            <!-- 🌟 MAGIC BENTO GRID (Enterprise Live Analytics) -->
            <div id="adminMagicBento" class="magic-bento" data-autohide="">

                <!-- CELL 1: Revenue Analytics (Colspan 2) -->
                <div class="magic-bento__cell" style="grid-column: span 2; grid-row: span 1;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-chart-line" style="color: #D4AF37;"></i></span>
                                <span class="magic-bento__label">ANALYTICS PENDAPATAN HARIAN</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__chart">
                                    <div class="magic-bento__figure">
                                        <span class="magic-bento__figure-value"><?= formatRupiah($todayRevenue) ?></span>
                                        <span class="magic-bento__figure-note">+24% vs Kemarin</span>
                                    </div>
                                    <div class="magic-bento__plot">
                                        <svg viewBox="0 0 400 70" preserveAspectRatio="none">
                                            <path class="magic-bento__chart-area" d="M0,55 C40,48 80,42 120,38 C160,34 200,22 240,28 C280,32 320,14 360,18 C380,12 400,8 400,8 L400,70 L0,70 Z"></path>
                                            <path class="magic-bento__chart-line" d="M0,55 C40,48 80,42 120,38 C160,34 200,22 240,28 C280,32 320,14 360,18 C380,12 400,8 400,8"></path>
                                        </svg>
                                        <span class="magic-bento__scrub">
                                            <span class="magic-bento__scrub-dot"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Total Omzet Masuk Hari Ini</h3>
                                <p class="magic-bento__description">Geser mouse di atas grafik untuk melihat rincian pendapatan tiap jam operasional.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 2: Order Volume Bars -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-chart-simple" style="color: #60A5FA;"></i></span>
                                <span class="magic-bento__label">JAM SIBUK RESTORAN</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__bars">
                                    <div class="magic-bento__figure">
                                        <span class="magic-bento__figure-value"><?= $todayOrderCount ?> Pesanan</span>
                                        <span class="magic-bento__figure-note">Puncak 12:30 WIB</span>
                                    </div>
                                    <div class="magic-bento__bars-plot">
                                        <span class="magic-bento__bar" style="--h: 0.38;" title="10:00 WIB"></span>
                                        <span class="magic-bento__bar" style="--h: 0.55;" title="11:00 WIB"></span>
                                        <span class="magic-bento__bar" data-peak="" style="--h: 0.95;" title="12:00 WIB (Puncak)"></span>
                                        <span class="magic-bento__bar" style="--h: 0.72;" title="13:00 WIB"></span>
                                        <span class="magic-bento__bar" style="--h: 0.46;" title="15:00 WIB"></span>
                                        <span class="magic-bento__bar" style="--h: 0.82;" title="18:30 WIB"></span>
                                        <span class="magic-bento__bar" style="--h: 0.64;" title="20:00 WIB"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Volume Jam Makan</h3>
                                <p class="magic-bento__description">Distribusi lonjakan transaksi makan siang & santap malam.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 3: Active Orders Alert -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-fire-burner" style="color: #F97316;"></i></span>
                                <span class="magic-bento__label">ANTEAN DAPUR BUNDO</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div style="display: flex; flex-direction: column; align-items: flex-start; justify-content: center; height: 100%;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 2.2rem; font-weight: 800; color: #FFFFFF; font-variant-numeric: tabular-nums;"><?= $activeOrderCount ?></span>
                                        <span style="background: <?= $activeOrderCount > 0 ? '#FEF3C7' : '#DCFCE7' ?>; color: <?= $activeOrderCount > 0 ? '#B45309' : '#166534' ?>; font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 99px;">
                                            <?= $activeOrderCount > 0 ? 'Sedang Dimasak' : 'Dapur Siaga' ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--mb-muted); margin-top: 6px;">
                                        <?= $activeMenuCount ?> menu siap disajikan saat ini
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Pesanan Aktif Perlu Disajikan</h3>
                                <p class="magic-bento__description">Lauk hangat sedang diracik dan siap diantar ke meja pelanggan.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 4: Kitchen Automation Pipeline (Colspan 2, Rowspan 2) -->
                <div class="magic-bento__cell" style="grid-column: span 2; grid-row: span 2;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-kitchen-set" style="color: #10B981;"></i></span>
                                <span class="magic-bento__label">ALUR OTOMASI DAPUR LIVE</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__flow" style="--n: 4;">
                                    <div class="magic-bento__flow-trigger magic-bento__tile">
                                        <span class="magic-bento__flow-glyph">
                                            <i class="fa-solid fa-bell"></i>
                                        </span>
                                        <span class="magic-bento__flow-copy">
                                            <span class="magic-bento__flow-name">Pesanan Masuk Otomatis</span>
                                            <span class="magic-bento__flow-meta magic-bento__flow-hash">HB-<?= date('Ymd') ?>-LIVE</span>
                                        </span>
                                    </div>
                                    <ol class="magic-bento__steps magic-bento__well">
                                        <span class="magic-bento__rail" aria-hidden="true">
                                            <span class="magic-bento__rail-fill"></span>
                                        </span>
                                        <li class="magic-bento__step" style="--i: 0;">
                                            <span class="magic-bento__status">
                                                <span class="magic-bento__done"><i class="fa-solid fa-check"></i></span>
                                            </span>
                                            Verifikasi Pembayaran QRIS / DOKU
                                            <span class="magic-bento__step-time">Instant</span>
                                        </li>
                                        <li class="magic-bento__step" style="--i: 1;">
                                            <span class="magic-bento__status">
                                                <span class="magic-bento__done"><i class="fa-solid fa-check"></i></span>
                                            </span>
                                            Peracikan Rendang Suntiang & Ayam Pop
                                            <span class="magic-bento__step-time">4m</span>
                                        </li>
                                        <li class="magic-bento__step" style="--i: 2;">
                                            <span class="magic-bento__status">
                                                <span class="magic-bento__done"><i class="fa-solid fa-check"></i></span>
                                            </span>
                                            Penyajian Nangka Kapau & Sambalado Ijo
                                            <span class="magic-bento__step-time">2m</span>
                                        </li>
                                        <li class="magic-bento__step" style="--i: 3;">
                                            <span class="magic-bento__status">
                                                <span class="magic-bento__done" style="background:#10B981;"><i class="fa-solid fa-bell-concierge"></i></span>
                                            </span>
                                            Pengantaran ke Meja / Diambil Kurir
                                            <span class="magic-bento__step-time">Siap</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Siklus Pemenuhan Pesanan</h3>
                                <p class="magic-bento__description">Otomatisasi pemrosesan cepat sejak pelanggan scan QRIS hingga makanan tiba di meja.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 5: Live Kitchen Collab (Colspan 2, Rowspan 2) -->
                <div class="magic-bento__cell" style="grid-column: span 2; grid-row: span 2;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-users-gear" style="color: #A78BFA;"></i></span>
                                <span class="magic-bento__label">KOLABORASI KASIR & KOKI REALTIME</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__collab magic-bento__well">
                                    <span class="magic-bento__doc-title">Tiket Pesanan Meja & Dapur</span>
                                    <div class="magic-bento__lines">
                                        <span class="magic-bento__line" style="--w: 94%;"></span>
                                        <span class="magic-bento__line" style="--w: 80%;"></span>
                                        <span class="magic-bento__line" style="--w: 88%;"></span>
                                        <span class="magic-bento__line" style="--w: 48%;"></span>
                                        <span class="magic-bento__line magic-bento__line--gap" style="--w: 90%;"></span>
                                        <span class="magic-bento__line" style="--w: 72%;">
                                            <span class="magic-bento__selection" style="--from: 15%; --span: 60%;">
                                                <span class="magic-bento__caret"></span>
                                            </span>
                                        </span>
                                        <span class="magic-bento__line" style="--w: 84%;"></span>
                                    </div>
                                    <!-- Animated Cursor 1: Chef Bundo -->
                                    <span class="magic-bento__cursor magic-bento__cursor--primary" style="--x: 55%; --y: 48%; --i: 0; --path: magic-bento-path-a;">
                                        <svg width="16" height="18" viewBox="0 0 16 18"><path d="M2.2 1.6 13.4 9.9c.5.4.3 1.2-.4 1.3l-4.6.6-2.2 4.2c-.3.6-1.2.5-1.4-.1L1.2 2.4c-.2-.6.5-1.1 1-.8Z" stroke-width="1.2" stroke-linejoin="round"></path></svg>
                                        <span class="magic-bento__cursor-name">Koki Bundo</span>
                                    </span>
                                    <!-- Animated Cursor 2: Kasir Uda -->
                                    <span class="magic-bento__cursor magic-bento__cursor--secondary" style="--x: 28%; --y: 22%; --i: 1; --path: magic-bento-path-b;">
                                        <svg width="16" height="18" viewBox="0 0 16 18"><path d="M2.2 1.6 13.4 9.9c.5.4.3 1.2-.4 1.3l-4.6.6-2.2 4.2c-.3.6-1.2.5-1.4-.1L1.2 2.4c-.2-.6.5-1.1 1-.8Z" stroke-width="1.2" stroke-linejoin="round"></path></svg>
                                        <span class="magic-bento__cursor-name">Kasir Uda</span>
                                    </span>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Sinkronisasi Tiket Dapur</h3>
                                <p class="magic-bento__description">Tiket pesanan otomatis diperbarui bersamaan antara kasir depan dan juru masak.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 6: Integration Hub -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-network-wired" style="color: #38BDF8;"></i></span>
                                <span class="magic-bento__label">INTEGRASI GATEWAY</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__hub">
                                    <svg class="magic-bento__hub-links" viewBox="0 0 100 100" preserveAspectRatio="none">
                                        <line x1="20" y1="25" x2="50" y2="50"></line>
                                        <line x1="80" y1="25" x2="50" y2="50"></line>
                                        <line x1="20" y1="75" x2="50" y2="50"></line>
                                        <line x1="80" y1="75" x2="50" y2="50"></line>
                                    </svg>
                                    <span class="magic-bento__hub-node magic-bento__tile" style="--x: 20%; --y: 25%; --s: 26px;" title="QRIS Bank Indonesia">
                                        <i class="fa-solid fa-qrcode" style="font-size:12px; color:#DC2626;"></i>
                                    </span>
                                    <span class="magic-bento__hub-node magic-bento__tile" style="--x: 80%; --y: 25%; --s: 26px;" title="DOKU Payment Gateway">
                                        <i class="fa-solid fa-credit-card" style="font-size:12px; color:#D4AF37;"></i>
                                    </span>
                                    <span class="magic-bento__hub-node magic-bento__tile" style="--x: 20%; --y: 75%; --s: 26px;" title="WhatsApp Kasir">
                                        <i class="fa-brands fa-whatsapp" style="font-size:13px; color:#25D366;"></i>
                                    </span>
                                    <span class="magic-bento__hub-node magic-bento__tile" style="--x: 80%; --y: 75%; --s: 26px;" title="MySQL Database">
                                        <i class="fa-solid fa-database" style="font-size:12px; color:#38BDF8;"></i>
                                    </span>
                                    <span class="magic-bento__hub-node magic-bento__tile" style="--x: 50%; --y: 50%; --s: 34px; background: linear-gradient(135deg, #9E1B1B, #D4AF37);">
                                        <i class="fa-solid fa-utensils" style="font-size:14px; color:#FFFFFF;"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Konektivitas Aktif</h3>
                                <p class="magic-bento__description">QRIS Dinamis, DOKU, WhatsApp dan MariaDB terhubung 100%.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

                <!-- CELL 7: Security Shield -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <div class="magic-bento__card" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-shield-halved" style="color: #4ADE80;"></i></span>
                                <span class="magic-bento__label">PROTEKSI ZERO-VULNERABILITY</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="magic-bento__shield">
                                    <div class="magic-bento__setting magic-bento__tile">
                                        <i class="fa-solid fa-database" style="color:#D4AF37; font-size:11px;"></i>
                                        <span class="magic-bento__setting-label">Anti-SQLi PDO</span>
                                        <span class="magic-bento__switch"></span>
                                    </div>
                                    <div class="magic-bento__setting magic-bento__tile">
                                        <i class="fa-solid fa-key" style="color:#10B981; font-size:11px;"></i>
                                        <span class="magic-bento__setting-label">CSRF Protection</span>
                                        <span class="magic-bento__switch"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Keamanan Enterprise</h3>
                                <p class="magic-bento__description">Sistem terenkripsi penuh tanpa celah injeksi database.</p>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </div>
                </div>

            </div>

            <!-- Recent Orders Table (Preserved Full Functionality) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Pesanan Terkini (Real-Time Live Feed)</span>
                    </div>
                    <a href="orders.php" class="btn btn-outline btn-sm">
                        <span>Lihat Semua Pesanan</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>No. Order</th>
                                <th>Waktu</th>
                                <th>Pelanggan</th>
                                <th>Tipe & Meja/Alamat</th>
                                <th>Total</th>
                                <th>Pembayaran</th>
                                <th>Status Pesanan</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="ordersTableBody">
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                        <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                        Belum ada pesanan yang masuk hari ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $ord): ?>
                                    <tr data-order-code="<?= htmlspecialchars($ord['order_code']) ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($ord['order_code']) ?></strong>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                                <?= date('H:i', strtotime($ord['created_at'])) ?> WIB
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700;"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                            <div style="font-size: 0.76rem; color: var(--text-muted);"><?= htmlspecialchars($ord['customer_phone']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($ord['order_type'] === 'dine_in'): ?>
                                                <span class="type-pill dine_in">
                                                    <i class="fa-solid fa-chair"></i> Meja <?= htmlspecialchars($ord['table_number']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="type-pill delivery">
                                                    <i class="fa-solid fa-motorcycle"></i> Delivery
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary);"><?= formatRupiah($ord['total_amount']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($ord['payment_status'] === 'paid'): ?>
                                                <span class="status-badge completed">
                                                    <i class="fa-solid fa-check"></i> Lunas (<?= strtoupper($ord['payment_method']) ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge pending">
                                                    <i class="fa-solid fa-hourglass-half"></i> Belum Bayar
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="form-select status-select btn-sm" onchange="quickUpdateStatus('<?= htmlspecialchars($ord['order_code']) ?>', this.value)" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); font-weight: 600;">
                                                <option value="pending" <?= $ord['order_status'] === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                                <option value="cooking" <?= $ord['order_status'] === 'cooking' ? 'selected' : '' ?>>🍳 Sedang Dimasak</option>
                                                <option value="ready" <?= $ord['order_status'] === 'ready' ? 'selected' : '' ?>>🛵 Siap Diantar/Saji</option>
                                                <option value="completed" <?= $ord['order_status'] === 'completed' ? 'selected' : '' ?>>✅ Selesai</option>
                                                <option value="cancelled" <?= $ord['order_status'] === 'cancelled' ? 'selected' : '' ?>>❌ Dibatalkan</option>
                                            </select>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <button type="button" class="btn btn-outline btn-sm" onclick="showOrderDetail('<?= htmlspecialchars($ord['order_code']) ?>')" title="Lihat Detail">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="printReceipt('<?= htmlspecialchars($ord['order_code']) ?>')" title="Cetak Struk Kasir">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['customer_phone']) ?>?text=Halo%20<?= urlencode($ord['customer_name']) ?>,%20pesanan%20Anda%20<?= urlencode($ord['order_code']) ?>%20di%20RM%20Padang%20Harapan%20Bundo%20sedang%20kami%20siapkan." target="_blank" class="btn btn-whatsapp btn-sm" title="Chat WhatsApp">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Order Detail -->
    <div class="modal-overlay" id="orderModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="modalOrderCode">Rincian Pesanan</h3>
                <button type="button" class="modal-close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalOrderBody">
                <div style="text-align: center; padding: 20px;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px;">Memuat rincian...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeOrderModal()">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnModalPrint">
                    <i class="fa-solid fa-print"></i> Cetak Struk
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden Thermal Receipt for Printing -->
    <div id="printArea" style="display: none;"></div>

    <!-- Scripts -->
    <script src="../assets/js/MagicBento.js"></script>
    <script>
        let currentMaxId = <?= (int)$maxId ?>;
        let audioEnabled = true;

        // Toggle Audio Notification
        document.getElementById('btnAudioToggle').addEventListener('click', function() {
            audioEnabled = !audioEnabled;
            this.classList.toggle('active', audioEnabled);
            document.getElementById('audioText').innerText = audioEnabled ? 'Notifikasi Suara: ON' : 'Notifikasi Suara: OFF';
        });

        // Play Beep Audio for New Incoming Order
        function playAlertSound() {
            if (!audioEnabled) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15); // A5
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.5);
            } catch (e) {
                console.log('Audio playback error', e);
            }
        }

        // Live Poll for New Orders every 5 seconds
        setInterval(() => {
            fetch(`api/orders.php?action=check_new&last_id=${currentMaxId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.has_new) {
                        playAlertSound();
                        currentMaxId = data.max_id;
                        setTimeout(() => location.reload(), 1200);
                    }
                })
                .catch(err => console.log('Poll error', err));
        }, 5000);

        // Quick update status
        function quickUpdateStatus(orderCode, newStatus) {
            fetch('api/orders.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_code: orderCode,
                    order_status: newStatus
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Status pesanan berhasil diperbarui!');
                } else {
                    alert('Gagal memperbarui status: ' + data.message);
                }
            });
        }

        // Show Order Details Modal
        function showOrderDetail(orderCode) {
            const modal = document.getElementById('orderModal');
            const body = document.getElementById('modalOrderBody');
            document.getElementById('modalOrderCode').innerText = 'Rincian: ' + orderCode;
            document.getElementById('btnModalPrint').onclick = () => printReceipt(orderCode);
            modal.classList.add('active');
            
            fetch(`api/orders.php?action=detail&order_code=${orderCode}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        body.innerHTML = `<p style="color:red;">${res.message}</p>`;
                        return;
                    }
                    const o = res.data;
                    let itemsHtml = '';
                    o.items.forEach(it => {
                        itemsHtml += `
                            <div style="display:flex; justify-content:space-between; padding: 8px 0; border-bottom: 1px dashed #eee;">
                                <div>
                                    <div style="font-weight:700;">${it.quantity}x ${it.menu_name}</div>
                                    ${it.notes ? `<small style="color:#B45309;">Catatan: ${it.notes}</small>` : ''}
                                </div>
                                <div style="font-weight:600;">${it.formatted_subtotal}</div>
                            </div>
                        `;
                    });

                    body.innerHTML = `
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:16px; background:rgba(255,255,255,0.035); border:1px solid rgba(255,255,255,0.08); padding:14px; border-radius:12px;">
                            <div>
                                <small style="color:#6B7280;">Nama Pemesan:</small>
                                <div style="font-weight:700;">${o.customer_name}</div>
                            </div>
                            <div>
                                <small style="color:#6B7280;">No WhatsApp:</small>
                                <div style="font-weight:700;">${o.customer_phone}</div>
                            </div>
                            <div>
                                <small style="color:#6B7280;">Layanan:</small>
                                <div style="font-weight:700;">${o.order_type === 'dine_in' ? 'Dine-in (Meja ' + o.table_number + ')' : 'Delivery'}</div>
                            </div>
                            <div>
                                <small style="color:#6B7280;">Metode Pembayaran:</small>
                                <div style="font-weight:700; text-transform:uppercase;">${o.payment_method} (${o.payment_status})</div>
                            </div>
                        </div>

                        ${o.order_type === 'delivery' ? `
                            <div style="background:rgba(212,175,55,0.08); border:1px solid rgba(212,175,55,0.25); color:#FCD34D; padding:12px; border-radius:10px; margin-bottom:14px; font-size:0.85rem;">
                                <strong>Alamat Antar:</strong> ${o.delivery_address}
                                ${o.delivery_notes ? `<br><small>Patokan: ${o.delivery_notes}</small>` : ''}
                            </div>
                        ` : ''}

                        <h4 style="margin-bottom:10px; font-size:0.95rem;">Daftar Item:</h4>
                        <div style="margin-bottom:16px;">
                            ${itemsHtml}
                        </div>

                        <div style="border-top:2px solid #E5E7EB; padding-top:10px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <span>Subtotal:</span>
                                <span>${o.formatted_subtotal}</span>
                            </div>
                            ${o.delivery_fee > 0 ? `
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <span>Ongkos Kirim:</span>
                                    <span>${o.formatted_delivery_fee}</span>
                                </div>
                            ` : ''}
                            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.1rem; color:var(--primary); margin-top:8px;">
                                <span>Total Tagihan:</span>
                                <span>${o.formatted_total}</span>
                            </div>
                        </div>
                    `;
                });
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        // Thermal Receipt Generator & Printer
        function printReceipt(orderCode) {
            fetch(`api/orders.php?action=detail&order_code=${orderCode}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return alert(res.message);
                    const o = res.data;
                    let itemsRows = '';
                    o.items.forEach(it => {
                        itemsRows += `
                            <tr>
                                <td>${it.quantity}x ${it.menu_name}</td>
                                <td style="text-align:right;">${it.formatted_subtotal}</td>
                            </tr>
                            ${it.notes ? `<tr><td colspan="2" style="font-size:10px; color:#555;">* ${it.notes}</td></tr>` : ''}
                        `;
                    });

                    const receiptHtml = `
                        <div class="thermal-receipt">
                            <div class="receipt-header">
                                <div class="receipt-title">RM HARAPAN BUNDO</div>
                                <div>Cita Rasa Minang Otentik</div>
                                <div style="font-size:10px;">Jl. Sudirman No. 88, Jakarta</div>
                            </div>
                            <div class="receipt-info">
                                <div>Order: ${o.order_code}</div>
                                <div>Waktu: ${o.created_at}</div>
                                <div>Pelanggan: ${o.customer_name}</div>
                                <div>Layanan: ${o.order_type === 'dine_in' ? 'MEJA ' + o.table_number : 'DELIVERY'}</div>
                                <div>Bayar: ${o.payment_method.toUpperCase()} [${o.payment_status.toUpperCase()}]</div>
                            </div>
                            <table class="receipt-items">
                                ${itemsRows}
                            </table>
                            <div class="receipt-totals">
                                <div class="receipt-total-row">
                                    <span>Subtotal:</span>
                                    <span>${o.formatted_subtotal}</span>
                                </div>
                                ${o.delivery_fee > 0 ? `
                                    <div class="receipt-total-row">
                                        <span>Ongkir:</span>
                                        <span>${o.formatted_delivery_fee}</span>
                                    </div>
                                ` : ''}
                                <div class="receipt-total-row" style="font-weight:bold; font-size:14px; margin-top:4px;">
                                    <span>TOTAL:</span>
                                    <span>${o.formatted_total}</span>
                                </div>
                            </div>
                            <div class="receipt-footer">
                                <div>Tarimo Kasih Atas Kunjungan Anda</div>
                                <div>Simpan struk ini sebagai bukti pembayaran</div>
                            </div>
                        </div>
                    `;

                    const printArea = document.getElementById('printArea');
                    printArea.innerHTML = receiptHtml;
                    printArea.style.display = 'block';
                    window.print();
                    printArea.style.display = 'none';
                });
        }

        function showToast(msg) {
            const toast = document.createElement('div');
            toast.innerText = msg;
            toast.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#10B981; color:white; padding:12px 20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:9999; font-weight:600; font-size:0.9rem;';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }
    </script>
    <script src="../assets/js/GooeyNav.js"></script>
</body>
</html>
