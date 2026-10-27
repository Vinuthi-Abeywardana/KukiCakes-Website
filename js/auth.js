/* ================================================================
   KúkiCakes — Authentication Module   (js/auth.js)
   ----------------------------------------------------------------
   Real PHP + MySQL backend authentication.
   Session managed server-side via PHP sessions (KUKI_SESS cookie).
   localStorage is used only for harmless display cache (name/email)
   to avoid an extra network round-trip for navbar rendering, but
   all protected operations always verify the server session first.
   ================================================================ */

(function () {
    'use strict';

    /* ============================================================
       CONSTANTS
       ============================================================ */
    const DISPLAY_KEY = 'kukiUserDisplay'; // safe display cache only (name, email — no secrets)
    const MERGE_KEY   = 'kukiCartMergeShown';

    /* ============================================================
       API HELPERS
       ============================================================ */

    /**
     * POST JSON body to a PHP API endpoint.
     * Returns parsed JSON or throws on network/HTTP error.
     */
    async function apiPost(endpoint, data) {
        const res = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',   // include session cookie
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        json._status = res.status;
        return json;
    }

    async function apiGet(endpoint) {
        const res = await fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
        });
        const json = await res.json();
        json._status = res.status;
        return json;
    }

    async function apiDelete(endpoint) {
        const res = await fetch(endpoint, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        json._status = res.status;
        return json;
    }

    /* ============================================================
       AUTH STATE — backed by server session
       ============================================================ */

    /**
     * Returns the cached display object {name, email} from
     * localStorage, or null if not set.
     * This is purely for fast navbar rendering; never used to
     * authorise any action.
     */
    function getDisplayCache() {
        try { return JSON.parse(localStorage.getItem(DISPLAY_KEY)); }
        catch { return null; }
    }

    function setDisplayCache(userData) {
        const safe = { name: userData.name || '', email: userData.email || '' };
        localStorage.setItem(DISPLAY_KEY, JSON.stringify(safe));
    }

    function clearDisplayCache() {
        localStorage.removeItem(DISPLAY_KEY);
        localStorage.removeItem(MERGE_KEY);
        localStorage.removeItem('kukiUser');
        localStorage.removeItem('kukiPendingUser');
    }

    /* ============================================================
       NAVBAR — guest / authenticated states
       ============================================================ */

    function updateNavbar(user) {
        const navGuest     = document.getElementById('navGuest');
        const navUser      = document.getElementById('navUser');
        const userAvatar   = document.getElementById('userAvatar');
        const userDispName = document.getElementById('userDisplayName');

        if (!navGuest || !navUser) return;

        if (user && user.name) {
            navGuest.hidden = true;
            navUser.hidden  = false;

            const initials = (user.name || 'K')
                .split(' ')
                .filter(Boolean)
                .map(n => n[0].toUpperCase())
                .slice(0, 2)
                .join('');

            if (userAvatar)   userAvatar.textContent   = initials;
            if (userDispName) userDispName.textContent = (user.name || '').split(' ')[0];
        } else {
            navGuest.hidden = false;
            navUser.hidden  = true;
        }
    }

    /**
     * Check the server session and update the navbar accordingly.
     * Uses display cache for instant render, then confirms with server.
     */
    async function initNavbar() {
        // 1. Instant render using cached display info (avoids flash)
        const cached = getDisplayCache();
        if (cached) updateNavbar(cached);

        // 2. Verify with the server
        try {
            const data = await apiGet('api/check-session.php');
            if (data.authenticated && data.user) {
                setDisplayCache(data.user);
                updateNavbar(data.user);
                if (data.user.customer_id && !data.user.is_admin && data.user.role !== 'admin') {
                    syncCartOnLoad();
                }
            } else {
                // Session gone — clear cache and show guest nav
                clearDisplayCache();
                updateNavbar(null);
            }
        } catch (err) {
            // Network issue — fall back to cached state silently
            console.warn('[KúkiCakes] Could not verify session:', err);
        }
    }

    /* ============================================================
       USER DROPDOWN (nav)
       ============================================================ */

    function setupUserMenuDropdown() {
        const btn      = document.getElementById('userMenuBtn');
        const dropdown = document.getElementById('userDropdown');
        if (!btn || !dropdown) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = dropdown.classList.toggle('open');
            btn.setAttribute('aria-expanded', String(open));
        });

        document.addEventListener('click', () => {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        });

        dropdown.addEventListener('click', () => {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    /* ============================================================
       LOGOUT
       ============================================================ */

    async function handleLogout() {
        try {
            await apiPost('api/logout.php', {});
        } catch (err) {
            console.warn('[KúkiCakes] Logout request failed:', err);
        }
        clearDisplayCache();
        _customerOrders = null;
        _ordersFetchPromise = null;
        _currentOrdersFilter = 'all';
        updateNavbar(null);
        if (typeof window.setClientCart === 'function') {
            window.setClientCart([]);
        } else {
            try { localStorage.removeItem('kukiCart'); } catch (_) {}
        }
        _toast('You have been logged out. See you soon! 👋');
        if (typeof goPage === 'function') goPage('home');
    }

    function setupLogoutButtons() {
        document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
        document.getElementById('profileLogoutBtn')?.addEventListener('click', handleLogout);
    }

    /* ============================================================
       PROTECTED PAGE GUARD & ADMIN AUTHORIZATION
       Capture-phase intercept — runs before site.js bubble handler.
       Redirect unauthenticated users to auth-guard/login.
       Auth is verified via server session.
       ============================================================ */

    const PROTECTED = ['profile', 'my-orders', 'account', 'admin'];
    let _adminCheckPromise = null;

    window.guardAdminRoute = async function guardAdminRoute() {
        if (!_adminCheckPromise) {
            _adminCheckPromise = apiGet('api/admin-check.php').finally(() => {
                _adminCheckPromise = null;
            });
        }

        try {
            const res = await _adminCheckPromise;
            if (res && res.success && res.admin) {
                // Legitimate administrator — allow admin page display
                if (typeof window.allowAdminPage === 'function') {
                    window.allowAdminPage();
                } else if (typeof goPage === 'function') {
                    goPage('admin');
                }
                return;
            }

            if (res && res._status === 403) {
                // Authenticated customer — forbidden from admin area
                _toast('Access denied. Administrator privileges required.');
                if (typeof goPage === 'function') goPage('home');
                return;
            }

            // Unauthenticated guest (401 or not authenticated)
            _toast('Please log in with an administrator account.');
            if (typeof goPage === 'function') goPage('login');
        } catch (err) {
            console.warn('[KúkiCakes] Admin authorization check error:', err);
            _toast('Access denied. Administrator privileges required.');
            if (typeof goPage === 'function') goPage('home');
        }
    };

    document.addEventListener('click', async (e) => {
        const link = e.target.closest('[data-page]');
        if (!link) return;

        const page = link.dataset.page;
        if (!PROTECTED.includes(page)) return;

        // Check server session before allowing navigation
        e.stopImmediatePropagation();
        e.preventDefault();

        if (page === 'admin') {
            await window.guardAdminRoute();
            return;
        }

        try {
            const data = await apiGet('api/check-session.php');
            if (!data.authenticated || !data.user || !data.user.customer_id || data.user.is_admin || data.user.role === 'admin') {
                if (data.authenticated && (data.user?.is_admin || data.user?.role === 'admin')) {
                    _toast('Please use a customer account to view this page.');
                }
                if (typeof goPage === 'function') goPage('auth-guard');
                return;
            }
            // Authenticated customer — navigate, then populate
            if (typeof goPage === 'function') goPage(page);
            if (page === 'profile' || page === 'account') setTimeout(populateProfilePage, 30);
            if (page === 'my-orders') setTimeout(() => {
                setupMyOrdersPage();
                loadAndRenderOrders('all', true);
            }, 30);
        } catch (err) {
            // Network error — deny access to be safe
            if (typeof goPage === 'function') goPage('auth-guard');
        }
    }, true);

    /* ============================================================
       CHECKOUT PROTECTION
       ============================================================ */

    function setupCheckoutProtection() {
        const btn = document.getElementById('checkoutBtn');
        if (!btn) return;

        btn.onclick = async () => {
            let cart = [];
            try { cart = JSON.parse(localStorage.getItem('kukiCart') || '[]'); } catch { }

            if (cart.length === 0) {
                _toast('Add a cake before checking out 🎂');
                return;
            }

            try {
                const data = await apiGet('api/check-session.php');
                if (!data.authenticated || !data.user || !data.user.customer_id || data.user.is_admin || data.user.role === 'admin') {
                    if (data.authenticated && (data.user?.is_admin || data.user?.role === 'admin')) {
                        _toast('Administrator accounts cannot place customer orders.');
                    }
                    if (typeof goPage === 'function') goPage('auth-guard');
                } else {
                    if (typeof goPage === 'function') goPage('checkout');
                }
            } catch (err) {
                if (typeof goPage === 'function') goPage('auth-guard');
            }
        };
    }

    /* ============================================================
       PERSISTENT CART SYNCHRONIZATION (Database)
       ============================================================ */

    let _cartSyncTimer = null;

    /**
     * Called whenever cart is modified in site.js.
     * If user is authenticated, debounces a save to MySQL via POST /api/cart.php.
     */
    window.syncCartWithServer = function (items) {
        clearTimeout(_cartSyncTimer);
        _cartSyncTimer = setTimeout(async () => {
            try {
                const session = await apiGet('api/check-session.php');
                if (session && session.authenticated && session.user && session.user.customer_id && !session.user.is_admin && session.user.role !== 'admin') {
                    await apiPost('api/cart.php', { action: 'save', items });
                }
            } catch (err) {
                // Resilient fallback to localStorage
            }
        }, 300);
    };

    /**
     * Clear server-side cart when order is placed.
     */
    window.clearServerCart = async function () {
        try {
            await apiPost('api/cart.php', { action: 'clear' });
        } catch (_) {}
    };

    /**
     * Load authenticated user's cart from database on page load/init.
     */
    async function syncCartOnLoad() {
        try {
            const data = await apiGet('api/cart.php');
            if (data && data.authenticated && Array.isArray(data.cart)) {
                if (data.cart.length > 0) {
                    if (typeof window.setClientCart === 'function') {
                        window.setClientCart(data.cart);
                    } else {
                        localStorage.setItem('kukiCart', JSON.stringify(data.cart));
                    }
                } else {
                    // If DB cart is empty, check if guest cart had items and save them to DB
                    let local = [];
                    try { local = JSON.parse(localStorage.getItem('kukiCart') || '[]'); } catch (_) {}
                    if (local.length > 0) {
                        await apiPost('api/cart.php', { action: 'save', items: local });
                    }
                }
            }
        } catch (err) {
            console.warn('[KúkiCakes] Cart load sync warning:', err);
        }
    }

    /**
     * Merge guest cart with user's persistent DB cart on login.
     */
    async function mergeCartOnLogin() {
        let guestCart = [];
        try { guestCart = JSON.parse(localStorage.getItem('kukiCart') || '[]'); } catch (_) {}

        try {
            const res = await apiPost('api/cart.php', { action: 'merge', items: guestCart });
            if (res && res.success && Array.isArray(res.cart)) {
                if (typeof window.setClientCart === 'function') {
                    window.setClientCart(res.cart);
                } else {
                    localStorage.setItem('kukiCart', JSON.stringify(res.cart));
                }
            }
        } catch (err) {
            console.warn('[KúkiCakes] Cart merge on login warning:', err);
        }
    }

    /* ============================================================
       CART MERGE NOTIFICATION
       ============================================================ */

    function maybeShowCartMergeBanner(firstName) {
        if (localStorage.getItem(MERGE_KEY)) return;

        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('kukiCart') || '[]'); } catch { }
        if (cart.length === 0) return;

        localStorage.setItem(MERGE_KEY, '1');
        _toast(`Welcome back, ${firstName}! Your cart items have been saved. 🛒`);
    }

    /* ============================================================
       SIGN-UP FORM
       ============================================================ */

    function setupSignupForm() {
        const form = document.getElementById('signupForm');
        if (!form) return;

        // Password show/hide toggles
        form.querySelectorAll('.password-toggle').forEach(setupToggle);

        // Live strength + requirements
        const pwInput = document.getElementById('su-password');
        pwInput?.addEventListener('input', () => {
            updateStrength(pwInput.value, 'su-strength-fill', 'su-strength-label');
            updateReqs(pwInput.value, 'req-len', 'req-upper', 'req-lower', 'req-num');
        });

        // Blur validation
        ['su-name', 'su-email', 'su-phone', 'su-password', 'su-confirm'].forEach(id => {
            const field = id.replace('su-', '');
            document.getElementById(id)?.addEventListener('blur', () => validateSignupField(field));
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ok = ['name', 'email', 'phone', 'password', 'confirm'].every(validateSignupField);
            if (!ok) return;

            const submitBtn = document.getElementById('signupSubmitBtn');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Creating account…'; }

            const payload = {
                full_name:        document.getElementById('su-name').value.trim(),
                email:            document.getElementById('su-email').value.trim().toLowerCase(),
                phone:            document.getElementById('su-phone').value.trim(),
                password:         document.getElementById('su-password').value,
                confirm_password: document.getElementById('su-confirm').value,
            };

            try {
                const data = await apiPost('api/register.php', payload);

                if (data.success) {
                    // Show success state — same existing UI
                    form.style.display = 'none';
                    const successEl = document.getElementById('signupSuccess');
                    if (successEl) successEl.removeAttribute('hidden');
                } else {
                    _toast(data.message || 'Registration failed. Please try again.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Account →'; }
                }
            } catch (err) {
                _toast('Registration failed. Please check your connection.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Create Account →'; }
            }
        });

        setupPolicyModal();
    }

    function setupPolicyModal() {
        const termsBtn = document.getElementById('suTermsBtn');
        const privacyBtn = document.getElementById('suPrivacyBtn');
        const overlay = document.getElementById('policyModalOverlay');
        const titleEl = document.getElementById('policyModalTitle');
        const bodyEl = document.getElementById('policyModalBody');
        const closeBtn = document.getElementById('policyModalClose');
        const confirmBtn = document.getElementById('policyModalConfirm');

        if (!overlay || !titleEl || !bodyEl) return;

        let lastFocusedElement = null;

        const policies = {
            terms: {
                title: 'Terms of Service',
                html: '<h4>1. Custom Cake Orders &amp; Advance Notice</h4>' +
                      '<p>All celebration cakes and confections from KúkiCakes are freshly handcrafted to order from our studio in Colombo. To allow proper baking, decorating, and chilling, <strong>all celebration cake orders require a minimum of 48 hours advance notice</strong> prior to the scheduled delivery date.</p>' +
                      '<h4>2. Orders &amp; Payment Confirmation</h4>' +
                      '<p>An order is officially confirmed once payment or valid order details are verified:</p>' +
                      '<ul>' +
                      '<li><strong>Card Payment:</strong> Securely processed online via PayHere (Visa, Mastercard, Amex).</li>' +
                      '<li><strong>Cash on Delivery:</strong> Available for select locations within the Colombo delivery zone.</li>' +
                      '</ul>' +
                      '<h4>3. Perishable Product Policy &amp; Cancellations</h4>' +
                      '<p>Because cakes and baked confections are fresh, perishable culinary items made to custom specifications:</p>' +
                      '<ul>' +
                      '<li>Order cancellations or major design adjustments must be notified at least 24 hours prior to the scheduled delivery date.</li>' +
                      '<li>Once production and decoration have commenced, orders cannot be cancelled or refunded.</li>' +
                      '</ul>' +
                      '<h4>4. Delivery &amp; Receipt</h4>' +
                      '<p>Delivery is fulfilled during the selected delivery time slot. Please ensure an authorized recipient is present at the provided delivery address to safely receive the cake and store it under appropriate refrigeration or ambient conditions.</p>'
            },
            privacy: {
                title: 'Privacy Policy',
                html: '<h4>1. Information We Collect</h4>' +
                      '<p>When you register an account or place an order with KúkiCakes, we collect necessary personal details, including your full name, email address, contact telephone number, and delivery address.</p>' +
                      '<h4>2. How We Use Your Information</h4>' +
                      '<p>Your personal data is used exclusively to:</p>' +
                      '<ul>' +
                      '<li>Process and deliver your cake orders accurately.</li>' +
                      '<li>Communicate order confirmations, delivery schedules, and customer support updates.</li>' +
                      '<li>Maintain your account details and order history for seamless re-ordering.</li>' +
                      '</ul>' +
                      '<h4>3. Payment Card Security</h4>' +
                      '<p>Your payment security is paramount. KúkiCakes does not collect, process, or store credit or debit card numbers on our servers. All card transactions are handled directly through our certified payment gateway provider, PayHere.</p>' +
                      '<h4>4. Data Protection &amp; Password Security</h4>' +
                      '<p>Account passwords are encrypted using secure cryptographic hashing algorithms (BCRYPT). We do not sell, rent, or trade your personal data to any third-party marketing companies.</p>' +
                      '<h4>5. Contact Us</h4>' +
                      '<p>If you have any questions or wish to update your stored contact or delivery information, you can manage your profile directly in your account dashboard or contact our Colombo bakery studio.</p>'
            }
        };

        function openModal(type) {
            const policy = policies[type];
            if (!policy) return;

            lastFocusedElement = document.activeElement;
            titleEl.textContent = policy.title;
            bodyEl.innerHTML = policy.html;
            overlay.removeAttribute('hidden');
            document.body.style.overflow = 'hidden';

            (closeBtn || bodyEl).focus();
        }

        function closeModal() {
            if (overlay.hasAttribute('hidden')) return;
            overlay.setAttribute('hidden', '');
            document.body.style.overflow = '';
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        termsBtn?.addEventListener('click', () => openModal('terms'));
        privacyBtn?.addEventListener('click', () => openModal('privacy'));
        closeBtn?.addEventListener('click', closeModal);
        confirmBtn?.addEventListener('click', closeModal);

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal();
            }
        });

        overlay.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                const focusable = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) {
                closeModal();
            }
        });
    }

    function validateSignupField(field) {
        const map = {
            name:     ['su-name',     'su-name-err'],
            email:    ['su-email',    'su-email-err'],
            phone:    ['su-phone',    'su-phone-err'],
            password: ['su-password', 'su-password-err'],
            confirm:  ['su-confirm',  'su-confirm-err'],
        };
        const [inputId, errId] = map[field];
        const input = document.getElementById(inputId);
        const errEl = document.getElementById(errId);
        if (!input || !errEl) return true;

        const val = input.value.trim();
        let error = '';

        switch (field) {
            case 'name':
                if (!val)           error = 'Full name is required.';
                else if (val.length < 2) error = 'Name must be at least 2 characters.';
                else if (val.length > 100) error = 'Full name cannot exceed 100 characters.';
                else if (/\d/.test(val)) error = 'Full name cannot contain numbers.';
                else if (!/^[\p{L}\p{M}\s\-'.]+$/u.test(val)) error = 'Full name may only contain letters, spaces, hyphens, and apostrophes.';
                else if (!/\p{L}/u.test(val)) error = 'Full name must contain at least one letter.';
                break;
            case 'email':
                if (!val)           error = 'Email address is required.';
                else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) error = 'Please enter a valid email address.';
                break;
            case 'phone':
                if (!val)           error = 'Phone number is required.';
                else if (!/^[+\d\s\-()]{7,20}$/.test(val)) error = 'Please enter a valid phone number.';
                break;
            case 'password': {
                if (!val)               error = 'Password is required.';
                else if (val.length < 8) error = 'Password must be at least 8 characters.';
                else if (!/[A-Z]/.test(val)) error = 'Include at least one uppercase letter.';
                else if (!/[0-9]/.test(val)) error = 'Include at least one number.';
                break;
            }
            case 'confirm': {
                const pw = document.getElementById('su-password')?.value || '';
                if (!val)          error = 'Please confirm your password.';
                else if (val !== pw) error = 'Passwords do not match.';
                break;
            }
        }

        _applyFieldState(input, errEl, error);
        return !error;
    }

    /* ============================================================
       LOGIN FORM
       ============================================================ */

    function showLoginForm() {
        const loginSection  = document.getElementById('loginFormSection');
        const forgotSection = document.getElementById('forgotPasswordSection');
        const heroEyebrow   = document.getElementById('authHeroEyebrow');
        const heroTitle     = document.getElementById('authHeroTitle');
        const heroSubtext   = document.getElementById('authHeroSubtext');

        if (loginSection) loginSection.hidden = false;
        if (forgotSection) forgotSection.hidden = true;
        if (heroEyebrow) heroEyebrow.textContent = 'Welcome back';
        if (heroTitle) heroTitle.textContent = 'Log in to KúkiCakes.';
        if (heroSubtext) heroSubtext.textContent = 'Access your saved orders, addresses, and custom cake inquiries.';
    }

    function showForgotPasswordForm() {
        const loginSection  = document.getElementById('loginFormSection');
        const forgotSection = document.getElementById('forgotPasswordSection');
        const heroEyebrow   = document.getElementById('authHeroEyebrow');
        const heroTitle     = document.getElementById('authHeroTitle');
        const heroSubtext   = document.getElementById('authHeroSubtext');

        if (loginSection) loginSection.hidden = true;
        if (forgotSection) forgotSection.hidden = false;
        if (heroEyebrow) heroEyebrow.textContent = 'Account Recovery';
        if (heroTitle) heroTitle.textContent = 'Forgot Password.';
        if (heroSubtext) heroSubtext.textContent = 'Enter your registered email address to receive password reset instructions.';

        const fpEmail = document.getElementById('fp-email');
        const liEmail = document.getElementById('li-email');
        if (fpEmail) {
            if (liEmail && liEmail.value.trim() && !fpEmail.value.trim()) {
                fpEmail.value = liEmail.value.trim();
            }
            setTimeout(() => fpEmail.focus(), 80);
        }
    }

    function setupLoginForm() {
        const form = document.getElementById('loginForm');
        if (!form) return;

        // Password show/hide
        form.querySelector('.password-toggle')?.addEventListener('click', function () {
            _togglePasswordVisibility(this);
        });

        // Forgot password view toggle
        document.getElementById('forgotPasswordBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            showForgotPasswordForm();
        });

        document.getElementById('fpBackToLoginBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            showLoginForm();
        });

        // Dedicated Forgot Password form submission
        const fpForm = document.getElementById('forgotPasswordForm');
        fpForm?.addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailInput = document.getElementById('fp-email');
            const errSpan    = document.getElementById('fp-email-err');
            const submitBtn  = document.getElementById('fpSubmitBtn');
            const statusBox  = document.getElementById('fp-status-box');

            const emailVal = (emailInput?.value || '').trim().toLowerCase();
            if (errSpan) errSpan.textContent = '';
            if (statusBox) statusBox.hidden = true;

            if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                if (errSpan) errSpan.textContent = 'Please enter a valid email address.';
                emailInput?.focus();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Generating…';
            }

            try {
                const res = await apiPost('api/forgot-password.php', { email: emailVal });
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Reset Link →';
                }

                if (res && res.success) {
                    if (statusBox) {
                        statusBox.hidden = false;
                        statusBox.style.background = 'var(--peach-2)';
                        statusBox.style.border = '1px solid var(--peach)';
                        statusBox.innerHTML = `
                            <div style="color:var(--forest,#2d6a4f);font-weight:700;margin-bottom:6px;">✓ Password Reset Request Received</div>
                            <div>If an account is registered under "<strong>${_esc(emailVal)}</strong>", password reset instructions have been sent.</div>
                            <div style="margin-top:6px;font-size:12px;color:var(--muted);">
                                Please check your email inbox and spam folder.
                            </div>
                        `;
                    }
                } else {
                    if (statusBox) {
                        statusBox.hidden = false;
                        statusBox.innerHTML = `<div style="color:var(--berry);">${_esc(res?.message || 'Unable to generate reset link.')}</div>`;
                    }
                }
            } catch (err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Reset Link →';
                }
                if (statusBox) {
                    statusBox.hidden = false;
                    statusBox.innerHTML = '<div style="color:var(--berry);">Connection error. Please try again.</div>';
                }
            }
        });

        // Blur validation & input reset
        document.getElementById('li-email')?.addEventListener('blur',    () => validateLoginField('email'));
        document.getElementById('li-password')?.addEventListener('blur', () => validateLoginField('password'));
        document.getElementById('li-email')?.addEventListener('input', () => {
            const errEl = document.getElementById('li-password-err');
            if (errEl && errEl.textContent === 'Invalid email or password.') errEl.textContent = '';
        });
        document.getElementById('li-password')?.addEventListener('input', () => {
            const errEl = document.getElementById('li-password-err');
            if (errEl && errEl.textContent === 'Invalid email or password.') errEl.textContent = '';
            document.getElementById('li-password')?.classList.remove('is-error');
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ok = validateLoginField('email') & validateLoginField('password');
            if (!ok) return;

            const submitBtn = document.getElementById('loginSubmitBtn');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Logging in…'; }

            const rawIdent = document.getElementById('li-email').value.trim();
            const payload = {
                email:    rawIdent.toLowerCase(),
                username: rawIdent,
                password: document.getElementById('li-password').value,
            };

            try {
                const data = await apiPost('api/login.php', payload);

                if (data && data._status === 200 && data.success === true && data.user) {
                    setDisplayCache(data.user);
                    updateNavbar(data.user);

                    if (data.user.is_admin || data.user.role === 'admin') {
                        // Administrator login: navigate directly to admin dashboard
                        _toast(`Welcome back, ${data.user.name || 'Admin'}! 👋`);
                        if (typeof window.allowAdminPage === 'function') {
                            window.allowAdminPage();
                        } else if (typeof goPage === 'function') {
                            goPage('admin');
                        }
                    } else if (data.user.customer_id) {
                        // Customer login: merge cart and go to profile
                        await mergeCartOnLogin();
                        maybeShowCartMergeBanner(data.user.name.split(' ')[0]);

                        if (typeof goPage === 'function') goPage('profile');
                        setTimeout(populateProfilePage, 60);
                    } else {
                        clearDisplayCache();
                        updateNavbar(null);
                        const errEl = document.getElementById('li-password-err');
                        if (errEl) errEl.textContent = 'Invalid email or password.';
                    }
                } else {
                    // Failed login — clear any stale display cache and show guest nav
                    clearDisplayCache();
                    updateNavbar(null);
                    // Show generic error from server
                    const errEl = document.getElementById('li-password-err');
                    if (errEl) errEl.textContent = data.message || 'Invalid email or password.';
                    const pwInput = document.getElementById('li-password');
                    if (pwInput) pwInput.classList.add('is-error');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Log In →'; }
                }
            } catch (err) {
                // Network or JSON parse error
                clearDisplayCache();
                updateNavbar(null);
                _toast('Login failed. Please check your connection.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Log In →'; }
            }
        });
    }

    function validateLoginField(field) {
        const map = {
            email:    ['li-email',    'li-email-err'],
            password: ['li-password', 'li-password-err'],
        };
        const [inputId, errId] = map[field];
        const input = document.getElementById(inputId);
        const errEl = document.getElementById(errId);
        if (!input || !errEl) return true;

        const val = input.value.trim();
        let error = '';

        if (field === 'email') {
            if (!val)           error = 'Email address or username is required.';
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) && !/^[a-zA-Z0-9_.-]{3,50}$/.test(val)) {
                error = 'Please enter a valid email address or username.';
            }
        } else {
            if (!input.value)   error = 'Password is required.';
        }

        _applyFieldState(input, errEl, error);
        return !error;
    }

    /* ============================================================
       PROFILE PAGE — populate & interactions
       ============================================================ */

    async function populateProfilePage() {
        try {
            const data = await apiGet('api/profile.php');

            if (!data.success || !data.user) {
                // Not authenticated — redirect to guard
                if (typeof goPage === 'function') goPage('auth-guard');
                return;
            }

            const user = data.user;

            const initials = (user.name || 'K')
                .split(' ').filter(Boolean)
                .map(n => n[0].toUpperCase()).slice(0, 2).join('');

            _setText('profileAvatar',      initials);
            _setText('profileSidebarName', user.name  || '—');
            _setText('profileSidebarEmail',user.email || '—');
            _setText('profileName',        user.name  || '—');
            _setText('profileJoinDate',    user.joinDate || '—');
            _setText('profileEmail',       user.email || '—');
            _setText('profilePhone',       user.phone || 'Not provided');

            // Pre-fill fields in edit forms
            const emailInput = document.getElementById('editEmailInput');
            if (emailInput) emailInput.value = user.email || '';
            const phoneInput = document.getElementById('editPhoneInput');
            if (phoneInput) phoneInput.value = (user.phone && user.phone !== 'Not provided' && user.phone !== '—') ? user.phone : '';
            const nameInput = document.getElementById('editNameInput');
            if (nameInput) nameInput.value = user.name || '';

            // Mini orders (still uses demo data — orders DB integration
            // requires orders to be linked to authenticated users)
            renderMiniOrders();

        } catch (err) {
            console.error('[KúkiCakes] Could not load profile:', err);
            _toast('Could not load profile. Please try again.');
            if (typeof goPage === 'function') goPage('auth-guard');
        }
    }

    function setupProfilePage() {
        // Panel switching via sidebar buttons
        document.querySelectorAll('.sidebar-nav-item[data-panel]').forEach(btn => {
            btn.addEventListener('click', () => {
                const panel = btn.dataset.panel;

                document.querySelectorAll('.sidebar-nav-item').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                document.querySelectorAll('.profile-panel').forEach(p => p.classList.remove('active'));
                document.getElementById(`panel-${panel}`)?.classList.add('active');

                if (panel === 'orders') {
                    if (typeof goPage === 'function') goPage('my-orders');
                    setTimeout(() => {
                        setupMyOrdersPage();
                        loadAndRenderOrders('all', true);
                    }, 50);
                }

                // Load addresses on-demand
                if (panel === 'shipping') loadAndRenderAddresses('shipping');
                if (panel === 'billing')  loadAndRenderAddresses('billing');
            });
        });

        setupEditableSection('personal', 'personalDisplay', 'personalEdit', 'personalEditBtn', 'personalSaveBtn', 'personalCancelBtn', savePersInfo);
        setupEditableSection('contact',  'contactDisplay',  'contactEdit',  'contactEditBtn',  'contactSaveBtn',  'contactCancelBtn',  saveContactInfo);

        setupChangePasswordForm();
        setupAddressManagement();

        // Populate profile if already on profile page
        const isOnProfile = document.getElementById('profile-page')?.classList.contains('active');
        if (isOnProfile) populateProfilePage();
    }

    function setupEditableSection(prefix, displayId, editId, editBtnId, saveBtnId, cancelBtnId, saveFn) {
        const displayEl = document.getElementById(displayId);
        const editEl    = document.getElementById(editId);
        const editBtn   = document.getElementById(editBtnId);
        const saveBtn   = document.getElementById(saveBtnId);
        const cancelBtn = document.getElementById(cancelBtnId);

        if (!editBtn || !saveBtn || !cancelBtn) return;

        editBtn.addEventListener('click', () => {
            displayEl?.classList.add('hidden');
            editEl?.classList.remove('hidden');
            editBtn.style.display = 'none';
            // Pre-fill edit inputs from current displayed values
            if (prefix === 'personal') {
                const input = document.getElementById('editNameInput');
                const currName = document.getElementById('profileName')?.textContent?.trim() || '';
                if (input) input.value = (currName === '—') ? '' : currName;
                _applyFieldState(input, document.getElementById('editNameErr'), '');
            }
            if (prefix === 'contact') {
                const phoneInput = document.getElementById('editPhoneInput');
                const currPhone = document.getElementById('profilePhone')?.textContent?.trim() || '';
                if (phoneInput) phoneInput.value = (currPhone === 'Not provided' || currPhone === '—') ? '' : currPhone;
                const emailInput = document.getElementById('editEmailInput');
                const currEmail = document.getElementById('profileEmail')?.textContent?.trim() || getDisplayCache()?.email || '';
                if (emailInput) emailInput.value = (currEmail === '—') ? '' : currEmail;
                _applyFieldState(phoneInput, document.getElementById('editPhoneErr'), '');
                _applyFieldState(emailInput, document.getElementById('editEmailErr'), '');
            }
        });

        cancelBtn.addEventListener('click', () => {
            displayEl?.classList.remove('hidden');
            editEl?.classList.add('hidden');
            editBtn.style.display = '';
        });

        saveBtn.addEventListener('click', async () => {
            const ok = await saveFn();
            if (!ok) return;
            displayEl?.classList.remove('hidden');
            editEl?.classList.add('hidden');
            editBtn.style.display = '';
        });
    }

    async function savePersInfo() {
        const input   = document.getElementById('editNameInput');
        const errEl   = document.getElementById('editNameErr');
        const newName = input?.value.trim() || '';

        if (!newName) {
            _applyFieldState(input, errEl, 'Full name is required.');
            input?.focus();
            return false;
        }
        if (newName.length < 2) {
            _applyFieldState(input, errEl, 'Full name must be at least 2 characters.');
            input?.focus();
            return false;
        }
        if (newName.length > 100) {
            _applyFieldState(input, errEl, 'Full name cannot exceed 100 characters.');
            input?.focus();
            return false;
        }
        if (/\d/.test(newName)) {
            _applyFieldState(input, errEl, 'Full name cannot contain numbers.');
            input?.focus();
            return false;
        }
        if (!/^[\p{L}\p{M}\s\-'.]+$/u.test(newName)) {
            _applyFieldState(input, errEl, 'Full name may only contain letters, spaces, hyphens, and apostrophes.');
            input?.focus();
            return false;
        }
        if (!/\p{L}/u.test(newName)) {
            _applyFieldState(input, errEl, 'Full name must contain at least one letter.');
            input?.focus();
            return false;
        }
        _applyFieldState(input, errEl, '');

        try {
            const data = await apiPost('api/update-profile.php', {
                full_name: newName,
            });

            if (!data.success) {
                const errMsg = data.errors?.full_name || data.message || 'Update failed.';
                _applyFieldState(input, errEl, errMsg);
                _toast(errMsg);
                return false;
            }

            const user = data.user;
            _setText('profileName',        user.name);
            _setText('profileSidebarName', user.name);
            const initials = user.name.split(' ').filter(Boolean).map(n => n[0].toUpperCase()).slice(0, 2).join('');
            _setText('profileAvatar',  initials);
            _setText('userAvatar',     initials);
            _setText('userDisplayName', user.name.split(' ')[0]);
            setDisplayCache(user);
            _toast('Personal information saved ✓');
            return true;
        } catch (err) {
            _applyFieldState(input, errEl, 'Update failed. Please check your connection.');
            _toast('Update failed. Please check your connection.');
            return false;
        }
    }

    async function saveContactInfo() {
        const phoneInput = document.getElementById('editPhoneInput');
        const phoneErr   = document.getElementById('editPhoneErr');
        const emailInput = document.getElementById('editEmailInput');
        const emailErr   = document.getElementById('editEmailErr');

        const newPhone = phoneInput?.value.trim() || '';
        const newEmail = emailInput?.value.trim() || '';

        let hasError = false;

        // Validate email
        if (!newEmail) {
            _applyFieldState(emailInput, emailErr, 'Email address is required.');
            hasError = true;
        } else if (newEmail.length > 150) {
            _applyFieldState(emailInput, emailErr, 'Email address cannot exceed 150 characters.');
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
            _applyFieldState(emailInput, emailErr, 'Please enter a valid email address.');
            hasError = true;
        } else {
            _applyFieldState(emailInput, emailErr, '');
        }

        // Validate phone
        if (!newPhone) {
            _applyFieldState(phoneInput, phoneErr, 'Phone number is required.');
            hasError = true;
        } else if (!/^[0-9+\s\-().]{7,20}$/.test(newPhone)) {
            _applyFieldState(phoneInput, phoneErr, 'Please enter a valid phone number (7 to 20 digits).');
            hasError = true;
        } else {
            _applyFieldState(phoneInput, phoneErr, '');
        }

        if (hasError) return false;

        try {
            const data = await apiPost('api/update-profile.php', {
                email: newEmail,
                phone: newPhone,
            });

            if (!data.success) {
                if (data.errors?.email) _applyFieldState(emailInput, emailErr, data.errors.email);
                if (data.errors?.phone) _applyFieldState(phoneInput, phoneErr, data.errors.phone);
                _toast(data.message || 'Update failed.');
                return false;
            }

            const user = data.user;
            _setText('profileEmail',        user.email);
            _setText('profileSidebarEmail', user.email);
            _setText('profilePhone',        user.phone || 'Not provided');
            setDisplayCache(user);
            _toast('Contact information saved ✓');
            return true;
        } catch (err) {
            _toast('Update failed. Please check your connection.');
            return false;
        }
    }

    /* ============================================================
       CHANGE PASSWORD FORM
       ============================================================ */

    function setupChangePasswordForm() {
        const form = document.getElementById('changePwForm');
        if (!form) return;

        form.querySelectorAll('.password-toggle').forEach(setupToggle);

        document.getElementById('cp-new')?.addEventListener('input', function () {
            updateStrength(this.value, 'cp-strength-fill', 'cp-strength-label');
            updateReqs(this.value, 'cp-req-len', 'cp-req-upper', 'cp-req-lower', 'cp-req-num');
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            let ok = true;

            const current = document.getElementById('cp-current');
            const newPw   = document.getElementById('cp-new');
            const confirm = document.getElementById('cp-confirm');

            if (!current?.value) {
                _applyFieldState(current, document.getElementById('cp-current-err'), 'Current password is required.');
                ok = false;
            } else {
                _applyFieldState(current, document.getElementById('cp-current-err'), '');
            }

            if (!newPw?.value || newPw.value.length < 8) {
                _applyFieldState(newPw, document.getElementById('cp-new-err'), 'New password must be at least 8 characters.');
                ok = false;
            } else {
                _applyFieldState(newPw, document.getElementById('cp-new-err'), '');
            }

            if (confirm?.value !== newPw?.value) {
                _applyFieldState(confirm, document.getElementById('cp-confirm-err'), 'Passwords do not match.');
                ok = false;
            } else {
                _applyFieldState(confirm, document.getElementById('cp-confirm-err'), '');
            }

            if (!ok) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Changing…'; }

            try {
                const data = await apiPost('api/change-password.php', {
                    current_password: current.value,
                    new_password:     newPw.value,
                    confirm_password: confirm.value,
                });

                if (data.success) {
                    const successEl = document.getElementById('changePwSuccess');
                    if (successEl) {
                        successEl.textContent = '✓ Password changed successfully!';
                        successEl.classList.add('show');
                    }
                    form.reset();
                    document.getElementById('cp-strength-fill').className = 'strength-fill';
                    document.getElementById('cp-strength-label').textContent = '';
                    document.querySelectorAll('#cp-pw-reqs li').forEach(li => li.classList.remove('met'));
                    setTimeout(() => successEl?.classList.remove('show'), 5000);
                } else {
                    if (data._status === 401) {
                        _applyFieldState(current, document.getElementById('cp-current-err'), data.message);
                    } else {
                        _toast(data.message || 'Password change failed.');
                    }
                }
            } catch (err) {
                _toast('Password change failed. Please check your connection.');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Change Password'; }
            }
        });
    }

    /* ============================================================
       ADDRESS MANAGEMENT (Shipping & Billing)
       Connected to real MySQL via /api/addresses.php
       ============================================================ */

    function setupAddressManagement() {
        // Addresses are loaded on-demand when the panel is activated
        setupAddressModal();
    }

    async function loadAndRenderAddresses(type) {
        const grid = document.getElementById(`${type}AddrGrid`);
        if (!grid) return;

        grid.innerHTML = '<p style="color:var(--muted);padding:1rem 0">Loading…</p>';

        try {
            const data = await apiGet(`api/addresses.php?type=${type}`);
            if (!data.success) {
                grid.innerHTML = '<p style="color:var(--muted)">Could not load addresses.</p>';
                return;
            }
            renderAddresses(type, data.addresses || []);
        } catch (err) {
            grid.innerHTML = '<p style="color:var(--muted)">Could not load addresses.</p>';
        }
    }

    function renderAddresses(type, list) {
        const grid = document.getElementById(`${type}AddrGrid`);
        if (!grid) return;

        grid.innerHTML = list.map(addr => `
            <div class="address-card${addr.is_default ? ' is-default' : ''}">
                ${addr.is_default ? '<span class="address-default-badge">✦ Default</span>' : ''}
                <div class="address-name">${_esc(addr.full_name)}</div>
                <div class="address-line">
                    ${_esc(addr.line1)}<br>
                    ${addr.line2 ? _esc(addr.line2) + '<br>' : ''}
                    ${_esc(addr.city)}${addr.postal ? ', ' + _esc(addr.postal) : ''}<br>
                    ${addr.phone ? _esc(addr.phone) : ''}
                </div>
                <div class="address-actions">
                    ${!addr.is_default
                        ? `<button class="address-btn" onclick="kukiSetDefault('${type}',${addr.address_id})">Set Default</button>`
                        : ''}
                    <button class="address-btn" onclick="kukiEditAddr('${type}',${addr.address_id})">Edit</button>
                    <button class="address-btn address-btn-delete" onclick="kukiDeleteAddr('${type}',${addr.address_id})">Delete</button>
                </div>
            </div>
        `).join('') + `
            <button class="add-address-card" data-addr-type="${type}">
                <span class="add-addr-icon">＋</span>
                <span>Add New Address</span>
            </button>
        `;

        grid.querySelector(`[data-addr-type="${type}"]`)?.addEventListener('click', () => {
            openAddressModal(type, null);
        });
    }

    // Global helpers (called from inline onclick)
    window.kukiSetDefault = async function (type, id) {
        try {
            await apiPost('api/addresses.php', {
                address_id: id, type,
                // Fetch current data to re-send
                full_name: '', line1: '', city: '', is_default: true,
            });
        } catch (err) { /* ignore */ }
        // Re-load to get the server-confirmed state
        // First fetch current data for the address being set as default
        try {
            const listData = await apiGet(`api/addresses.php?type=${type}`);
            const addr = (listData.addresses || []).find(a => a.address_id === id);
            if (addr) {
                await apiPost('api/addresses.php', {
                    address_id: id, type,
                    full_name: addr.full_name, line1: addr.line1, line2: addr.line2 || '',
                    city: addr.city, postal: addr.postal || '', phone: addr.phone || '',
                    is_default: true,
                });
            }
        } catch (err) { /* ignore */ }
        loadAndRenderAddresses(type);
        _toast('Default address updated ✓');
    };

    window.kukiEditAddr = async function (type, id) {
        try {
            const data = await apiGet(`api/addresses.php?type=${type}`);
            const addr = (data.addresses || []).find(a => a.address_id === id);
            if (addr) openAddressModal(type, addr);
        } catch (err) {
            _toast('Could not load address data.');
        }
    };

    window.kukiDeleteAddr = async function (type, id) {
        if (!confirm('Remove this address?')) return;
        try {
            await apiDelete(`api/addresses.php?id=${id}`);
            loadAndRenderAddresses(type);
            _toast('Address removed.');
        } catch (err) {
            _toast('Could not delete address.');
        }
    };

    // Modal state
    let _modalType = null;
    let _modalAddrId = null;

    function openAddressModal(type, addr) {
        _modalType   = type;
        _modalAddrId = addr ? addr.address_id : null;

        const overlay = document.getElementById('addressModalOverlay');
        if (!overlay) return;

        _setText('addrModalTitle', addr ? 'Edit Address' : 'Add New Address');
        _setVal('addrName',   addr?.full_name || '');
        _setVal('addrLine1',  addr?.line1     || '');
        _setVal('addrLine2',  addr?.line2     || '');
        _setVal('addrCity',   addr?.city      || '');
        _setVal('addrPostal', addr?.postal    || '');
        _setVal('addrPhone',  addr?.phone     || '');

        overlay.classList.add('open');
        document.getElementById('addrName')?.focus();
    }

    function closeAddressModal() {
        document.getElementById('addressModalOverlay')?.classList.remove('open');
        _modalType   = null;
        _modalAddrId = null;
    }

    function setupAddressModal() {
        document.getElementById('addrModalClose')?.addEventListener('click',  closeAddressModal);
        document.getElementById('addrModalCancel')?.addEventListener('click', closeAddressModal);

        document.getElementById('addressModalOverlay')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeAddressModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAddressModal();
        });

        document.getElementById('addrSaveBtn')?.addEventListener('click', async () => {
            const name  = document.getElementById('addrName')?.value.trim();
            const line1 = document.getElementById('addrLine1')?.value.trim();
            const city  = document.getElementById('addrCity')?.value.trim();

            if (!name || !line1 || !city) {
                _toast('Please fill in Name, Address, and City.');
                return;
            }

            const payload = {
                type:       _modalType,
                address_id: _modalAddrId || undefined,
                full_name:  name,
                line1,
                line2:   document.getElementById('addrLine2')?.value.trim()  || '',
                city,
                postal:  document.getElementById('addrPostal')?.value.trim() || '',
                phone:   document.getElementById('addrPhone')?.value.trim()  || '',
            };

            try {
                const data = await apiPost('api/addresses.php', payload);
                if (!data.success) {
                    _toast(data.message || 'Could not save address.');
                    return;
                }
                loadAndRenderAddresses(_modalType);
                closeAddressModal();
                _toast('Address saved ✓');
            } catch (err) {
                _toast('Could not save address. Please try again.');
            }
        });
    }

    /* ============================================================
       MY ORDERS — LIVE DATABASE-BACKED ORDER HISTORY
       Connected to server via GET /api/my-orders.php
       ============================================================ */

    let _customerOrders = null;
    let _ordersFetchPromise = null;
    let _currentOrdersFilter = 'all';
    let _myOrdersInitialized = false;

    const STATUS_LABEL = {
        pending: 'Pending',
        confirmed: 'Confirmed',
        preparing: 'Preparing',
        in_progress: 'Preparing',
        ready: 'Ready',
        delivering: 'Out for Delivery',
        delivered: 'Delivered',
        cancelled: 'Cancelled',
    };

    /**
     * Fetch orders for the authenticated customer.
     * Caches in memory during the active session; supports forced reload.
     */
    async function fetchCustomerOrders(forceReload = false) {
        if (_customerOrders !== null && !forceReload) {
            return _customerOrders;
        }
        if (_ordersFetchPromise && !forceReload) {
            return _ordersFetchPromise;
        }

        _ordersFetchPromise = apiGet('api/my-orders.php')
            .then(res => {
                if (res && res.success && Array.isArray(res.orders)) {
                    _customerOrders = res.orders;
                    return _customerOrders;
                }
                throw new Error(res?.message || 'Could not load orders');
            })
            .finally(() => {
                _ordersFetchPromise = null;
            });

        return _ordersFetchPromise;
    }

    /**
     * Render the orders list given an array of orders and a filter status.
     */
    function renderOrdersList(orders, filterStatus) {
        const list  = document.getElementById('ordersList');
        const empty = document.getElementById('ordersEmpty');
        if (!list) return;

        const filtered = (filterStatus === 'all')
            ? orders
            : orders.filter(o => o.status === filterStatus || o.raw_status === filterStatus);

        if (filtered.length === 0) {
            list.innerHTML = '';
            if (empty) {
                empty.removeAttribute('hidden');
                if (orders.length === 0) {
                    empty.innerHTML = `
                        <strong>You have not placed any orders yet</strong>
                        Browse our handcrafted cakes and celebrate your sweetest moments.
                        <div style="margin-top:16px;">
                            <a href="#cakes" class="btn btn-primary btn-sm" style="display:inline-block;text-decoration:none;">Explore Our Cakes →</a>
                        </div>
                    `;
                } else {
                    empty.innerHTML = `
                        <strong>No orders found</strong>
                        No orders match the "${_esc(STATUS_LABEL[filterStatus] || filterStatus)}" filter. Try a different status or view all orders.
                    `;
                }
            }
        } else {
            empty?.setAttribute('hidden', '');
            list.innerHTML = filtered.map(o => {
                const items = Array.isArray(o.items) && o.items.length ? o.items : [{
                    cake: o.cake || 'Artisan Cake',
                    img: o.img || 'docs/logo1_1.png',
                    qty: o.qty || 1,
                    weight: o.weight || '500g',
                    flavor: o.flavor || 'Standard',
                    unit_price: o.total || 0,
                    line_total: o.total || 0,
                    note: ''
                }];

                let itemsHtml = '';
                if (items.length === 1) {
                    const it = items[0];
                    itemsHtml = `
                        <div class="order-card-body">
                            <img class="order-cake-img" src="${_esc(it.img || 'docs/logo1_1.png')}" alt="${_esc(it.cake)}" loading="lazy" onerror="this.src='docs/logo1_1.png'">
                            <div class="order-cake-info">
                                <h4>${_esc(it.cake)}</h4>
                                <p>Weight: ${_esc(it.weight)} &nbsp;·&nbsp; Flavour: ${_esc(it.flavor)} &nbsp;·&nbsp; Qty: ${Number(it.qty)}</p>
                                ${it.note ? `<p style="font-size:11px;color:var(--muted);margin-top:3px;font-style:italic">Note: ${_esc(it.note)}</p>` : ''}
                            </div>
                        </div>
                    `;
                } else {
                    itemsHtml = `
                        <div class="order-items-list">
                            ${items.map(it => `
                                <div class="order-item-row">
                                    <img class="order-cake-img" src="${_esc(it.img || 'docs/logo1_1.png')}" alt="${_esc(it.cake)}" loading="lazy" onerror="this.src='docs/logo1_1.png'">
                                    <div class="order-cake-info">
                                        <h4>${_esc(it.cake)}</h4>
                                        <p>Weight: ${_esc(it.weight)} &nbsp;·&nbsp; Flavour: ${_esc(it.flavor)} &nbsp;·&nbsp; Qty: ${Number(it.qty)} &nbsp;·&nbsp; Rs. ${Number(it.line_total || (it.unit_price * it.qty)).toLocaleString()}</p>
                                        ${it.note ? `<p style="font-size:11px;color:var(--muted);margin-top:3px;font-style:italic">Note: ${_esc(it.note)}</p>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }

                const statusKey = o.status || 'pending';
                const statusLabel = STATUS_LABEL[statusKey] || STATUS_LABEL[o.raw_status] || statusKey;
                const badgeClass = `status--${_esc(statusKey)}`;
                const deliveryStr = o.deliveryDate || o.delivery_date || '—';
                const slotStr = o.deliveryTimeSlot ? ` (${_esc(o.deliveryTimeSlot)})` : '';

                return `
                    <article class="order-card" id="ocard-${_esc(o.id)}">
                        <div class="order-card-header">
                            <div>
                                <div class="order-number">Order #${_esc(o.id)}</div>
                                <div class="order-date">Ordered ${_esc(o.date)}</div>
                            </div>
                            <span class="order-status-badge ${badgeClass}">${_esc(statusLabel)}</span>
                        </div>
                        ${itemsHtml}
                        <div class="order-card-footer">
                            <div>
                                <div class="order-total">Rs. ${Number(o.total).toLocaleString()}</div>
                                <div class="order-delivery-date">📅 Delivery: ${_esc(deliveryStr)}${slotStr}</div>
                            </div>
                            <button class="order-view-btn" onclick="kukiToggleDetails('${_esc(o.id)}')">View Details ↓</button>
                        </div>
                        <dl class="order-details-content" id="odetails-${_esc(o.id)}" style="display:none">
                            <dt>Order Number</dt><dd>#${_esc(o.id)}</dd>
                            <dt>Order Date</dt>  <dd>${_esc(o.date)}</dd>
                            <dt>Delivery Date</dt><dd>${_esc(deliveryStr)}${o.deliveryTimeSlot ? ` · ${_esc(o.deliveryTimeSlot)}` : ''}</dd>
                            <dt>Delivery Address</dt><dd>${_esc(o.address || '—')}</dd>
                            ${o.recipient_name ? `<dt>Recipient</dt><dd>${_esc(o.recipient_name)}${o.recipient_phone ? ` (${_esc(o.recipient_phone)})` : ''}</dd>` : ''}
                            <dt>Payment Method</dt><dd>${_esc((o.payment_method || '').toUpperCase())} &nbsp;(${_esc(o.payment_status || 'pending')})</dd>
                            <dt>Subtotal / Delivery</dt><dd>Rs. ${Number(o.subtotal || 0).toLocaleString()} + Rs. ${Number(o.delivery_fee || 0).toLocaleString()} delivery</dd>
                            <dt>Total Amount</dt><dd style="font-weight:700;color:var(--berry)">Rs. ${Number(o.total).toLocaleString()}</dd>
                            ${o.special_instructions ? `<dt>Special Instructions</dt><dd style="grid-column:span 2">${_esc(o.special_instructions)}</dd>` : ''}
                        </dl>
                    </article>
                `;
            }).join('');
        }
    }

    /**
     * Load orders from server and render with current status filter.
     * Shows loading skeleton, handles errors with retry button.
     */
    async function loadAndRenderOrders(filterStatus = _currentOrdersFilter, forceReload = false) {
        const list  = document.getElementById('ordersList');
        const empty = document.getElementById('ordersEmpty');
        if (!list) return;

        _currentOrdersFilter = filterStatus || 'all';

        // Show loading state if orders not yet in memory or if explicitly refreshing
        if (_customerOrders === null || forceReload) {
            list.innerHTML = `
                <div class="orders-loading">
                    <div style="font-size:24px;margin-bottom:8px;">🎂</div>
                    <strong>Loading your sweet orders...</strong>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px;">Retrieving your cake history</div>
                </div>
            `;
            empty?.setAttribute('hidden', '');
        }

        try {
            const orders = await fetchCustomerOrders(forceReload);
            renderOrdersList(orders, _currentOrdersFilter);
        } catch (err) {
            console.warn('[KúkiCakes] Failed to load orders:', err);
            empty?.setAttribute('hidden', '');
            if (err.message && err.message.includes('Authentication required')) {
                if (typeof goPage === 'function') goPage('auth-guard');
                return;
            }
            list.innerHTML = `
                <div class="orders-error">
                    <strong>Could not load your orders</strong>
                    <p style="margin:0;font-size:13px;color:var(--muted);">${_esc(err.message || 'Please check your connection and try again.')}</p>
                    <button class="btn btn-primary btn-sm orders-retry-btn" onclick="kukiRetryOrders()">Retry ⟳</button>
                </div>
            `;
        }
    }

    window.kukiRetryOrders = function () {
        loadAndRenderOrders(_currentOrdersFilter, true);
    };

    window.kukiToggleDetails = function (orderId) {
        const details = document.getElementById(`odetails-${orderId}`);
        const btn     = document.getElementById(`ocard-${orderId}`)?.querySelector('.order-view-btn');
        if (!details) return;
        const hidden = details.style.display === 'none';
        details.style.display = hidden ? 'grid' : 'none';
        if (btn) btn.textContent = hidden ? 'Hide Details ↑' : 'View Details ↓';
    };

    function setupMyOrdersPage() {
        if (!_myOrdersInitialized) {
            document.querySelectorAll('.orders-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.orders-filter-btn').forEach(b => {
                        b.classList.remove('active');
                        b.setAttribute('aria-selected', 'false');
                    });
                    btn.classList.add('active');
                    btn.setAttribute('aria-selected', 'true');
                    _currentOrdersFilter = btn.dataset.status || 'all';
                    if (_customerOrders !== null) {
                        renderOrdersList(_customerOrders, _currentOrdersFilter);
                    } else {
                        loadAndRenderOrders(_currentOrdersFilter);
                    }
                });
            });
            _myOrdersInitialized = true;
        }

        const myOrdersPage = document.getElementById('my-orders-page');
        const isCurrentPage = myOrdersPage?.classList.contains('active') || location.hash.slice(1).split('?')[0] === 'my-orders';
        if (isCurrentPage) {
            loadAndRenderOrders(_currentOrdersFilter);
        }
    }

    async function renderMiniOrders() {
        const container = document.getElementById('profileOrdersMini');
        if (!container) return;

        try {
            const orders = await fetchCustomerOrders();
            if (!orders || orders.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center;padding:24px 10px;color:var(--muted);font-size:13px;">
                        No orders placed yet.
                    </div>
                `;
                return;
            }

            const recent = orders.slice(0, 3);
            container.innerHTML = recent.map(o => {
                const statusKey = o.status || 'pending';
                const statusLabel = STATUS_LABEL[statusKey] || STATUS_LABEL[o.raw_status] || statusKey;
                const badgeClass = `status--${_esc(statusKey)}`;
                const itemCount = o.item_count || (o.items ? o.items.length : 1);
                const cakeTitle = itemCount > 1 ? `${_esc(o.cake)} + ${itemCount - 1} more` : _esc(o.cake);

                return `
                    <div class="profile-order-mini">
                        <div class="profile-order-mini-info">
                            <b>${cakeTitle}</b>
                            <span>${_esc(o.date)} &nbsp;·&nbsp; #${_esc(o.id)}</span>
                        </div>
                        <div class="profile-order-mini-right">
                            <div class="profile-order-mini-price">Rs. ${Number(o.total).toLocaleString()}</div>
                            <span class="order-status-badge ${badgeClass}" style="font-size:10px;padding:3px 8px">${_esc(statusLabel)}</span>
                        </div>
                    </div>
                `;
            }).join('');
        } catch (err) {
            container.innerHTML = `
                <div style="text-align:center;padding:16px 10px;color:var(--muted);font-size:12px;">
                    Unable to load recent orders.
                </div>
            `;
        }
    }

    /* ============================================================
       CART-MERGE BANNER dismiss
       ============================================================ */
    function setupCartMergeDismiss() {
        document.getElementById('cartMergeDismiss')?.addEventListener('click', () => {
            document.getElementById('cart-merge-banner')?.classList.remove('show');
        });
    }

    /* ============================================================
       PRIVATE HELPERS
       ============================================================ */

    function _toast(msg) {
        if (typeof showToast === 'function') { showToast(msg); return; }
        const el = document.querySelector('.toast');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('show');
        setTimeout(() => el.classList.remove('show'), 3500);
    }

    function _applyFieldState(input, errEl, error) {
        if (!input || !errEl) return;
        errEl.textContent = error;
        input.classList.toggle('is-error', !!error);
        input.classList.toggle('is-ok', !error && input.value.length > 0);
    }

    function _setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function _setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    function _esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setupToggle(btn) {
        btn.addEventListener('click', function () {
            _togglePasswordVisibility(this);
        });
    }

    function _togglePasswordVisibility(btn) {
        const input = btn.previousElementSibling;
        if (!input) return;
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.textContent = isText ? '👁' : '🙈';
        btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
    }

    function updateStrength(pw, fillId, labelId) {
        const fill  = document.getElementById(fillId);
        const label = document.getElementById(labelId);
        if (!fill || !label) return;

        if (!pw) { fill.className = 'strength-fill'; label.textContent = ''; label.className = 'strength-label'; return; }

        let score = 0;
        if (pw.length >= 8)            score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw))          score++;
        if (/[^A-Za-z0-9]/.test(pw))  score++;

        const level = score <= 1 ? 'weak' : score <= 3 ? 'moderate' : 'strong';
        const text  = { weak: 'Weak', moderate: 'Moderate', strong: 'Strong' }[level];

        fill.className  = `strength-fill ${level}`;
        label.className = `strength-label ${level}`;
        label.textContent = text;
    }

    function updateReqs(pw, lenId, upperId, lowerId, numId) {
        const toggle = (id, met) => document.getElementById(id)?.classList.toggle('met', met);
        toggle(lenId,   pw.length >= 8);
        toggle(upperId, /[A-Z]/.test(pw));
        toggle(lowerId, /[a-z]/.test(pw));
        toggle(numId,   /[0-9]/.test(pw));
    }

    /* ============================================================
       RESET PASSWORD PAGE
       ============================================================ */

    let _activeResetToken = null;

    function getResetToken() {
        if (_activeResetToken) return _activeResetToken;
        const searchParams = new URLSearchParams(window.location.search);
        if (searchParams.get('token')) return searchParams.get('token');
        const hash = window.location.hash;
        const qIndex = hash.indexOf('?');
        if (qIndex !== -1) {
            const hashParams = new URLSearchParams(hash.slice(qIndex));
            if (hashParams.get('token')) return hashParams.get('token');
        }
        return '';
    }

    async function checkResetPasswordState(tokenOverride) {
        const page = document.getElementById('reset-password-page');
        if (!page) return;

        const checkingEl = document.getElementById('rp-checking');
        const invalidEl  = document.getElementById('rp-invalid');
        const successEl  = document.getElementById('rp-success');
        const formEl     = document.getElementById('resetPasswordForm');
        const errorDesc  = document.getElementById('rp-error-desc');
        const tokenInput = document.getElementById('rp-token');
        const emailEl    = document.getElementById('rp-user-email');

        const token = tokenOverride || getResetToken();
        if (token) _activeResetToken = token;

        if (!token) {
            if (checkingEl) checkingEl.hidden = true;
            if (formEl) formEl.hidden = true;
            if (successEl) successEl.hidden = true;
            if (invalidEl) invalidEl.hidden = false;
            if (errorDesc) errorDesc.textContent = 'No password reset token was provided. Please request a new link from the login page.';
            return;
        }

        // Show checking spinner
        if (checkingEl) checkingEl.hidden = false;
        if (invalidEl) invalidEl.hidden = true;
        if (formEl) formEl.hidden = true;
        if (successEl) successEl.hidden = true;

        try {
            const data = await apiGet(`api/reset-password.php?token=${encodeURIComponent(token)}`);
            if (checkingEl) checkingEl.hidden = true;

            if (data && data._status === 200 && data.valid) {
                if (tokenInput) tokenInput.value = token;
                if (emailEl) emailEl.textContent = data.masked_email || 'your account';
                if (formEl) formEl.hidden = false;
            } else {
                if (invalidEl) invalidEl.hidden = false;
                if (errorDesc) errorDesc.textContent = data.message || 'This reset link is invalid, expired, or has already been used.';
            }
        } catch (err) {
            if (checkingEl) checkingEl.hidden = true;
            if (invalidEl) invalidEl.hidden = false;
            if (errorDesc) errorDesc.textContent = 'Unable to verify reset link. Please check your internet connection.';
        }
    }

    function setupResetPasswordPage() {
        const form = document.getElementById('resetPasswordForm');
        if (!form) return;

        // Password show/hide toggles
        form.querySelectorAll('.password-toggle').forEach(setupToggle);

        // Live strength + requirements
        const pwInput = document.getElementById('rp-password');
        pwInput?.addEventListener('input', () => {
            updateStrength(pwInput.value, 'rp-strength-fill', 'rp-strength-label');
            updateReqs(pwInput.value, 'rp-req-len', 'rp-req-upper', 'rp-req-lower', 'rp-req-num');
            const err = document.getElementById('rp-password-err');
            if (err) err.textContent = '';
        });

        const confirmInput = document.getElementById('rp-confirm');
        confirmInput?.addEventListener('input', () => {
            const err = document.getElementById('rp-confirm-err');
            if (err) err.textContent = '';
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const tokenVal   = document.getElementById('rp-token')?.value.trim() || _activeResetToken || getResetToken();
            const pwVal      = document.getElementById('rp-password')?.value || '';
            const confirmVal = document.getElementById('rp-confirm')?.value || '';

            const pwErr      = document.getElementById('rp-password-err');
            const confirmErr = document.getElementById('rp-confirm-err');

            if (pwErr) pwErr.textContent = '';
            if (confirmErr) confirmErr.textContent = '';

            let hasError = false;
            if (!tokenVal) {
                _toast('Reset token is missing.');
                hasError = true;
            }
            if (pwVal.length < 8) {
                if (pwErr) pwErr.textContent = 'Password must be at least 8 characters.';
                hasError = true;
            } else if (!/[A-Z]/.test(pwVal)) {
                if (pwErr) pwErr.textContent = 'Password must include at least one uppercase letter.';
                hasError = true;
            } else if (!/[0-9]/.test(pwVal)) {
                if (pwErr) pwErr.textContent = 'Password must include at least one number.';
                hasError = true;
            }

            if (pwVal !== confirmVal) {
                if (confirmErr) confirmErr.textContent = 'Passwords do not match.';
                hasError = true;
            }

            if (hasError) return;

            const submitBtn = document.getElementById('resetSubmitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving password…';
            }

            try {
                const res = await apiPost('api/reset-password.php', {
                    token: tokenVal,
                    new_password: pwVal,
                    confirm_password: confirmVal,
                });

                if (res && res._status === 200 && res.success) {
                    form.hidden = true;
                    _activeResetToken = null;
                    const successEl = document.getElementById('rp-success');
                    if (successEl) successEl.hidden = false;
                    _toast('✓ ' + (res.message || 'Password reset successful!'));
                    clearDisplayCache();
                    updateNavbar(null);
                } else {
                    _toast(res?.message || 'Password reset failed.');
                    if (pwErr) pwErr.textContent = res?.message || 'Password reset failed.';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Save New Password →';
                    }
                }
            } catch (err) {
                _toast('An error occurred. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save New Password →';
                }
            }
        });
    }

    /* ============================================================
       INITIALIZATION
       ============================================================ */

    function initAuth() {
        // Clean up any legacy mock auth keys from previous browser sessions
        try {
            localStorage.removeItem('kukiUser');
            localStorage.removeItem('kukiPendingUser');
        } catch (_) {}

        initNavbar();             // check server session and update navbar
        setupUserMenuDropdown();
        setupLogoutButtons();
        setupSignupForm();
        setupLoginForm();
        setupProfilePage();
        setupMyOrdersPage();
        setupCheckoutProtection();
        setupCartMergeDismiss();
        setupResetPasswordPage();

        // Handle direct deep-links
        const hash = location.hash.slice(1).split('?')[0].split('&')[0];
        if (hash === 'profile' || hash === 'account') populateProfilePage();
        if (hash === 'my-orders') { setupMyOrdersPage(); loadAndRenderOrders('all'); }
        if (hash === 'forgot-password') setTimeout(showForgotPasswordForm, 100);
        if (hash.startsWith('reset-password')) setTimeout(checkResetPasswordState, 120);
        if (hash === 'admin') window.guardAdminRoute();

        window.addEventListener('hashchange', () => {
            const h = location.hash.slice(1).split('?')[0].split('&')[0];
            if (h === 'profile' || h === 'account') {
                populateProfilePage();
            } else if (h === 'my-orders') {
                setupMyOrdersPage();
                loadAndRenderOrders('all');
            } else if (h === 'forgot-password') {
                showForgotPasswordForm();
            } else if (h === 'login') {
                showLoginForm();
            } else if (h.startsWith('reset-password')) {
                setTimeout(checkResetPasswordState, 50);
            } else if (h === 'admin') {
                window.guardAdminRoute();
            }
        });
    }

    initAuth();

})();
