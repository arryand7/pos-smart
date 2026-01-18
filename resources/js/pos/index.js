import { clearAuthToken, getAuthToken, withAuthToken } from '../services/authToken';
import { enqueueTransaction, getQueue, flushQueue, removeTransaction } from '../services/offlineQueue';

const USER_STORAGE_KEY = 'smart.auth.user';
const LOCATION_CACHE_KEY = 'smart.pos.cache.locations';
const CATEGORY_CACHE_KEY = 'smart.pos.cache.categories';

function loadStoredUser() {
    try {
        const raw = window.localStorage.getItem(USER_STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (error) {
        console.warn('SMART POS: gagal membaca pengguna', error);
        return {};
    }
}

function persistUser(user) {
    try {
        window.localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user));
    } catch (error) {
        console.warn('SMART POS: gagal menyimpan pengguna', error);
    }
}

function loadCache(key, fallback = []) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        return fallback;
    }
}

function persistCache(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        console.warn('SMART POS: gagal menyimpan cache', error);
    }
}

function productsCacheKey(locationId) {
    return `smart.pos.cache.products.${locationId || 'all'}`;
}

function currency(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(Number(amount || 0));
}

function formatTime(value) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function debounce(callback, delay = 350) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = window.setTimeout(() => callback(...args), delay);
    };
}

window.addEventListener('auth:unauthorized', () => {
    try {
        window.localStorage.removeItem(USER_STORAGE_KEY);
    } catch (error) {
        console.warn('SMART POS: gagal membersihkan pengguna saat unauthorized', error);
    }
    clearAuthToken();
    window.location.href = '/login';
});

