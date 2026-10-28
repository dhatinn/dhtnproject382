<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();

$db = getDb();

$statusFilter = cleanInput($_GET['status'] ?? 'all');
$typeFilter = cleanInput($_GET['type'] ?? 'all');
$search = cleanInput($_GET['q'] ?? '');

$query = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($statusFilter === 'active') {
    $query .= " AND order_status IN ('pending', 'confirmed', 'cooking', 'ready')";
} elseif ($statusFilter !== 'all') {
    $query .= " AND order_status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $query .= " AND order_type = ?";
    $params[] = $typeFilter;
}

if (!empty($search)) {
    $query .= " AND (order_code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Semua Pesanan - RM Padang Harapan Bundo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/GooeyNav.css">
</head>
<body class="orders-page">

    <!-- Sidebar -->
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
            <a href="index.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard Bento</span>
            </a>
            <a href="orders.php" class="nav-link active">
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
    <button type="button" class="sidebar-overlay" id="sidebarOverlay" aria-label="Tutup menu navigasi"></button>

    <!-- Main -->
    <main class="admin-main">
        <header class="top-header">
            <div class="header-title">
                <h1>Daftar Riwayat & Arsip Pesanan</h1>
                <p>Kelola seluruh transaksi, cetak invoice, dan pantau status pengiriman</p>
            </div>
            <div class="header-actions">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" title="Buka Menu" aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </header>

        <div class="page-container">
            <!-- Filter Bar -->
            <div class="card" style="margin-bottom: 24px;">
                <div style="padding: 16px 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;">
                    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; flex: 1;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kode / nama / WA..." style="padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; min-width: 220px;">
                        
                        <select name="status" style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem;">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Pesanan Aktif</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="cooking" <?= $statusFilter === 'cooking' ? 'selected' : '' ?>>Sedang Dimasak</option>
                            <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Siap Diantar</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>

                        <select name="type" style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem;">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Semua Layanan</option>
                            <option value="dine_in" <?= $typeFilter === 'dine_in' ? 'selected' : '' ?>>Dine-in (Meja)</option>
                            <option value="delivery" <?= $typeFilter === 'delivery' ? 'selected' : '' ?>>Delivery (Antar)</option>
                        </select>

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-filter"></i> Terapkan
                        </button>
                        
                        <?php if ($statusFilter !== 'all' || $typeFilter !== 'all' || !empty($search)): ?>
                            <a href="orders.php" class="btn btn-outline btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>

                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-secondary);">
                        Ditemukan: <?= count($orders) ?> Pesanan
                    </div>
                    <?php if ($statusFilter !== 'active'): ?>
                        <a href="orders.php?status=active" class="btn btn-finish-order btn-sm">
                            <i class="fa-solid fa-list-check"></i> Pesanan Aktif
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>No. Order</th>
                                <th>Tanggal & Waktu</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Total Biaya</th>
                                <th>Status Pembayaran</th>
                                <th>Status Pesanan</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                        Tidak ada pesanan yang sesuai dengan filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): ?>
                                    <tr data-order-row="<?= htmlspecialchars($ord['order_code']) ?>">
                                        <td><strong><?= htmlspecialchars($ord['order_code']) ?></strong></td>
                                        <td>
                                            <div style="font-size: 0.85rem; font-weight: 600;"><?= date('d M Y', strtotime($ord['created_at'])) ?></div>
                                            <small style="color: var(--text-muted);"><?= date('H:i', strtotime($ord['created_at'])) ?> WIB</small>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700;"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                            <small style="color: var(--text-muted);"><?= htmlspecialchars($ord['customer_phone']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($ord['order_type'] === 'dine_in'): ?>
                                                <span class="type-pill dine_in"><i class="fa-solid fa-chair"></i> Meja <?= htmlspecialchars($ord['table_number']) ?></span>
                                            <?php else: ?>
                                                <span class="type-pill delivery"><i class="fa-solid fa-motorcycle"></i> Delivery</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong style="color: var(--primary);"><?= formatRupiah($ord['total_amount']) ?></strong></td>
                                        <td>
                                            <?php if ($ord['payment_status'] === 'paid'): ?>
                                                <span class="status-badge completed"><i class="fa-solid fa-check"></i> Lunas (<?= strtoupper($ord['payment_method']) ?>)</span>
                                            <?php else: ?>
                                                <span class="status-badge pending"><i class="fa-solid fa-clock"></i> Belum Bayar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $orderStatusLabels = ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'cooking' => 'Dimasak', 'ready' => 'Siap Diantar', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
                                            ?>
                                            <div class="order-status-control" data-order-status-control>
                                                <button type="button" class="btn order-status-label <?= htmlspecialchars($ord['order_status']) ?>" data-order-status disabled>
                                                    <?= htmlspecialchars($orderStatusLabels[$ord['order_status']] ?? $ord['order_status']) ?>
                                                </button>
                                            <?php if (!in_array($ord['order_status'], ['completed', 'cancelled'], true)): ?>
                                                <button type="button" class="btn btn-finish-order btn-sm" onclick="finishOrder('<?= htmlspecialchars($ord['order_code']) ?>', this)">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    <span>Selesaikan</span>
                                                </button>
                                            <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <button type="button" class="btn btn-outline btn-sm" onclick="showDetail('<?= htmlspecialchars($ord['order_code']) ?>')" title="Detail">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline btn-sm" onclick="printReceipt('<?= htmlspecialchars($ord['order_code']) ?>')" title="Cetak Struk">
                                                <i class="fa-solid fa-print"></i>
                                            </button>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['customer_phone']) ?>" target="_blank" class="btn btn-whatsapp btn-sm" title="WhatsApp">
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
            <div class="modal-body" id="modalOrderBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeOrderModal()">Tutup</button>
            </div>
        </div>
    </div>

    <div id="printArea" style="display: none;"></div>
    <div class="admin-toast" id="orderToast" role="status" aria-live="polite"></div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileMenuButton = document.getElementById('mobileMenuBtn');

        function setSidebarOpen(isOpen) {
            sidebar.classList.toggle('show', isOpen);
            sidebarOverlay.classList.toggle('active', isOpen);
            mobileMenuButton.setAttribute('aria-expanded', String(isOpen));
            mobileMenuButton.setAttribute('aria-label', isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        }

        mobileMenuButton.addEventListener('click', () => {
            setSidebarOpen(!sidebar.classList.contains('show'));
        });
        sidebarOverlay.addEventListener('click', () => setSidebarOpen(false));
        sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setSidebarOpen(false)));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') setSidebarOpen(false);
        });

        function updateStatus(orderCode, newStatus, button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Menyimpan...</span>';
            fetch('api/orders.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_code: orderCode, order_status: newStatus })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateFinishedAction(orderCode, newStatus, button);
                    showOrderToast(`Status pesanan ${orderCode} diperbarui.`);
                } else {
                    button.disabled = false;
                    button.innerHTML = '<i class="fa-solid fa-circle-check"></i><span>Selesaikan</span>';
                    showOrderToast(data.message || 'Status pesanan gagal diperbarui.', true);
                }
            })
            .catch(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-circle-check"></i><span>Selesaikan</span>';
                showOrderToast('Koneksi bermasalah. Status belum berubah.', true);
            });
        }

        function finishOrder(orderCode, button) {
            updateStatus(orderCode, 'completed', button);
        }

        function updateFinishedAction(orderCode, status, button) {
            const row = document.querySelector(`[data-order-row="${CSS.escape(orderCode)}"]`);
            const statusLabel = row?.querySelector('[data-order-status]');
            if (status !== 'completed' || !button || !statusLabel) return;

            statusLabel.textContent = 'Selesai';
            statusLabel.className = 'btn order-status-label completed';
            statusLabel.disabled = true;
            button.remove();
        }

        function showOrderToast(message, isError = false) {
            const toast = document.getElementById('orderToast');
            toast.textContent = message;
            toast.classList.toggle('is-error', isError);
            toast.classList.add('is-visible');
            clearTimeout(window.orderToastTimeout);
            window.orderToastTimeout = setTimeout(() => toast.classList.remove('is-visible'), 2800);
        }

        function showDetail(orderCode) {
            const modal = document.getElementById('orderModal');
            const body = document.getElementById('modalOrderBody');
            document.getElementById('modalOrderCode').innerText = 'Rincian: ' + orderCode;
            modal.classList.add('active');
            
            fetch(`api/orders.php?action=detail&order_code=${orderCode}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return alert(res.message);
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
                        <div style="background: rgba(255,255,255,0.035); border: 1px solid rgba(255,255,255,0.08); padding:14px; border-radius:12px; margin-bottom:16px;">
                            <div><strong style="color:var(--text-primary);">Pelanggan:</strong> <span style="color:var(--text-secondary);">${o.customer_name} (${o.customer_phone})</span></div>
                            <div><strong style="color:var(--text-primary);">Layanan:</strong> <span style="color:var(--text-secondary);">${o.order_type === 'dine_in' ? 'Meja ' + o.table_number : 'Delivery: ' + o.delivery_address}</span></div>
                            <div><strong style="color:var(--text-primary);">Waktu Pesan:</strong> <span style="color:var(--text-secondary);">${o.created_at}</span></div>
                            <div><strong style="color:var(--text-primary);">Metode Bayar:</strong> <span style="color:var(--gold); font-weight:700;">${o.payment_method.toUpperCase()} (${o.payment_status.toUpperCase()})</span></div>
                        </div>
                        <h4 style="margin-bottom:12px; color:var(--text-primary);">Daftar Item:</h4>
                        ${itemsHtml}
                        <div style="border-top:1px solid rgba(255,255,255,0.1); margin-top:14px; padding-top:12px;">
                            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.1rem; color:var(--primary);">
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
                        `;
                    });

                    const receiptHtml = `
                        <div class="thermal-receipt">
                            <div class="receipt-header">
                                <div class="receipt-title">RM HARAPAN BUNDO</div>
                                <div>Cita Rasa Minang Otentik</div>
                            </div>
                            <div class="receipt-info">
                                <div>Order: ${o.order_code}</div>
                                <div>Pelanggan: ${o.customer_name}</div>
                                <div>Layanan: ${o.order_type === 'dine_in' ? 'MEJA ' + o.table_number : 'DELIVERY'}</div>
                            </div>
                            <table class="receipt-items">
                                ${itemsRows}
                            </table>
                            <div class="receipt-totals">
                                <div class="receipt-total-row">
                                    <span>TOTAL:</span>
                                    <span>${o.formatted_total}</span>
                                </div>
                            </div>
                            <div class="receipt-footer">
                                <div>Terima Kasih</div>
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
    </script>
    <script src="../assets/js/GooeyNav.js"></script>
</body>
</html>
