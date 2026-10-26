/**
 * RM Padang Harapan Bundo - Customer App Interactive Logic
 * Cart Management, Live Filter, Checkout Wizard & QRIS Polling
 */

const AppState = {
    cart: JSON.parse(localStorage.getItem('hb_cart') || '[]'),
    serviceType: localStorage.getItem('hb_service_type') || 'dine_in',
    tableNumber: localStorage.getItem('hb_table_number') || '05',
    deliveryAddress: localStorage.getItem('hb_delivery_address') || '',
    customerName: localStorage.getItem('hb_customer_name') || '',
    customerPhone: localStorage.getItem('hb_customer_phone') || '',
    activeCategory: 'all',
    searchQuery: '',
    deliveryFee: 10000,
    currentOrderCode: null,
    paymentPollInterval: null
};

// Document Ready
document.addEventListener('DOMContentLoaded', () => {
    updateCartUI();
    renderServiceTypeUI();
    setupEventListeners();
});

// Sound effect for adding to cart
function playAddSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(440, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
    } catch (e) {}
}

// Event Listeners
function setupEventListeners() {
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            AppState.searchQuery = e.target.value.toLowerCase().trim();
            filterMenus();
        });
    }

    // Category pills
    document.querySelectorAll('.cat-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            AppState.activeCategory = pill.dataset.category || 'all';
            filterMenus();
        });
    });

    // Secret Admin Access: Logo Click 5x within 3 seconds
    let logoClicks = 0;
    let logoTimer = null;
    const logoBadge = document.getElementById('brandLogoWrapper');
    if (logoBadge) {
        logoBadge.addEventListener('click', () => {
            logoClicks++;
            if (logoClicks === 1) {
                logoTimer = setTimeout(() => { logoClicks = 0; }, 3000);
            }
            if (logoClicks >= 5) {
                clearTimeout(logoTimer);
                logoClicks = 0;
                showToast('Membuka Panel Manajemen Resto...');
                setTimeout(() => {
                    window.location.href = 'admin/login.php';
                }, 500);
            }
        });
    }

    // Secret Admin Access: Keyboard Shortcut (Ctrl + Shift + A or Cmd + Shift + A)
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'A' || e.key === 'a')) {
            e.preventDefault();
            showToast('Membuka Panel Manajemen Resto...');
            setTimeout(() => {
                window.location.href = 'admin/login.php';
            }, 300);
        }
    });
}