window.addEventListener('auth:logout', () => {
    try {
        window.localStorage.removeItem(USER_STORAGE_KEY);
    } catch (error) {
        console.warn('SMART POS: gagal membersihkan pengguna saat logout', error);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const bootstrap = window.__SMART_BOOTSTRAP__ || {};

    if (bootstrap.token) {
        withAuthToken(bootstrap.token);
    }

    if (bootstrap.user) {
        persistUser(bootstrap.user);
    }

    let token = getAuthToken();

    if (! token && bootstrap.token) {
        token = bootstrap.token;
    }

    if (! token) {
        window.location.href = '/login';
        return;
    }

    const root = document.getElementById('pos-app');

    if (! root) {
        throw new Error('Elemen POS tidak ditemukan.');
    }

    const elements = {
        userName: document.getElementById('user-name'),
        logoutButton: document.getElementById('logout-btn'),
        statusBadge: document.getElementById('status-badge'),
        syncButton: document.getElementById('sync-button'),
        offlineCount: document.getElementById('offline-count'),
        locationSelect: document.getElementById('input-location'),
        productSearch: document.getElementById('product-search'),
        categoryList: document.getElementById('category-list'),
        productGrid: document.getElementById('product-grid'),
        productEmpty: document.getElementById('product-empty'),
        cartBody: document.getElementById('cart-body'),
        cartCount: document.getElementById('cart-count'),
        subtotalText: document.getElementById('subtotal-text'),
        totalPayableText: document.getElementById('total-text'),
        changeText: document.getElementById('change-text'),
        payCash: document.getElementById('pay-cash'),
        payWallet: document.getElementById('pay-wallet'),
        payGateway: document.getElementById('pay-gateway'),
        payExactButton: document.getElementById('pay-exact-btn'),
        submitButton: document.getElementById('submit-btn'),
        statusMessage: document.getElementById('status-message'),
        queueList: document.getElementById('offline-queue'),
        santriSearch: document.getElementById('input-santri'),
        santriResults: document.getElementById('santri-results'),
        santriName: document.getElementById('santri-name'),
        santriNis: document.getElementById('santri-nis'),
        santriBalance: document.getElementById('santri-balance'),
        clearSantriButton: document.getElementById('clear-santri-btn'),
        printButton: document.getElementById('print-receipt-btn'),
        receiptSummary: document.getElementById('receipt-summary'),
        scanProductButton: document.getElementById('scan-product-btn'),
        scanSantriButton: document.getElementById('scan-santri-btn'),
        scanModal: document.getElementById('scan-modal'),
        scanCloseButton: document.getElementById('scan-close-btn'),
        scanVideo: document.getElementById('scan-video'),
        scanTitle: document.getElementById('scan-title'),
        scanHint: document.getElementById('scan-hint'),
    };

    const currentUser = Object.keys(bootstrap.user || {}).length ? bootstrap.user : loadStoredUser();

    if (elements.userName) {
        elements.userName.textContent = currentUser?.name || 'Kasir';
    }

    const state = {
        locationId: root.dataset.locationId || '',
        santriId: root.dataset.santriId || '',
        santri: null,
        santriResults: [],
        cart: [],
        products: [],
        categories: [],
        locations: [],
        selectedCategory: 'all',
        searchQuery: '',
        payments: {
            cash: 0,
            wallet: 0,
            gateway: 0,
        },
        isSubmitting: false,
        isSyncing: false,
        offlineQueue: getQueue(),
        lastReceipt: null,
        scanStream: null,
        scanMode: 'product',
        scanActive: false,
    };

    function setStatus(type, text) {
        if (! elements.statusMessage) {
            return;
        }

        elements.statusMessage.className = `alert alert-${type}`;
        elements.statusMessage.textContent = text;
        elements.statusMessage.hidden = false;

        window.setTimeout(() => {
            if (elements.statusMessage.textContent === text) {
                elements.statusMessage.hidden = true;
            }
        }, 5000);
    }

    function totals() {
        const subTotal = state.cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
        const paymentTotal = Number(state.payments.cash || 0)
            + Number(state.payments.wallet || 0)
            + Number(state.payments.gateway || 0);
        const change = Math.max(0, paymentTotal - subTotal);

        return { subTotal, paymentTotal, change };
    }

    function renderLocations() {
        if (! elements.locationSelect) {
            return;
        }

        elements.locationSelect.innerHTML = '<option value="">Pilih lokasi</option>';

        state.locations.forEach((location) => {
            const option = document.createElement('option');
            option.value = location.id;
            option.textContent = `${location.name} (${location.code})`;
            elements.locationSelect.appendChild(option);
        });

        if (state.locationId) {
            elements.locationSelect.value = state.locationId;
        }
    }

    function renderCategories() {
        if (! elements.categoryList) {
            return;
        }

        elements.categoryList.innerHTML = '';

        const categories = [{ id: 'all', name: 'Semua' }, ...state.categories];

        categories.forEach((category) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = `category-pill ${state.selectedCategory === String(category.id) ? 'active' : ''}`;
            pill.dataset.categoryId = category.id;
            pill.textContent = category.name;
            elements.categoryList.appendChild(pill);
        });
    }

    function filteredProducts() {
        let products = [...state.products];

        if (state.selectedCategory !== 'all') {
            products = products.filter((product) => String(product.category_id) === String(state.selectedCategory));
        }

        if (state.searchQuery) {
            const query = state.searchQuery.toLowerCase();
            products = products.filter((product) => {
                return [product.name, product.barcode, product.sku]
                    .filter(Boolean)
                    .some((value) => value.toLowerCase().includes(query));
            });
        }

        return products;
    }

    function renderProducts() {
        if (! elements.productGrid) {
            return;
        }

        const products = filteredProducts();
        elements.productGrid.innerHTML = '';
        elements.productEmpty.hidden = products.length > 0;

        products.forEach((product) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'product-card';
            card.dataset.id = product.id;
            const thumb = product.photo_url
                ? `<img src="${product.photo_url}" alt="${product.name}" loading="lazy">`
                : `<span>Tanpa Foto</span>`;
            card.innerHTML = `
                <div class="product-thumb">${thumb}</div>
                <div class="product-title">${product.name}</div>
                <div class="product-meta">
                    <strong>${currency(product.sale_price)}</strong>
                    <span class="product-stock ${Number(product.stock) <= Number(product.stock_alert || 0) ? 'low' : ''}">Stok ${product.stock}</span>
                </div>
            `;
            elements.productGrid.appendChild(card);
        });
    }

    function renderCart() {
        if (! elements.cartBody) {
            return;
        }

        elements.cartBody.innerHTML = '';

        if (state.cart.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'hint';
            empty.textContent = 'Keranjang masih kosong.';
            elements.cartBody.appendChild(empty);
        } else {
            state.cart.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'cart-item';
                row.innerHTML = `
                    <div class="cart-item-info">
                        <strong>${item.product_name}</strong>
                        <div><small>${currency(item.unit_price)}</small></div>
                    </div>
                    <div class="qty-controls">
                        <button type="button" class="qty-btn" data-action="minus" data-index="${index}">-</button>
                        <input type="number" class="qty-input" min="1" value="${item.quantity}" data-index="${index}">
                        <button type="button" class="qty-btn" data-action="plus" data-index="${index}">+</button>
                    </div>
                    <div class="cart-item-meta">
                        <span class="cart-item-total">${currency(item.unit_price * item.quantity)}</span>
                        <button type="button" class="cart-remove" data-action="remove" data-index="${index}">Hapus</button>
                    </div>
                `;
                elements.cartBody.appendChild(row);
            });
        }

        const { subTotal, paymentTotal, change } = totals();

        elements.subtotalText.textContent = currency(subTotal);
        elements.totalPayableText.textContent = currency(paymentTotal);
        elements.changeText.textContent = currency(change);
        elements.cartCount.textContent = state.cart.reduce((sum, item) => sum + item.quantity, 0);
    }

    function renderOfflineQueue() {
        elements.queueList.innerHTML = '';

        if (state.offlineQueue.length === 0) {
            elements.queueList.innerHTML = '<li class="hint">Tidak ada transaksi offline.</li>';
            return;
        }

        state.offlineQueue.forEach((item) => {
            const li = document.createElement('li');
            li.innerHTML = `
                <div>
                    <strong>${item.reference}</strong>
                    <p class="hint">Total ${currency(sumOfflineItem(item.items))} - ${formatTime(item.enqueued_at)}</p>
                </div>
                <button type="button" class="cart-remove" data-reference="${item.reference}">Hapus</button>
            `;
            elements.queueList.appendChild(li);
        });
    }

    function updateOfflineBadge() {
        const online = navigator.onLine;
        elements.statusBadge.textContent = online ? 'Online' : 'Offline';
        elements.statusBadge.className = `badge ${online ? 'badge-online' : 'badge-offline'}`;
        elements.offlineCount.textContent = state.offlineQueue.length;
        elements.syncButton.disabled = state.isSyncing || state.offlineQueue.length === 0;
    }

    function sumOfflineItem(items = []) {
        return (items || []).reduce((sum, item) => sum + (item.unit_price || 0) * (item.quantity || 0), 0);
    }

    function addToCart(product) {
        const existing = state.cart.find((item) => item.product_id === product.id);

        if (existing) {
            existing.quantity += 1;
        } else {
            state.cart.push({
                product_id: product.id,
                product_name: product.name,
                quantity: 1,
                unit_price: Number(product.sale_price || 0),
                barcode: product.barcode,
                sku: product.sku,
            });
        }

        renderCart();
    }

    function handleProductClick(event) {
        const target = event.target.closest('button[data-id]');
        if (! target) {
            return;
        }

        const productId = Number(target.dataset.id);
        const product = state.products.find((item) => item.id === productId);

        if (product) {
            addToCart(product);
        }
    }

    function handleCategoryClick(event) {
        const target = event.target.closest('button[data-category-id]');
        if (! target) {
            return;
        }

        state.selectedCategory = String(target.dataset.categoryId);
        renderCategories();
        renderProducts();
    }

    function handleCartAction(event) {
        const target = event.target.closest('button[data-action]');
        if (! target) {
            return;
        }

        const index = Number(target.dataset.index);
        if (Number.isNaN(index)) {
            return;
        }

        const item = state.cart[index];
        if (! item) {
            return;
        }

        if (target.dataset.action === 'plus') {
            item.quantity += 1;
        } else if (target.dataset.action === 'minus') {
            item.quantity = Math.max(1, item.quantity - 1);
        } else if (target.dataset.action === 'remove') {
            state.cart.splice(index, 1);
        }

        renderCart();
    }

    function handleCartQuantity(event) {
        const input = event.target.closest('input[data-index]');
        if (! input) {
            return;
        }

        const index = Number(input.dataset.index);
        const value = Number(input.value || 0);
        if (Number.isNaN(index) || ! state.cart[index]) {
            return;
        }

        state.cart[index].quantity = Math.max(1, value || 1);
        renderCart();
    }

    function handlePaymentsChange() {
        state.payments.cash = Number(elements.payCash.value || 0);
        state.payments.wallet = Number(elements.payWallet.value || 0);
        state.payments.gateway = Number(elements.payGateway.value || 0);
        const { paymentTotal, change } = totals();
        elements.totalPayableText.textContent = currency(paymentTotal);
        elements.changeText.textContent = currency(change);
    }

    function setPayExact() {
        const { subTotal } = totals();
        elements.payCash.value = subTotal;
        state.payments.cash = subTotal;
        handlePaymentsChange();
    }

    function buildPayload() {
        const reference = `POS-${Date.now()}`;
        const items = state.cart.map((item) => ({
            product_id: item.product_id,
            product_name: item.product_name,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount_amount: 0,
        }));

        return {
            reference,
            location_id: state.locationId || null,
            santri_id: state.santriId || null,
            payments: {
                cash: Number(elements.payCash.value || 0),
                wallet: Number(elements.payWallet.value || 0),
                gateway: Number(elements.payGateway.value || 0),
            },
            items,
        };
    }

    function resetForm() {
        state.cart = [];
        elements.payCash.value = 0;
        elements.payWallet.value = 0;
        elements.payGateway.value = 0;
        state.payments.cash = 0;
        state.payments.wallet = 0;
        state.payments.gateway = 0;
        clearSantri();
        renderCart();
    }

    function updateReceiptSummary(receipt) {
        if (! elements.receiptSummary) {
            return;
        }

        if (! receipt) {
            elements.receiptSummary.textContent = 'Belum ada transaksi.';
            elements.printButton.disabled = true;
            return;
        }

        elements.receiptSummary.textContent = `${receipt.reference} - ${currency(receipt.total)}`;
        elements.printButton.disabled = false;
    }

    function buildReceipt(payload, transaction, offline) {
        const { subTotal, paymentTotal, change } = totals();
        const location = state.locations.find((item) => String(item.id) === String(state.locationId));
        const now = new Date();

        return {
            reference: transaction?.reference || payload.reference,
            items: payload.items,
            subtotal: transaction?.sub_total ?? subTotal,
            total: transaction?.total_amount ?? subTotal,
            payments: payload.payments,
            change: transaction?.change_amount ?? change,
            location: location?.name || '- -',
            kasir: currentUser?.name || 'Kasir',
            santri: state.santri,
            timestamp: transaction?.processed_at || now.toISOString(),
            offline,
        };
    }

    function printReceipt() {
        if (! state.lastReceipt) {
            return;
        }

        const receipt = state.lastReceipt;
        const rows = receipt.items.map((item) => {
            return `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${currency(item.unit_price)}</td>
                    <td style="text-align:right;">${currency(item.unit_price * item.quantity)}</td>
                </tr>
            `;
        }).join('');

        const html = `
            <html>
            <head>
                <title>Struk ${receipt.reference}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 16px; }
                    h1 { font-size: 18px; margin: 0 0 8px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
                    th, td { padding: 4px 0; font-size: 12px; }
                    th { text-align: left; border-bottom: 1px solid #ddd; }
                    .meta { font-size: 12px; margin: 2px 0; }
                    .summary { margin-top: 12px; border-top: 1px dashed #999; padding-top: 8px; }
                </style>
            </head>
            <body>
                <h1>SMART POS</h1>
                <div class="meta">Ref: ${receipt.reference}</div>
                <div class="meta">Lokasi: ${receipt.location}</div>
                <div class="meta">Kasir: ${receipt.kasir}</div>
                <div class="meta">Waktu: ${formatTime(receipt.timestamp)}</div>
                ${receipt.santri ? `<div class="meta">Santri: ${receipt.santri.name} (${receipt.santri.nis})</div>` : ''}
                ${receipt.offline ? '<div class="meta">Mode: Offline</div>' : ''}
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
                <div class="summary">
                    <div class="meta">Total: ${currency(receipt.total)}</div>
                    <div class="meta">Tunai: ${currency(receipt.payments.cash)}</div>
                    <div class="meta">Saldo: ${currency(receipt.payments.wallet)}</div>
                    <div class="meta">Gateway: ${currency(receipt.payments.gateway)}</div>
                    <div class="meta">Kembalian: ${currency(receipt.change)}</div>
                </div>
                <script>
                    window.onload = () => {
                        window.print();
                        window.close();
                    };
                </script>
            </body>
            </html>
        `;

        if (window.SmartAndroid && typeof window.SmartAndroid.printReceipt === 'function') {
            window.SmartAndroid.printReceipt(html);
            return;
        }

        const printWindow = window.open('', '_blank', 'width=480,height=700');
        if (! printWindow) {
            setStatus('warning', 'Popup diblokir. Izinkan popup untuk mencetak struk.');
            return;
        }

        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
    }

    async function submitTransaction() {
        const payload = buildPayload();

        if (! state.locationId) {
            setStatus('error', 'Pilih lokasi terlebih dahulu.');
            return;
        }

        if (payload.items.length === 0) {
            setStatus('error', 'Keranjang masih kosong.');
            return;
        }

        if (payload.payments.cash + payload.payments.wallet + payload.payments.gateway <= 0) {
            setStatus('error', 'Isi minimal satu metode pembayaran.');
            return;
        }

        if (payload.payments.wallet > 0 && ! state.santriId) {
            setStatus('error', 'Pilih santri sebelum menggunakan saldo.');
            return;
        }

        if (state.santri && payload.payments.wallet > Number(state.santri.wallet_balance || 0)) {
            setStatus('error', 'Saldo santri tidak mencukupi.');
            return;
        }

        state.isSubmitting = true;
        elements.submitButton.disabled = true;

        try {
            if (! navigator.onLine || ! getAuthToken()) {
                enqueueTransaction(payload);
                state.offlineQueue = getQueue();
                renderOfflineQueue();
                updateOfflineBadge();
                setStatus('warning', 'Transaksi disimpan offline dan akan tersinkron otomatis.');
                state.lastReceipt = buildReceipt(payload, null, true);
            } else {
                const response = await axios.post('/api/pos/transactions', payload);
                setStatus('success', 'Transaksi berhasil diproses.');
                state.lastReceipt = buildReceipt(payload, response.data, false);
            }

            updateReceiptSummary(state.lastReceipt);
            resetForm();
        } catch (error) {
            if (! error.response) {
                enqueueTransaction(payload);
                state.offlineQueue = getQueue();
                renderOfflineQueue();
                updateOfflineBadge();
                setStatus('warning', 'Koneksi bermasalah. Transaksi disimpan offline.');
                state.lastReceipt = buildReceipt(payload, null, true);
                updateReceiptSummary(state.lastReceipt);
                resetForm();
            } else {
                setStatus('error', error.response?.data?.message || 'Transaksi gagal diproses.');
            }
        } finally {
            state.isSubmitting = false;
            elements.submitButton.disabled = false;
        }
    }

    async function syncOffline() {
        const queue = getQueue();

        if (queue.length === 0 || ! getAuthToken()) {
            return;
        }

        state.isSyncing = true;
        elements.syncButton.disabled = true;

        try {
            await flushQueue(async (transaction) => {
                await axios.post('/api/pos/transactions', transaction);
            });

            state.offlineQueue = getQueue();
            renderOfflineQueue();
            setStatus('success', 'Semua transaksi offline berhasil dikirim.');
        } catch (error) {
            setStatus('warning', 'Sebagian transaksi gagal disinkron. Coba lagi nanti.');
        } finally {
            state.isSyncing = false;
            updateOfflineBadge();
        }
    }

    function renderSantriResults() {
        if (! elements.santriResults) {
            return;
        }

        if (state.santriResults.length === 0) {
            elements.santriResults.innerHTML = '<div class="hint" style="padding:0.25rem 0.4rem;">Santri tidak ditemukan.</div>';
            elements.santriResults.hidden = false;
            return;
        }

        elements.santriResults.innerHTML = '';
        state.santriResults.forEach((santri) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.santriId = santri.id;
            button.textContent = `${santri.name} - ${santri.nis}`;
            elements.santriResults.appendChild(button);
        });
        elements.santriResults.hidden = false;
    }

    function selectSantri(santri) {
        state.santriId = santri.id;
        state.santri = santri;
        root.dataset.santriId = santri.id;
        elements.santriName.textContent = santri.name;
        elements.santriNis.textContent = `NIS: ${santri.nis}`;
        elements.santriBalance.textContent = `Saldo: ${currency(santri.wallet_balance)}`;
        elements.payWallet.max = santri.wallet_balance;
        elements.santriResults.hidden = true;
    }

    function clearSantri() {
        state.santriId = '';
        state.santri = null;
        root.dataset.santriId = '';
        elements.santriName.textContent = 'Belum dipilih';
        elements.santriNis.textContent = 'NIS: -';
        elements.santriBalance.textContent = 'Saldo: Rp0';
        elements.santriResults.hidden = true;
        elements.santriSearch.value = '';
        elements.payWallet.value = 0;
        state.payments.wallet = 0;
        handlePaymentsChange();
    }

    const fetchSantri = debounce(async (query) => {
        const trimmed = query.trim();
        if (! trimmed) {
            elements.santriResults.hidden = true;
            return;
        }

        if (! navigator.onLine || ! getAuthToken()) {
            elements.santriResults.innerHTML = '<div class="hint" style="padding:0.25rem 0.4rem;">Mode offline, tidak bisa mencari santri.</div>';
            elements.santriResults.hidden = false;
            return;
        }

        try {
            const response = await axios.get('/api/pos/santris', { params: { search: trimmed } });
            state.santriResults = response.data?.data || response.data || [];
            renderSantriResults();

            if (state.santriResults.length === 1 && (state.santriResults[0].nis === trimmed || state.santriResults[0].qr_code === trimmed)) {
                selectSantri(state.santriResults[0]);
            }
        } catch (error) {
            console.warn('SMART POS: gagal mencari santri', error);
        }
    }, 400);

    async function loadLocations() {
        let locations = [];

        if (navigator.onLine && getAuthToken()) {
            try {
                const response = await axios.get('/api/pos/locations');
                locations = response.data?.data || response.data || [];
                persistCache(LOCATION_CACHE_KEY, locations);
            } catch (error) {
                locations = loadCache(LOCATION_CACHE_KEY, []);
            }
        } else {
            locations = loadCache(LOCATION_CACHE_KEY, []);
        }

        state.locations = locations;

        if (! state.locationId) {
            const stored = window.localStorage.getItem('smart.pos.location');
            state.locationId = stored || (locations[0]?.id ? String(locations[0].id) : '');
        }

        root.dataset.locationId = state.locationId;
        renderLocations();
    }

    async function loadCategories() {
        let categories = [];

        if (navigator.onLine && getAuthToken()) {
            try {
                const response = await axios.get('/api/pos/categories');
                categories = response.data?.data || response.data || [];
                persistCache(CATEGORY_CACHE_KEY, categories);
            } catch (error) {
                categories = loadCache(CATEGORY_CACHE_KEY, []);
            }
        } else {
            categories = loadCache(CATEGORY_CACHE_KEY, []);
        }

        state.categories = categories;
        renderCategories();
    }

    async function loadProducts() {
        let products = [];
        const cacheKey = productsCacheKey(state.locationId);

        if (navigator.onLine && getAuthToken()) {
            try {
                const response = await axios.get('/api/pos/products', {
                    params: {
                        location_id: state.locationId || undefined,
                    },
                });
                products = response.data?.data || response.data || [];
                persistCache(cacheKey, products);
            } catch (error) {
                products = loadCache(cacheKey, []);
            }
        } else {
            products = loadCache(cacheKey, []);
        }

        state.products = products;
        renderProducts();
    }

    async function initialize() {
        await loadLocations();
        await loadCategories();
        await loadProducts();
        renderCart();
        renderOfflineQueue();
        updateOfflineBadge();
        updateReceiptSummary(state.lastReceipt);
    }

    async function openScanner(mode) {
        state.scanMode = mode;

        if (window.SmartAndroid && typeof window.SmartAndroid.startScan === 'function') {
            window.SmartAndroid.startScan(mode);
            return;
        }

        if (! ('mediaDevices' in navigator) || ! window.BarcodeDetector) {
            setStatus('warning', 'Scanner tidak didukung di browser ini.');
            return;
        }

        elements.scanTitle.textContent = mode === 'santri' ? 'Scan QR Santri' : 'Scan Barcode Produk';
        elements.scanHint.textContent = 'Arahkan kamera ke barcode atau QR.';

        try {
            state.scanStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });

            elements.scanVideo.srcObject = state.scanStream;
            elements.scanModal.hidden = false;
            state.scanActive = true;

            const detector = new BarcodeDetector({ formats: ['qr_code', 'ean_13', 'code_128'] });

            const scanLoop = async () => {
                if (! state.scanActive) {
                    return;
                }

                try {
                    const barcodes = await detector.detect(elements.scanVideo);
                    if (barcodes.length > 0) {
                        const code = barcodes[0].rawValue;
                        handleScanResult(code, mode);
                        closeScanner();
                        return;
                    }
                } catch (error) {
                    console.warn('SMART POS: gagal membaca barcode', error);
                }

                window.requestAnimationFrame(scanLoop);
            };

            window.requestAnimationFrame(scanLoop);
        } catch (error) {
            console.warn('SMART POS: tidak bisa membuka kamera', error);
            setStatus('warning', 'Tidak bisa membuka kamera.');
        }
    }

    function closeScanner() {
        state.scanActive = false;
        elements.scanModal.hidden = true;

        if (state.scanStream) {
            state.scanStream.getTracks().forEach((track) => track.stop());
            state.scanStream = null;
        }
    }

    function handleScanResult(code, mode) {
        if (mode === 'santri') {
            elements.santriSearch.value = code;
            fetchSantri(code);
            return;
        }

        const product = state.products.find((item) => item.barcode === code || item.sku === code);
        if (product) {
            addToCart(product);
        } else {
            elements.productSearch.value = code;
            state.searchQuery = code;
            renderProducts();
        }
    }

    function handleQueueAction(event) {
        const target = event.target.closest('button[data-reference]');
        if (! target) {
            return;
        }

        const reference = target.dataset.reference;
        state.offlineQueue = removeTransaction(reference);
        renderOfflineQueue();
        updateOfflineBadge();
    }

    function logout() {
        try {
            window.localStorage.removeItem(USER_STORAGE_KEY);
        } catch (error) {
            console.warn('SMART POS: gagal menghapus data pengguna', error);
        }

        clearAuthToken();
        window.location.href = '/logout';
    }

    elements.productSearch.addEventListener('input', (event) => {
        state.searchQuery = event.target.value || '';
        renderProducts();
    });

    elements.categoryList.addEventListener('click', handleCategoryClick);
    elements.productGrid.addEventListener('click', handleProductClick);
    elements.cartBody.addEventListener('click', handleCartAction);
    elements.cartBody.addEventListener('change', handleCartQuantity);

    [elements.payCash, elements.payWallet, elements.payGateway].forEach((input) => {
        if (input) {
            input.addEventListener('input', handlePaymentsChange);
        }
    });

    elements.payExactButton.addEventListener('click', setPayExact);
    elements.submitButton.addEventListener('click', submitTransaction);
    elements.syncButton.addEventListener('click', syncOffline);
    elements.logoutButton.addEventListener('click', logout);
    elements.queueList.addEventListener('click', handleQueueAction);
    elements.printButton.addEventListener('click', printReceipt);

    elements.locationSelect.addEventListener('change', (event) => {
        state.locationId = event.target.value;
        window.localStorage.setItem('smart.pos.location', state.locationId);
        root.dataset.locationId = state.locationId;
        state.selectedCategory = 'all';
        renderCategories();
        loadProducts();
    });

    elements.santriSearch.addEventListener('input', (event) => {
        fetchSantri(event.target.value || '');
    });

    elements.santriResults.addEventListener('click', (event) => {
        const target = event.target.closest('button[data-santri-id]');
        if (! target) {
            return;
        }

        const santriId = Number(target.dataset.santriId);
        const santri = state.santriResults.find((item) => item.id === santriId);
        if (santri) {
            selectSantri(santri);
        }
    });

    elements.clearSantriButton.addEventListener('click', clearSantri);
    elements.scanProductButton.addEventListener('click', () => openScanner('product'));
    elements.scanSantriButton.addEventListener('click', () => openScanner('santri'));
    elements.scanCloseButton.addEventListener('click', closeScanner);

    window.addEventListener('online', () => {
        setStatus('success', 'Koneksi kembali online.');
        updateOfflineBadge();
        if (state.offlineQueue.length > 0) {
            syncOffline();
        }
    });

    window.addEventListener('offline', () => {
        setStatus('warning', 'Anda sedang offline. Transaksi akan disimpan sementara.');
        updateOfflineBadge();
    });

    window.addEventListener('smart:scan', (event) => {
        const { code, mode } = event.detail || {};
        if (! code) {
            return;
        }
        handleScanResult(code, mode || state.scanMode);
    });

    initialize();
});
