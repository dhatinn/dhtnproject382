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

$stmtYesterdayRevenue = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = DATE_SUB(?, INTERVAL 1 DAY)");
$stmtYesterdayRevenue->execute([$today]);
$yesterdayRevenue = (float)$stmtYesterdayRevenue->fetchColumn();
$revenueChange = $yesterdayRevenue > 0 ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 : null;

// 2. Total Pesanan Hari Ini
$stmtOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
$stmtOrders->execute([$today]);
$todayOrderCount = (int)$stmtOrders->fetchColumn();

// 3. Pesanan aktif hari ini
$stmtActive = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND order_status IN ('confirmed', 'cooking', 'ready')");
$stmtActive->execute([$today]);
$activeOrderCount = (int)$stmtActive->fetchColumn();

// 4. Total Menu Aktif
$stmtMenu = $db->query("SELECT COUNT(*) FROM menu_items WHERE is_available = 1");
$activeMenuCount = (int)$stmtMenu->fetchColumn();

$unavailableMenuCount = (int)$db->query("SELECT COUNT(*) FROM menu_items WHERE is_available = 0")->fetchColumn();

$stmtOrderStatuses = $db->prepare("SELECT order_status, COUNT(*) AS total FROM orders WHERE DATE(created_at) = ? GROUP BY order_status");
$stmtOrderStatuses->execute([$today]);
$todayStatusCounts = array_column($stmtOrderStatuses->fetchAll(), 'total', 'order_status');
$stmtWaitingPayment = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND payment_status = 'unpaid'");
$stmtWaitingPayment->execute([$today]);
$waitingPaymentCount = (int)$stmtWaitingPayment->fetchColumn();
$cookingOrderCount = (int)($todayStatusCounts['cooking'] ?? 0);
$readyOrderCount = (int)($todayStatusCounts['ready'] ?? 0);
$completedOrderCount = (int)($todayStatusCounts['completed'] ?? 0);

$hourlyBuckets = [];
for ($hour = 9; $hour < 23; $hour += 2) {
    $hourlyBuckets[] = [
        'label' => sprintf('%02d-%02d', $hour, $hour + 2),
        'orders' => 0,
        'revenue' => 0.0
    ];
}
$stmtHourly = $db->prepare("SELECT HOUR(created_at) AS order_hour, COUNT(*) AS order_count, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS revenue FROM orders WHERE DATE(created_at) = ? AND HOUR(created_at) >= 9 AND HOUR(created_at) < 23 GROUP BY HOUR(created_at)");
$stmtHourly->execute([$today]);
foreach ($stmtHourly->fetchAll() as $hourRow) {
    $bucketIndex = intdiv((int)$hourRow['order_hour'] - 9, 2);
    $hourlyBuckets[$bucketIndex]['orders'] += (int)$hourRow['order_count'];
    $hourlyBuckets[$bucketIndex]['revenue'] += (float)$hourRow['revenue'];
}
$peakBucket = null;
foreach ($hourlyBuckets as $bucket) {
    if ($peakBucket === null || $bucket['orders'] > $peakBucket['orders']) {
        $peakBucket = $bucket;
    }
}
$peakBucketLabel = $peakBucket && $peakBucket['orders'] > 0 ? $peakBucket['label'] . ' WIB' : 'Belum ada pesanan';
$maxHourlyOrders = max(1, ...array_column($hourlyBuckets, 'orders'));
$chartLinePath = '';
foreach ($hourlyBuckets as $index => $bucket) {
    $x = count($hourlyBuckets) > 1 ? $index * 400 / (count($hourlyBuckets) - 1) : 0;
    $y = 62 - ($bucket['revenue'] / max(1, max(array_column($hourlyBuckets, 'revenue')))) * 48;
    $chartLinePath .= ($index === 0 ? 'M' : ' L') . sprintf('%.1f,%.1f', $x, $y);
}
$chartAreaPath = $chartLinePath . ' L400,70 L0,70 Z';

$stmtBestSellers = $db->prepare("SELECT oi.menu_name, SUM(oi.quantity) AS quantity_sold FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE DATE(o.created_at) = ? AND o.order_status <> 'cancelled' GROUP BY oi.menu_name ORDER BY quantity_sold DESC, oi.menu_name ASC LIMIT 4");
$stmtBestSellers->execute([$today]);
$todayBestSellers = $stmtBestSellers->fetchAll();

$stmtPaymentMix = $db->prepare("SELECT payment_method, COUNT(*) AS total FROM orders WHERE DATE(created_at) = ? GROUP BY payment_method");
$stmtPaymentMix->execute([$today]);
$todayPaymentMix = array_column($stmtPaymentMix->fetchAll(), 'total', 'payment_method');