// Filter Menus on Page
function filterMenus() {
    const cards = document.querySelectorAll('.food-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const cat = card.dataset.categorySlug || '';
        const title = (card.dataset.title || '').toLowerCase();
        const desc = (card.dataset.desc || '').toLowerCase();

        const matchesCat = (AppState.activeCategory === 'all' || cat === AppState.activeCategory);
        const matchesSearch = (!AppState.searchQuery || title.includes(AppState.searchQuery) || desc.includes(AppState.searchQuery));

        if (matchesCat && matchesSearch) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noResult = document.getElementById('noResultsMessage');
    if (noResult) {
        noResult.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    if (typeof ScrollTrigger !== 'undefined') {
        ScrollTrigger.refresh();
    }
}

// Cart Logic
function addToCart(item, qty = 1, notes = '') {
    playAddSound();
    const existingIndex = AppState.cart.findIndex(i => i.id === item.id && i.notes === notes);
    
    if (existingIndex > -1) {
        AppState.cart[existingIndex].qty += qty;
    } else {
        AppState.cart.push({
            id: item.id,
            name: item.name,
            price: item.price,
            promo_price: item.promo_price,
            final_price: item.promo_price ? item.promo_price : item.price,
            image_url: item.image_url,
            qty: qty,
            notes: notes
        });
    }

    saveCart();
    updateCartUI();
    showToast(`Ditambahkan: ${item.name}`);
}

function updateItemQty(index, delta) {
    if (!AppState.cart[index]) return;
    AppState.cart[index].qty += delta;
    if (AppState.cart[index].qty <= 0) {
        AppState.cart.splice(index, 1);
    }
    saveCart();
    updateCartUI();
    renderCartDrawer();
}

function saveCart() {
    localStorage.setItem('hb_cart', JSON.stringify(AppState.cart));
}

function calculateCartTotals() {
    let subtotal = 0;
    let itemCount = 0;

    AppState.cart.forEach(item => {
        subtotal += (item.final_price * item.qty);
        itemCount += item.qty;
    });

    const deliveryFee = AppState.serviceType === 'delivery' ? AppState.deliveryFee : 0;
    const grandTotal = subtotal + deliveryFee;

    return {
        subtotal,
        deliveryFee,
        grandTotal,
        itemCount
    };
}

function formatRupiah(amount) {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

function updateCartUI() {
    const totals = calculateCartTotals();
    
    // Mobile bottom nav badge
    const badge = document.getElementById('cartBadgeCount');
    if (badge) {
        badge.innerText = totals.itemCount;
        badge.style.display = totals.itemCount > 0 ? 'flex' : 'none';
    }

    // Desktop header badge and total
    const deskBadge = document.getElementById('desktopCartBadge');
    if (deskBadge) {
        deskBadge.innerText = totals.itemCount;
    }
    const deskTotal = document.getElementById('desktopCartTotal');
    if (deskTotal) {
        deskTotal.innerText = formatRupiah(totals.subtotal);
    }

    // Desktop floating cart badge
    const floatBadge = document.getElementById('floatCartBadge');
    if (floatBadge) {
        floatBadge.innerText = totals.itemCount;
    }
}

// Order Lookup Modal Functions (For Customers)
function openTrackLookupModal() {
    const modal = document.getElementById('trackLookupModal');
    if (modal) modal.classList.add('active');
}

function closeTrackLookupModal() {
    const modal = document.getElementById('trackLookupModal');
    if (modal) modal.classList.remove('active');
}

function submitTrackLookup(e) {
    e.preventDefault();
    const code = document.getElementById('lookupOrderCode').value.trim();
    if (!code) {
        alert('Silakan masukkan nomor / kode pesanan Anda.');
        return;
    }
    window.location.href = `track.php?code=${encodeURIComponent(code)}`;
}

// Service Selector (Dine-in vs Delivery)
function openServiceModal() {
    const modal = document.getElementById('serviceModal');
    if (modal) modal.classList.add('active');
}

function closeServiceModal() {
    const modal = document.getElementById('serviceModal');
    if (modal) modal.classList.remove('active');
}

function selectServiceType(type) {
    AppState.serviceType = type;
    localStorage.setItem('hb_service_type', type);
    
    document.querySelectorAll('.service-card-option').forEach(card => {
        card.classList.toggle('selected', card.dataset.type === type);
    });

    document.getElementById('serviceDineInForm').style.display = type === 'dine_in' ? 'block' : 'none';
    document.getElementById('serviceDeliveryForm').style.display = type === 'delivery' ? 'block' : 'none';
}

function saveServiceSelection() {
    if (AppState.serviceType === 'dine_in') {
        const table = document.getElementById('modalTableSelect').value;
        AppState.tableNumber = table;
        localStorage.setItem('hb_table_number', table);
    } else {
        const addr = document.getElementById('modalDeliveryAddress').value.trim();
        if (!addr) {
            alert('Silakan tuliskan alamat pengantaran Anda.');
            return;
        }
        AppState.deliveryAddress = addr;
        localStorage.setItem('hb_delivery_address', addr);
    }

    renderServiceTypeUI();
    closeServiceModal();
    updateCartUI();
}

function renderServiceTypeUI() {
    const label = document.getElementById('headerServiceLabel');
    const val = document.getElementById('headerServiceVal');
    const icon = document.getElementById('headerServiceIcon');

    if (AppState.serviceType === 'dine_in') {
        if (label) label.innerText = 'DINE-IN';
        if (val) val.innerText = `Makan di Meja ${AppState.tableNumber}`;
        if (icon) icon.className = 'fa-solid fa-chair service-icon';
    } else {
        if (label) label.innerText = 'DELIVERY';
        if (val) val.innerText = AppState.deliveryAddress ? AppState.deliveryAddress : 'Pilih Alamat Antar';
        if (icon) icon.className = 'fa-solid fa-motorcycle service-icon';
    }
}

// Item Customization Modal
let activeCustomizingItem = null;
function openCustomizeModal(item) {
    activeCustomizingItem = item;
    const modal = document.getElementById('customModal');
    document.getElementById('custItemImage').src = item.image_url;
    document.getElementById('custItemTitle').innerText = item.name;
    document.getElementById('custItemDesc').innerText = item.description || '';
    document.getElementById('custItemPrice').innerText = formatRupiah(item.promo_price || item.price);
    document.getElementById('custItemQty').innerText = '1';
    document.getElementById('custNotes').value = '';
    modal.classList.add('active');
}

function closeCustomizeModal() {
    document.getElementById('customModal').classList.remove('active');
    activeCustomizingItem = null;
}

function adjustCustomQty(delta) {
    const el = document.getElementById('custItemQty');
    let curr = parseInt(el.innerText) || 1;
    curr += delta;
    if (curr < 1) curr = 1;
    el.innerText = curr;
}

function confirmCustomAddToCart() {
    if (!activeCustomizingItem) return;
    const qty = parseInt(document.getElementById('custItemQty').innerText) || 1;
    const note = document.getElementById('custNotes').value.trim();
    addToCart(activeCustomizingItem, qty, note);
    closeCustomizeModal();
}

// Cart Drawer
function openCartDrawer() {
    renderCartDrawer();
    document.getElementById('cartDrawer').classList.add('active');
}

function closeCartDrawer() {
    document.getElementById('cartDrawer').classList.remove('active');
}

function renderCartDrawer() {
    const container = document.getElementById('cartItemsContainer');
    const footer = document.getElementById('cartDrawerFooter');
    
    if (AppState.cart.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-basket-shopping" style="font-size: 3rem; color: #E5E7EB; margin-bottom: 14px; display: block;"></i>
                <h4 style="color: var(--text-main); margin-bottom: 6px;">Keranjang Masih Kosong</h4>
                <p style="font-size: 0.8rem;">Pilih hidangan otentik Padang Harapan Bundo untuk mulai memesan!</p>
            </div>
        `;
        footer.style.display = 'none';
        return;
    }

    footer.style.display = 'block';
    let html = '';

    AppState.cart.forEach((item, idx) => {
        html += `
            <div class="cart-row">
                <img src="${item.image_url}" alt="${item.name}" class="cart-row-thumb" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=150&q=80'">
                <div class="cart-row-info">
                    <div class="cart-row-name">${item.name}</div>
                    <div class="cart-row-price">${formatRupiah(item.final_price)}</div>
                    ${item.notes ? `<div class="cart-row-note"><i class="fa-solid fa-pen"></i> ${item.notes}</div>` : ''}
                </div>
                <div class="cart-qty-ctrl">
                    <button type="button" class="qty-btn" onclick="updateItemQty(${idx}, -1)">-</button>
                    <span class="qty-count">${item.qty}</span>
                    <button type="button" class="qty-btn" onclick="updateItemQty(${idx}, 1)">+</button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    const totals = calculateCartTotals();
    document.getElementById('cartSubtotalText').innerText = formatRupiah(totals.subtotal);
    document.getElementById('cartDeliveryFeeText').innerText = totals.deliveryFee > 0 ? formatRupiah(totals.deliveryFee) : 'Gratis / Dine-in';
    document.getElementById('cartGrandTotalText').innerText = formatRupiah(totals.grandTotal);
}

// Checkout Wizard
function openCheckoutModal() {
    if (AppState.cart.length === 0) {
        alert('Keranjang pesanan masih kosong.');
        return;
    }

    closeCartDrawer();
    const modal = document.getElementById('checkoutModal');
    
    // Pre-populate customer info
    document.getElementById('checkoutName').value = AppState.customerName;
    document.getElementById('checkoutPhone').value = AppState.customerPhone;
    
    // Set service mode
    if (AppState.serviceType === 'dine_in') {
        document.getElementById('chkTypeDineIn').checked = true;
        document.getElementById('chkTableField').style.display = 'block';
        document.getElementById('chkAddressField').style.display = 'none';
        document.getElementById('chkTableInput').value = AppState.tableNumber;
    } else {
        document.getElementById('chkTypeDelivery').checked = true;
        document.getElementById('chkTableField').style.display = 'none';
        document.getElementById('chkAddressField').style.display = 'block';
        document.getElementById('chkAddressInput').value = AppState.deliveryAddress;
    }

    const totals = calculateCartTotals();
    document.getElementById('checkoutTotalBadge').innerText = formatRupiah(totals.grandTotal);
    modal.classList.add('active');
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').classList.remove('active');
}

function toggleCheckoutType(type) {
    AppState.serviceType = type;
    document.getElementById('chkTableField').style.display = type === 'dine_in' ? 'block' : 'none';
    document.getElementById('chkAddressField').style.display = type === 'delivery' ? 'block' : 'none';
    
    const totals = calculateCartTotals();
    document.getElementById('checkoutTotalBadge').innerText = formatRupiah(totals.grandTotal);
}

// Process Order Submission
function submitOrder() {
    const name = document.getElementById('checkoutName').value.trim();
    const phone = document.getElementById('checkoutPhone').value.trim();
    const type = document.querySelector('input[name="chk_type"]:checked').value;
    const table = document.getElementById('chkTableInput').value.trim();
    const address = document.getElementById('chkAddressInput').value.trim();
    const paymentMethod = document.querySelector('input[name="chk_payment"]:checked').value;
    const customerNotes = document.getElementById('chkOrderNotes').value.trim();

    if (!name) {
        alert('Mohon masukkan nama pemesan.');
        return;
    }
    if (!phone || phone.length < 8) {
        alert('Mohon masukkan nomor WhatsApp aktif yang valid.');
        return;
    }
    if (type === 'dine_in' && !table) {
        alert('Mohon masukkan atau pilih nomor meja Anda.');
        return;
    }
    if (type === 'delivery' && !address) {
        alert('Mohon isi alamat lengkap pengantaran.');
        return;
    }

    // Save info
    AppState.customerName = name;
    AppState.customerPhone = phone;
    localStorage.setItem('hb_customer_name', name);
    localStorage.setItem('hb_customer_phone', phone);

    const btn = document.getElementById('btnSubmitOrder');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses Pesanan...';

    const orderPayload = {
        customer_name: name,
        customer_phone: phone,
        order_type: type,
        table_number: type === 'dine_in' ? table : null,
        delivery_address: type === 'delivery' ? address : null,
        payment_method: paymentMethod,
        notes: customerNotes,
        items: AppState.cart.map(i => ({
            menu_id: i.id,
            quantity: i.qty,
            notes: i.notes
        }))
    };

    fetch('api/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderPayload)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<span>Lanjutkan Pembayaran</span> <i class="fa-solid fa-arrow-right"></i>';

        if (!res.success) {
            alert('Gagal membuat pesanan: ' + res.message);
            return;
        }

        const data = res.data;
        AppState.currentOrderCode = data.order_code;

        // Clear cart
        AppState.cart = [];
        saveCart();
        updateCartUI();
        closeCheckoutModal();

        // Show QRIS / Payment Presentation Modal
        showPaymentModal(data);
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span>Lanjutkan Pembayaran</span> <i class="fa-solid fa-arrow-right"></i>';
        alert('Terjadi kendala jaringan saat memproses pesanan.');
    });
}

// QRIS & Payment Modal
function showPaymentModal(orderData) {
    const modal = document.getElementById('paymentModal');
    document.getElementById('payOrderCode').innerText = orderData.order_code;
    document.getElementById('payAmount').innerText = orderData.formatted_total;
    
    // Generate QR Code URL using QR Server API
    const qrEncoded = encodeURIComponent(orderData.qris_payload);
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${qrEncoded}&margin=10`;
    document.getElementById('qrisImg').src = qrUrl;

    // Start 15-minute countdown
    startTimer(15 * 60, document.getElementById('qrisTimer'));

    modal.classList.add('active');

    // Start polling payment status every 3 seconds
    if (AppState.paymentPollInterval) clearInterval(AppState.paymentPollInterval);
    AppState.paymentPollInterval = setInterval(() => {
        fetch(`api/payment.php?action=status&order_code=${orderData.order_code}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data.is_paid) {
                    clearInterval(AppState.paymentPollInterval);
                    window.location.href = `track.php?code=${orderData.order_code}`;
                }
            });
    }, 3000);
}

function simulatePaymentSuccess() {
    if (!AppState.currentOrderCode) return;
    const btn = document.getElementById('btnSimulatePay');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengonfirmasi Pembayaran...';

    fetch(`api/payment.php?action=simulate_pay&order_code=${AppState.currentOrderCode}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (AppState.paymentPollInterval) clearInterval(AppState.paymentPollInterval);
                window.location.href = `track.php?code=${AppState.currentOrderCode}`;
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Simulasikan Pembayaran Berhasil (Demo)';
            }
        });
}

function startTimer(duration, display) {
    let timer = duration, minutes, seconds;
    const interval = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            clearInterval(interval);
            display.textContent = "KADALUARSA";
        }
    }, 1000);
}

// Toast Notification
function showToast(text) {
    const existing = document.querySelector('.app-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'app-toast';
    toast.innerText = text;
    toast.style.cssText = `
        position: fixed;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(31, 26, 23, 0.92);
        color: white;
        padding: 10px 18px;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 700;
        z-index: 9999;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        animation: toastIn 0.25s ease-out;
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2200);
}
