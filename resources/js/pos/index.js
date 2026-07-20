import { clearAuthToken, getAuthToken, withAuthToken } from '../services/authToken';
import { getQueue, removeTransaction } from '../services/offlineQueue';

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

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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
        paymentMethodButtons: document.querySelectorAll('.payment-method-btn'),
        paymentSections: document.querySelectorAll('[data-payment-section]'),
        walletWarning: document.getElementById('wallet-warning'),
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
        historyButton: document.getElementById('transaction-history-btn'),
        historyModal: document.getElementById('transaction-history-modal'),
        historyBackdrop: document.getElementById('transaction-history-backdrop'),
        historyCloseButton: document.getElementById('transaction-history-close-btn'),
        historyState: document.getElementById('transaction-history-state'),
        historyList: document.getElementById('transaction-history-list'),
        historyDetail: document.getElementById('transaction-history-detail'),
        scanProductButton: document.getElementById('scan-product-btn'),
        scanSantriButton: document.getElementById('scan-santri-btn'),
        scanModal: document.getElementById('scan-modal'),
        scanCloseButton: document.getElementById('scan-close-btn'),
        scanVideo: document.getElementById('scan-video'),
        scanTitle: document.getElementById('scan-title'),
        scanHint: document.getElementById('scan-hint'),
        successModal: document.getElementById('success-modal'),
        successRef: document.getElementById('success-ref'),
        successDetails: document.getElementById('success-details'),
        successSummary: document.getElementById('success-summary'),
        successPrintBtn: document.getElementById('success-print-btn'),
        successNewBtn: document.getElementById('success-new-btn'),
        successBackdrop: document.getElementById('success-backdrop'),
        confirmModal: document.getElementById('confirm-modal'),
        confirmBackdrop: document.getElementById('confirm-backdrop'),
        confirmCloseBtn: document.getElementById('confirm-close-btn'),
        confirmCancelBtn: document.getElementById('confirm-cancel-btn'),
        confirmBuyBtn: document.getElementById('confirm-buy-btn'),
        confirmRef: document.getElementById('confirm-ref'),
        confirmItems: document.getElementById('confirm-items'),
        confirmOrderSummary: document.getElementById('confirm-order-summary'),
        confirmCustomer: document.getElementById('confirm-customer'),
        confirmPaymentInfo: document.getElementById('confirm-payment-info'),
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
        paymentMethod: 'wallet',
        isSubmitting: false,
        isSyncing: false,
        offlineQueue: getQueue(),
        lastReceipt: null,
        pendingPayload: null,
        clientTransactionId: null,
        scanStream: null,
        scanMode: 'product',
        scanActive: false,
        historyTransactions: [],
        historySelectedId: null,
        isHistoryLoading: false,
        historyError: null,
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

    function cartSubtotal() {
        return state.cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
    }

    function totals() {
        const subTotal = cartSubtotal();
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
                ? `<img src="${product.photo_url}" alt="${product.name}" loading="lazy" onerror="this.closest('.product-thumb').innerHTML='<span>Tanpa Foto</span>'">`
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

        if (state.paymentMethod === 'wallet') {
            applyWalletAutoAmount();
        } else {
            updateWalletWarning();
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
        elements.syncButton.disabled = true;
        elements.submitButton.disabled = ! online || state.isSubmitting;
        elements.submitButton.title = online ? '' : 'Pembayaran saldo memerlukan koneksi server.';
    }

    function sumOfflineItem(items = []) {
        return (items || []).reduce((sum, item) => sum + (item.unit_price || 0) * (item.quantity || 0), 0);
    }

    function productRestrictionMessage(product, santri = state.santri) {
        if (! santri || ! product.category_id) {
            return null;
        }

        const categoryId = Number(product.category_id);
        const blocked = (santri.blocked_category_ids || []).map(Number);
        const allowed = (santri.whitelisted_category_ids || []).map(Number);

        if (blocked.includes(categoryId) || (allowed.length > 0 && ! allowed.includes(categoryId))) {
            return `Produk ${product.name} tidak diizinkan untuk santri yang dipilih.`;
        }

        return null;
    }

    function addToCart(product) {
        const restriction = productRestrictionMessage(product);
        if (restriction) {
            setStatus('error', restriction);
            return;
        }

        state.clientTransactionId = null;
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
                category_id: product.category_id,
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

        state.clientTransactionId = null;

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

        state.clientTransactionId = null;
        state.cart[index].quantity = Math.max(1, value || 1);
        renderCart();
    }

    function handlePaymentsChange() {
        let cash = Number(elements.payCash.value || 0);
        let wallet = Number(elements.payWallet.value || 0);
        let gateway = Number(elements.payGateway.value || 0);

        if (state.paymentMethod === 'cash') {
            wallet = 0;
            gateway = 0;
        } else if (state.paymentMethod === 'wallet') {
            cash = 0;
            gateway = 0;
        } else if (state.paymentMethod === 'gateway') {
            cash = 0;
            wallet = 0;
        }

        state.payments.cash = cash;
        state.payments.wallet = wallet;
        state.payments.gateway = gateway;

        elements.payCash.value = cash;
        elements.payWallet.value = wallet;
        elements.payGateway.value = gateway;

        const { paymentTotal, change } = totals();
        elements.totalPayableText.textContent = currency(paymentTotal);
        elements.changeText.textContent = currency(change);
        updateWalletWarning();
    }

    function setPayExact() {
        if (state.paymentMethod !== 'cash') {
            return;
        }
        const { subTotal } = totals();
        elements.payCash.value = subTotal;
        state.payments.cash = subTotal;
        handlePaymentsChange();
    }

    function updateWalletWarning() {
        if (! elements.walletWarning) {
            return;
        }

        if (state.paymentMethod !== 'wallet') {
            elements.walletWarning.hidden = true;
            return;
        }

        const subTotal = cartSubtotal();

        // No santri selected but wallet method active
        if (! state.santri) {
            elements.walletWarning.hidden = subTotal === 0;
            elements.walletWarning.textContent = 'Pilih santri terlebih dahulu';
            return;
        }

        const balance = Number(state.santri?.wallet_balance || 0);
        const insufficient = subTotal > 0 && balance < subTotal;

        elements.walletWarning.hidden = ! insufficient;
        elements.walletWarning.textContent = 'Saldo tidak cukup';
    }

    function applyWalletAutoAmount() {
        const subTotal = cartSubtotal();
        const balance = Number(state.santri?.wallet_balance || 0);
        const amount = Math.min(subTotal, balance);
        elements.payWallet.value = amount;
        state.payments.wallet = amount;
        updateWalletWarning();
    }

    function setPaymentMethod(method, options = {}) {
        const target = method || 'cash';
        if (state.paymentMethod !== target) {
            state.clientTransactionId = null;
        }
        state.paymentMethod = target;

        elements.paymentMethodButtons?.forEach((button) => {
            button.classList.toggle('active', button.dataset.method === target);
        });

        elements.paymentSections?.forEach((section) => {
            section.hidden = section.dataset.paymentSection !== target;
        });

        if (target === 'cash') {
            if (options.auto !== false) {
                elements.payCash.value = cartSubtotal();
            }
            elements.payWallet.value = 0;
            elements.payGateway.value = 0;
        } else if (target === 'gateway') {
            if (options.auto !== false) {
                elements.payGateway.value = cartSubtotal();
            }
            elements.payCash.value = 0;
            elements.payWallet.value = 0;
        } else if (target === 'wallet') {
            elements.payCash.value = 0;
            elements.payGateway.value = 0;
            applyWalletAutoAmount();
        }

        handlePaymentsChange();
    }

    function buildPayload() {
        state.clientTransactionId ||= window.crypto.randomUUID();
        const items = state.cart.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
        }));

        return {
            client_transaction_id: state.clientTransactionId,
            location_id: state.locationId || null,
            santri_id: state.santriId || null,
            payment_method: state.paymentMethod,
            cash_received: state.paymentMethod === 'cash' ? Number(state.payments.cash || 0) : 0,
            items,
        };
    }

    function resetForm() {
        state.clientTransactionId = null;
        state.cart = [];
        elements.payCash.value = 0;
        elements.payWallet.value = 0;
        elements.payGateway.value = 0;
        state.payments.cash = 0;
        state.payments.wallet = 0;
        state.payments.gateway = 0;
        clearSantri();
        setPaymentMethod('wallet', { auto: false });
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
            reference: transaction?.reference || payload.client_transaction_id,
            items: transaction?.items ?? state.cart.map((item) => ({ ...item })),
            subtotal: transaction?.sub_total ?? subTotal,
            total: transaction?.total_amount ?? subTotal,
            payments: {
                cash: transaction?.cash_amount ?? 0,
                wallet: transaction?.wallet_amount ?? 0,
                gateway: transaction?.gateway_amount ?? 0,
            },
            change: transaction?.change_amount ?? change,
            location: location?.name || '- -',
            kasir: currentUser?.name || 'Kasir',
            santri: state.santri,
            walletBalanceBefore: transaction?.wallet_balance_before ?? null,
            walletBalanceAfter: transaction?.wallet_balance_after ?? transaction?.santri?.wallet_balance ?? null,
            timestamp: transaction?.processed_at || now.toISOString(),
            status: transaction?.status || 'completed',
            offline,
        };
    }

    function receiptFromTransaction(transaction) {
        return {
            reference: transaction.reference,
            items: transaction.items || [],
            subtotal: transaction.sub_total,
            total: transaction.total_amount,
            payments: {
                cash: transaction.cash_amount || 0,
                wallet: transaction.wallet_amount || 0,
                gateway: transaction.gateway_amount || 0,
            },
            change: transaction.change_amount || 0,
            location: transaction.location?.name || '-',
            kasir: transaction.kasir?.name || '-',
            santri: transaction.santri || null,
            walletBalanceBefore: null,
            walletBalanceAfter: null,
            timestamp: transaction.processed_at || transaction.created_at,
            status: transaction.status,
            offline: false,
        };
    }

    function transactionStatusLabel(status) {
        return ({
            completed: 'Selesai',
            pending: 'Menunggu pembayaran',
            cancelled: 'Dibatalkan',
            failed: 'Gagal',
        })[status] || status || '-';
    }

    function transactionPaymentLabel(transaction) {
        return ({
            cash: 'Tunai',
            wallet: 'Saldo santri',
            gateway: 'Gateway',
        })[transaction.primary_payment_method] || transaction.primary_payment_method || '-';
    }

    function renderHistoryDetail(transaction) {
        if (! elements.historyDetail) return;

        if (! transaction) {
            elements.historyDetail.hidden = true;
            elements.historyDetail.innerHTML = '';
            return;
        }

        const itemRows = (transaction.items || []).map((item) => `
            <tr>
                <td>${escapeHtml(item.product_name)}</td>
                <td>${item.quantity}</td>
                <td>${currency(item.unit_price)}</td>
                <td>${currency(item.subtotal || Number(item.unit_price) * item.quantity)}</td>
            </tr>
        `).join('');

        elements.historyDetail.innerHTML = `
            <div class="history-detail-heading">
                <div>
                    <strong>${escapeHtml(transaction.reference)}</strong>
                    <span>${formatTime(transaction.processed_at || transaction.created_at)}</span>
                </div>
                <button type="button" class="btn secondary" data-history-action="print" data-history-id="${transaction.id}">🖨️ Cetak ulang</button>
            </div>
            <div class="history-detail-meta">
                <span>Kasir: <strong>${escapeHtml(transaction.kasir?.name || '-')}</strong></span>
                <span>Santri: <strong>${escapeHtml(transaction.santri ? `${transaction.santri.name} (${transaction.santri.nis})` : '-')}</strong></span>
                <span>Pembayaran: <strong>${escapeHtml(transactionPaymentLabel(transaction))}</strong></span>
                <span>Status: <strong>${escapeHtml(transactionStatusLabel(transaction.status))}</strong></span>
            </div>
            <div class="history-detail-table-wrap">
                <table class="history-detail-table">
                    <thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
                    <tbody>${itemRows || '<tr><td colspan="4">Detail barang tidak tersedia.</td></tr>'}</tbody>
                </table>
            </div>
            <div class="history-detail-total"><span>Total</span><strong>${currency(transaction.total_amount)}</strong></div>
        `;
        elements.historyDetail.hidden = false;
    }

    function renderTransactionHistory() {
        if (! elements.historyList || ! elements.historyState) return;

        elements.historyList.innerHTML = '';
        elements.historyState.hidden = false;

        if (state.isHistoryLoading) {
            elements.historyState.textContent = 'Memuat riwayat transaksi…';
            renderHistoryDetail(null);
            return;
        }

        if (! navigator.onLine) {
            elements.historyState.textContent = 'Riwayat transaksi memerlukan koneksi server. Keranjang tetap dapat digunakan secara offline.';
            renderHistoryDetail(null);
            return;
        }

        if (state.historyError) {
            elements.historyState.textContent = state.historyError;
            renderHistoryDetail(null);
            return;
        }

        if (state.historyTransactions.length === 0) {
            elements.historyState.textContent = 'Belum ada transaksi pada lokasi ini.';
            renderHistoryDetail(null);
            return;
        }

        elements.historyState.hidden = true;
        state.historyTransactions.forEach((transaction) => {
            const row = document.createElement('div');
            row.className = 'history-row';
            row.innerHTML = `
                <div class="history-row-main">
                    <strong>${escapeHtml(transaction.reference)}</strong>
                    <span>${formatTime(transaction.processed_at || transaction.created_at)} · ${escapeHtml(transaction.santri?.name || 'Umum')}</span>
                </div>
                <div class="history-row-total">
                    <strong>${currency(transaction.total_amount)}</strong>
                    <span>${escapeHtml(transactionStatusLabel(transaction.status))}</span>
                </div>
                <div class="history-row-actions">
                    <button type="button" class="history-action-link" data-history-action="view" data-history-id="${transaction.id}">Lihat</button>
                    <button type="button" class="history-action-link" data-history-action="print" data-history-id="${transaction.id}">Cetak</button>
                </div>
            `;
            elements.historyList.appendChild(row);
        });

        const selected = state.historyTransactions.find((transaction) => transaction.id === state.historySelectedId);
        renderHistoryDetail(selected || null);
    }

    async function loadTransactionHistory() {
        state.isHistoryLoading = true;
        state.historyError = null;
        renderTransactionHistory();

        if (! navigator.onLine) {
            state.isHistoryLoading = false;
            renderTransactionHistory();
            return;
        }

        try {
            const params = new URLSearchParams({ per_page: '20' });
            if (state.locationId) params.set('location_id', state.locationId);
            const response = await axios.get(`/api/pos/transactions?${params.toString()}`);
            state.historyTransactions = response.data?.data || [];
            state.historySelectedId = null;
        } catch (error) {
            state.historyTransactions = [];
            state.historyError = error.response?.data?.message || 'Riwayat transaksi gagal dimuat.';
            setStatus('danger', 'Riwayat transaksi gagal dimuat. Silakan coba lagi.');
        } finally {
            state.isHistoryLoading = false;
            renderTransactionHistory();
        }
    }

    function showTransactionHistory() {
        if (! elements.historyModal) return;
        elements.historyModal.hidden = false;
        document.body.style.overflow = 'hidden';
        elements.historyCloseButton?.focus();
        loadTransactionHistory();
    }

    function hideTransactionHistory() {
        if (! elements.historyModal) return;
        elements.historyModal.hidden = true;
        document.body.style.overflow = '';
        elements.historyButton?.focus();
    }

    function handleHistoryAction(event) {
        const button = event.target.closest('[data-history-action]');
        if (! button) return;

        const transaction = state.historyTransactions.find((item) => String(item.id) === button.dataset.historyId);
        if (! transaction) return;

        if (button.dataset.historyAction === 'print') {
            printReceipt(receiptFromTransaction(transaction));
            return;
        }

        state.historySelectedId = transaction.id;
        renderTransactionHistory();
        elements.historyDetail?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function printReceipt(receipt = state.lastReceipt) {
        if (! receipt) {
            return;
        }

        const rows = receipt.items.map((item) => {
            return `
                <tr>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td>${item.quantity}</td>
                    <td>${currency(item.unit_price)}</td>
                    <td style="text-align:right;">${currency(item.unit_price * item.quantity)}</td>
                </tr>
            `;
        }).join('');

        const html = `
            <html>
            <head>
                <title>Struk ${escapeHtml(receipt.reference)}</title>
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
                <div class="meta">Ref: ${escapeHtml(receipt.reference)}</div>
                <div class="meta">Lokasi: ${escapeHtml(receipt.location)}</div>
                <div class="meta">Kasir: ${escapeHtml(receipt.kasir)}</div>
                <div class="meta">Waktu: ${formatTime(receipt.timestamp)}</div>
                <div class="meta">Status: ${escapeHtml(transactionStatusLabel(receipt.status))}</div>
                ${receipt.santri ? `<div class="meta">Santri: ${escapeHtml(receipt.santri.name)} (${escapeHtml(receipt.santri.nis)})</div>` : ''}
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

    function showSuccessModal(receipt) {
        if (! elements.successModal) return;

        // Reference
        elements.successRef.textContent = receipt.reference + (receipt.offline ? ' (Offline)' : '');

        // Items table
        const itemRows = receipt.items.map(item =>
            `<tr>
                <td>${item.product_name}</td>
                <td style="text-align:center">${item.quantity}</td>
                <td style="text-align:right">${currency(item.unit_price)}</td>
                <td style="text-align:right;font-weight:600">${currency(item.unit_price * item.quantity)}</td>
            </tr>`
        ).join('');

        elements.successDetails.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left">Item</th>
                        <th style="text-align:center">Qty</th>
                        <th style="text-align:right">Harga</th>
                        <th style="text-align:right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>${itemRows}</tbody>
            </table>
        `;

        // Summary
        const summaryParts = [];
        if (receipt.santri) {
            summaryParts.push(`<div class="success-summary-row"><span>Santri</span><span>${receipt.santri.name} (${receipt.santri.nis})</span></div>`);
        }
        summaryParts.push(`<div class="success-summary-row"><span>Lokasi</span><span>${receipt.location}</span></div>`);
        summaryParts.push(`<div class="success-summary-row"><span>Kasir</span><span>${receipt.kasir}</span></div>`);
        summaryParts.push(`<div class="success-summary-row"><span>Waktu</span><span>${formatTime(receipt.timestamp)}</span></div>`);

        if (receipt.payments.cash > 0) {
            summaryParts.push(`<div class="success-summary-row"><span>Tunai</span><span>${currency(receipt.payments.cash)}</span></div>`);
        }
        if (receipt.payments.wallet > 0) {
            summaryParts.push(`<div class="success-summary-row"><span>Saldo</span><span>${currency(receipt.payments.wallet)}</span></div>`);
        }
        if (receipt.walletBalanceAfter !== null) {
            summaryParts.push(`<div class="success-summary-row"><span>Sisa saldo</span><span>${currency(receipt.walletBalanceAfter)}</span></div>`);
        }
        if (receipt.payments.gateway > 0) {
            summaryParts.push(`<div class="success-summary-row"><span>Gateway</span><span>${currency(receipt.payments.gateway)}</span></div>`);
        }

        summaryParts.push(`<div class="success-summary-row total"><span>Total</span><span>${currency(receipt.total)}</span></div>`);

        if (receipt.change > 0) {
            summaryParts.push(`<div class="success-summary-row change"><span>Kembalian</span><span>${currency(receipt.change)}</span></div>`);
        }

        elements.successSummary.innerHTML = summaryParts.join('');

        // Show modal
        elements.successModal.hidden = false;
        document.body.style.overflow = 'hidden';

        // Re-trigger animations by cloning SVG
        const ring = elements.successModal.querySelector('.success-icon-ring');
        if (ring) {
            const clone = ring.cloneNode(true);
            ring.replaceWith(clone);
        }
    }

    function hideSuccessModal() {
        if (! elements.successModal) return;
        elements.successModal.hidden = true;
        document.body.style.overflow = '';
    }

    // Step 1: "Bayar Sekarang" opens confirm modal
    function submitTransaction() {
        const payload = buildPayload();

        if (! state.locationId) {
            setStatus('error', 'Pilih lokasi terlebih dahulu.');
            return;
        }

        if (payload.items.length === 0) {
            setStatus('error', 'Keranjang masih kosong.');
            return;
        }

        if (state.paymentMethod === 'wallet' && ! state.santriId) {
            setStatus('error', 'Pilih santri sebelum menggunakan saldo.');
            return;
        }
        if (! navigator.onLine) {
            setStatus('warning', 'Pembayaran saldo memerlukan koneksi server. Katalog dan keranjang tetap tersedia.');
            return;
        }
        if (state.paymentMethod === 'wallet' && state.santri && cartSubtotal() > Number(state.santri.wallet_balance || 0)) {
            setStatus('error', 'Saldo santri tidak mencukupi.');
            return;
        }
        if (state.paymentMethod === 'cash' && Number(state.payments.cash || 0) < cartSubtotal()) {
            setStatus('error', 'Jumlah tunai yang diterima kurang dari total transaksi.');
            return;
        }

        // Store payload for processTransaction
        state.pendingPayload = payload;
        showConfirmModal(payload);
    }

    // Show confirmation modal with order review
    function showConfirmModal(payload) {
        if (! elements.confirmModal) return;

        const location = state.locations.find(l => String(l.id) === String(state.locationId));
        const { subTotal, change } = totals();

        // Reference
        elements.confirmRef.textContent = payload.client_transaction_id;

        // Items table
        elements.confirmItems.innerHTML = state.cart.map((item, i) =>
            `<tr>
                <td>${i + 1}</td>
                <td>${item.product_name}</td>
                <td style="text-align:center">${item.quantity}</td>
                <td style="text-align:right">${currency(item.unit_price)}</td>
                <td style="text-align:right">${currency(item.unit_price * item.quantity)}</td>
            </tr>`
        ).join('');

        // Order summary
        const summaryLines = [];
        summaryLines.push(`<div class="confirm-summary-line"><span>Subtotal</span><span>${currency(subTotal)}</span></div>`);
        summaryLines.push(`<div class="confirm-summary-line grand-total"><span>Total</span><span>${currency(subTotal)}</span></div>`);
        if (change > 0) {
            summaryLines.push(`<div class="confirm-summary-line"><span>Kembalian</span><span style="color:#d97706;font-weight:700">${currency(change)}</span></div>`);
        }
        elements.confirmOrderSummary.innerHTML = summaryLines.join('');

        // Customer details
        const customerRows = [];
        if (state.santri) {
            const initials = state.santri.name
                .trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
            customerRows.push(`
                <div class="confirm-student-profile">
                    <div class="confirm-student-photo" data-confirm-student-photo>${initials}</div>
                    <div class="confirm-student-check">
                        <strong>Verifikasi pemilik kartu</strong>
                        <span>Pastikan wajah pembeli sesuai dengan foto santri sebelum melanjutkan pembayaran.</span>
                    </div>
                </div>
            `);
            customerRows.push(infoRow('Nama', state.santri.name));
            customerRows.push(infoRow('NIS', state.santri.nis));
            customerRows.push(infoRow('Saldo', currency(state.santri.wallet_balance), 'brand'));
        } else {
            customerRows.push(infoRow('Pembeli', 'Guest (Umum)'));
        }
        customerRows.push(infoRow('Lokasi', location?.name || '—'));
        customerRows.push(infoRow('Kasir', currentUser?.name || 'Kasir'));
        elements.confirmCustomer.innerHTML = customerRows.join('');

        const photoContainer = elements.confirmCustomer.querySelector('[data-confirm-student-photo]');
        if (photoContainer && state.santri?.photo_url) {
            const fallback = photoContainer.textContent;
            const image = document.createElement('img');
            image.src = state.santri.photo_url;
            image.alt = `Foto ${state.santri.name}`;
            image.addEventListener('error', () => {
                photoContainer.textContent = fallback;
            });
            photoContainer.textContent = '';
            photoContainer.appendChild(image);
        }

        // Payment info
        const payRows = [];
        const methodLabels = { cash: 'Tunai', wallet: 'Saldo', gateway: 'Gateway' };
        const method = state.paymentMethod;
        payRows.push(`<div class="confirm-info-row"><span class="info-label">Metode</span><span class="confirm-payment-badge ${method}">${methodLabels[method] || method}</span></div>`);

        payRows.push(infoRow(methodLabels[method] || 'Jumlah', currency(cartSubtotal())));
        elements.confirmPaymentInfo.innerHTML = payRows.join('');

        // Show
        elements.confirmModal.hidden = false;
        elements.confirmBuyBtn.disabled = false;
        elements.confirmBuyBtn.classList.remove('loading');
        document.body.style.overflow = 'hidden';
    }

    function infoRow(label, value, valueClass = '') {
        return `<div class="confirm-info-row"><span class="info-label">${label}</span><span class="info-value ${valueClass}">${value}</span></div>`;
    }

    function hideConfirmModal() {
        if (! elements.confirmModal) return;
        elements.confirmModal.hidden = true;
        document.body.style.overflow = '';
        state.pendingPayload = null;
    }

    // Step 2: "Konfirmasi Beli" processes the transaction
    async function processTransaction() {
        const payload = state.pendingPayload;
        if (! payload) return;

        if (import.meta.env.DEV) {
            console.debug('[SMART POS] checkout clicked');
            console.debug('[SMART POS] checkout payload', {
                client_transaction_id: payload.client_transaction_id,
                santri_id: payload.santri_id,
                location_id: payload.location_id,
                items: payload.items,
            });
        }

        state.isSubmitting = true;
        elements.confirmBuyBtn.disabled = true;
        elements.confirmBuyBtn.classList.add('loading');

        try {
            if (! navigator.onLine || ! getAuthToken()) {
                hideConfirmModal();
                setStatus('warning', 'Pembayaran saldo memerlukan koneksi server dan tidak disimpan untuk sinkronisasi.');
                return;
            } else {
                const response = await axios.post('/api/pos/transactions', payload);
                const transaction = response.data.data;
                if (transaction.status === 'pending' && transaction.payment_redirect_url) {
                    window.location.assign(transaction.payment_redirect_url);
                    return;
                }
                if (state.santri && transaction.wallet_balance_after !== undefined) {
                    state.santri.wallet_balance = transaction.wallet_balance_after;
                    elements.santriBalance.textContent = `Saldo: ${currency(transaction.wallet_balance_after)}`;
                }
                state.lastReceipt = buildReceipt(payload, transaction, false);
                await loadProducts();
            }

            hideConfirmModal();
            updateReceiptSummary(state.lastReceipt);
            showSuccessModal(state.lastReceipt);
            resetForm();
        } catch (error) {
            hideConfirmModal();
            if (! error.response) {
                setStatus('warning', 'Koneksi bermasalah. Pembayaran tidak diproses; keranjang tetap tersimpan.');
            } else {
                const code = error.response?.data?.code;
                const messages = {
                    ACCOUNTING_CONFIGURATION_MISSING: 'Transaksi belum dapat diproses karena konfigurasi akun keuangan belum lengkap. Hubungi administrator.',
                    CATEGORY_NOT_ALLOWED: error.response?.data?.message,
                    INSUFFICIENT_WALLET_BALANCE: 'Saldo santri tidak mencukupi.',
                    DAILY_LIMIT_EXCEEDED: 'Limit harian santri telah terlampaui.',
                    WEEKLY_LIMIT_EXCEEDED: 'Limit mingguan santri telah terlampaui.',
                    MONTHLY_LIMIT_EXCEEDED: 'Limit bulanan santri telah terlampaui.',
                    INSUFFICIENT_CASH: 'Jumlah tunai yang diterima kurang dari total transaksi.',
                    PAYMENT_GATEWAY_UNAVAILABLE: 'Payment gateway belum tersedia atau belum dikonfigurasi. Hubungi administrator.',
                };
                setStatus('error', messages[code] || error.response?.data?.message || 'Transaksi gagal diproses.');
            }
        } finally {
            state.isSubmitting = false;
            elements.submitButton.disabled = ! navigator.onLine;
            elements.confirmBuyBtn.disabled = false;
            elements.confirmBuyBtn.classList.remove('loading');
        }
    }

    async function syncOffline() {
        setStatus('warning', 'Sinkronisasi transaksi saldo offline dinonaktifkan untuk keamanan.');
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
            button.className = 'santri-result-item';
            const initials = santri.name
                ? santri.name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
                : 'S';
            button.innerHTML = `
                <div class="santri-avatar">${initials}</div>
                <div>
                    <div>${santri.name}</div>
                    <div class="santri-result-meta">${santri.nis}</div>
                </div>
                <div class="santri-balance-chip">${currency(santri.wallet_balance)}</div>
            `;
            elements.santriResults.appendChild(button);
        });
        elements.santriResults.hidden = false;
    }

    function selectSantri(santri) {
        state.clientTransactionId = null;
        state.santriId = santri.id;
        state.santri = santri;
        root.dataset.santriId = santri.id;
        elements.santriName.textContent = santri.name;
        elements.santriNis.textContent = `NIS: ${santri.nis}`;
        elements.santriBalance.textContent = `Saldo: ${currency(santri.wallet_balance)}`;
        elements.payWallet.max = santri.wallet_balance;
        elements.santriResults.hidden = true;

        const removed = state.cart.filter((item) => productRestrictionMessage(item, santri));
        if (removed.length > 0) {
            const removedIds = new Set(removed.map((item) => item.product_id));
            state.cart = state.cart.filter((item) => ! removedIds.has(item.product_id));
            setStatus('error', `Produk tidak diizinkan dikeluarkan dari keranjang: ${removed.map((item) => item.product_name).join(', ')}.`);
            renderCart();
        }

        if (state.paymentMethod === 'wallet') {
            applyWalletAutoAmount();
            handlePaymentsChange();
        }
    }

    function clearSantri() {
        state.clientTransactionId = null;
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

        if (state.paymentMethod === 'wallet') {
            applyWalletAutoAmount();
        }

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
        setPaymentMethod('wallet');
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

    elements.paymentMethodButtons?.forEach((button) => {
        button.addEventListener('click', () => {
            setPaymentMethod(button.dataset.method);
        });
    });

    elements.payExactButton.addEventListener('click', setPayExact);
    elements.submitButton.addEventListener('click', submitTransaction);

    // Success modal events
    if (elements.successNewBtn) {
        elements.successNewBtn.addEventListener('click', hideSuccessModal);
    }
    if (elements.successPrintBtn) {
        elements.successPrintBtn.addEventListener('click', () => {
            printReceipt();
        });
    }
    if (elements.successBackdrop) {
        elements.successBackdrop.addEventListener('click', hideSuccessModal);
    }
    // Confirm modal events
    if (elements.confirmBuyBtn) {
        elements.confirmBuyBtn.addEventListener('click', processTransaction);
    }
    if (elements.confirmCancelBtn) {
        elements.confirmCancelBtn.addEventListener('click', hideConfirmModal);
    }
    if (elements.confirmCloseBtn) {
        elements.confirmCloseBtn.addEventListener('click', hideConfirmModal);
    }
    if (elements.confirmBackdrop) {
        elements.confirmBackdrop.addEventListener('click', hideConfirmModal);
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (elements.historyModal && !elements.historyModal.hidden) {
                hideTransactionHistory();
            } else if (elements.confirmModal && !elements.confirmModal.hidden) {
                hideConfirmModal();
            } else if (elements.successModal && !elements.successModal.hidden) {
                hideSuccessModal();
            }
        }
    });
    elements.syncButton.addEventListener('click', syncOffline);
    elements.logoutButton.addEventListener('click', logout);
    elements.queueList.addEventListener('click', handleQueueAction);
    elements.printButton.addEventListener('click', () => printReceipt());
    elements.historyButton?.addEventListener('click', showTransactionHistory);
    elements.historyCloseButton?.addEventListener('click', hideTransactionHistory);
    elements.historyBackdrop?.addEventListener('click', hideTransactionHistory);
    elements.historyList?.addEventListener('click', handleHistoryAction);
    elements.historyDetail?.addEventListener('click', handleHistoryAction);

    elements.locationSelect.addEventListener('change', (event) => {
        state.clientTransactionId = null;
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
        setStatus('warning', 'Anda sedang offline. Pembayaran saldo dinonaktifkan; katalog dan keranjang tetap tersedia.');
        updateOfflineBadge();
    });

    window.addEventListener('smart:app-update', () => {
        setStatus('warning', 'Versi baru SMART tersedia. Muat ulang aplikasi setelah menyelesaikan keranjang.');
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
