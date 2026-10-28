<?php
require_once __DIR__ . '/config.php';

$db = getDb();

// Fetch Categories
$stmtCat = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $stmtCat->fetchAll();

// Fetch Popular / Featured Menus
try {
    $stmtFeatured = $db->query("
        SELECT m.*, c.slug AS category_slug, c.name AS category_name 
        FROM menu_items m 
        JOIN categories c ON m.category_id = c.id 
        WHERE m.is_available = 1 AND m.is_featured = 1 
        ORDER BY m.total_sold DESC 
        LIMIT 8
    ");
    $featuredMenus = $stmtFeatured->fetchAll();

    // Fallback to top-sold menus if no item has is_featured = 1
    if (empty($featuredMenus)) {
        $stmtFallback = $db->query("
            SELECT m.*, c.slug AS category_slug, c.name AS category_name 
            FROM menu_items m 
            JOIN categories c ON m.category_id = c.id 
            WHERE m.is_available = 1 
            ORDER BY m.total_sold DESC, m.rating DESC 
            LIMIT 8
        ");
        $featuredMenus = $stmtFallback->fetchAll();
    }
} catch (Exception $e) {
    $featuredMenus = [];
}

// Guaranteed fallback signature menus so the hero carousel is NEVER empty under any circumstance
if (empty($featuredMenus)) {
    $featuredMenus = [
        [
            'id' => 1,
            'name' => 'Rendang Daging Sapi Suntiang',
            'category_name' => 'Lauk Daging',
            'category_slug' => 'lauk-daging',
            'price' => 25000,
            'promo_price' => null,
            'rating' => 5.0,
            'image_url' => 'https://images.unsplash.com/photo-1626074353765-517a681e40be?auto=format&fit=crop&w=800&q=80',
            'description' => 'Daging sapi pilihan dimasak perlahan 8 jam dengan santan kelapa tua & 16 rempah rahasia khas Bundo.'
        ],
        [
            'id' => 2,
            'name' => 'Ayam Pop Bukittinggi Asli',
            'category_name' => 'Ayam & Bebek',
            'category_slug' => 'ayam-bebek',
            'price' => 22000,
            'promo_price' => null,
            'rating' => 4.9,
            'image_url' => 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=800&q=80',
            'description' => 'Ayam kampung muda empuk gurih dimasak air kelapa murni, disajikan dengan sambalado tomat merah segar.'
        ],
        [
            'id' => 3,
            'name' => 'Dendeng Batokok Balado Basah',
            'category_name' => 'Lauk Daging',
            'category_slug' => 'lauk-daging',
            'price' => 26000,
            'promo_price' => null,
            'rating' => 4.9,
            'image_url' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80',
            'description' => 'Irisan daging sapi pipih empuk dipukul berpadu lumuran cabai merah ulek kasar minyak kelapa.'
        ],
        [
            'id' => 4,
            'name' => 'Gulai Kepala Ikan Kakap Merah',
            'category_name' => 'Seafood & Gulai',
            'category_slug' => 'seafood-gulai',
            'price' => 48000,
            'promo_price' => null,
            'rating' => 5.0,
            'image_url' => 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?auto=format&fit=crop&w=800&q=80',
            'description' => 'Kepala kakap merah segar dengan kuah gulai rempah kuning kental kaya asam kandis & daun ruku-ruku.'
        ],
        [
            'id' => 5,
            'name' => 'Gulai Tunjang Kikil Kenyal',
            'category_name' => 'Seafood & Gulai',
            'category_slug' => 'seafood-gulai',
            'price' => 26000,
            'promo_price' => null,
            'rating' => 4.8,
            'image_url' => 'https://images.unsplash.com/photo-1541832676-9b763b0239ab?auto=format&fit=crop&w=800&q=80',
            'description' => 'Tunjang sapi lembut kenyal dengan kuah santan bumbu kari Padang yang kaya rempah dan medok.'
        ],
        [
            'id' => 7,
            'name' => 'Paket Nasi Padang Komplit Rendang',
            'category_name' => 'Paket Nasi Lengkap',
            'category_slug' => 'paket-nasi',
            'price' => 35000,
            'promo_price' => 32000,
            'rating' => 5.0,
            'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=800&q=80',
            'description' => 'Nasi putih pulen, rendang daging sapi, sayur nangka kapau, daun singkong rebus, dan sambal ijo otentik.'
        ],
        [
            'id' => 12,
            'name' => 'Es Tebak Bundo Segar',
            'category_name' => 'Minuman',
            'category_slug' => 'minuman',
            'price' => 15000,
            'promo_price' => null,
            'rating' => 4.8,
            'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=800&q=80',
            'description' => 'Es campur legendaris khas Minang berisi tebak tepung beras, cincau, kolang-kaling dan sirup manis harum.'
        ]
    ];
}

// Fetch All Menus
$stmtAll = $db->query("
    SELECT m.*, c.slug AS category_slug, c.name AS category_name 
    FROM menu_items m 
    JOIN categories c ON m.category_id = c.id 
    WHERE m.is_available = 1 
    ORDER BY c.sort_order ASC, m.total_sold DESC
");
$allMenus = $stmtAll->fetchAll();

$restoName = getSetting('restaurant_name', 'RM Padang Harapan Bundo');
$tagline = getSetting('tagline', 'Cita Rasa Minang Otentik, Warisan Tradisi Bundo');
$address = getSetting('address', 'Jl. Sudirman No. 88, Jakarta');
$whatsapp = getSetting('whatsapp_number', '6281234567890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restoName) ?> - Pesan Menu Masakan Padang Otentik</title>
    <meta name="description" content="Pesan hidangan otentik Rendang Daging Sapi, Ayam Pop Bukittinggi, Gulai Kepala Kakap dan aneka masakan Padang Harapan Bundo. Bayar praktis pakai QRIS & DOKU.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;0,800;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/GradualBlur.css">
    <link rel="stylesheet" href="assets/css/Carousel.css">
    <link rel="stylesheet" href="assets/css/GooeyNav.css">
    <link rel="stylesheet" href="assets/css/TiltedCard.css">
</head>
<body>

    <!-- Gradual Blur Vignettes (Progressive Multi-layer Glassmorphism) -->
    <div data-gradual-blur data-gradual-blur-position="top" data-gradual-blur-height="4.5rem" data-gradual-blur-strength="2" data-gradual-blur-curve="bezier" data-gradual-blur-autohide="true" data-gradual-blur-zindex="45" data-gradual-blur-tint="rgba(255,255,255,0.05)"></div>
    <div data-gradual-blur data-gradual-blur-position="bottom" data-gradual-blur-height="5.5rem" data-gradual-blur-strength="2.2" data-gradual-blur-curve="bezier" data-gradual-blur-zindex="40" data-gradual-blur-tint="rgba(248,245,240,0.18)"></div>

    <div class="app-viewport">
        <!-- Responsive Header (Full-width Desktop & Mobile App Navigation) -->
        <header class="app-header">
            <div class="header-container">
                <!-- Brand / Logo (With Hidden Admin 5-Click Trigger) -->
                <div class="header-top">
                    <div class="brand-wrapper" id="brandLogoWrapper" title="RM Padang Harapan Bundo">
                        <div class="brand-logo-badge">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                        <div class="brand-text">
                            <h1>HARAPAN BUNDO</h1>
                            <span>RUMAH MAKAN PADANG</span>
                        </div>
                    </div>

                    <!-- Mobile-Only Action Buttons -->
                    <div class="header-actions mobile-header-actions">
                        <button type="button" class="icon-btn" onclick="openTrackLookupModal()" title="Lacak Pesanan Saya">
                            <i class="fa-solid fa-receipt"></i>
                        </button>
                        <button type="button" class="icon-btn" onclick="openServiceModal()" title="Pilih Meja / Lokasi">
                            <i class="fa-solid fa-location-dot"></i>
                        </button>
                    </div>
                </div>

                <!-- Center Controls on Desktop / Normal Bar on Mobile -->
                <div class="header-center-desktop">
                    <!-- Service & Location Pill -->
                    <div class="service-selector" onclick="openServiceModal()">
                        <div class="service-info">
                            <i class="fa-solid fa-chair service-icon" id="headerServiceIcon"></i>
                            <div class="service-label-group">
                                <span class="service-label" id="headerServiceLabel">DINE-IN</span>
                                <span class="service-current" id="headerServiceVal">Makan di Meja 05</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down service-toggle-arrow"></i>
                    </div>

                    <!-- Search Bar -->
                    <div class="search-container">
                        <div class="search-bar">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="searchInput" class="search-input" placeholder="Cari Rendang, Ayam Pop, Es Tebak...">
                            <button type="button" class="search-clear-btn" id="searchClearBtn" onclick="clearSearch()" style="display:none;" title="Hapus Pencarian">&times;</button>
                        </div>
                    </div>
                </div>

                <!-- Desktop-Only Right Actions -->
                <div class="desktop-controls">
                    <button type="button" class="btn-desktop-track" onclick="openTrackLookupModal()">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Lacak Pesanan</span>
                    </button>

                    <button type="button" class="btn-desktop-cart" onclick="openCartDrawer()">
                        <i class="fa-solid fa-basket-shopping"></i>
                        <span>Keranjang</span>
                        <span class="desktop-cart-badge" id="desktopCartBadge">0</span>
                        <span id="desktopCartTotal" style="margin-left: 4px; font-weight: 800; opacity: 0.95;">Rp 0</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Hero Promo Banner Card (Wide Responsive Card with 3D Menu Carousel) -->
        <section class="hero-section" data-animated-content="" data-trigger="mount" data-distance="60" data-duration="0.9" data-scale="0.97" data-blur="8" data-tilt="3" data-ease="power3.out">
            <div class="hero-card">
                <!-- Left: Headline & Actions -->
                <div class="hero-content-left">
                    <div class="hero-tag">
                        <i class="fa-solid fa-crown"></i>
                        <span>Resep Warisan Bundo Sejak 1982</span>
                    </div>
                    <h2 class="hero-title">CITA RASA MINANG OTENTIK.</h2>
                    <p class="hero-subtitle">Dimasak perlahan dengan santan murni kelapa tua dan racikan 16 rempah rahasia Bukittinggi asli yang meresap sempurna hingga ke serat terdalam.</p>
                    
                    <div class="hero-stats-row">
                        <span class="hero-stat-pill"><i class="fa-solid fa-star" style="color: #D4AF37;"></i> 4.9+ Rating</span>
                        <span class="hero-stat-pill"><i class="fa-solid fa-motorcycle" style="color: #D4AF37;"></i> Siap Santap & Delivery</span>
                        <span class="hero-stat-pill"><i class="fa-solid fa-certificate" style="color: #10B981;"></i> 100% Halal Otentik</span>
                    </div>

                    <div class="hero-buttons-group">
                        <button type="button" class="hero-cta" onclick="document.getElementById('popularSection').scrollIntoView({behavior: 'smooth'})">
                            <span>Pesan Sekarang</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <button type="button" class="hero-secondary-cta" onclick="document.getElementById('allMenuSection').scrollIntoView({behavior: 'smooth'})">
                            <i class="fa-solid fa-bowl-food"></i>
                            <span>Daftar Menu Lengkap</span>
                        </button>
                    </div>
                </div>

                <!-- Right: 3D Momentum Physics Carousel (Menu-Menu Andalan) -->
                <div class="hero-carousel-wrap">
                    <div class="hero-carousel-header">
                        <div class="carousel-header-badge">
                            <i class="fa-solid fa-fire" style="color: #F59E0B;"></i>
                            <span>Menu Andalan Bundo</span>
                        </div>
                    </div>

                    <div class="carousel-container has-frame" data-effect="tilt" data-indicator="bars" data-autoplay="true" data-autoplay-delay="3400" data-loop="true" data-aspect-ratio="1.2">
                        <div class="carousel-stage">
                            <div class="carousel-viewport" data-draggable="true">
                                <?php foreach ($featuredMenus as $idx => $fm): ?>
                                    <?php 
                                        $finalPrice = (!empty($fm['promo_price']) && $fm['promo_price'] > 0) ? (float)$fm['promo_price'] : (float)$fm['price'];
                                        $itemJson = htmlspecialchars(json_encode([
                                            'id' => (int)$fm['id'],
                                            'name' => $fm['name'],
                                            'price' => (float)$fm['price'],
                                            'promo_price' => !empty($fm['promo_price']) ? (float)$fm['promo_price'] : null,
                                            'image_url' => $fm['image_url'],
                                            'description' => $fm['description']
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="carousel-slide" data-index="<?= $idx ?>">
                                        <div class="carousel-card has-image" onclick='if(this.closest(".carousel-slide").dataset.current === "true") { openCustomizeModal(<?= $itemJson ?>); }'>
                                            <img class="carousel-image" src="<?= htmlspecialchars($fm['image_url']) ?>" alt="<?= htmlspecialchars($fm['name']) ?>" draggable="false" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'">
                                            <span class="carousel-scrim"></span>
                                            
                                            <div class="carousel-top-badge">
                                                <span class="badge-icon"><i class="fa-solid fa-star"></i> <?= number_format($fm['rating'] ?: 4.9, 1) ?></span>
                                                <span>• <?= ($idx === 0) ? 'Paling Favorit #1' : htmlspecialchars($fm['category_name']) ?></span>
                                            </div>

                                            <div class="carousel-text">
                                                <div class="carousel-title"><?= htmlspecialchars($fm['name']) ?></div>
                                                <p class="carousel-description"><?= htmlspecialchars($fm['description']) ?></p>
                                                
                                                <div class="carousel-footer-action">
                                                    <div class="carousel-price"><?= formatRupiah($finalPrice) ?></div>
                                                    <button type="button" class="carousel-btn-order" onclick='event.stopPropagation(); openCustomizeModal(<?= $itemJson ?>)'>
                                                        <span>Pesan</span>
                                                        <i class="fa-solid fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <span class="carousel-card-rim"></span>
                                        </div>
                                        <span class="carousel-shade"></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="carousel-controls"></div>
                            <span class="carousel-frame-rim"></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Popular Picks Section (Paling Terlaris) -->
        <section id="popularSection">
            <div class="section-header" data-animated-content="" data-distance="35" data-threshold="0.15">
                <h3 class="section-title">
                    <i class="fa-solid fa-fire" style="color: #DC2626;"></i>
                    <span>Paling Terlaris</span>
                </h3>
                <span class="section-link">Sajian Andalan Bundo</span>
            </div>

            <div class="menu-grid" data-animated-content="" data-distance="50" data-stagger="0.08" data-scale="0.95" data-threshold="0.1" data-duration="0.75" data-tilt="3">
                <?php foreach ($featuredMenus as $fm): ?>
                    <?php 
                        $finalPrice = (!empty($fm['promo_price']) && $fm['promo_price'] > 0) ? (float)$fm['promo_price'] : (float)$fm['price'];
                        $itemJson = htmlspecialchars(json_encode([
                            'id' => (int)$fm['id'],
                            'name' => $fm['name'],
                            'price' => (float)$fm['price'],
                            'promo_price' => !empty($fm['promo_price']) ? (float)$fm['promo_price'] : null,
                            'image_url' => $fm['image_url'],
                            'description' => $fm['description']
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <figure class="tilted-card food-card" data-category-slug="<?= htmlspecialchars($fm['category_slug']) ?>" data-title="<?= htmlspecialchars($fm['name']) ?>" data-desc="<?= htmlspecialchars($fm['description']) ?>" data-interactive="" data-caption="<?= htmlspecialchars($fm['name']) ?> • <?= formatRupiah($finalPrice) ?>" data-rotate-amplitude="12" data-scale="1.04" data-glare="0.45" data-shadow="0.4" data-intro="false">
                        <div class="tilted-card__shadow" aria-hidden="true"></div>
                        <div class="tilted-card__body">
                            <div class="tilted-card__face tilted-card__front">
                                <div class="food-thumb-wrap" onclick='openCustomizeModal(<?= $itemJson ?>)' style="cursor: pointer;">
                                    <?php 
                                        $imgUrl = $fm['image_url'];
                                        if (empty($imgUrl)) {
                                            $imgUrl = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80';
                                        }
                                    ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($fm['name']) ?>" class="food-thumb tilted-card__img" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'">
                                    <?php if (!empty($fm['promo_price']) && $fm['promo_price'] > 0): ?>
                                        <span class="food-badge-promo">PROMO</span>
                                    <?php endif; ?>
                                    <span class="food-spicy-tag"><?= str_repeat('🌶️', $fm['spicy_level'] ?: 1) ?></span>
                                </div>
                                <div class="food-details">
                                    <h4 class="food-title" onclick='openCustomizeModal(<?= $itemJson ?>)' style="cursor: pointer;"><?= htmlspecialchars($fm['name']) ?></h4>
                                    <p class="food-desc"><?= htmlspecialchars($fm['description']) ?></p>
                                    <div class="food-footer">
                                        <div class="price-wrap">
                                            <?php if (!empty($fm['promo_price']) && $fm['promo_price'] > 0): ?>
                                                <span class="price-original"><?= formatRupiah($fm['price']) ?></span>
                                            <?php endif; ?>
                                            <span class="price-current"><?= formatRupiah($finalPrice) ?></span>
                                        </div>
                                        <button type="button" class="btn-add-food" onclick='event.stopPropagation(); addToCart(<?= $itemJson ?>, 1, "", 0, this)' title="Tambah ke Keranjang">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <span class="tilted-card__glare" aria-hidden="true"></span>
                                <span class="tilted-card__rim" aria-hidden="true"></span>
                                <span class="tilted-card__edge" aria-hidden="true"></span>
                            </div>
                        </div>
                        <figcaption class="tilted-card__caption"><?= htmlspecialchars($fm['name']) ?> • <?= formatRupiah($finalPrice) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Rewards Loyalty Card -->
        <section data-animated-content="" data-distance="60" data-wipe="up" data-duration="0.85" data-threshold="0.15">
            <div class="rewards-banner">
                <div class="rewards-left">
                    <div class="rewards-icon">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div class="rewards-text">
                        <h4>Harapan Bundo Rewards</h4>
                        <p>Kumpulkan poin setiap pemesanan & raih porsi Sambalado atau Teh Talua gratis!</p>
                    </div>
                </div>
                <button type="button" class="btn-rewards" onclick="alert('Program loyalitas Harapan Bundo aktif otomatis setiap nomor WhatsApp Anda bertransaksi!')">
                    Gabung Sekarang
                </button>
            </div>
        </section>

        <!-- All Menus Section (Katalog Lengkap) -->
        <section id="allMenuSection">
            <div class="section-header" data-animated-content="" data-distance="35" data-threshold="0.15">
                <h3 class="section-title">
                    <i class="fa-solid fa-bowl-food" style="color: var(--primary);"></i>
                    <span>Daftar Hidangan Lengkap</span>
                </h3>
            </div>

            <div id="noResultsMessage" style="display: none; text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-utensils" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.3; display: block;"></i>
                <p>Tidak ada hidangan yang cocok dengan pencarian Anda.</p>
            </div>

            <div class="menu-grid" id="mainMenuGrid" data-animated-content="" data-distance="50" data-stagger="0.05" data-scale="0.95" data-threshold="0.1" data-duration="0.7">
                <?php foreach ($allMenus as $m): ?>
                    <?php 
                        $finalPrice = (!empty($m['promo_price']) && $m['promo_price'] > 0) ? (float)$m['promo_price'] : (float)$m['price'];
                        $itemJson = htmlspecialchars(json_encode([
                            'id' => (int)$m['id'],
                            'name' => $m['name'],
                            'price' => (float)$m['price'],
                            'promo_price' => !empty($m['promo_price']) ? (float)$m['promo_price'] : null,
                            'image_url' => $m['image_url'],
                            'description' => $m['description']
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <figure class="tilted-card food-card" data-category-slug="<?= htmlspecialchars($m['category_slug']) ?>" data-title="<?= htmlspecialchars($m['name']) ?>" data-desc="<?= htmlspecialchars($m['description']) ?>" data-interactive="" data-caption="<?= htmlspecialchars($m['name']) ?> • <?= formatRupiah($finalPrice) ?>" data-rotate-amplitude="12" data-scale="1.04" data-glare="0.45" data-shadow="0.4" data-intro="false">
                        <div class="tilted-card__shadow" aria-hidden="true"></div>
                        <div class="tilted-card__body">
                            <div class="tilted-card__face tilted-card__front">
                                <div class="food-thumb-wrap" onclick='openCustomizeModal(<?= $itemJson ?>)' style="cursor: pointer;">
                                    <?php 
                                        $imgUrl = $m['image_url'];
                                        if (empty($imgUrl)) {
                                            $imgUrl = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80';
                                        }
                                    ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($m['name']) ?>" class="food-thumb tilted-card__img" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'">
                                    <?php if (!empty($m['promo_price']) && $m['promo_price'] > 0): ?>
                                        <span class="food-badge-promo">PROMO</span>
                                    <?php endif; ?>
                                    <span class="food-spicy-tag"><?= str_repeat('🌶️', $m['spicy_level'] ?: 1) ?></span>
                                </div>
                                <div class="food-details">
                                    <h4 class="food-title" onclick='openCustomizeModal(<?= $itemJson ?>)' style="cursor: pointer;"><?= htmlspecialchars($m['name']) ?></h4>
                                    <p class="food-desc"><?= htmlspecialchars($m['description']) ?></p>
                                    <div class="food-footer">
                                        <div class="price-wrap">
                                            <?php if (!empty($m['promo_price']) && $m['promo_price'] > 0): ?>
                                                <span class="price-original"><?= formatRupiah($m['price']) ?></span>
                                            <?php endif; ?>
                                            <span class="price-current"><?= formatRupiah($finalPrice) ?></span>
                                        </div>
                                        <button type="button" class="btn-add-food" onclick='event.stopPropagation(); addToCart(<?= $itemJson ?>, 1, "", 0, this)' title="Tambah ke Keranjang">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <span class="tilted-card__glare" aria-hidden="true"></span>
                                <span class="tilted-card__rim" aria-hidden="true"></span>
                                <span class="tilted-card__edge" aria-hidden="true"></span>
                            </div>
                        </div>
                        <figcaption class="tilted-card__caption"><?= htmlspecialchars($m['name']) ?> • <?= formatRupiah($finalPrice) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Rich Restaurant Footer (Desktop & Mobile) -->
        <footer class="app-footer">
            <div class="footer-grid" data-animated-content="" data-distance="40" data-stagger="0.1" data-threshold="0.1">
                <div class="footer-col">
                    <div class="brand-wrapper" style="margin-bottom: 14px;">
                        <div class="brand-logo-badge" style="width: 36px; height: 36px; font-size: 1rem;">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                        <div class="brand-text">
                            <h1 style="font-size: 1.05rem; color: #FFFFFF;">HARAPAN BUNDO</h1>
                            <span style="color: #D4AF37;">CITA RASA MINANG OTENTIK</span>
                        </div>
                    </div>
                    <p>Menghadirkan kelezatan masakan Minangkabau asli dengan resep turun-temurun sejak 1982, menggunakan bahan baku segar pilihan dan rempah Bukittinggi.</p>
                </div>

                <div class="footer-col">
                    <h4>Jam Operasional</h4>
                    <ul>
                        <li><i class="fa-solid fa-clock" style="color: #D4AF37;"></i> Senin - Minggu: 09:00 - 22:00 WIB</li>
                        <li><i class="fa-solid fa-motorcycle" style="color: #D4AF37;"></i> Pengantaran Delivery: 09:30 - 21:00 WIB</li>
                        <li><i class="fa-solid fa-check-circle" style="color: #10B981;"></i> Buka Setiap Hari (Termasuk Libur Nasional)</li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Lokasi & Kontak</h4>
                    <ul>
                        <li><i class="fa-solid fa-location-dot" style="color: #D4AF37;"></i> <?= htmlspecialchars($address) ?></li>
                        <li><i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> WhatsApp: <?= htmlspecialchars($whatsapp) ?></li>
                        <li><i class="fa-solid fa-utensils" style="color: #D4AF37;"></i> Layanan: Dine-in & Delivery</li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Pembayaran Digital</h4>
                    <p>Pembayaran praktis & instan menggunakan standar QRIS Bank Indonesia (m-Banking & e-Wallet) serta Gateway DOKU.</p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                        <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">QRIS</span>
                        <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">DOKU</span>
                        <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">BCA</span>
                        <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">GoPay</span>
                        <span style="background: rgba(255,255,255,0.1); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">OVO</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div>
                    &copy; <?= date('Y') ?> RM Padang Harapan Bundo. All Rights Reserved.
                </div>
                <!-- Subtle discreet trigger for admin portal -->
                <div>
                    <a href="admin/login.php" class="secret-admin-trigger" title="Portal Manajemen Resto">
                        <i class="fa-solid fa-shield-halved"></i>
                    </a>
                </div>
            </div>
        </footer>

        <!-- Desktop Floating Cart Button (Bottom Right) -->
        <button type="button" class="floating-desktop-cart" onclick="openCartDrawer()" title="Buka Keranjang Pesanan">
            <i class="fa-solid fa-basket-shopping" style="font-size: 1.25rem;"></i>
            <span>Keranjang Belanja</span>
            <span class="desktop-cart-badge" id="floatCartBadge">0</span>
        </button>

        <!-- Fluid WebGL2 Liquid Bottom Navigation Bar (Mobile Only < 1024px) -->
        <div class="mobile-bottom-gooey">
            <nav class="gooey-nav gooey-nav--md gooey-nav--framed" id="mobileGooeyNav" data-theme="dark" data-color="#9E1B1B">
                <ul class="gooey-nav__list">
                    <li class="gooey-nav__item" data-active>
                        <button type="button" class="gooey-nav__link" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
                            <span class="gooey-nav__icon"><i class="fa-solid fa-house"></i></span>
                            <span class="gooey-nav__label">Beranda</span>
                        </button>
                    </li>
                    <li class="gooey-nav__item">
                        <button type="button" class="gooey-nav__link" onclick="document.getElementById('allMenuSection').scrollIntoView({behavior: 'smooth'})">
                            <span class="gooey-nav__icon"><i class="fa-solid fa-bowl-food"></i></span>
                            <span class="gooey-nav__label">Menu</span>
                        </button>
                    </li>
                    <li class="gooey-nav__item">
                        <button type="button" class="gooey-nav__link" onclick="openCartDrawer()">
                            <span class="gooey-nav__icon" style="position: relative;">
                                <i class="fa-solid fa-basket-shopping"></i>
                                <span class="cart-badge" id="cartBadgeCount" style="display: none;">0</span>
                            </span>
                            <span class="gooey-nav__label">Keranjang</span>
                        </button>
                    </li>
                    <li class="gooey-nav__item">
                        <button type="button" class="gooey-nav__link" onclick="openServiceModal()">
                            <span class="gooey-nav__icon"><i class="fa-solid fa-chair"></i></span>
                            <span class="gooey-nav__label">Layanan</span>
                        </button>
                    </li>
                    <li class="gooey-nav__item">
                        <button type="button" class="gooey-nav__link" onclick="openTrackLookupModal()">
                            <span class="gooey-nav__icon"><i class="fa-solid fa-receipt"></i></span>
                            <span class="gooey-nav__label">Pesanan</span>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- 1. Drawer: Cart Review -->
    <div class="drawer-overlay" id="cartDrawer">
        <div class="drawer-content">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title">Keranjang Pesanan</h3>
                <button type="button" class="drawer-close" onclick="closeCartDrawer()">&times;</button>
            </div>
            <div class="drawer-body" id="cartItemsContainer">
                <!-- Dynamically injected items -->
            </div>
            <div class="drawer-footer" id="cartDrawerFooter">
                <div style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-muted);">
                        <span>Subtotal Item:</span>
                        <span id="cartSubtotalText" style="font-weight: 700; color: var(--text-main);">Rp 0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 8px; color: var(--text-muted);">
                        <span>Biaya Pengantaran / Meja:</span>
                        <span id="cartDeliveryFeeText" style="font-weight: 700; color: var(--text-main);">Gratis</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: var(--primary); border-top: 1px dashed var(--border); padding-top: 8px;">
                        <span>Total Pembayaran:</span>
                        <span id="cartGrandTotalText">Rp 0</span>
                    </div>
                </div>
                <button type="button" class="btn-checkout-primary" onclick="openCheckoutModal()">
                    <span>Lanjut ke Pembayaran</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Drawer: Item Customization -->
    <div class="drawer-overlay" id="customModal">
        <div class="drawer-content" style="max-height: 90vh;">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title">Kustomisasi Santapan</h3>
                <button type="button" class="drawer-close" onclick="closeCustomizeModal()">&times;</button>
            </div>
            <div class="drawer-body">
                <div style="text-align: center; margin-bottom: 16px;">
                    <img id="custItemImage" src="" alt="" style="width: 140px; height: 140px; border-radius: 20px; object-fit: cover; box-shadow: var(--shadow-card); margin-bottom: 12px;">
                    <h3 id="custItemTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;"></h3>
                    <p id="custItemDesc" style="font-size: 0.8rem; color: var(--text-muted); max-width: 340px; margin: 0 auto;"></p>
                    <div id="custItemPrice" style="font-size: 1.2rem; font-weight: 800; color: var(--primary); margin-top: 8px;"></div>
                </div>

                <div style="margin-bottom: 16px; background: #FAF8F5; padding: 14px; border-radius: 16px; border: 1px solid var(--border);">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 8px;">Jumlah Porsi:</label>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                        <button type="button" class="qty-btn" style="width: 36px; height: 36px; font-size: 1.2rem;" onclick="adjustCustomQty(-1)">-</button>
                        <span id="custItemQty" style="font-size: 1.2rem; font-weight: 800; min-width: 30px; text-align: center;">1</span>
                        <button type="button" class="qty-btn" style="width: 36px; height: 36px; font-size: 1.2rem;" onclick="adjustCustomQty(1)">+</button>
                    </div>
                </div>

                <!-- Spicy Level Selector -->
                <div class="cust-section">
                    <label class="cust-section-label"><i class="fa-solid fa-pepper-hot" style="color: #EF4444;"></i> Tingkat Kepedasan:</label>
                    <div class="spicy-level-group">
                        <label class="spicy-chip active" onclick="selectSpicyLevel(this, 'Standar Minang')">
                            <input type="radio" name="cust_spicy" value="Standar Minang" checked hidden>
                            <span>🌶️ Standar</span>
                        </label>
                        <label class="spicy-chip" onclick="selectSpicyLevel(this, 'Pedas Bundo')">
                            <input type="radio" name="cust_spicy" value="Pedas Bundo" hidden>
                            <span>🌶️🌶️ Pedas Bundo</span>
                        </label>
                        <label class="spicy-chip" onclick="selectSpicyLevel(this, 'Tambuah Pedas')">
                            <input type="radio" name="cust_spicy" value="Tambuah Pedas" hidden>
                            <span>🌶️🌶️🌶️ Tambuah Pedas</span>
                        </label>
                    </div>
                </div>

                <!-- Gourmet Addons -->
                <div class="cust-section">
                    <label class="cust-section-label"><i class="fa-solid fa-layer-group" style="color: #D4AF37;"></i> Tambahan Nikmat (Opsional):</label>
                    <div class="cust-addons-list">
                        <label class="cust-addon-item" onclick="toggleCustomAddon(this, 3000, 'Sambalado Ijo Ekstra')">
                            <input type="checkbox" class="cust-addon-check" value="3000" data-name="Sambalado Ijo Ekstra" hidden>
                            <div class="addon-info">
                                <span class="addon-name">Sambalado Ijo Ekstra</span>
                                <span class="addon-price">+Rp 3.000</span>
                            </div>
                            <i class="fa-solid fa-circle-check addon-icon"></i>
                        </label>
                        <label class="cust-addon-item" onclick="toggleCustomAddon(this, 2000, 'Kuah Gulai & Bumbu Banjir')">
                            <input type="checkbox" class="cust-addon-check" value="2000" data-name="Kuah Gulai & Bumbu Banjir" hidden>
                            <div class="addon-info">
                                <span class="addon-name">Kuah Gulai & Bumbu Banjir</span>
                                <span class="addon-price">+Rp 2.000</span>
                            </div>
                            <i class="fa-solid fa-circle-check addon-icon"></i>
                        </label>
                        <label class="cust-addon-item" onclick="toggleCustomAddon(this, 5000, 'Kerupuk Kulit Jangek Gurih')">
                            <input type="checkbox" class="cust-addon-check" value="5000" data-name="Kerupuk Kulit Jangek Gurih" hidden>
                            <div class="addon-info">
                                <span class="addon-name">Kerupuk Kulit Jangek Gurih</span>
                                <span class="addon-price">+Rp 5.000</span>
                            </div>
                            <i class="fa-solid fa-circle-check addon-icon"></i>
                        </label>
                        <label class="cust-addon-item" onclick="toggleCustomAddon(this, 7000, 'Telur Dadar Barenda Crispy')">
                            <input type="checkbox" class="cust-addon-check" value="7000" data-name="Telur Dadar Barenda Crispy" hidden>
                            <div class="addon-info">
                                <span class="addon-name">Telur Dadar Barenda Crispy</span>
                                <span class="addon-price">+Rp 7.000</span>
                            </div>
                            <i class="fa-solid fa-circle-check addon-icon"></i>
                        </label>
                    </div>
                </div>

                <!-- Kitchen Quick Notes -->
                <div class="cust-section">
                    <label class="cust-section-label"><i class="fa-solid fa-message" style="color: #6366F1;"></i> Permintaan Khusus Dapur:</label>
                    <div class="quick-note-chips">
                        <button type="button" class="quick-note-chip" onclick="appendKitchenNote('Kuah Banjir')">Kuah Banjir</button>
                        <button type="button" class="quick-note-chip" onclick="appendKitchenNote('Kuah Dipisah')">Kuah Dipisah</button>
                        <button type="button" class="quick-note-chip" onclick="appendKitchenNote('Bumbu Rendang Banyak')">Bumbu Banyak</button>
                        <button type="button" class="quick-note-chip" onclick="appendKitchenNote('Bungkus Daun Rapi')">Bungkus Daun</button>
                        <button type="button" class="quick-note-chip" onclick="appendKitchenNote('Nasi Sedikit Saja')">Nasi Dikit</button>
                    </div>
                    <input type="text" id="custNotes" class="search-input" placeholder="Atau ketik catatan khusus Anda di sini..." style="border-radius: 12px; margin-top: 8px;">
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-checkout-primary" id="custAddBtn" onclick="confirmCustomAddToCart()">
                    <span id="custAddBtnText">Tambahkan ke Pesanan</span>
                    <span id="custAddBtnPrice" style="font-weight: 800; background: rgba(0,0,0,0.2); padding: 4px 10px; border-radius: 99px;"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- 3. Drawer: Service Selector (Dine-in vs Delivery) -->
    <div class="drawer-overlay" id="serviceModal">
        <div class="drawer-content">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title">Pilih Metode Layanan</h3>
                <button type="button" class="drawer-close" onclick="closeServiceModal()">&times;</button>
            </div>
            <div class="drawer-body">
                <div class="service-toggle-grid">
                    <div class="service-card-option selected" data-type="dine_in" onclick="selectServiceType('dine_in')">
                        <i class="fa-solid fa-chair"></i>
                        <h4>Makan di Tempat (Dine-In)</h4>
                        <p>Pesan langsung ke meja Anda</p>
                    </div>

                    <div class="service-card-option" data-type="delivery" onclick="selectServiceType('delivery')">
                        <i class="fa-solid fa-motorcycle"></i>
                        <h4>Antar ke Alamat (Delivery)</h4>
                        <p>Diantar hangat sampai rumah</p>
                    </div>
                </div>

                <div id="serviceDineInForm">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 8px;">Pilih Nomor Meja Restoran:</label>
                    <select id="modalTableSelect" class="search-input" style="border-radius: 12px; margin-bottom: 10px;">
                        <?php for ($i = 1; $i <= 30; $i++): ?>
                            <?php $tableStr = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?= $tableStr ?>" <?= $tableStr === '05' ? 'selected' : '' ?>>Meja <?= $tableStr ?> (Lantai 1)</option>
                        <?php endfor; ?>
                    </select>
                    <small style="color: var(--text-muted); display: block;">Nomor meja tertera pada stiker akrilik di atas meja Anda.</small>
                </div>

                <div id="serviceDeliveryForm" style="display: none;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 8px;">Alamat Pengantaran Lengkap:</label>
                    <textarea id="modalDeliveryAddress" class="search-input" rows="3" style="border-radius: 12px;" placeholder="Tuliskan nama jalan, nomor rumah, blok, RT/RW, dan patokan..."></textarea>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-checkout-primary" onclick="saveServiceSelection()">
                    <span>Simpan & Lanjutkan Menu</span>
                    <i class="fa-solid fa-check"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Drawer: Checkout Form Wizard -->
    <div class="drawer-overlay" id="checkoutModal">
        <div class="drawer-content" style="max-height: 92vh;">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title">Konfirmasi & Pembayaran</h3>
                <button type="button" class="drawer-close" onclick="closeCheckoutModal()">&times;</button>
            </div>
            <div class="drawer-body">
                <!-- Mode Selector -->
                <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                    <label style="flex: 1; border: 1.5px solid var(--border); padding: 10px; border-radius: 12px; text-align: center; cursor: pointer; font-size: 0.85rem; font-weight: 700;">
                        <input type="radio" name="chk_type" id="chkTypeDineIn" value="dine_in" checked onchange="toggleCheckoutType('dine_in')">
                        <span style="margin-left: 6px;">Dine-in (Meja)</span>
                    </label>
                    <label style="flex: 1; border: 1.5px solid var(--border); padding: 10px; border-radius: 12px; text-align: center; cursor: pointer; font-size: 0.85rem; font-weight: 700;">
                        <input type="radio" name="chk_type" id="chkTypeDelivery" value="delivery" onchange="toggleCheckoutType('delivery')">
                        <span style="margin-left: 6px;">Delivery (Antar)</span>
                    </label>
                </div>

                <!-- Fields -->
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Nama Lengkap Pemesan *</label>
                    <input type="text" id="checkoutName" class="search-input" placeholder="Contoh: Sutan Bagindo" style="border-radius: 12px;" required>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Nomor WhatsApp Aktif *</label>
                    <input type="tel" id="checkoutPhone" class="search-input" placeholder="Contoh: 081234567890" style="border-radius: 12px;" required>
                    <small style="color: var(--text-muted); font-size: 0.72rem;">Status pesanan dan struk digital akan dikirimkan ke WhatsApp ini.</small>
                </div>

                <div id="chkTableField" style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Nomor Meja *</label>
                    <input type="text" id="chkTableInput" class="search-input" placeholder="Contoh: 05" style="border-radius: 12px;">
                </div>

                <div id="chkAddressField" style="display: none; margin-bottom: 14px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Alamat Pengantaran *</label>
                    <textarea id="chkAddressInput" class="search-input" rows="2" placeholder="Nama Jalan, No. Rumah, Patokan..." style="border-radius: 12px;"></textarea>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Catatan Tambahan untuk Kasir & Dapur:</label>
                    <input type="text" id="chkOrderNotes" class="search-input" placeholder="Misal: Sambal ijo dipisah, kuah gulai dibanyakin" style="border-radius: 12px;">
                </div>

                <!-- Payment Method Options -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 8px;">Pilih Metode Pembayaran:</label>
                    
                    <!-- QRIS Dinamis -->
                    <label class="pay-method-opt selected" onclick="selectPayOption(this)">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="chk_payment" value="qris" checked>
                            <div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong style="color: var(--primary); font-size: 0.9rem;">QRIS Dinamis</strong>
                                    <span style="background: var(--primary); color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 7px; border-radius: 99px;">INSTAN</span>
                                </div>
                                <p style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">BCA, Mandiri, BRI, BNI, GoPay, OVO, Dana, ShopeePay</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-qrcode" style="font-size: 1.25rem; color: var(--primary);"></i>
                    </label>

                    <!-- DOKU Payment Gateway -->
                    <label class="pay-method-opt" onclick="selectPayOption(this)">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="chk_payment" value="doku">
                            <div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong style="font-size: 0.9rem;">DOKU Payment Gateway</strong>
                                    <span style="background: #E5E7EB; color: #374151; font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 99px;">PILIHAN LENGKAP</span>
                                </div>
                                <p style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">Transfer bank / Virtual Account, e-wallet, kartu debit atau kredit</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-building-columns" style="font-size: 1.15rem; color: #6B7280;"></i>
                    </label>
                    <div class="doku-channel-field" id="dokuChannelField" hidden>
                        <label for="chkDokuChannel">Pilih kanal pembayaran DOKU:</label>
                        <select id="chkDokuChannel" class="search-input">
                            <option value="bank_transfer">Transfer bank / Virtual Account</option>
                            <option value="ewallet">E-wallet (GoPay, OVO, DANA, ShopeePay)</option>
                            <option value="debit">Kartu debit / kredit</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="drawer-footer">
                <button type="button" class="btn-checkout-primary" id="btnSubmitOrder" onclick="submitOrder()">
                    <span>Lanjutkan Pembayaran (<span id="checkoutTotalBadge">Rp 0</span>)</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- 5. Drawer: Dynamic QRIS & Payment Screen -->
    <div class="drawer-overlay" id="paymentModal">
        <div class="drawer-content modal-dialog-centered">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title" id="paymentModalTitle">Pembayaran Pesanan Harapan Bundo</h3>
                <button type="button" class="drawer-close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="drawer-body">
                <div class="qris-card" id="paymentPresentation">
                    <!-- Standard QRIS Header Image / Branding -->
                    <div id="qrisBranding" style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px;">
                        <span style="font-weight: 800; font-size: 1.4rem; color: #DC2626; letter-spacing: 1px;">QRIS</span>
                        <span style="font-size: 0.75rem; color: #4B5563; font-weight: 700; border-left: 1px solid #CCC; padding-left: 8px;">PEMBAYARAN DIGITAL ASPI</span>
                    </div>

                    <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">RM PADANG HARAPAN BUNDO</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;">NMID: ID1020039281720 &bull; Order: <span id="payOrderCode" style="font-weight: 700; color: var(--primary);"></span></div>

                    <div id="dokuPaymentInfo" class="doku-payment-info" hidden>
                        <i class="fa-solid fa-lock"></i>
                        <strong>Pembayaran melalui DOKU</strong>
                        <span id="dokuPaymentChannelText">Kanal pembayaran pilihan Anda.</span>
                        <small>Mode demo: transaksi ini belum terhubung ke checkout DOKU live.</small>
                    </div>

                    <div class="qris-timer" id="qrisTimerWrap">
                        <i class="fa-solid fa-clock"></i>
                        <span>Selesaikan dalam: </span>
                        <strong id="qrisTimer">15:00</strong>
                    </div>

                    <div class="qris-code-container" id="qrisCodeWrap">
                        <img id="qrisImg" src="" alt="QRIS Code" style="width: 210px; height: 210px; display: block; margin: 0 auto;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <small style="color: var(--text-muted); display: block; margin-bottom: 2px;">Total Tagihan Pas:</small>
                        <div id="payAmount" style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Rp 0</div>
                    </div>

                    <div id="qrisInstructions" style="background: #FAF8F5; border: 1px solid var(--border); border-radius: 12px; padding: 12px; margin-bottom: 16px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.5; text-align: left;">
                        <strong style="color: var(--text-main); display: block; margin-bottom: 4px;"><i class="fa-solid fa-mobile-screen"></i> Cara Bayar:</strong>
                        1. Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI) atau e-Wallet (GoPay, OVO, Dana).<br>
                        2. Pilih menu <strong>Scan / Bayar QRIS</strong>.<br>
                        3. Arahkan kamera ke kode QR di atas.<br>
                        4. Halaman akan otomatis terupdate setelah pembayaran diverifikasi!
                    </div>

                    <!-- Sandbox Instant Test Button -->
                    <button type="button" class="btn" id="btnSimulatePay" onclick="simulatePaymentSuccess()" style="width: 100%; background: #10B981; color: white; padding: 12px; border-radius: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Simulasikan Pembayaran Berhasil (Uji Coba Demo)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Modal: Customer Order Tracking Lookup -->
    <div class="drawer-overlay" id="trackLookupModal">
        <div class="drawer-content">
            <div class="drawer-handle"></div>
            <div class="drawer-header">
                <h3 class="drawer-title">Lacak Status Pesanan Anda</h3>
                <button type="button" class="drawer-close" onclick="closeTrackLookupModal()">&times;</button>
            </div>
            <form onsubmit="submitTrackLookup(event)">
                <div class="drawer-body">
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                        Masukkan kode pesanan Anda (contoh: <code>HB-20261025-XXXX</code>) untuk memantau progres masakan dan status pengantaran langsung dari dapur Bundo.
                    </p>

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Kode Pesanan / Invoice:</label>
                        <input type="text" id="lookupOrderCode" class="search-input" placeholder="HB-20261025-..." style="border-radius: 12px; font-weight: 700; text-transform: uppercase;" required autofocus>
                    </div>
                </div>
                <div class="drawer-footer">
                    <button type="submit" class="btn-checkout-primary">
                        <span>Cek Status Sekarang</span>
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Active Order Bar (Shown if customer has an active order) -->
    <div id="floatingOrderBanner" class="floating-order-banner" style="display: none;">
        <div class="fob-content">
            <span class="fob-pulse"></span>
            <div class="fob-text">
                <span class="fob-title">Pesanan Terakhir Sedang Diproses:</span>
                <span class="fob-code" id="fobOrderCode">HB-...</span>
            </div>
        </div>
        <button type="button" class="fob-btn" onclick="openLastOrderTrack()">
            <span>Lacak</span>
            <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>

    <!-- Scripts (GSAP Animation Engine, Gradual Blur & 3D Carousel) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="assets/js/AnimatedContent.js"></script>
    <script src="assets/js/GradualBlur.js"></script>
    <script src="assets/js/Carousel.js"></script>
    <script src="assets/js/GooeyNav.js"></script>
    <script src="assets/js/TiltedCard.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