$stmtKitchenQueue = $db->prepare("SELECT order_code, customer_name, order_status, created_at, order_type, table_number FROM orders WHERE DATE(created_at) = ? AND order_status IN ('confirmed', 'cooking', 'ready') ORDER BY FIELD(order_status, 'ready', 'cooking', 'confirmed'), created_at ASC LIMIT 4");
$stmtKitchenQueue->execute([$today]);
$kitchenQueue = $stmtKitchenQueue->fetchAll();

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
                    <a href="orders.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
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
                                        <span class="magic-bento__figure-note"><?= $revenueChange === null ? 'Belum ada data kemarin' : (($revenueChange >= 0 ? '+' : '') . number_format($revenueChange, 1, ',', '.') . '% vs Kemarin') ?></span>
                                    </div>
                                    <div class="magic-bento__plot" data-revenue-series="<?= htmlspecialchars(json_encode(array_column($hourlyBuckets, 'revenue')), ENT_QUOTES, 'UTF-8') ?>" data-bucket-labels="<?= htmlspecialchars(json_encode(array_column($hourlyBuckets, 'label')), ENT_QUOTES, 'UTF-8') ?>">
                                        <svg viewBox="0 0 400 70" preserveAspectRatio="none">
                                            <path class="magic-bento__chart-area" d="<?= htmlspecialchars($chartAreaPath, ENT_QUOTES, 'UTF-8') ?>"></path>
                                            <path class="magic-bento__chart-line" d="<?= htmlspecialchars($chartLinePath, ENT_QUOTES, 'UTF-8') ?>"></path>
                                        </svg>
                                        <span class="magic-bento__scrub">
                                            <span class="magic-bento__scrub-dot"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Total Omzet Masuk Hari Ini</h3>
                                <p class="magic-bento__description">Omzet transaksi lunas hari ini. Arahkan ke grafik untuk melihat omzet per rentang waktu.</p>
                                <span class="magic-bento__action">Buka pesanan <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 2: Order Volume Bars -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <a href="orders.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
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
                                        <span class="magic-bento__figure-note"><?= htmlspecialchars($peakBucketLabel) ?></span>
                                    </div>
                                    <div class="magic-bento__bars-plot">
                                        <?php foreach ($hourlyBuckets as $bucket): ?>
                                            <span class="magic-bento__bar<?= $bucket['orders'] > 0 && $bucket['orders'] === $peakBucket['orders'] ? ' is-peak' : '' ?>" style="--h: <?= number_format(max(0.06, $bucket['orders'] / $maxHourlyOrders), 3, '.', '') ?>;" title="<?= htmlspecialchars($bucket['label']) ?> WIB: <?= (int)$bucket['orders'] ?> pesanan"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="magic-bento__bar-labels">
                                        <?php foreach ($hourlyBuckets as $bucket): ?>
                                            <span><?= htmlspecialchars(explode('-', $bucket['label'])[0]) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Volume Jam Makan</h3>
                                <p class="magic-bento__description">Jumlah pesanan per dua jam. Buka daftar untuk memproses antrean.</p>
                                <span class="magic-bento__action">Kelola antrean <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 3: Active Orders Alert -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <a href="orders.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-fire-burner" style="color: #F97316;"></i></span>
                                <span class="magic-bento__label">ANTREAN DAPUR BUNDO</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div style="display: flex; flex-direction: column; align-items: flex-start; justify-content: center; height: 100%;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 2.2rem; font-weight: 800; color: #FFFFFF; font-variant-numeric: tabular-nums;"><?= $activeOrderCount ?></span>
                                        <span style="background: <?= $cookingOrderCount > 0 ? '#FEF3C7' : '#DCFCE7' ?>; color: <?= $cookingOrderCount > 0 ? '#B45309' : '#166534' ?>; font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 99px;">
                                            <?= $cookingOrderCount ?> dimasak
                                        </span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--mb-muted); margin-top: 6px;">
                                        <?= $readyOrderCount ?> siap · <?= $waitingPaymentCount ?> menunggu bayar
                                    </div>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Pesanan Dalam Proses</h3>
                                <p class="magic-bento__description">Pesanan yang sedang dimasak dan siap disajikan.</p>
                                <span class="magic-bento__action">Lihat pesanan dapur <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 4: Kitchen Automation Pipeline (Colspan 2, Rowspan 2) -->
                <div class="magic-bento__cell" style="grid-column: span 2; grid-row: span 2;">
                    <a href="orders.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-kitchen-set" style="color: #10B981;"></i></span>
                                <span class="magic-bento__label">ANTREAN DAPUR HARI INI</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="mb-live-list">
                                    <?php if (empty($kitchenQueue)): ?>
                                        <div class="mb-empty-state"><i class="fa-solid fa-circle-check"></i><span>Belum ada pesanan aktif hari ini.</span></div>
                                    <?php else: ?>
                                        <?php foreach ($kitchenQueue as $queueOrder): ?>
                                            <?php
                                                $queueStatusLabels = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'cooking' => 'Dimasak', 'ready' => 'Siap'];
                                                $queueLocation = $queueOrder['order_type'] === 'dine_in' ? 'Meja ' . $queueOrder['table_number'] : ucfirst($queueOrder['order_type']);
                                            ?>
                                            <div class="mb-live-order">
                                                <span class="mb-live-order-icon <?= htmlspecialchars($queueOrder['order_status']) ?>"><i class="fa-solid <?= $queueOrder['order_status'] === 'ready' ? 'fa-bell-concierge' : ($queueOrder['order_status'] === 'cooking' ? 'fa-fire-burner' : 'fa-hourglass-half') ?>"></i></span>
                                                <span class="mb-live-order-copy">
                                                    <strong><?= htmlspecialchars($queueOrder['order_code']) ?> · <?= htmlspecialchars($queueOrder['customer_name']) ?></strong>
                                                    <small><?= htmlspecialchars($queueLocation) ?> · <?= date('H:i', strtotime($queueOrder['created_at'])) ?> WIB</small>
                                                </span>
                                                <span class="mb-live-order-status <?= htmlspecialchars($queueOrder['order_status']) ?>"><?= htmlspecialchars($queueStatusLabels[$queueOrder['order_status']] ?? $queueOrder['order_status']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Pesanan yang perlu ditangani</h3>
                                <p class="magic-bento__description">Tampilkan antrean terbaru untuk memperbarui status masak atau penyajian.</p>
                                <span class="magic-bento__action">Buka antrean dapur <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 5: Live Kitchen Collab (Colspan 2, Rowspan 2) -->
                <div class="magic-bento__cell" style="grid-column: span 2; grid-row: span 2;">
                    <a href="menu.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-ranking-star" style="color: #A78BFA;"></i></span>
                                <span class="magic-bento__label">MENU TERLARIS HARI INI</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="mb-ranked-list">
                                    <?php if (empty($todayBestSellers)): ?>
                                        <div class="mb-empty-state"><i class="fa-solid fa-bowl-food"></i><span>Belum ada penjualan menu hari ini.</span></div>
                                    <?php else: ?>
                                        <?php foreach ($todayBestSellers as $index => $bestSeller): ?>
                                            <div class="mb-ranked-item">
                                                <span class="mb-rank-number"><?= $index + 1 ?></span>
                                                <span class="mb-ranked-name"><?= htmlspecialchars($bestSeller['menu_name']) ?></span>
                                                <strong><?= (int)$bestSeller['quantity_sold'] ?> terjual</strong>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Performa menu berdasarkan jumlah item</h3>
                                <p class="magic-bento__description">Data berasal dari item pesanan hari ini, tidak termasuk pesanan yang dibatalkan.</p>
                                <span class="magic-bento__action">Kelola menu <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 6: Payment Mix -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <a href="orders.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-money-bill-transfer" style="color: #38BDF8;"></i></span>
                                <span class="magic-bento__label">METODE PEMBAYARAN</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="mb-summary-list">
                                    <?php
                                        $paymentLabels = ['qris' => 'QRIS', 'doku' => 'DOKU', 'cash' => 'Tunai'];
                                        foreach ($paymentLabels as $paymentKey => $paymentLabel):
                                            $paymentCount = (int)($todayPaymentMix[$paymentKey] ?? 0);
                                    ?>
                                        <div class="mb-summary-row">
                                            <span><?= htmlspecialchars($paymentLabel) ?></span>
                                            <strong><?= $paymentCount ?> pesanan</strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Bauran pembayaran hari ini</h3>
                                <p class="magic-bento__description">Jumlah pesanan tercatat untuk tiap metode.</p>
                                <span class="magic-bento__action">Lihat transaksi <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
                </div>

                <!-- CELL 7: Menu Availability -->
                <div class="magic-bento__cell" style="grid-column: span 1; grid-row: span 1;">
                    <a href="menu.php" class="magic-bento__card magic-bento__card-link" data-clickable="">
                        <span class="magic-bento__light"></span>
                        <canvas class="magic-bento__stars"></canvas>
                        <div class="magic-bento__content">
                            <div class="magic-bento__header">
                                <span class="magic-bento__icon"><i class="fa-solid fa-boxes-stacked" style="color: #4ADE80;"></i></span>
                                <span class="magic-bento__label">KETERSEDIAAN MENU</span>
                            </div>
                            <div class="magic-bento__visual">
                                <div class="mb-stock-summary">
                                    <strong><?= $activeMenuCount ?></strong>
                                    <span>menu tersedia</span>
                                    <small class="<?= $unavailableMenuCount > 0 ? 'has-unavailable' : '' ?>"><?= $unavailableMenuCount ?> menu tidak tersedia</small>
                                </div>
                            </div>
                            <div class="magic-bento__text">
                                <h3 class="magic-bento__title">Kontrol ketersediaan</h3>
                                <p class="magic-bento__description">Perbarui stok dari halaman manajemen menu.</p>
                                <span class="magic-bento__action">Kelola ketersediaan <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>
                        <span class="magic-bento__rim"></span>
                        <span class="magic-bento__border"></span>
                    </a>
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
