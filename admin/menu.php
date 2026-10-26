<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();

$db = getDb();

// Fetch categories
$categories = $db->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();

// Fetch all menu items
$query = "SELECT m.*, c.name AS category_name 
          FROM menu_items m 
          JOIN categories c ON m.category_id = c.id 
          ORDER BY m.category_id ASC, m.name ASC";
$menus = $db->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Menu - RM Padang Harapan Bundo</title>
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
            <a href="menu.php" class="nav-link active">
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

    <!-- Main -->
    <main class="admin-main">
        <header class="top-header">
            <div class="header-title">
                <h1>Manajemen Menu & Harga</h1>
                <p>Tambah masakan baru, ubah harga, perbarui foto & kontrol ketersediaan stok</p>
            </div>

            <div class="header-actions">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" title="Buka Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddMenuModal()">
                    <i class="fa-solid fa-plus"></i>
                    <span>Tambah Menu Baru</span>
                </button>
            </div>
        </header>

        <div class="page-container">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-list"></i>
                        <span>Daftar Hidangan Harapan Bundo (<?= count($menus) ?> Item)</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nama Hidangan</th>
                                <th>Kategori</th>
                                <th>Harga Normal</th>
                                <th>Harga Promo</th>
                                <th>Level Pedas</th>
                                <th>Status Stok</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menus as $m): ?>
                                <tr id="row-menu-<?= $m['id'] ?>">
                                    <td>
                                        <?php 
                                            $thumbSrc = $m['image_url'];
                                            if (!empty($thumbSrc) && !str_starts_with($thumbSrc, 'http') && !str_starts_with($thumbSrc, '/')) {
                                                $thumbSrc = '../' . $thumbSrc;
                                            }
                                        ?>
                                        <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="<?= htmlspecialchars($m['name']) ?>" class="menu-thumb" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=150&q=80'">
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color: var(--text-primary);"><?= htmlspecialchars($m['name']) ?></div>
                                        <?php if ($m['is_featured']): ?>
                                            <span class="featured-badge">★ Andalan Bundo</span>
                                        <?php endif; ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($m['description']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="type-pill"><?= htmlspecialchars($m['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= formatRupiah($m['price']) ?></strong>
                                    </td>
                                    <td>
                                        <?= !empty($m['promo_price']) ? '<strong style="color:#059669;">' . formatRupiah($m['promo_price']) . '</strong>' : '<span style="color:#9CA3AF;">-</span>' ?>
                                    </td>
                                    <td>
                                        <span class="spicy-badge">
                                            <?= str_repeat('🌶️', $m['spicy_level'] ?: 1) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="toggle-btn <?= $m['is_available'] ? 'available' : 'unavailable' ?>" onclick="toggleStock(<?= $m['id'] ?>, this)">
                                            <?= $m['is_available'] ? '<i class="fa-solid fa-check"></i> Tersedia' : '<i class="fa-solid fa-xmark"></i> Habis' ?>
                                        </button>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="editMenu(<?= $m['id'] ?>)" title="Edit Menu">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="deleteMenu(<?= $m['id'] ?>)" style="color: #DC2626;" title="Hapus Menu">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Add/Edit Menu -->
    <div class="modal-overlay" id="menuModal">
        <div class="modal-container" style="max-width: 640px;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalMenuTitle">Tambah Hidangan Baru</h3>
                <button type="button" class="modal-close" onclick="closeMenuModal()">&times;</button>
            </div>
            <form id="menuForm" onsubmit="saveMenu(event)">
                <input type="hidden" name="id" id="menuId" value="0">
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div style="grid-column: span 2;">
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Nama Hidangan *</label>
                            <input type="text" name="name" id="menuName" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="Contoh: Rendang Daging Sapi" required>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Kategori *</label>
                            <select name="category_id" id="menuCategoryId" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Level Pedas</label>
                            <select name="spicy_level" id="menuSpicyLevel" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                                <option value="1">🌶️ Sedang (Aman)</option>
                                <option value="2">🌶️🌶️ Pedas Khas Minang</option>
                                <option value="3">🌶️🌶️🌶️ Sangat Pedas (Balado Gahar)</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Harga Normal (Rp) *</label>
                            <input type="number" name="price" id="menuPrice" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="28000" required>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Harga Promo (Rp) - Opsional</label>
                            <input type="number" name="promo_price" id="menuPromoPrice" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="Kosongkan jika tidak diskon">
                        </div>

                        <!-- Dual Web URL / Upload Photo with Live Preview -->
                        <div style="grid-column: span 2; background: rgba(255,255,255,0.03); border: 1px dashed var(--border); border-radius: 12px; padding: 14px;">
                            <label style="display: block; font-size: 0.88rem; font-weight: 700; margin-bottom: 8px;">
                                <i class="fa-solid fa-camera"></i> Foto Makanan (Ambil dari Web atau Upload File)
                            </label>
                            
                            <div style="display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap;">
                                <div style="width: 90px; height: 90px; border-radius: 12px; overflow: hidden; border: 2px solid var(--border); background: #1f1b26; flex-shrink: 0; display: flex; align-items: center; justify-content: center; position: relative;">
                                    <img id="menuImagePreview" src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80" alt="Preview Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>

                                <div style="flex: 1; min-width: 240px;">
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">
                                            <i class="fa-solid fa-globe"></i> 1. Link Foto dari Web (Google / Unsplash / CDN):
                                        </label>
                                        <input type="url" name="image_url" id="menuImageUrl" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); font-size: 0.85rem;" placeholder="https://images.unsplash.com/photo-..." oninput="updateImagePreviewFromUrl(this.value)">
                                    </div>

                                    <div>
                                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">
                                            <i class="fa-solid fa-cloud-arrow-up"></i> 2. Atau Upload File dari Komputer / HP:
                                        </label>
                                        <input type="file" name="image_file" id="menuImageFile" accept="image/png, image/jpeg, image/webp" style="font-size: 0.82rem;" onchange="previewLocalFile(this)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="grid-column: span 2;">
                            <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Deskripsi & Cita Rasa</label>
                            <textarea name="description" id="menuDescription" rows="3" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);" placeholder="Jelaskan kelezatan bumbu dan teknik memasak..."></textarea>
                        </div>

                        <div style="display: flex; gap: 20px; grid-column: span 2;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                                <input type="checkbox" name="is_available" id="menuIsAvailable" value="1" checked>
                                <span>Stok Tersedia</span>
                            </label>

                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                                <input type="checkbox" name="is_featured" id="menuIsFeatured" value="1">
                                <span>Jadikan Menu Andalan (Featured)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeMenuModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitMenu">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Hidangan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateImagePreviewFromUrl(url) {
            if (url && url.trim()) {
                document.getElementById('menuImagePreview').src = url.trim();
            }
        }

        function previewLocalFile(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('menuImagePreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openAddMenuModal() {
            document.getElementById('menuForm').reset();
            document.getElementById('menuId').value = 0;
            document.getElementById('modalMenuTitle').innerText = 'Tambah Hidangan Baru';
            document.getElementById('menuIsAvailable').checked = true;
            document.getElementById('menuImagePreview').src = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80';
            document.getElementById('menuImageFile').value = '';
            document.getElementById('menuModal').classList.add('active');
        }

        function closeMenuModal() {
            document.getElementById('menuModal').classList.remove('active');
        }

        function editMenu(id) {
            fetch(`api/menu.php?action=get&id=${id}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return alert(res.message);
                    const d = res.data;
                    document.getElementById('menuId').value = d.id;
                    document.getElementById('menuName').value = d.name;
                    document.getElementById('menuCategoryId').value = d.category_id;
                    document.getElementById('menuPrice').value = Math.round(d.price);
                    document.getElementById('menuPromoPrice').value = d.promo_price ? Math.round(d.promo_price) : '';
                    document.getElementById('menuSpicyLevel').value = d.spicy_level;
                    document.getElementById('menuImageUrl').value = d.image_url;
                    document.getElementById('menuImageFile').value = '';
                    
                    let prevSrc = d.image_url;
                    if (prevSrc && !prevSrc.startsWith('http') && !prevSrc.startsWith('/')) {
                        prevSrc = '../' + prevSrc;
                    }
                    document.getElementById('menuImagePreview').src = prevSrc || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80';
                    
                    document.getElementById('menuDescription').value = d.description || '';
                    document.getElementById('menuIsAvailable').checked = d.is_available == 1;
                    document.getElementById('menuIsFeatured').checked = d.is_featured == 1;
                    document.getElementById('modalMenuTitle').innerText = 'Edit: ' + d.name;
                    document.getElementById('menuModal').classList.add('active');
                });
        }

        function saveMenu(e) {
            e.preventDefault();
            const form = document.getElementById('menuForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btnSubmitMenu');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

            fetch('api/menu.php?action=save', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Hidangan';
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Hidangan';
                alert('Terjadi kesalahan jaringan.');
            });
        }

        function toggleStock(id, btn) {
            const formData = new FormData();
            formData.append('id', id);

            fetch('api/menu.php?action=toggle_availability', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.is_available === 1) {
                        btn.className = 'toggle-btn available';
                        btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersedia';
                    } else {
                        btn.className = 'toggle-btn unavailable';
                        btn.innerHTML = '<i class="fa-solid fa-xmark"></i> Habis';
                    }
                } else {
                    alert(data.message);
                }
            });
        }

        function deleteMenu(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus hidangan ini dari daftar menu?')) return;
            const formData = new FormData();
            formData.append('id', id);

            fetch('api/menu.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('row-menu-' + id).remove();
                } else {
                    alert('Gagal: ' + data.message);
                }
            });
        }
    </script>
    <script src="../assets/js/GooeyNav.js"></script>
</body>
</html>
