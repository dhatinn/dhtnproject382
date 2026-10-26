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
    <title>Status Pesanan #<?= htmlspecialchars($order['order_code']) ?> - Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .track-timeline {
            position: relative;
            padding: 10px 0 20px 24px;
            margin-top: 10px;
        }
        .track-timeline::before {
            content: '';
            position: absolute;
            top: 10px;
            bottom: 10px;
            left: 9px;
            width: 2px;
            background: #E5E7EB;
        }
        .track-step {
            position: relative;
            margin-bottom: 24px;
        }
        .track-step:last-child {
            margin-bottom: 0;
        }
        .step-dot {
            position: absolute;
            left: -24px;
            top: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 2px solid #D1D5DB;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #9CA3AF;
            transition: all 0.3s ease;
        }
        .track-step.done .step-dot {
            background: #10B981;
            border-color: #10B981;
            color: #FFFFFF;
        }
        .track-step.active .step-dot {
            background: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(158, 27, 27, 0.2);
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
        .step-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .step-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <div class="app-viewport">
        <!-- Header -->
        <header class="app-header">
            <div class="header-top">
                <a href="index.php" class="icon-btn" title="Kembali ke Menu">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div class="brand-text" style="text-align: center;">
                    <h1>STATUS PESANAN</h1>
                    <span>RM HARAPAN BUNDO</span>
                </div>
                <div style="width: 38px;"></div>
            </div>
        </header>

        <div style="max-width: 680px; margin: 0 auto; width: 100%; padding: 28px 20px;">
            <!-- Order Success Badge Card -->
            <div style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; border-radius: 20px; padding: 20px; text-align: center; margin-bottom: 20px; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);">
                <div style="width: 48px; height: 48px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin: 0 auto 10px;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 4px;">Pesanan Diterima!</h2>
                <p style="font-size: 0.8rem; opacity: 0.9;">Kode: <strong style="font-size: 0.9rem;"><?= htmlspecialchars($order['order_code']) ?></strong></p>
                <div style="display: inline-block; background: rgba(0, 0, 0, 0.2); padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; margin-top: 8px;">
                    <?= $order['order_type'] === 'dine_in' ? 'Makan di Meja ' . htmlspecialchars($order['table_number']) : 'Delivery ke Alamat' ?>
                </div>
            </div>

            <!-- Live Status Timeline Card -->
            <div style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: var(--shadow-soft);">
                <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
                    <span>Progres Langsung Dapur Bundo</span>
                </h3>

                <div class="track-timeline" id="timelineContainer">
                    <!-- Populated dynamically via JS status poller -->
                    <div class="track-step done">
                        <div class="step-dot"><i class="fa-solid fa-check"></i></div>
                        <div class="step-label">Pesanan Diterima</div>
                        <div class="step-desc">Sistem telah mencatat pesanan Anda</div>
                    </div>
                    <div class="track-step <?= $order['payment_status'] === 'paid' ? 'done' : 'active' ?>">
                        <div class="step-dot"><?= $order['payment_status'] === 'paid' ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-hourglass-half"></i>' ?></div>
                        <div class="step-label">Pembayaran Terkonfirmasi</div>
                        <div class="step-desc"><?= $order['payment_status'] === 'paid' ? 'Lunas via ' . strtoupper($order['payment_method']) : 'Menunggu pembayaran' ?></div>
                    </div>
                    <div class="track-step <?= in_array($order['order_status'], ['cooking', 'ready', 'completed']) ? ($order['order_status'] === 'cooking' ? 'active' : 'done') : '' ?>">
                        <div class="step-dot"><i class="fa-solid fa-utensils"></i></div>
                        <div class="step-label">Diracik di Dapur Bundo</div>
                        <div class="step-desc">Koki menyiapkan lauk & rempah hangat</div>
                    </div>
                    <div class="track-step <?= in_array($order['order_status'], ['ready', 'completed']) ? ($order['order_status'] === 'ready' ? 'active' : 'done') : '' ?>">
                        <div class="step-dot"><i class="fa-solid fa-bell-concierge"></i></div>
                        <div class="step-label"><?= $order['order_type'] === 'dine_in' ? 'Disajikan ke Meja ' . $order['table_number'] : 'Siap Diantar Kurir' ?></div>
                        <div class="step-desc">Pesanan siap dinikmati</div>
                    </div>
                    <div class="track-step <?= $order['order_status'] === 'completed' ? 'done' : '' ?>">
                        <div class="step-dot"><i class="fa-solid fa-heart"></i></div>
                        <div class="step-label">Pesanan Selesai</div>
                        <div class="step-desc">Tarimo Kasih & Selamat Menikmati!</div>
                    </div>
                </div>
            </div>

            <!-- Digital Invoice Details -->
            <div style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 20px; padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow-soft);">
                <h3 style="font-size: 1rem; font-weight: 800; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                    <span>Rincian Struk Digital</span>
                    <span style="font-size: 0.75rem; color: #10B981; font-weight: 700; background: #DCFCE7; padding: 2px 8px; border-radius: 99px;">
                        <?= strtoupper($order['payment_status']) ?>
                    </span>
                </h3>

                <div style="margin-bottom: 14px;">
                    <?php foreach ($items as $it): ?>
                        <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed var(--border-light); font-size: 0.88rem;">
                            <div>
                                <div style="font-weight: 700;"><?= $it['quantity'] ?>x <?= htmlspecialchars($it['menu_name']) ?></div>
                                <?php if (!empty($it['notes'])): ?>
                                    <div style="font-size: 0.72rem; color: #B45309;">* <?= htmlspecialchars($it['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="font-weight: 700; color: var(--text-main);"><?= formatRupiah($it['subtotal']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Subtotal:</span>
                        <span style="font-weight: 700; color: var(--text-main);"><?= formatRupiah($order['subtotal']) ?></span>
                    </div>
                    <?php if ($order['delivery_fee'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Ongkos Kirim:</span>
                            <span style="font-weight: 700; color: var(--text-main);"><?= formatRupiah($order['delivery_fee']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: var(--primary); border-top: 1px dashed var(--border); padding-top: 10px; margin-top: 6px;">
                        <span>Total Tagihan:</span>
                        <span><?= formatRupiah($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $restoPhone) ?>?text=Halo%20RM%20Harapan%20Bundo,%20saya%20mau%20menanyakan%20status%20pesanan%20nomor%20<?= urlencode($order['order_code']) ?>" target="_blank" class="btn btn-whatsapp" style="padding: 14px; border-radius: var(--radius-pill); justify-content: center; font-weight: 700;">
                    <i class="fa-brands fa-whatsapp" style="font-size: 1.1rem;"></i>
                    <span>Hubungi Rumah Makan via WhatsApp</span>
                </a>

                <a href="index.php" class="btn btn-outline" style="padding: 14px; border-radius: var(--radius-pill); justify-content: center; font-weight: 700;">
                    <i class="fa-solid fa-utensils"></i>
                    <span>Pesan Hidangan Lainnya</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Live Status Poller Script -->
    <script>
        const orderCode = '<?= htmlspecialchars($order['order_code']) ?>';

        function pollOrderStatus() {
            fetch(`api/order_status.php?code=${orderCode}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const d = res.data;
                        const container = document.getElementById('timelineContainer');
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
                            }
                            html += `
                                <div class="track-step ${statusClass}">
                                    <div class="step-dot">${icon}</div>
                                    <div class="step-label">${step.label}</div>
                                    <div class="step-desc">${step.desc}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    }
                })
                .catch(err => console.log('Poll status error', err));
        }

        // Poll every 4 seconds
        setInterval(pollOrderStatus, 4000);
    </script>
</body>
</html>
