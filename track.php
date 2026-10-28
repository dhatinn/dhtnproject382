<?php
require_once __DIR__ . '/config.php';

$orderCode = cleanInput($_GET['code'] ?? '');

if (empty($orderCode)) {
    header('Location: index.php');
    exit;
}

$db = getDb();
$stmt = $db->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    die("Pesanan dengan kode {$orderCode} tidak ditemukan.");
}

$stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

$restoPhone = getSetting('whatsapp_number', '6281234567890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Status Pesanan #<?= htmlspecialchars($order['order_code']) ?> - RM Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        :root {
            --track-primary: #9E1B1B;
            --track-primary-dark: #7A1212;
            --track-gold: #D4AF37;
            --track-green: #10B981;
            --track-bg: #F9F7F4;
        }

        body {
            background-color: var(--track-bg);
            background-image:
                radial-gradient(circle at 10% 20%, rgba(158, 27, 27, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(212, 175, 55, 0.05) 0%, transparent 40%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1F2937;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .track-container {
            max-width: 640px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }

        /* Top Header Bar */
        .track-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: #FFFFFF;
            border: 1px solid #EBE7DF;
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            margin-bottom: 20px;
        }

        .track-nav-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #F3F0E9;
            color: #374151;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .track-nav-back:hover {
            background: #E5DFD4;
            color: var(--track-primary);
            transform: scale(1.05);
        }

        .track-nav-title {
            text-align: center;
        }

        .track-nav-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--track-primary);
            letter-spacing: 0.5px;
            margin: 0;
        }

        .track-nav-title span {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #9CA3AF;
            display: block;
            margin-top: 1px;
        }

        .track-live-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #DCFCE7;
            color: #15803D;
            padding: 5px 11px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .pulse-circle {
            width: 8px;
            height: 8px;
            background: #16A34A;
            border-radius: 50%;
            animation: pulseChip 1.5s infinite;
        }

        @keyframes pulseChip {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.6; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Success Hero Card */
        .track-hero-card {
            background: linear-gradient(135deg, #9E1B1B 0%, #751010 100%);
            border-radius: 24px;
            padding: 26px 20px;
            color: #FFFFFF;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 36px rgba(158, 27, 27, 0.28);
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .track-hero-card::after {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .track-hero-card.is-paid {
            background: linear-gradient(135deg, #087F5B 0%, #075E45 100%);
            box-shadow: 0 16px 36px rgba(8, 127, 91, 0.22);
        }

        .track-hero-card.is-paid .track-hero-icon {
            color: #D1FAE5;
            background: rgba(255, 255, 255, 0.18);
        }

        .track-hero-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #FDE047;
            margin-bottom: 12px;
            animation: floatIcon 3s ease-in-out infinite;
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .track-hero-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 0 6px;
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.3px;
        }

        .track-hero-subtitle {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0 0 16px;
        }

        .track-code-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 99px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }

        .track-code-pill:hover {
            background: rgba(0, 0, 0, 0.45);
            transform: scale(1.02);
        }

        .track-code-val {
            font-family: monospace;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: #FDE047;
        }

        .track-copy-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #FFFFFF;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .track-info-badges {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .track-badge {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.76rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Kitchen Live ETA Widget */
        .track-eta-box {
            background: #FFFFFF;
            border: 1.5px solid #EBE7DF;
            border-radius: 18px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
        }

        .track-eta-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .track-eta-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #FEF3C7;
            color: #B45309;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .track-eta-title {
            font-weight: 800;
            font-size: 0.88rem;
            color: #111827;
        }

        .track-eta-desc {
            font-size: 0.76rem;
            color: #6B7280;
            margin-top: 1px;
        }

        .track-eta-time {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
            padding: 6px 12px;
            border-radius: 99px;
            font-weight: 800;
            font-size: 0.8rem;
            letter-spacing: 0.3px;
        }

        /* Card Section Wrapper */
        .track-card {
            background: #FFFFFF;
            border: 1.5px solid #EBE7DF;
            border-radius: 22px;
            padding: 22px 20px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
        }

        .track-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #F3EFE6;
        }

        .track-card-title {
            font-size: 0.98rem;
            font-weight: 800;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
        }

        .track-card-title i {
            color: var(--track-primary);
        }

        /* Stepper Timeline */
        .timeline-list {
            position: relative;
            padding: 6px 0 6px 36px;
        }

        .timeline-list::before {
            content: '';
            position: absolute;
            top: 16px;
            bottom: 24px;
            left: 14px;
            width: 3px;
            background: #E5E7EB;
            border-radius: 2px;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 26px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-bullet {
            position: absolute;
            left: -36px;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 2px solid #D1D5DB;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #9CA3AF;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 2;
        }

        .timeline-item.done .timeline-bullet {
            background: #10B981;
            border-color: #10B981;
            color: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        .timeline-item.active .timeline-bullet {
            background: var(--track-primary);
            border-color: var(--track-primary);
            color: #FFFFFF;
            box-shadow: 0 0 0 6px rgba(158, 27, 27, 0.2);
            animation: pulseTimeline 1.6s infinite;
        }

        @keyframes pulseTimeline {
            0% { transform: scale(1); box-shadow: 0 0 0 4px rgba(158, 27, 27, 0.25); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(158, 27, 27, 0.1); }
            100% { transform: scale(1); box-shadow: 0 0 0 4px rgba(158, 27, 27, 0.25); }
        }

        .timeline-heading {
            font-size: 0.92rem;
            font-weight: 800;
            color: #111827;
            margin-bottom: 3px;
        }

        .timeline-item.active .timeline-heading {
            color: var(--track-primary);
        }

        .timeline-subtext {
            font-size: 0.78rem;
            color: #6B7280;
            line-height: 1.4;
        }

        /* Digital Receipt - Styled Luxury Paper */
        .receipt-container {
            background: #FDFCF7;
            border: 1.5px solid #E6E1D5;
            border-radius: 20px;
            padding: 24px 20px;
            position: relative;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .receipt-header {
            text-align: center;
            padding-bottom: 16px;
            border-bottom: 1.5px dashed #D6D0C2;
            margin-bottom: 16px;
        }

        .receipt-logo-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--track-primary);
            margin: 0;
            letter-spacing: 0.5px;
        }

        .receipt-meta-line {
            font-size: 0.75rem;
            color: #6B7280;
            margin-top: 4px;
        }

        .receipt-stamp {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #DCFCE7;
            border: 1px solid #86EFAC;
            color: #15803D;
            padding: 3px 12px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .receipt-item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px dashed #EDE8DC;
            font-size: 0.88rem;
        }

        .receipt-item-row:last-child {
            border-bottom: none;
        }

        .receipt-item-name {
            font-weight: 700;
            color: #111827;
        }

        .receipt-item-note {
            font-size: 0.72rem;
            color: #B45309;
            font-style: italic;
            margin-top: 2px;
        }

        .receipt-item-price {
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            margin-left: 12px;
        }

        .receipt-summary-box {
            border-top: 1.5px dashed #D6D0C2;
            padding-top: 14px;
            margin-top: 10px;
        }

        .receipt-summary-line {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #4B5563;
            margin-bottom: 6px;
        }

        .receipt-total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid #111827;
            padding-top: 12px;
            margin-top: 10px;
            font-size: 1.18rem;
            font-weight: 800;
            color: var(--track-primary);
        }

        /* Prominent Action Buttons */
        .track-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-whatsapp-cta {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%) !important;
            color: #FFFFFF !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 24px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none !important;
            box-shadow: 0 10px 24px rgba(37, 211, 102, 0.35);
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
        }

        .btn-whatsapp-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(37, 211, 102, 0.45);
            color: #FFFFFF !important;
        }

        .btn-whatsapp-cta i {
            font-size: 1.35rem;
        }

        .track-secondary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-action-pill {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #FFFFFF;
            border: 1.5px solid #D1D5DB;
            color: #374151 !important;
            padding: 12px 18px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.86rem;
            text-decoration: none !important;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .btn-action-pill:hover {
            background: #FAF8F5;
            border-color: var(--track-primary);
            color: var(--track-primary) !important;
            transform: translateY(-1px);
        }

        .btn-action-pill.primary {
            background: #FEF2F2;
            border-color: #FECACA;
            color: var(--track-primary) !important;
        }

        .btn-action-pill.primary:hover {
            background: var(--track-primary);
            border-color: var(--track-primary);
            color: #FFFFFF !important;
        }

        /* Print Mode Styling */
        @media print {
            body {
                background: #FFFFFF !important;
            }
            .track-nav, .track-eta-box, .track-actions, .timeline-list {
                display: none !important;
            }
            .track-container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            .track-card, .receipt-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

    <div class="track-container">
        <!-- 1. Top Navigation Bar -->
        <header class="track-nav">
            <a href="index.php" class="track-nav-back" title="Kembali ke Beranda">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="track-nav-title">
                <h1>STATUS PESANAN</h1>
                <span>RM HARAPAN BUNDO</span>
            </div>
            <div class="track-live-chip">
                <span class="pulse-circle"></span>
                <span>Live Update</span>
            </div>
        </header>

        <!-- 2. Order Success Hero Card -->
        <div class="track-hero-card <?= $order['payment_status'] === 'paid' ? 'is-paid' : '' ?>">
            <div class="track-hero-icon">
                <i class="fa-solid <?= $order['payment_status'] === 'paid' ? 'fa-circle-check' : 'fa-utensils' ?>"></i>
            </div>
            <h2 class="track-hero-title"><?= $order['payment_status'] === 'paid' ? 'Pembayaran Berhasil!' : 'Pesanan Berhasil Dibuat' ?></h2>
            <p class="track-hero-subtitle"><?= $order['payment_status'] === 'paid' ? 'Pembayaran Anda telah terverifikasi. Pesanan segera disiapkan oleh Dapur Bundo.' : 'Pesanan tercatat. Selesaikan pembayaran untuk mulai diproses oleh Dapur Bundo.' ?></p>

            <div class="track-code-pill" onclick="copyOrderCode()" title="Klik untuk menyalin kode">
                <span>Kode:</span>
                <span class="track-code-val" id="orderCodeText"><?= htmlspecialchars($order['order_code']) ?></span>
                <button type="button" class="track-copy-btn">
                    <i class="fa-regular fa-copy"></i>
                    <span id="copyBtnLabel">Salin</span>
                </button>
            </div>

            <div class="track-info-badges">
                <span class="track-badge">
                    <i class="fa-solid <?= $order['order_type'] === 'dine_in' ? 'fa-chair' : 'fa-motorcycle' ?>"></i>
                    <?= $order['order_type'] === 'dine_in' ? 'Makan di Meja ' . htmlspecialchars($order['table_number']) : 'Delivery ke Alamat Tujuan' ?>
                </span>
                <span class="track-badge">
                    <i class="fa-solid fa-receipt"></i>
                    Metode: <?= strtoupper(str_replace('_', ' ', $order['payment_method'])) ?>
                </span>
            </div>
        </div>

        <!-- 3. Kitchen Live ETA Box -->
        <div class="track-eta-box">
            <div class="track-eta-left">
                <div class="track-eta-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="track-eta-title">Estimasi Dapur Bundo</div>
                    <div class="track-eta-desc" id="etaSubText">Diracik hangat & segar langsung dari kuali</div>
                </div>
            </div>
            <div class="track-eta-time" id="etaBadge">
                <i class="fa-solid fa-fire-burner"></i> 15 - 20 Menit
            </div>
        </div>

        <!-- 4. Progress Stepper Timeline Card -->
        <div class="track-card">
            <div class="track-card-header">
                <h3 class="track-card-title">
                    <i class="fa-solid fa-bars-progress"></i>
                    <span>Progres Langsung Dapur Bundo</span>
                </h3>
                <span style="font-size: 0.74rem; color: #6B7280; font-weight: 600;">Otomatis Sinkron</span>
            </div>

            <div class="timeline-list" id="timelineContainer">
                <!-- Step 1: Pesanan Dibuat -->
                <div class="timeline-item done">
                    <div class="timeline-bullet"><i class="fa-solid fa-check"></i></div>
                    <div class="timeline-heading">Pesanan Dibuat</div>
                    <div class="timeline-subtext">Sistem telah mencatat pesanan Anda secara resmi</div>
                </div>

                <!-- Step 2: Pembayaran Terverifikasi -->
                <div class="timeline-item <?= $order['payment_status'] === 'paid' ? 'done' : 'active' ?>">
                    <div class="timeline-bullet">
                        <?= $order['payment_status'] === 'paid' ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-hourglass-half"></i>' ?>
                    </div>
                    <div class="timeline-heading">Pembayaran Terverifikasi</div>
                    <div class="timeline-subtext">
                        <?= $order['payment_status'] === 'paid' ? 'Lunas terkonfirmasi via ' . strtoupper(str_replace('_', ' ', $order['payment_method'])) : 'Menunggu penyelesaian pembayaran' ?>
                    </div>
                </div>

                <!-- Step 3: Diracik di Dapur Bundo -->
                <div class="timeline-item <?= in_array($order['order_status'], ['cooking', 'ready', 'completed']) ? ($order['order_status'] === 'cooking' ? 'active' : 'done') : '' ?>">
                    <div class="timeline-bullet"><i class="fa-solid fa-fire"></i></div>
                    <div class="timeline-heading">Diracik di Dapur Bundo</div>
                    <div class="timeline-subtext">Koki sedang meracik bumbu & memanaskan lauk khas Minang</div>
                </div>

                <!-- Step 4: Disajikan / Diantar -->
                <div class="timeline-item <?= in_array($order['order_status'], ['ready', 'completed']) ? ($order['order_status'] === 'ready' ? 'active' : 'done') : '' ?>">
                    <div class="timeline-bullet"><i class="fa-solid fa-bell-concierge"></i></div>
                    <div class="timeline-heading">
                        <?= $order['order_type'] === 'dine_in' ? 'Disajikan ke Meja ' . htmlspecialchars($order['table_number']) : 'Siap Diantar Kurir Bundo' ?>
                    </div>
                    <div class="timeline-subtext">
                        <?= $order['order_type'] === 'dine_in' ? 'Pelayan segera mengantarkan sajian hangat ke meja Anda' : 'Kurir bergerak menuju alamat pengiriman' ?>
                    </div>
                </div>

                <!-- Step 5: Selesai -->
                <div class="timeline-item <?= $order['order_status'] === 'completed' ? 'done' : '' ?>">
                    <div class="timeline-bullet"><i class="fa-solid fa-heart"></i></div>
                    <div class="timeline-heading">Pesanan Selesai</div>
                    <div class="timeline-subtext">Tarimo Kasih & Selamat Menikmati Sajian Harapan Bundo!</div>
                </div>
            </div>
        </div>

        <!-- 5. Luxury Digital Receipt -->
        <div class="receipt-container">
            <div class="receipt-header">
                <h4 class="receipt-logo-title">RM HARAPAN BUNDO</h4>
                <div class="receipt-meta-line">
                    Struk Digital Resmi &bull; <?= date('d M Y, H:i', strtotime($order['created_at'])) ?> WIB
                </div>
                <div>
                    <span class="receipt-stamp">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= $order['payment_status'] === 'paid' ? 'LUNAS (VERIFIED)' : 'STATUS: ' . strtoupper($order['payment_status']) ?>
                    </span>
                </div>
            </div>

            <!-- List of Ordered Items -->
            <div style="margin-bottom: 12px;">
                <?php foreach ($items as $it): ?>
                    <div class="receipt-item-row">
                        <div style="flex: 1;">
                            <div class="receipt-item-name">
                                <?= (int)$it['quantity'] ?>x <?= htmlspecialchars($it['menu_name']) ?>
                            </div>
                            <?php if (!empty($it['notes'])): ?>
                                <div class="receipt-item-note">* Catatan: <?= htmlspecialchars($it['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="receipt-item-price"><?= formatRupiah($it['subtotal']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Receipt Financial Summary -->
            <div class="receipt-summary-box">
                <div class="receipt-summary-line">
                    <span>Subtotal Makanan:</span>
                    <span style="font-weight: 700; color: #111827;"><?= formatRupiah($order['subtotal']) ?></span>
                </div>
                <?php if ($order['delivery_fee'] > 0): ?>
                    <div class="receipt-summary-line">
                        <span>Ongkos Kirim Kurir:</span>
                        <span style="font-weight: 700; color: #111827;"><?= formatRupiah($order['delivery_fee']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($order['tax_amount'] > 0): ?>
                    <div class="receipt-summary-line">
                        <span>Pajak (PB1):</span>
                        <span style="font-weight: 700; color: #111827;"><?= formatRupiah($order['tax_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="receipt-total-line">
                    <span>Total Tagihan:</span>
                    <span><?= formatRupiah($order['total_amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- 6. Prominent Action Buttons -->
        <div class="track-actions">
            <!-- Official WhatsApp Button (High Contrast & Tactile) -->
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $restoPhone) ?>?text=Halo%20RM%20Harapan%20Bundo,%20saya%20mau%20menanyakan%20status%20pesanan%20nomor%20<?= urlencode($order['order_code']) ?>"
               target="_blank"
               class="btn-whatsapp-cta">
                <i class="fa-brands fa-whatsapp"></i>
                <span>Hubungi Rumah Makan via WhatsApp</span>
            </a>

            <!-- Secondary Action Row -->
            <div class="track-secondary-grid">
                <button type="button" onclick="window.print()" class="btn-action-pill">
                    <i class="fa-solid fa-print"></i>
                    <span>Cetak Struk</span>
                </button>
                <a href="index.php" class="btn-action-pill primary">
                    <i class="fa-solid fa-utensils"></i>
                    <span>Pesan Lagi</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Live Status Polling Script -->
    <script>
        const orderCode = '<?= htmlspecialchars($order['order_code']) ?>';

        try {
            localStorage.setItem('hb_last_order_code', orderCode);
        } catch (e) {}

        function copyOrderCode() {
            navigator.clipboard.writeText(orderCode).then(() => {
                const label = document.getElementById('copyBtnLabel');
                if (label) {
                    label.innerText = 'Tersalin!';
                    setTimeout(() => { label.innerText = 'Salin'; }, 2000);
                }
            }).catch(() => {
                prompt('Salin kode pesanan:', orderCode);
            });
        }

        function pollOrderStatus() {
            fetch(`api/order_status.php?code=${encodeURIComponent(orderCode)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const d = res.data;
                        const container = document.getElementById('timelineContainer');
                        if (!container) return;

                        let html = '';
                        d.timeline.forEach(step => {
                            let statusClass = '';
                            let icon = '<i class="fa-solid fa-hourglass-half"></i>';
                            if (step.done) {
                                statusClass = 'done';
                                icon = '<i class="fa-solid fa-check"></i>';
                            }
                            if (step.key === d.order_status) {
                                statusClass = 'active';
                                if (step.key === 'cooking') icon = '<i class="fa-solid fa-fire"></i>';
                                else if (step.key === 'ready') icon = '<i class="fa-solid fa-bell-concierge"></i>';
                            }
                            html += `
                                <div class="timeline-item ${statusClass}">
                                    <div class="timeline-bullet">${icon}</div>
                                    <div class="timeline-heading">${step.label}</div>
                                    <div class="timeline-subtext">${step.desc}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;

                        // Update ETA if completed
                        const etaBadge = document.getElementById('etaBadge');
                        const etaSubText = document.getElementById('etaSubText');
                        if (d.order_status === 'completed' && etaBadge) {
                            etaBadge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Sudah Selesai';
                            etaBadge.style.background = '#DCFCE7';
                            etaBadge.style.color = '#15803D';
                            if (etaSubText) etaSubText.innerText = 'Pesanan telah disajikan / diterima dengan baik';
                        }
                    }
                })
                .catch(err => console.log('Poll order status error:', err));
        }

        // Poll every 4 seconds
        setInterval(pollOrderStatus, 4000);
    </script>
</body>
</html>
