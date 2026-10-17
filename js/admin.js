/**
 * KúkiCakes — Administrator Dashboard UI Controller (js/admin.js)
 *
 * Full integration with admin backend APIs:
 * - api/admin-dashboard.php (metrics, order breakdowns, revenue, recent orders, alerts)
 * - api/admin-orders.php (orders listing, filters, item details, order & payment status updates)
 * - api/admin-products.php (catalog listing, search, multi-category, create, edit, toggle, delete)
 * - api/admin-inventory.php (stock management, health status, untracked handling, quick adjustments)
 *
 * Security:
 * - Server-side requireAdmin() is the actual authority
 * - XSS prevention: all dynamic strings escaped safely before DOM insertion
 * - Confirmation dialogs for consequential mutations
 * - Graceful session expiry and error handling
 */

(function () {
    'use strict';

    let _initialized = false;
    let _activeTab = 'dashboard';
    let _systemCategories = [];

    // Cache / filter states
    const _ordersState = {
        search: '',
        status: '',
        payment: '',
        date: '',
        timer: null
    };

    const _productsState = {
        search: '',
        cat: '',
        availability: '',
        stock: '',
        timer: null
    };

    const _inventoryState = {
        search: '',
        health: '',
        cat: '',
        timer: null
    };

    // ─────────────────────────────────────────────────────────────────
    // Utility Helpers
    // ─────────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatMoney(amount) {
        const val = parseFloat(amount) || 0;
        return 'Rs. ' + val.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return escapeHtml(dateStr);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return escapeHtml(dateStr);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function showToast(msg) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg);
        } else {
            console.log('[Admin Toast]', msg);
        }
    }

    /**
     * Unified fetch wrapper with error handling and session verification.
     */
    async function adminFetch(endpoint, options = {}) {
        const defaultHeaders = {
            'Accept': 'application/json'
        };
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            defaultHeaders['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        const fetchOptions = {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {})
            }
        };

        try {
            const resp = await fetch(endpoint, fetchOptions);
            let json = null;
            try {
                json = await resp.json();
            } catch (err) {
                console.warn('[Admin] Non-JSON API response from', endpoint);
            }

            // Check authorization boundaries
            if (resp.status === 401) {
                showToast('Administrator session expired. Please log in.');
                if (typeof window.goPage === 'function') window.goPage('login');
                return { ok: false, status: 401, data: null };
            }
            if (resp.status === 403) {
                showToast('Access denied. Administrator privileges required.');
                if (typeof window.goPage === 'function') window.goPage('home');
                return { ok: false, status: 403, data: null };
            }

            return {
                ok: resp.ok && (json ? json.success !== false : true),
                status: resp.status,
                data: json
            };
        } catch (netErr) {
            console.error('[Admin] Network error connecting to', endpoint, netErr);
            return { ok: false, status: 0, error: netErr, data: null };
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Status Badge Renderers
    // ─────────────────────────────────────────────────────────────────

    function getOrderStatusBadge(status) {
        const s = String(status || '').toLowerCase();
        const label = s.replace('_', ' ');
        return `<span class="badge-status st-${escapeHtml(s)}">${escapeHtml(label)}</span>`;
    }

    function getPaymentStatusBadge(status) {
        const s = String(status || '').toLowerCase();
        const label = s.replace('_', ' ');
        return `<span class="badge-status pay-${escapeHtml(s)}">${escapeHtml(label)}</span>`;
    }

    function getStockBadge(qty) {
        if (qty === null || qty === undefined) {
            return `<span class="badge-status stock-untracked">Untracked</span>`;
        }
        const num = parseInt(qty, 10);
        if (num === 0) {
            return `<span class="badge-status stock-out">Out of Stock (0)</span>`;
        }
        if (num <= 5) {
            return `<span class="badge-status stock-low">Low Stock (${num})</span>`;
        }
        return `<span class="badge-status stock-in">In Stock (${num})</span>`;
    }

    // ─────────────────────────────────────────────────────────────────
    // MODULE 1: DASHBOARD OVERVIEW
    // ─────────────────────────────────────────────────────────────────

    async function loadDashboard() {
        const currentDateEl = document.getElementById('dashCurrentDate');
        const greetingEl = document.getElementById('dashGreeting');

        if (currentDateEl) {
            const today = new Date();
            currentDateEl.textContent = today.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        if (greetingEl) {
            const hour = new Date().getHours();
            const timeGreeting = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
            greetingEl.textContent = `${timeGreeting}, Administrator.`;
        }

        const res = await adminFetch('api/admin-dashboard.php');
        if (!res.ok || !res.data || !res.data.stats) {
            if (res.status !== 401 && res.status !== 403) {
                showToast('Failed to load dashboard metrics.');
                const recentContainer = document.getElementById('dashRecentOrdersList');
                if (recentContainer) {
                    recentContainer.innerHTML = '<div class="admin-state-box" style="padding:15px;"><span class="state-icon">⚠️</span><h4>Failed to load recent orders</h4><p>Unable to connect to dashboard API.</p><button type="button" class="btn btn-primary btn-sm" id="retryDashRecentBtn">Try again</button></div>';
                    document.getElementById('retryDashRecentBtn')?.addEventListener('click', loadDashboard);
                }
                const alertsContainer = document.getElementById('dashInventoryAlerts');
                if (alertsContainer) {
                    alertsContainer.innerHTML = '<div class="admin-state-box" style="padding:15px;"><span class="state-icon">⚠️</span><h4>Failed to load inventory alerts</h4><p>Unable to connect to dashboard API.</p><button type="button" class="btn btn-primary btn-sm" id="retryDashAlertsBtn">Try again</button></div>';
                    document.getElementById('retryDashAlertsBtn')?.addEventListener('click', loadDashboard);
                }
                ['dashStatOrdersSub', 'dashStatRevenueSub', 'dashStatCustomersSub', 'dashStatProductsSub'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = 'Error loading';
                });
            }
            return;
        }

        const stats = res.data.stats;

        // Stat Grid Values
        const statOrders = document.getElementById('dashStatOrders');
        const statOrdersSub = document.getElementById('dashStatOrdersSub');
        if (statOrders) statOrders.textContent = stats.orders.total ?? 0;
        if (statOrdersSub) {
            statOrdersSub.textContent = `${stats.orders.upcoming ?? 0} upcoming · ${stats.orders.today ?? 0} today`;
        }

        const statRev = document.getElementById('dashStatRevenue');
        const statRevSub = document.getElementById('dashStatRevenueSub');
        if (statRev) statRev.textContent = formatMoney(stats.revenue.total_revenue ?? 0);
        if (statRevSub) {
            statRevSub.textContent = `${formatMoney(stats.revenue.pending_revenue ?? 0)} pending`;
        }

        const statCust = document.getElementById('dashStatCustomers');
        const statCustSub = document.getElementById('dashStatCustomersSub');
        if (statCust) statCust.textContent = stats.customers.total ?? 0;
        if (statCustSub) {
            statCustSub.textContent = `${stats.customers.active ?? 0} active accounts`;
        }

        const statProd = document.getElementById('dashStatProducts');
        const statProdSub = document.getElementById('dashStatProductsSub');
        if (statProd) statProd.textContent = stats.products.total ?? 0;
        if (statProdSub) {
            statProdSub.textContent = `${stats.products.active ?? 0} active · ${stats.products.untracked ?? 0} untracked`;
        }

        // Status Breakdown Counts
        const byStatus = stats.orders.by_status || {};
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? 0;
        };

        setVal('dashCountPending', byStatus.pending);
        setVal('dashCountConfirmed', byStatus.confirmed);
        setVal('dashCountInProgress', byStatus.in_progress);
        setVal('dashCountReady', byStatus.ready);
        setVal('dashCountDelivered', byStatus.delivered);
        setVal('dashCountCancelled', byStatus.cancelled);

        const byPay = stats.orders.by_payment || {};
        setVal('dashCountPaidPay', byPay.paid);
        setVal('dashCountPendingPay', byPay.pending);
        setVal('dashCountFailedPay', byPay.failed);

        // Recent Orders List
        const recentContainer = document.getElementById('dashRecentOrdersList');
        if (recentContainer) {
            const recents = res.data.recent_orders || [];
            if (recents.length === 0) {
                recentContainer.innerHTML = '<div class="admin-state-box" style="padding:15px;"><span class="state-icon">📭</span><p>No customer orders placed yet.</p></div>';
            } else {
                recentContainer.innerHTML = recents.map(ord => `
                    <div class="mini-order" style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--line);">
                        <div>
                            <b>#${escapeHtml(ord.order_number)} · ${escapeHtml(ord.full_name)}</b>
                            <span style="font-size:11px; color:var(--muted);">${formatDate(ord.delivery_date)} · ${formatMoney(ord.total)} (${escapeHtml(ord.item_count)} item${ord.item_count === 1 ? '' : 's'})</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            ${getOrderStatusBadge(ord.order_status)}
                            <button type="button" class="admin-action-btn" data-action="view-order" data-order-id="${escapeHtml(ord.order_id)}" style="padding:3px 8px; font-size:11px;">View</button>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Inventory Alerts Panel
        const alertsContainer = document.getElementById('dashInventoryAlerts');
        if (alertsContainer) {
            const alerts = res.data.low_stock_alerts || [];
            const untrackedCount = stats.products.untracked ?? 0;
            const outOfStockCount = stats.products.out_of_stock ?? 0;

            let alertsHtml = '';

            // Notice about untracked vs tracked
            alertsHtml += `
                <div style="background:#faf8f6; border:1px solid var(--line); border-radius:10px; padding:10px 14px; margin-bottom:12px; font-size:12px; color:var(--muted); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="color:var(--ink);">Catalog Inventory State:</strong>
                        ${stats.products.active ?? 0} active products ·
                        ${untrackedCount} made-to-order (untracked) ·
                        ${outOfStockCount} out of stock.
                    </div>
                    <span class="badge-status stock-untracked">${untrackedCount} Untracked</span>
                </div>
            `;

            if (alerts.length === 0 && outOfStockCount === 0) {
                alertsHtml += '<div style="padding:12px; text-align:center; font-size:12px; color:#166534; background:#f0fdf4; border-radius:8px;">✓ All tracked inventory is currently healthy with no low-stock alerts.</div>';
            } else if (alerts.length > 0) {
                alertsHtml += '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:10px;">';
                alerts.forEach(item => {
                    alertsHtml += `
                        <div style="background:#fff; border:1px solid #fed7aa; border-radius:10px; padding:10px 12px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="font-size:12px; color:var(--ink);">${escapeHtml(item.cake_name)}</strong>
                                <span style="font-size:11px; color:var(--muted); display:block;">${formatMoney(item.price)}</span>
                            </div>
                            <span class="badge-status stock-low">${item.stock_quantity} left</span>
                        </div>
                    `;
                });
                alertsHtml += '</div>';
            }

            alertsContainer.innerHTML = alertsHtml;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MODULE 2: ORDERS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────

    async function loadOrders() {
        const tbody = document.getElementById('ordersTbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8"><div class="admin-state-box"><span class="state-icon">⏳</span><p>Loading orders from database...</p></div></td></tr>';

        const params = new URLSearchParams();
        if (_ordersState.status) params.append('status', _ordersState.status);
        if (_ordersState.payment) params.append('payment', _ordersState.payment);
        if (_ordersState.search) params.append('search', _ordersState.search);
        if (_ordersState.date) params.append('date', _ordersState.date);

        const res = await adminFetch('api/admin-orders.php?' + params.toString());
        if (!res.ok || !res.data) {
            tbody.innerHTML = '<tr><td colspan="8"><div class="admin-state-box"><span class="state-icon">⚠️</span><h4>Failed to load orders</h4><p>Unable to connect to order API.</p><button type="button" class="btn btn-primary btn-sm" id="retryOrdersBtn">Try again</button></div></td></tr>';
            return;
        }

        const orders = res.data.orders || [];
        const statusCounts = res.data.status_counts || {};

        // Update status chips count
        const setChip = (id, count) => {
            const el = document.getElementById(id);
            if (el) el.textContent = count ?? 0;
        };
        setChip('chipCountPending', statusCounts.pending);
        setChip('chipCountConfirmed', statusCounts.confirmed);
        setChip('chipCountInProgress', statusCounts.in_progress);
        setChip('chipCountReady', statusCounts.ready);
        setChip('chipCountDelivered', statusCounts.delivered);
        setChip('chipCountCancelled', statusCounts.cancelled);

        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8"><div class="admin-state-box"><span class="state-icon">📦</span><h4>No orders found</h4><p>No customer orders match the selected filters.</p></div></td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(ord => `
            <tr>
                <td><strong>#${escapeHtml(ord.order_number)}</strong></td>
                <td>
                    <span class="admin-item-name">${escapeHtml(ord.full_name)}</span>
                    <span class="admin-item-sub">${escapeHtml(ord.phone_no)}</span>
                </td>
                <td>
                    <span>${formatDate(ord.delivery_date)}</span>
                    <span class="admin-item-sub">${escapeHtml(ord.delivery_time_slot)}</span>
                </td>
                <td>
                    <span title="${escapeHtml(ord.items_summary || '')}">${escapeHtml(ord.items_summary || (ord.item_count + ' items'))}</span>
                    <span class="admin-item-sub">${escapeHtml(ord.total_quantity)} total item(s)</span>
                </td>
                <td><strong>${formatMoney(ord.total)}</strong></td>
                <td>${getPaymentStatusBadge(ord.payment_status)}</td>
                <td>${getOrderStatusBadge(ord.order_status)}</td>
                <td>
                    <button type="button" class="admin-action-btn" data-action="view-order" data-order-id="${escapeHtml(ord.order_id)}">
                        View Details
                    </button>
                </td>
            </tr>
        `).join('');
    }

    async function viewOrderDetails(orderId) {
        const modal = document.getElementById('adminOrderModal');
        const modalBody = document.getElementById('orderModalBody');
        const modalTitle = document.getElementById('orderModalTitle');
        const modalSubtitle = document.getElementById('orderModalSubtitle');

        if (!modal || !modalBody) return;

        modalTitle.textContent = 'Loading Order...';
        modalSubtitle.textContent = 'Fetching details from database';
        modalBody.innerHTML = '<div class="admin-state-box"><span class="state-icon">⏳</span><p>Loading order details...</p></div>';
        modal.classList.add('open');

        const res = await adminFetch(`api/admin-orders.php?id=${encodeURIComponent(orderId)}`);
        if (!res.ok || !res.data || !res.data.order) {
            modalBody.innerHTML = '<div class="admin-state-box"><span class="state-icon">⚠️</span><p>Could not load order details.</p></div>';
            return;
        }

        const ord = res.data.order;
        const validOrderStatuses = res.data.valid_order_statuses || ['pending', 'confirmed', 'in_progress', 'ready', 'delivered', 'cancelled'];
        const validPaymentStatuses = res.data.valid_payment_statuses || ['pending', 'paid', 'failed', 'cancelled', 'charged_back'];

        modalTitle.textContent = `Order #${ord.order_number}`;
        modalSubtitle.textContent = `Placed on ${formatDateTime(ord.created_at)} · Payment: ${ord.payment_method.toUpperCase()}`;

        let itemsRows = '';
        (ord.items || []).forEach(item => {
            itemsRows += `
                <tr>
                    <td>
                        <div class="admin-item-cell">
                            ${item.cake_image ? `<img src="${escapeHtml(item.cake_image)}" class="admin-item-thumb" alt="">` : ''}
                            <div>
                                <span class="admin-item-name">${escapeHtml(item.cake_name)}</span>
                                <span class="admin-item-sub">Weight: ${escapeHtml(item.weight || '500g')}${item.special_note ? ` · Note: "${escapeHtml(item.special_note)}"` : ''}</span>
                            </div>
                        </div>
                    </td>
                    <td>${formatMoney(item.unit_price)}</td>
                    <td>${escapeHtml(item.quantity)}</td>
                    <td><strong>${formatMoney(item.line_total)}</strong></td>
                </tr>
            `;
        });

        modalBody.innerHTML = `
            <!-- Customer & Delivery Meta Grid -->
            <div class="order-detail-meta-grid">
                <div>
                    <strong>Customer Information</strong>
                    <div>${escapeHtml(ord.full_name)}</div>
                    <div style="color:var(--muted); font-size:11px;">${escapeHtml(ord.email)}</div>
                    <div>Phone: <a href="tel:${escapeHtml(ord.phone_no)}" style="color:var(--berry); font-weight:600;">${escapeHtml(ord.phone_no)}</a></div>
                </div>
                <div>
                    <strong>Delivery Schedule</strong>
                    <div>Date: <strong>${formatDate(ord.delivery_date)}</strong></div>
                    <div>Slot: ${escapeHtml(ord.delivery_time_slot)}</div>
                </div>
                <div style="grid-column: span 2;">
                    <strong>Delivery Address</strong>
                    <div>${escapeHtml(ord.delivery_address)}</div>
                    ${ord.special_instructions ? `<div style="margin-top:4px; font-style:italic; color:var(--muted);">Instructions: ${escapeHtml(ord.special_instructions)}</div>` : ''}
                </div>
            </div>

            <!-- Items Table -->
            <h4 style="margin:16px 0 8px; font-size:14px; color:var(--ink);">Ordered Line Items</h4>
            <div class="admin-table-wrap">
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th>Unit Price</th>
                            <th>Qty</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsRows}
                    </tbody>
                </table>
            </div>

            <!-- Totals Summary -->
            <div class="order-totals-summary">
                <div>Subtotal: <strong>${formatMoney(ord.subtotal)}</strong></div>
                <div>Delivery Fee: <strong>${formatMoney(ord.delivery_fee)}</strong></div>
                <div class="grand-total">Total: ${formatMoney(ord.total)}</div>
                <div style="font-size:11px; color:var(--muted); margin-top:2px;">
                    Payment Status: ${getPaymentStatusBadge(ord.payment_status)}
                    ${ord.payhere_payment_id ? ` · PayHere Ref: #${escapeHtml(ord.payhere_payment_id)}` : ''}
                </div>
            </div>

            <!-- Order Status & Payment Status Controls -->
            <div class="order-status-actions-box">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Update Order Status</label>
                    <div style="display:flex; gap:8px;">
                        <select class="admin-filter-select" id="modalOrderStatusSelect" style="flex:1;">
                            ${validOrderStatuses.map(st => `<option value="${escapeHtml(st)}" ${st === ord.order_status ? 'selected' : ''}>${escapeHtml(st.replace('_', ' '))}</option>`).join('')}
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" id="modalSaveOrderStatusBtn" data-order-id="${escapeHtml(ord.order_id)}">Save</button>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px;">Update Payment Status</label>
                    <div style="display:flex; gap:8px;">
                        <select class="admin-filter-select" id="modalPaymentStatusSelect" style="flex:1;">
                            ${validPaymentStatuses.map(st => `<option value="${escapeHtml(st)}" ${st === ord.payment_status ? 'selected' : ''}>${escapeHtml(st.replace('_', ' '))}</option>`).join('')}
                        </select>
                        <button type="button" class="btn btn-light btn-sm" id="modalSavePaymentStatusBtn" data-order-id="${escapeHtml(ord.order_id)}">Save</button>
                    </div>
                </div>
            </div>
        `;

        // Wire up status update events inside modal
        const saveOrderBtn = document.getElementById('modalSaveOrderStatusBtn');
        if (saveOrderBtn) {
            saveOrderBtn.onclick = async () => {
                const newStatus = document.getElementById('modalOrderStatusSelect')?.value;
                if (!newStatus) return;
                if (!confirm(`Are you sure you want to update order #${ord.order_number} status to '${newStatus}'?`)) {
                    return;
                }
                saveOrderBtn.disabled = true;
                saveOrderBtn.textContent = 'Updating...';
                const upRes = await adminFetch('api/admin-orders.php', {
                    method: 'POST',
                    body: {
                        action: 'update_status',
                        order_id: ord.order_id,
                        order_status: newStatus
                    }
                });
                saveOrderBtn.disabled = false;
                saveOrderBtn.textContent = 'Save';
                if (upRes.ok) {
                    showToast(`Order #${ord.order_number} status updated to '${newStatus}'.`);
                    viewOrderDetails(ord.order_id);
                    loadOrders();
                    loadDashboard();
                } else {
                    showToast(upRes.data?.message || 'Failed to update order status.');
                }
            };
        }

        const savePayBtn = document.getElementById('modalSavePaymentStatusBtn');
        if (savePayBtn) {
            savePayBtn.onclick = async () => {
                const newPayStatus = document.getElementById('modalPaymentStatusSelect')?.value;
                if (!newPayStatus) return;
                if (ord.payment_method === 'card' && (newPayStatus === 'cancelled' || newPayStatus === 'charged_back')) {
                    const refundWarning = "Warning: This action updates the payment status in the local KúkiCakes database only. It does NOT issue a refund through PayHere. If a refund is required, process it separately through your PayHere Merchant Portal before recording the appropriate status. Do you want to continue?";
                    if (!confirm(refundWarning)) {
                        savePayBtn.disabled = false;
                        savePayBtn.textContent = 'Save';
                        return;
                    }
                } else {
                    const warnMsg = newPayStatus === 'paid' && ord.payment_status !== 'paid'
                        ? `Are you sure you want to mark order #${ord.order_number} as PAID? This will confirm payment and deduct product stock if applicable.`
                        : `Update order #${ord.order_number} payment status to '${newPayStatus}'?`;
                    if (!confirm(warnMsg)) {
                        return;
                    }
                }
                savePayBtn.disabled = true;
                savePayBtn.textContent = 'Updating...';
                const upPayRes = await adminFetch('api/admin-orders.php', {
                    method: 'POST',
                    body: {
                        action: 'update_payment_status',
                        order_id: ord.order_id,
                        payment_status: newPayStatus
                    }
                });
                savePayBtn.disabled = false;
                savePayBtn.textContent = 'Save';
                if (upPayRes.ok) {
                    showToast(`Order #${ord.order_number} payment status updated to '${newPayStatus}'.`);
                    viewOrderDetails(ord.order_id);
                    loadOrders();
                    loadDashboard();
                } else {
                    showToast(upPayRes.data?.message || 'Failed to update payment status.');
                }
            };
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MODULE 3: PRODUCT MANAGEMENT
    // ─────────────────────────────────────────────────────────────────

    async function loadProducts() {
        const tbody = document.getElementById('productsTbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">⏳</span><p>Loading products from database...</p></div></td></tr>';

        const params = new URLSearchParams();
        if (_productsState.search) params.append('search', _productsState.search);
        if (_productsState.cat) params.append('cat', _productsState.cat);
        if (_productsState.availability) params.append('availability', _productsState.availability);
        if (_productsState.stock) params.append('stock', _productsState.stock);

        const res = await adminFetch('api/admin-products.php?' + params.toString());
        if (!res.ok || !res.data) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">⚠️</span><h4>Failed to load products</h4><p>Unable to connect to product API.</p><button type="button" class="btn btn-primary btn-sm" id="retryProductsBtn">Try again</button></div></td></tr>';
            return;
        }

        // Cache system categories if provided
        if (Array.isArray(res.data.categories) && res.data.categories.length > 0) {
            _systemCategories = res.data.categories;
            populateCategoryDropdowns();
        }

        const prods = res.data.products || [];
        if (prods.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">🧁</span><h4>No cakes found</h4><p>No products match your current filters.</p></div></td></tr>';
            return;
        }

        tbody.innerHTML = prods.map(p => {
            const catBadges = (p.categories || []).map(c => `<span class="badge-cat">${escapeHtml(c.name)}</span>`).join('');
            const isAvail = p.availability == 1;

            return `
                <tr>
                    <td>
                        <div class="admin-item-cell">
                            <img src="${escapeHtml(p.image || p.img || 'assets/images/cakes/chocolate-fudge.jpg')}" class="admin-item-thumb" alt="" onerror="this.src='docs/logo1_1.png'">
                            <div>
                                <span class="admin-item-name">${escapeHtml(p.name || p.cake_name)}</span>
                                ${p.badge ? `<span style="font-size:10px; background:var(--peach); padding:1px 5px; border-radius:4px; font-weight:600; color:var(--ink);">${escapeHtml(p.badge)}</span>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>${catBadges || '<span style="color:var(--muted); font-size:11px;">Uncategorized</span>'}</td>
                    <td><strong>${formatMoney(p.price)}</strong></td>
                    <td>${getStockBadge(p.stock_quantity)}</td>
                    <td>
                        <span class="badge-status ${isAvail ? 'st-delivered' : 'st-cancelled'}">
                            ${isAvail ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <div class="admin-btn-group">
                            <button type="button" class="admin-action-btn" data-action="edit-product" data-product-id="${escapeHtml(p.id)}">
                                Edit
                            </button>
                            <button type="button" class="admin-action-btn" data-action="toggle-product" data-product-id="${escapeHtml(p.id)}" data-current-avail="${escapeHtml(p.availability)}">
                                ${isAvail ? 'Hide' : 'Show'}
                            </button>
                            <button type="button" class="admin-action-btn danger" data-action="delete-product" data-product-id="${escapeHtml(p.id)}" data-product-name="${escapeHtml(p.name || p.cake_name)}">
                                🗑
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function populateCategoryDropdowns() {
        const prodCatSelect = document.getElementById('productsCategoryFilter');
        const invCatSelect = document.getElementById('inventoryCategoryFilter');

        const optionsHtml = '<option value="">All Categories</option>' +
            _systemCategories.map(c => `<option value="${escapeHtml(c.slug || c.category_id)}">${escapeHtml(c.category_name)}</option>`).join('');

        if (prodCatSelect && prodCatSelect.options.length <= 1) {
            prodCatSelect.innerHTML = optionsHtml;
            prodCatSelect.value = _productsState.cat;
        }
        if (invCatSelect && invCatSelect.options.length <= 1) {
            invCatSelect.innerHTML = optionsHtml;
            invCatSelect.value = _inventoryState.cat;
        }

        // Also populate checkboxes in product form
        const chkContainer = document.getElementById('prodFormCategoryCheckboxes');
        if (chkContainer && chkContainer.children.length === 0) {
            chkContainer.innerHTML = _systemCategories.map(c => `
                <label class="admin-checkbox-label">
                    <input type="checkbox" name="product_category" value="${escapeHtml(c.category_id)}">
                    ${escapeHtml(c.category_name)}
                </label>
            `).join('');
        }
    }

    async function openProductForm(cakeId = null) {
        const modal = document.getElementById('adminProductModal');
        const titleEl = document.getElementById('productModalTitle');
        const subEl = document.getElementById('productModalSubtitle');
        const form = document.getElementById('adminProductForm');
        const errBanner = document.getElementById('prodFormErrorBanner');

        if (!modal || !form) return;
        errBanner.style.display = 'none';

        // Ensure category checkboxes exist
        populateCategoryDropdowns();

        if (cakeId) {
            titleEl.textContent = 'Edit Product';
            subEl.textContent = `Update details for cake ID #${cakeId}`;
            document.getElementById('prodFormCakeId').value = cakeId;

            // Fetch single product data
            const res = await adminFetch(`api/admin-products.php?id=${encodeURIComponent(cakeId)}`);
            if (!res.ok || !res.data || !res.data.product) {
                showToast('Could not load product details.');
                return;
            }
            const p = res.data.product;
            document.getElementById('prodFormName').value = p.name || p.cake_name || '';
            document.getElementById('prodFormPrice').value = p.price ?? '';
            document.getElementById('prodFormDesc').value = p.description || p.desc || '';
            document.getElementById('prodFormImage').value = p.image || p.img || '';
            document.getElementById('prodFormBadge').value = p.badge || '';
            document.getElementById('prodFormStock').value = p.stock_quantity !== null && p.stock_quantity !== undefined ? p.stock_quantity : '';
            document.getElementById('prodFormAvail').value = p.availability ?? '1';

            // Check selected categories
            const selectedCatIds = (p.category_ids || []).map(Number);
            document.querySelectorAll('input[name="product_category"]').forEach(chk => {
                chk.checked = selectedCatIds.includes(parseInt(chk.value, 10));
            });
        } else {
            titleEl.textContent = 'Add New Cake';
            subEl.textContent = 'Fill in the details to add a new cake to your catalog.';
            form.reset();
            document.getElementById('prodFormCakeId').value = '';
            document.getElementById('prodFormAvail').value = '1';
            document.querySelectorAll('input[name="product_category"]').forEach(chk => chk.checked = false);
        }

        modal.classList.add('open');
    }

    async function handleProductFormSubmit(e) {
        e.preventDefault();
        const errBanner = document.getElementById('prodFormErrorBanner');
        errBanner.style.display = 'none';

        const cakeId = document.getElementById('prodFormCakeId').value;
        const name = document.getElementById('prodFormName').value.trim();
        const price = parseFloat(document.getElementById('prodFormPrice').value);
        const desc = document.getElementById('prodFormDesc').value.trim();
        const image = document.getElementById('prodFormImage').value.trim();
        const badge = document.getElementById('prodFormBadge').value.trim();
        const stockRaw = document.getElementById('prodFormStock').value.trim();
        const avail = parseInt(document.getElementById('prodFormAvail').value, 10);

        const categoryIds = [];
        document.querySelectorAll('input[name="product_category"]:checked').forEach(chk => {
            categoryIds.push(parseInt(chk.value, 10));
        });

        // Validation
        if (name.length < 2 || name.length > 150) {
            errBanner.textContent = 'Product name must be between 2 and 150 characters.';
            errBanner.style.display = 'block';
            return;
        }
        if (isNaN(price) || price < 0) {
            errBanner.textContent = 'Please enter a valid non-negative price.';
            errBanner.style.display = 'block';
            return;
        }
        if (categoryIds.length === 0) {
            errBanner.textContent = 'Please select at least one category for this cake.';
            errBanner.style.display = 'block';
            return;
        }

        const payload = {
            action: cakeId ? 'update' : 'create',
            cake_name: name,
            price: price,
            description: desc,
            image: image,
            badge: badge,
            availability: avail,
            stock_quantity: stockRaw !== '' ? parseInt(stockRaw, 10) : null,
            category_ids: categoryIds
        };
        if (cakeId) payload.cake_id = parseInt(cakeId, 10);

        const submitBtn = document.getElementById('prodFormSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const res = await adminFetch('api/admin-products.php', {
            method: 'POST',
            body: payload
        });

        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Product';

        if (res.ok) {
            showToast(cakeId ? 'Product updated successfully.' : 'Product created successfully.');
            document.getElementById('adminProductModal')?.classList.remove('open');
            loadProducts();
            loadDashboard();
        } else {
            errBanner.textContent = res.data?.message || 'Failed to save product. Please try again.';
            errBanner.style.display = 'block';
        }
    }

    async function toggleProductAvailability(cakeId, currentAvail) {
        const newAvail = parseInt(currentAvail, 10) === 1 ? 0 : 1;
        const res = await adminFetch('api/admin-products.php', {
            method: 'POST',
            body: {
                action: 'toggle_availability',
                cake_id: cakeId,
                availability: newAvail
            }
        });

        if (res.ok) {
            showToast(newAvail === 1 ? 'Product activated and visible.' : 'Product hidden from catalog.');
            loadProducts();
            loadDashboard();
        } else {
            showToast(res.data?.message || 'Failed to update availability.');
        }
    }

    async function deleteProduct(cakeId, cakeName) {
        if (!confirm(`Are you sure you want to delete or deactivate "${cakeName}"?`)) {
            return;
        }

        const res = await adminFetch('api/admin-products.php', {
            method: 'POST',
            body: {
                action: 'delete',
                cake_id: cakeId
            }
        });

        if (res.ok) {
            showToast(res.data?.message || 'Product deleted successfully.');
            loadProducts();
            loadDashboard();
        } else {
            showToast(res.data?.message || 'Failed to delete product.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MODULE 4: INVENTORY MANAGEMENT
    // ─────────────────────────────────────────────────────────────────

    async function loadInventory() {
        const tbody = document.getElementById('inventoryTbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">⏳</span><p>Loading inventory from database...</p></div></td></tr>';

        const params = new URLSearchParams();
        if (_inventoryState.search) params.append('search', _inventoryState.search);
        if (_inventoryState.cat) params.append('cat', _inventoryState.cat);

        if (_inventoryState.health === 'low') params.append('low_stock', '1');
        if (_inventoryState.health === 'out') params.append('out_of_stock', '1');
        if (_inventoryState.health === 'untracked') params.append('untracked', '1');

        const res = await adminFetch('api/admin-inventory.php?' + params.toString());
        if (!res.ok || !res.data) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">⚠️</span><h4>Failed to load inventory</h4><p>Unable to connect to inventory API.</p><button type="button" class="btn btn-primary btn-sm" id="retryInventoryBtn">Try again</button></div></td></tr>';
            return;
        }

        const summary = res.data.summary || {};
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? 0;
        };

        setVal('invStatTotal', summary.total_items);
        setVal('invStatHealthy', summary.healthy_stock_count);
        setVal('invStatLow', summary.low_stock_count);
        setVal('invStatOut', summary.out_of_stock_count);
        setVal('invStatUntracked', summary.untracked_count);

        const items = res.data.items || [];
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="admin-state-box"><span class="state-icon">📦</span><h4>No inventory records match</h4><p>Try clearing your search or status filter.</p></div></td></tr>';
            return;
        }

        tbody.innerHTML = items.map(it => {
            const isUntracked = it.stock_quantity === null || it.stock_quantity === undefined;
            const stockDisplay = isUntracked
                ? '<span style="color:#645e5a; font-style:italic;">Untracked (Made to order)</span>'
                : `<strong style="font-size:14px;">${escapeHtml(it.stock_quantity)} units</strong>`;

            return `
                <tr>
                    <td>
                        <div class="admin-item-cell">
                            <img src="${escapeHtml(it.image || 'assets/images/cakes/chocolate-fudge.jpg')}" class="admin-item-thumb" alt="" onerror="this.src='docs/logo1_1.png'">
                            <div>
                                <span class="admin-item-name">${escapeHtml(it.cake_name)}</span>
                                <span class="admin-item-sub">ID #${escapeHtml(it.cake_id)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span style="font-size:12px; color:var(--muted);">${escapeHtml(it.categories || 'General')}</span></td>
                    <td><strong>${formatMoney(it.price)}</strong></td>
                    <td>${stockDisplay}</td>
                    <td>${getStockBadge(it.stock_quantity)}</td>
                    <td>
                        <div class="stock-adjust-box">
                            <button type="button" class="btn-stock-step" data-action="adjust-stock" data-cake-id="${escapeHtml(it.cake_id)}" data-delta="-1" data-cake-name="${escapeHtml(it.cake_name)}" title="Decrease by 1" ${it.stock_quantity === 0 ? 'disabled' : ''}>-</button>
                            <button type="button" class="btn-stock-step" data-action="adjust-stock" data-cake-id="${escapeHtml(it.cake_id)}" data-delta="1" data-cake-name="${escapeHtml(it.cake_name)}" title="Increase by 1">+</button>
                            <button type="button" class="admin-action-btn" data-action="set-stock" data-cake-id="${escapeHtml(it.cake_id)}" data-current-stock="${isUntracked ? '' : escapeHtml(it.stock_quantity)}" data-cake-name="${escapeHtml(it.cake_name)}" style="padding:4px 8px; margin-left:4px;">
                                Set
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function adjustStock(cakeId, delta, cakeName) {
        const promptMsg = delta > 0
            ? `Increase stock for "${cakeName}" by ${delta}?`
            : `Decrease stock for "${cakeName}" by 1?`;

        if (!confirm(promptMsg)) return;

        const res = await adminFetch('api/admin-inventory.php', {
            method: 'POST',
            body: {
                action: 'adjust_stock',
                cake_id: cakeId,
                adjustment: delta
            }
        });

        if (res.ok) {
            showToast(res.data?.message || 'Stock updated.');
            loadInventory();
            loadDashboard();
        } else {
            showToast(res.data?.message || 'Failed to adjust stock.');
        }
    }

    function openStockModal(cakeId, currentStock, cakeName) {
        const modal = document.getElementById('adminStockModal');
        const titleEl = document.getElementById('stockModalTitle');
        const cakeIdInput = document.getElementById('stockModalCakeId');
        const qtyInput = document.getElementById('stockModalInput');
        const untrackedChk = document.getElementById('stockModalUntrackedCheckbox');
        const errBanner = document.getElementById('stockModalErrorBanner');

        if (!modal) return;
        errBanner.style.display = 'none';

        titleEl.textContent = `Set Stock: ${cakeName}`;
        cakeIdInput.value = cakeId;

        if (currentStock === '' || currentStock === null || currentStock === undefined) {
            untrackedChk.checked = true;
            qtyInput.value = '';
            qtyInput.disabled = true;
        } else {
            untrackedChk.checked = false;
            qtyInput.value = currentStock;
            qtyInput.disabled = false;
        }

        modal.classList.add('open');
    }

    async function handleStockFormSubmit(e) {
        e.preventDefault();
        const errBanner = document.getElementById('stockModalErrorBanner');
        errBanner.style.display = 'none';

        const cakeId = document.getElementById('stockModalCakeId').value;
        const untracked = document.getElementById('stockModalUntrackedCheckbox').checked;
        const qtyRaw = document.getElementById('stockModalInput').value.trim();

        let stockVal = null;
        if (!untracked) {
            if (qtyRaw === '' || isNaN(qtyRaw) || parseInt(qtyRaw, 10) < 0) {
                errBanner.textContent = 'Please enter a valid non-negative stock quantity or check Untracked.';
                errBanner.style.display = 'block';
                return;
            }
            stockVal = parseInt(qtyRaw, 10);
        }

        const submitBtn = document.getElementById('stockModalSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const res = await adminFetch('api/admin-inventory.php', {
            method: 'POST',
            body: {
                action: 'update_stock',
                cake_id: parseInt(cakeId, 10),
                stock_quantity: stockVal
            }
        });

        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Stock';

        if (res.ok) {
            showToast('Stock quantity updated successfully.');
            document.getElementById('adminStockModal')?.classList.remove('open');
            loadInventory();
            loadDashboard();
        } else {
            errBanner.textContent = res.data?.message || 'Failed to update stock.';
            errBanner.style.display = 'block';
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Tab Switching & Navigation
    // ─────────────────────────────────────────────────────────────────

    function switchAdminTab(tab) {
        if (!['dashboard', 'orders', 'products', 'inventory'].includes(tab)) {
            tab = 'dashboard';
        }
        _activeTab = tab;

        // Toggle sidebar active button
        document.querySelectorAll('.admin-menu button[data-admin-tab]').forEach(btn => {
            const btnTab = btn.getAttribute('data-admin-tab') || btn.dataset?.adminTab;
            btn.classList.toggle('active', btnTab === tab);
        });

        // Toggle view containers
        document.querySelectorAll('.admin-view').forEach(view => {
            const isTarget = view.id === `admin-view-${tab}`;
            view.classList.toggle('active', isTarget);
            view.style.display = isTarget ? 'block' : 'none';
        });

        // Trigger module load
        if (tab === 'dashboard') loadDashboard();
        if (tab === 'orders') loadOrders();
        if (tab === 'products') loadProducts();
        if (tab === 'inventory') loadInventory();
    }

    // ─────────────────────────────────────────────────────────────────
    // Event Listeners & Binding
    // ─────────────────────────────────────────────────────────────────

    function bindEvents() {
        // Event delegation on document
        document.addEventListener('click', (e) => {
            const adminPage = document.getElementById('admin-page');
            if (!adminPage || !adminPage.classList.contains('active')) return;

            // Tab click
            const tabBtn = e.target.closest('[data-admin-tab]');
            if (tabBtn) {
                e.preventDefault();
                const tab = tabBtn.getAttribute('data-admin-tab') || tabBtn.dataset?.adminTab;
                if (tab) switchAdminTab(tab);
                return;
            }

            // Exit button
            if (e.target.closest('#adminExitBtn')) {
                e.preventDefault();
                if (typeof window.goPage === 'function') window.goPage('home');
                return;
            }

            // Action: View Order
            const viewOrderBtn = e.target.closest('[data-action="view-order"]');
            if (viewOrderBtn) {
                const orderId = viewOrderBtn.getAttribute('data-order-id') || viewOrderBtn.dataset?.orderId;
                if (orderId) viewOrderDetails(orderId);
                return;
            }

            // Action: Edit Product
            const editProdBtn = e.target.closest('[data-action="edit-product"]');
            if (editProdBtn) {
                const prodId = editProdBtn.getAttribute('data-product-id') || editProdBtn.dataset?.productId;
                if (prodId) openProductForm(prodId);
                return;
            }

            // Action: Toggle Product Availability
            const toggleProdBtn = e.target.closest('[data-action="toggle-product"]');
            if (toggleProdBtn) {
                const prodId = toggleProdBtn.getAttribute('data-product-id') || toggleProdBtn.dataset?.productId;
                const currAvail = toggleProdBtn.getAttribute('data-current-avail') || toggleProdBtn.dataset?.currentAvail;
                if (prodId) toggleProductAvailability(prodId, currAvail);
                return;
            }

            // Action: Delete Product
            const delProdBtn = e.target.closest('[data-action="delete-product"]');
            if (delProdBtn) {
                const prodId = delProdBtn.getAttribute('data-product-id') || delProdBtn.dataset?.productId;
                const prodName = delProdBtn.getAttribute('data-product-name') || delProdBtn.dataset?.productName;
                if (prodId) deleteProduct(prodId, prodName);
                return;
            }

            // Action: Adjust Stock Step
            const stepStockBtn = e.target.closest('[data-action="adjust-stock"]');
            if (stepStockBtn) {
                const cakeId = stepStockBtn.getAttribute('data-cake-id') || stepStockBtn.dataset?.cakeId;
                const delta = parseInt(stepStockBtn.getAttribute('data-delta') || stepStockBtn.dataset?.delta, 10);
                const cakeName = stepStockBtn.getAttribute('data-cake-name') || stepStockBtn.dataset?.cakeName;
                if (cakeId && !isNaN(delta)) adjustStock(cakeId, delta, cakeName);
                return;
            }

            // Action: Set Stock Dialog
            const setStockBtn = e.target.closest('[data-action="set-stock"]');
            if (setStockBtn) {
                const cakeId = setStockBtn.getAttribute('data-cake-id') || setStockBtn.dataset?.cakeId;
                const currStock = setStockBtn.getAttribute('data-current-stock') || setStockBtn.dataset?.currentStock;
                const cakeName = setStockBtn.getAttribute('data-cake-name') || setStockBtn.dataset?.cakeName;
                if (cakeId) openStockModal(cakeId, currStock, cakeName);
                return;
            }

            // Status chip click
            const chipBtn = e.target.closest('.admin-chip[data-chip-status]');
            if (chipBtn) {
                document.querySelectorAll('.admin-chip[data-chip-status]').forEach(c => c.classList.remove('active'));
                chipBtn.classList.add('active');
                _ordersState.status = chipBtn.getAttribute('data-chip-status') ?? '';
                loadOrders();
                return;
            }

            // Dashboard quick navigations
            if (e.target.closest('#dashGoOrdersBtn')) {
                switchAdminTab('orders');
                return;
            }
            if (e.target.closest('#dashGoInventoryBtn')) {
                switchAdminTab('inventory');
                return;
            }
            if (e.target.closest('#dashNewProductBtn') || e.target.closest('#productsAddNewBtn')) {
                openProductForm();
                return;
            }

            // Refresh buttons
            if (e.target.closest('#dashRefreshBtn')) { loadDashboard(); return; }
            if (e.target.closest('#ordersRefreshBtn') || e.target.closest('#retryOrdersBtn')) { loadOrders(); return; }
            if (e.target.closest('#productsRefreshBtn') || e.target.closest('#retryProductsBtn')) { loadProducts(); return; }
            if (e.target.closest('#inventoryRefreshBtn') || e.target.closest('#retryInventoryBtn')) { loadInventory(); return; }

            // Modal closes
            if (e.target.closest('#orderModalCloseBtn') || e.target.id === 'adminOrderModal') {
                document.getElementById('adminOrderModal')?.classList.remove('open');
                return;
            }
            if (e.target.closest('#productModalCloseBtn') || e.target.closest('#prodFormCancelBtn') || e.target.id === 'adminProductModal') {
                document.getElementById('adminProductModal')?.classList.remove('open');
                return;
            }
            if (e.target.closest('#stockModalCloseBtn') || e.target.closest('#stockModalCancelBtn') || e.target.id === 'adminStockModal') {
                document.getElementById('adminStockModal')?.classList.remove('open');
                return;
            }
        });

        // Orders filter inputs
        const ordersSearch = document.getElementById('ordersSearchInput');
        if (ordersSearch) {
            ordersSearch.oninput = () => {
                clearTimeout(_ordersState.timer);
                _ordersState.timer = setTimeout(() => {
                    _ordersState.search = ordersSearch.value.trim();
                    loadOrders();
                }, 280);
            };
        }

        const ordersPayment = document.getElementById('ordersPaymentFilter');
        if (ordersPayment) {
            ordersPayment.onchange = () => {
                _ordersState.payment = ordersPayment.value;
                loadOrders();
            };
        }

        const ordersDate = document.getElementById('ordersDateFilter');
        if (ordersDate) {
            ordersDate.onchange = () => {
                _ordersState.date = ordersDate.value;
                loadOrders();
            };
        }

        const ordersClear = document.getElementById('ordersClearFiltersBtn');
        if (ordersClear) {
            ordersClear.onclick = () => {
                if (ordersSearch) ordersSearch.value = '';
                if (ordersPayment) ordersPayment.value = '';
                if (ordersDate) ordersDate.value = '';
                _ordersState.search = '';
                _ordersState.status = '';
                _ordersState.payment = '';
                _ordersState.date = '';
                document.querySelectorAll('.admin-chip[data-chip-status]').forEach(c => c.classList.toggle('active', (c.getAttribute('data-chip-status') || '') === ''));
                loadOrders();
            };
        }

        // Products filter inputs
        const prodSearch = document.getElementById('productsSearchInput');
        if (prodSearch) {
            prodSearch.oninput = () => {
                clearTimeout(_productsState.timer);
                _productsState.timer = setTimeout(() => {
                    _productsState.search = prodSearch.value.trim();
                    loadProducts();
                }, 280);
            };
        }

        const prodCat = document.getElementById('productsCategoryFilter');
        if (prodCat) {
            prodCat.onchange = () => {
                _productsState.cat = prodCat.value;
                loadProducts();
            };
        }

        const prodAvail = document.getElementById('productsAvailabilityFilter');
        if (prodAvail) {
            prodAvail.onchange = () => {
                _productsState.availability = prodAvail.value;
                loadProducts();
            };
        }

        const prodStock = document.getElementById('productsStockFilter');
        if (prodStock) {
            prodStock.onchange = () => {
                _productsState.stock = prodStock.value;
                loadProducts();
            };
        }

        const prodClear = document.getElementById('productsClearFiltersBtn');
        if (prodClear) {
            prodClear.onclick = () => {
                if (prodSearch) prodSearch.value = '';
                if (prodCat) prodCat.value = '';
                if (prodAvail) prodAvail.value = '';
                if (prodStock) prodStock.value = '';
                _productsState.search = '';
                _productsState.cat = '';
                _productsState.availability = '';
                _productsState.stock = '';
                loadProducts();
            };
        }

        // Inventory filter inputs
        const invSearch = document.getElementById('inventorySearchInput');
        if (invSearch) {
            invSearch.oninput = () => {
                clearTimeout(_inventoryState.timer);
                _inventoryState.timer = setTimeout(() => {
                    _inventoryState.search = invSearch.value.trim();
                    loadInventory();
                }, 280);
            };
        }

        const invHealth = document.getElementById('inventoryHealthFilter');
        if (invHealth) {
            invHealth.onchange = () => {
                _inventoryState.health = invHealth.value;
                loadInventory();
            };
        }

        const invCat = document.getElementById('inventoryCategoryFilter');
        if (invCat) {
            invCat.onchange = () => {
                _inventoryState.cat = invCat.value;
                loadInventory();
            };
        }

        const invClear = document.getElementById('inventoryClearFiltersBtn');
        if (invClear) {
            invClear.onclick = () => {
                if (invSearch) invSearch.value = '';
                if (invHealth) invHealth.value = '';
                if (invCat) invCat.value = '';
                _inventoryState.search = '';
                _inventoryState.health = '';
                _inventoryState.cat = '';
                loadInventory();
            };
        }

        // Product Form submit
        const prodForm = document.getElementById('adminProductForm');
        if (prodForm) {
            prodForm.onsubmit = handleProductFormSubmit;
        }

        // Stock Form submit & untracked toggle
        const stockForm = document.getElementById('adminStockForm');
        if (stockForm) {
            stockForm.onsubmit = handleStockFormSubmit;
        }

        const untrackedChk = document.getElementById('stockModalUntrackedCheckbox');
        const stockInput = document.getElementById('stockModalInput');
        if (untrackedChk && stockInput) {
            untrackedChk.onchange = () => {
                stockInput.disabled = untrackedChk.checked;
                if (untrackedChk.checked) stockInput.value = '';
            };
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Public Activation & Lifecycle
    // ─────────────────────────────────────────────────────────────────

    window.initAdminDashboard = function initAdminDashboard() {
        const adminPage = document.getElementById('admin-page');
        if (!adminPage) {
            // Component might still be fetching in auth-loader.js
            if (location.hash.slice(1).split('?')[0].split('&')[0] === 'admin') {
                setTimeout(window.initAdminDashboard, 60);
            }
            return;
        }

        if (!adminPage.classList.contains('active')) {
            if (location.hash.slice(1).split('?')[0].split('&')[0] === 'admin') {
                adminPage.classList.add('active');
            } else {
                return;
            }
        }

        if (!_initialized) {
            bindEvents();
            _initialized = true;
        }

        // Always refresh active tab when activated
        switchAdminTab(_activeTab);
    };

    window.onAdminPageActivated = function onAdminPageActivated() {
        window.initAdminDashboard();
    };

    // Auto-check on hash changes
    window.addEventListener('hashchange', () => {
        const page = location.hash.slice(1).split('?')[0].split('&')[0];
        if (page === 'admin') {
            setTimeout(window.initAdminDashboard, 60);
        }
    });

    // Check if initial hash is #admin or if already active
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            const page = location.hash.slice(1).split('?')[0].split('&')[0];
            if (page === 'admin') {
                setTimeout(window.initAdminDashboard, 100);
            }
        });
    } else {
        const page = location.hash.slice(1).split('?')[0].split('&')[0];
        if (page === 'admin') {
            setTimeout(window.initAdminDashboard, 100);
        }
    }

})();
