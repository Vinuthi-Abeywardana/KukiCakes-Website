const gallery = (window.kukiData && Array.isArray(window.kukiData.gallery)) ? window.kukiData.gallery : [];
let products = [];
let isCatalogLoading = true;
let catalogError = null;
let cart = JSON.parse(localStorage.getItem('kukiCart') || '[]');
let activeProduct = null;
let searchTerm = '';
const money = value => `Rs. ${value.toLocaleString()}`;
const $ = selector => document.querySelector(selector);

function escapeHtml(str) {
	if (str == null) return '';
	return String(str).replace(/[&<>"']/g, ch => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;'
	}[ch] || ch));
}

function productCard(product) {
	const unitStr = product.sale_unit ? ` <span class="product-unit" style="font-size:12px;opacity:0.8;font-weight:normal;">/ ${escapeHtml(product.sale_unit)}</span>` : '';
	return `<article class="product-card" data-cat="${escapeHtml(product.cat || '')}"><div class="product-img" data-product="${product.id}"><img src="${escapeHtml(product.img)}" alt="${escapeHtml(product.name)}"><span class="badge">${escapeHtml(product.badge || '')}</span></div><div class="product-info"><h3>${escapeHtml(product.name)}</h3><div class="product-meta"><span>Price</span><span class="price">${money(product.price)}${unitStr}</span></div><button class="add-btn" data-product="${product.id}">Choose this cake</button></div></article>`;
}

function galleryCard(product) {
	const catStr = escapeHtml(product.cat || '');
	const nameStr = escapeHtml(product.name || 'Signature Cake');
	const imgStr = escapeHtml(product.img || '');
	const badgeStr = product.badge ? `<span class="badge gallery-badge">${escapeHtml(product.badge)}</span>` : '';
	const unitStr = product.sale_unit ? ` <span style="font-size:11px;opacity:0.85;">/ ${escapeHtml(product.sale_unit)}</span>` : '';
	return `<article class="gallery-item" data-product="${product.id}" data-cat="${catStr}" tabindex="0" role="button" aria-label="View ${nameStr} details">` +
		`<img src="${imgStr}" alt="${nameStr}" loading="lazy" onerror="this.onerror=null;this.style.opacity='0.6';">` +
		badgeStr +
		`<div class="gallery-overlay">` +
			`<b>${nameStr}</b>` +
			`<div class="gallery-overlay-meta">` +
				`<span>Price</span>` +
				`<span class="gallery-price">${money(product.price)}${unitStr}</span>` +
			`</div>` +
			`<span class="gallery-cta">Choose this cake →</span>` +
		`</div>` +
	`</article>`;
}

function renderGallery() {
	const grid = $('#galleryGrid');
	if (!grid) return;

	if (isCatalogLoading && (!products || products.length === 0)) {
		grid.innerHTML = '<div class="search-empty catalog-loading" style="grid-column: 1 / -1;"><div style="font-size:32px;margin-bottom:8px">🧁</div><h3>Loading our sweet gallery...</h3><p>Connecting to our bakery catalog.</p></div>';
		return;
	}

	if (catalogError && (!products || products.length === 0)) {
		grid.innerHTML = `<div class="search-empty catalog-error" style="grid-column: 1 / -1;"><div style="font-size:32px;margin-bottom:8px">⚠️</div><h3>Unable to load gallery</h3><p>${escapeHtml(catalogError)}</p><button type="button" class="btn btn-primary" id="retryGalleryBtn" style="margin-top:14px">Try again</button></div>`;
		return;
	}

	if (!products || products.length === 0) {
		grid.innerHTML = '<div class="search-empty" style="grid-column: 1 / -1;"><h3>No cakes found</h3><p>Our gallery is currently empty.</p></div>';
		return;
	}

	grid.innerHTML = products.map(galleryCard).join('');
}

function renderAllProducts() {
	if (isCatalogLoading && (!products || products.length === 0)) {
		if ($('#allProducts')) $('#allProducts').innerHTML = '<div class="search-empty catalog-loading"><div style="font-size:32px;margin-bottom:8px">🧁</div><h3>Loading our fresh cakes...</h3><p>Connecting to our bakery catalog.</p></div>';
		return;
	}
	if (catalogError && (!products || products.length === 0)) {
		if ($('#allProducts')) $('#allProducts').innerHTML = `<div class="search-empty catalog-error"><div style="font-size:32px;margin-bottom:8px">⚠️</div><h3>Unable to load cakes</h3><p>${escapeHtml(catalogError)}</p><button type="button" class="btn btn-primary" id="retryCatalogBtn" style="margin-top:14px">Try again</button></div>`;
		return;
	}
	const activeFilter = document.querySelector('.filter.active')?.dataset.filter || 'all';
	const sort = $('#priceSort')?.value || 'default';
	const visibleProducts = products.filter(product => {
		const matchesCategory = activeFilter === 'all' || product.cat.includes(activeFilter);
		const searchableText = `${product.name} ${product.cat} ${product.desc}`.toLowerCase();
		return matchesCategory && (!searchTerm || searchableText.includes(searchTerm));
	});
	if (sort === 'new') visibleProducts.sort((first, second) => second.id - first.id);
	if (sort === 'low-high') visibleProducts.sort((first, second) => first.price - second.price);
	if (sort === 'high-low') visibleProducts.sort((first, second) => second.price - first.price);
	if ($('#allProducts')) {
		$('#allProducts').innerHTML = visibleProducts.length ? visibleProducts.map(productCard).join('') : '<div class="search-empty"><h3>No cakes found</h3><p>Try a different name, flavour or category.</p></div>';
	}
}

function renderCatalog() {
	if (isCatalogLoading && (!products || products.length === 0)) {
		const loadingHtml = '<div class="search-empty catalog-loading"><div style="font-size:32px;margin-bottom:8px">🧁</div><h3>Loading our fresh cakes...</h3><p>Connecting to our bakery catalog.</p></div>';
		if ($('#featuredProducts')) $('#featuredProducts').innerHTML = loadingHtml;
		if ($('#allProducts')) $('#allProducts').innerHTML = loadingHtml;
	} else if (catalogError && (!products || products.length === 0)) {
		const errorHtml = `<div class="search-empty catalog-error"><div style="font-size:32px;margin-bottom:8px">⚠️</div><h3>Unable to load cakes</h3><p>${escapeHtml(catalogError)}</p><button type="button" class="btn btn-primary" id="retryCatalogBtn" style="margin-top:14px">Try again</button></div>`;
		if ($('#featuredProducts')) $('#featuredProducts').innerHTML = errorHtml;
		if ($('#allProducts')) $('#allProducts').innerHTML = errorHtml;
	} else {
		if ($('#featuredProducts')) $('#featuredProducts').innerHTML = products.slice(0, 4).map(productCard).join('');
		renderAllProducts();
	}
	renderGallery();
}

/**
 * Randomize product display order for the storefront catalogue while keeping it
 * completely stable across re-renders, category filtering, search, and page navigation.
 * Generates a fresh randomized order for each new visit/session.
 */
function applyVisitCatalogOrder(rawProducts) {
	if (!Array.isArray(rawProducts) || rawProducts.length <= 1) {
		return rawProducts;
	}

	const storageKey = 'kuki_visit_catalog_order';
	let storedOrder = null;

	try {
		const saved = sessionStorage.getItem(storageKey);
		if (saved) {
			const parsed = JSON.parse(saved);
			if (Array.isArray(parsed) && parsed.length > 0) {
				storedOrder = parsed;
			}
		}
	} catch (_) {
		// sessionStorage may be blocked or restricted; handle gracefully
	}

	// Map products by ID for fast, deterministic re-ordering
	const productMap = new Map();
	for (const p of rawProducts) {
		productMap.set(p.id, p);
	}

	// If we have an existing visit order, apply it consistently
	if (storedOrder) {
		const ordered = [];
		for (const id of storedOrder) {
			if (productMap.has(id)) {
				ordered.push(productMap.get(id));
				productMap.delete(id);
			}
		}
		// Append any newly added products that were not in the stored session order
		for (const remaining of productMap.values()) {
			ordered.push(remaining);
		}

		if (ordered.length === rawProducts.length) {
			return ordered;
		}
	}

	// Fresh visit: produce an unbiased randomized order using Fisher-Yates shuffle
	const shuffled = [...rawProducts];
	for (let i = shuffled.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		const temp = shuffled[i];
		shuffled[i] = shuffled[j];
		shuffled[j] = temp;
	}

	try {
		sessionStorage.setItem(storageKey, JSON.stringify(shuffled.map(p => p.id)));
	} catch (_) {
		// Ignore storage quota or security errors
	}

	return shuffled;
}

window.reshuffleCatalog = function reshuffleCatalog() {
	try {
		sessionStorage.removeItem('kuki_visit_catalog_order');
	} catch (_) {}
	loadProducts();
};

async function loadProducts() {
	isCatalogLoading = true;
	catalogError = null;
	renderCatalog();

	try {
		const response = await fetch('api/products.php');
		if (!response.ok) {
			throw new Error(`Failed to load catalog (HTTP ${response.status})`);
		}
		const data = await response.json();
		if (!data.success || !Array.isArray(data.products)) {
			throw new Error(data.message || 'Invalid catalog response');
		}
		products = applyVisitCatalogOrder(data.products);
		if (window.kukiData) {
			window.kukiData.products = products;
		}
		isCatalogLoading = false;
		catalogError = null;
		renderCatalog();
	} catch (err) {
		console.error('[KukiCakes] Catalog loading error:', err);
		isCatalogLoading = false;
		catalogError = 'Unable to connect to the cake catalog. Please try again.';
		renderCatalog();
	}
}

let _adminAccessAllowed = false;

window.checkAdminSession = async function checkAdminSession() {
	try {
		const res = await fetch('api/admin-check.php', { credentials: 'same-origin' });
		const json = await res.json();
		return res.ok && (json.success === true) && (json.admin === true);
	} catch {
		return false;
	}
};

window.allowAdminPage = function () {
	_adminAccessAllowed = true;
	goPage('admin');
	if (typeof window.onAdminPageActivated === 'function') {
		window.onAdminPageActivated();
	}
};

function goPage(rawPage, preserveQuery = true) {
	const raw = String(rawPage || '').replace(/^#/, '');
	const targetRoute = raw.split('?')[0].split('&')[0];
	let page = targetRoute;
	if (page === 'forgot-password') page = 'login';
	if (page === 'account') page = 'profile';
	const query = raw.includes('?') ? raw.slice(raw.indexOf('?')) : (preserveQuery && location.hash.includes('?') ? location.hash.slice(location.hash.indexOf('?')) : '');

	// Protect admin page from unauthorized access
	if (page === 'admin') {
		if (!_adminAccessAllowed) {
			if (typeof window.guardAdminRoute === 'function') {
				window.guardAdminRoute();
			} else {
				window.checkAdminSession().then(isAdmin => {
					if (isAdmin) {
						window.allowAdminPage();
					} else {
						showToast('Please log in with an administrator account.');
						goPage('login');
					}
				});
			}
			return;
		}
		_adminAccessAllowed = false;
	}

	document.querySelectorAll('.page').forEach(item => item.classList.toggle('active', item.id === `${page}-page`));
	document.querySelectorAll('[data-page]').forEach(item => item.classList.toggle('active', item.dataset.page === page || item.dataset.page === targetRoute));
	$('#navLinks').classList.remove('open');
	if (page === 'gallery') renderGallery();
	if (page === 'cart') renderCart();
	if (page === 'checkout') renderCheckout();
	if (page === 'confirmation') renderConfirmation();
	if (page === 'admin') {
		if (typeof window.onAdminPageActivated === 'function') {
			window.onAdminPageActivated();
		}
	}
	window.scrollTo({ top: 0, behavior: 'smooth' });

	const displayRoute = (targetRoute === 'account') ? 'account' : (targetRoute === 'forgot-password' ? 'login' : page);
	history.replaceState(null, '', `#${displayRoute}${query}`);
}

async function openProduct(id) {
	if (!id) return;
	activeProduct = products.find(product => product.id === id);
	if (!activeProduct) {
		try {
			const resp = await fetch(`api/products.php?id=${id}`);
			if (resp.ok) {
				const data = await resp.json();
				if (data.success && data.product) {
					activeProduct = data.product;
				}
			}
		} catch (_) {}
	}
	if (!activeProduct) {
		showToast('Unable to open cake details.');
		return;
	}
	$('#modalImage').src = activeProduct.img;
	$('#modalTitle').textContent = activeProduct.name;
	$('#modalDesc').textContent = activeProduct.desc;
	const unitDisplay = activeProduct.sale_unit ? ` · ${activeProduct.sale_unit}` : '';
	$('#modalPrice').textContent = `${money(activeProduct.price)}${unitDisplay}`;
	$('#productModal').classList.add('open');
	document.body.style.overflow = 'hidden';

	// Dynamically configure unit / size choice using confirmed worksheet values
	const weightSelect = $('#weightSelect');
	const weightGroup = weightSelect ? weightSelect.closest('.choice-group') : null;
	const weightLabel = weightGroup ? weightGroup.querySelector('label') : null;
	const rawUnitText = activeProduct.confirmed_size || activeProduct.sale_unit || 'Standard size';
	const unitText = rawUnitText.replace(/\s*\(assumed[^)]*\)/gi, '').trim();

	if (weightSelect) {
		if (weightLabel) {
			if (activeProduct.sale_unit && activeProduct.sale_unit.toLowerCase().includes('box')) {
				weightLabel.textContent = 'Package Size';
			} else if (activeProduct.cat && activeProduct.cat.includes('wedding')) {
				weightLabel.textContent = 'Tier Configuration';
			} else if (activeProduct.cat && activeProduct.cat.includes('number')) {
				weightLabel.textContent = 'Numeral Configuration';
			} else {
				weightLabel.textContent = 'Size / Weight';
			}
		}
		weightSelect.innerHTML = `<option value="${escapeHtml(unitText)}" selected>${escapeHtml(unitText)} — ${money(activeProduct.price)}</option>`;
		weightSelect.value = unitText;
	}

	if ($('#cakeFlavor')) $('#cakeFlavor').value = 'Not sure yet';
	if ($('#specialNote')) $('#specialNote').value = '';
	const flavorCategories = ['wedding', 'anniversary', 'birthday', 'cupcake', 'engagement', 'graduation', 'party', 'number'];
	if ($('#flavorChoice')) $('#flavorChoice').hidden = !flavorCategories.some(category => (activeProduct.cat || '').includes(category));
}

function closeModal() { $('#productModal').classList.remove('open'); document.body.style.overflow = ''; }

function saveCart(sync = true) {
	localStorage.setItem('kukiCart', JSON.stringify(cart));
	$('#cartCount').textContent = cart.reduce((total, item) => total + item.qty, 0);
	renderCart();
	if (sync && typeof window.syncCartWithServer === 'function') {
		window.syncCartWithServer(cart);
	}
}

window.setClientCart = function(newCart) {
	cart = Array.isArray(newCart) ? newCart : [];
	saveCart(false);
};

function matchesCartItem(item, itemId, weight, flavor, note) {
	return item.id === itemId && (item.weight || 'Standard') === weight && (item.flavor || '') === flavor && (item.note || '') === note;
}

function renderCart() {
	const box = $('#cartItems');
	box.innerHTML = cart.length ? cart.map(item => `<article class="cart-item"><img src="${item.img}" alt="${item.name}"><div><h3>${item.name}</h3><p>Size / Unit: ${item.weight || 'Standard'}${item.flavor ? ` · Flavour: ${item.flavor}` : ''}${item.note ? ` · Note: ${item.note}` : ''}</p><div class="qty"><button data-qty="-1" data-id="${item.id}" data-weight="${item.weight || 'Standard'}" data-flavor="${item.flavor || ''}" data-note="${item.note || ''}">−</button><b>${item.qty}</b><button data-qty="1" data-id="${item.id}" data-weight="${item.weight || 'Standard'}" data-flavor="${item.flavor || ''}" data-note="${item.note || ''}">+</button></div></div><div class="item-price"><b>${money(item.price * item.qty)}</b><button class="remove" data-remove="${item.id}" data-weight="${item.weight || 'Standard'}" data-flavor="${item.flavor || ''}" data-note="${item.note || ''}">Remove</button></div></article>`).join('') : '<div class="empty-cart"><div>🎂</div><h3>Your cake box is empty</h3><p class="subtext">A celebration this sweet deserves a cake.</p><button class="btn btn-primary" data-page="cakes">Explore our cakes</button></div>';
	const subtotal = cart.reduce((total, item) => total + item.price * item.qty, 0);
	$('#subtotal').textContent = money(subtotal);
	$('#total').textContent = money(subtotal);
}

function getMinDeliveryDateString() {
	const now = new Date();
	const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
	const colomboNow = new Date(utc + (330 * 60000));
	colomboNow.setDate(colomboNow.getDate() + 2); // 48-hour advance notice
	const yyyy = colomboNow.getFullYear();
	const mm = String(colomboNow.getMonth() + 1).padStart(2, '0');
	const dd = String(colomboNow.getDate()).padStart(2, '0');
	return `${yyyy}-${mm}-${dd}`;
}

function initDeliveryDateInput() {
	const input = document.getElementById('deliveryDate');
	if (!input) return;
	const minDate = getMinDeliveryDateString();
	input.min = minDate;

	const validate = () => {
		let errEl = document.getElementById('deliveryDateErr');
		if (!errEl) {
			errEl = document.createElement('span');
			errEl.id = 'deliveryDateErr';
			errEl.className = 'field-error';
			errEl.style.cssText = 'font-size:12px;color:var(--berry);display:none;margin-top:4px;';
			input.parentNode.appendChild(errEl);
		}
		const parts = minDate.split('-');
		const minFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
		if (input.value && input.value < minDate) {
			errEl.textContent = `Celebration cakes require 48 hours notice. Earliest available date: ${minFormatted}.`;
			errEl.style.display = 'block';
			input.style.borderColor = 'var(--berry)';
			return false;
		} else {
			errEl.textContent = '';
			errEl.style.display = 'none';
			input.style.borderColor = '';
			return true;
		}
	};

	input.addEventListener('change', validate);
	input.addEventListener('input', validate);
}

function initInquiryDateInput() {
	const input = document.getElementById('eventDate');
	if (!input) return;
	const minDate = getMinDeliveryDateString();
	input.min = minDate;

	const validate = () => {
		let errEl = document.getElementById('eventDateErr');
		if (!errEl) {
			errEl = document.createElement('span');
			errEl.id = 'eventDateErr';
			errEl.className = 'field-error';
			errEl.style.cssText = 'font-size:12px;color:var(--berry);display:none;margin-top:4px;';
			input.parentNode.appendChild(errEl);
		}
		const parts = minDate.split('-');
		const minFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
		if (input.value && input.value < minDate) {
			errEl.textContent = `Inquiries require at least 48 hours advance notice (earliest available: ${minFormatted}).`;
			errEl.style.display = 'block';
			input.style.borderColor = 'var(--berry)';
			return false;
		} else {
			errEl.textContent = '';
			errEl.style.display = 'none';
			input.style.borderColor = '';
			return true;
		}
	};

	input.addEventListener('change', validate);
	input.addEventListener('input', validate);
}

async function autofillCheckoutFromProfile() {
	const nameInput    = document.getElementById('checkoutName');
	const phoneInput   = document.getElementById('checkoutPhone');
	const emailInput   = document.getElementById('checkoutEmail');
	const addressInput = document.getElementById('deliveryAddress');

	const needsAutofill = (nameInput && !nameInput.value.trim()) ||
	                      (phoneInput && !phoneInput.value.trim()) ||
	                      (emailInput && !emailInput.value.trim()) ||
	                      (addressInput && !addressInput.value.trim());
	if (!needsAutofill) return;

	try {
		const sessionRes = await fetch('api/check-session.php', { credentials: 'same-origin' });
		if (!sessionRes.ok) return;
		const sessionData = await sessionRes.json();
		if (!sessionData.authenticated || !sessionData.user) return;

		const user = sessionData.user;
		if (nameInput && !nameInput.value.trim() && user.name) {
			nameInput.value = user.name;
		}
		if (emailInput && !emailInput.value.trim() && user.email) {
			emailInput.value = user.email;
		}
		if (phoneInput && !phoneInput.value.trim() && user.phone) {
			phoneInput.value = user.phone;
		}

		if (addressInput && !addressInput.value.trim()) {
			const addrRes = await fetch('api/addresses.php?type=shipping', { credentials: 'same-origin' });
			if (addrRes.ok) {
				const addrData = await addrRes.json();
				if (addrData.success && Array.isArray(addrData.addresses) && addrData.addresses.length > 0) {
					const defaultAddr = addrData.addresses.find(a => a.is_default) || addrData.addresses[0];
					if (defaultAddr) {
						const parts = [defaultAddr.line1, defaultAddr.line2, defaultAddr.city, defaultAddr.postal].filter(Boolean);
						addressInput.value = parts.join(', ');
						if (phoneInput && !phoneInput.value.trim() && defaultAddr.phone) {
							phoneInput.value = defaultAddr.phone;
						}
						if (nameInput && !nameInput.value.trim() && defaultAddr.full_name) {
							nameInput.value = defaultAddr.full_name;
						}
					}
				}
			}
		}
	} catch (err) {
		console.warn('[KúkiCakes] Checkout autofill skipped:', err);
	}
}

function renderCheckout() {
	initDeliveryDateInput();
	const items = $('#checkoutItems');
	if (!items) return;
	items.innerHTML = cart.length ? cart.map(item => `<div class="checkout-item"><div><b>${item.name}</b><span>${item.weight || 'Standard'} · Qty ${item.qty}</span></div><strong>${money(item.price * item.qty)}</strong></div>`).join('') : '<p class="checkout-empty">Your cart is empty. Add a cake before checking out.</p>';
	const subtotal = cart.reduce((total, item) => total + item.price * item.qty, 0);
	const delivery = cart.length ? 500 : 0;
	$('#checkoutSubtotal').textContent = money(subtotal);
	$('#checkoutDelivery').textContent = money(delivery);
	$('#checkoutTotal').textContent = money(subtotal + delivery);
	autofillCheckoutFromProfile();
}

function renderConfirmation() {
	const order = JSON.parse(localStorage.getItem('kukiLastOrder') || '{}');
	if ($('#confirmationNumber')) $('#confirmationNumber').textContent = order.number || 'KC1025';
	if ($('#confirmationTotal')) $('#confirmationTotal').textContent = money(order.total || 0);
}

function showToast(message) { const toast = $('#toast'); toast.textContent = message; toast.classList.add('show'); clearTimeout(window.toastTimer); window.toastTimer = setTimeout(() => toast.classList.remove('show'), 2800); }

document.addEventListener('click', event => {
	const retryBtn = event.target.closest('#retryCatalogBtn, #retryGalleryBtn');
	if (retryBtn) {
		loadProducts();
		return;
	}
	const pageLink = event.target.closest('[data-page]');
	if (pageLink) {
		event.preventDefault();
		const href = pageLink.getAttribute('href') || '';
		const target = (href.startsWith('#') && href.includes('?')) ? href.slice(1) : pageLink.dataset.page;
		goPage(target);
		return;
	}
	const scrollLink = event.target.closest('[data-scroll]');
	if (scrollLink) { event.preventDefault(); goPage('home'); setTimeout(() => document.getElementById(scrollLink.dataset.scroll)?.scrollIntoView({ behavior: 'smooth' }), 100); return; }
	const productLink = event.target.closest('[data-product]');
	if (productLink) openProduct(Number(productLink.dataset.product));
	const quantity = event.target.closest('[data-qty]');
	if (quantity) { 
		const itemId = Number(quantity.dataset.id);
		const weight = quantity.dataset.weight || 'Standard';
		const flavor = quantity.dataset.flavor || '';
		const note = quantity.dataset.note || '';
		const item = cart.find(entry => matchesCartItem(entry, itemId, weight, flavor, note)); 
		if (item) { 
			item.qty += Number(quantity.dataset.qty); 
			if (item.qty < 1) cart = cart.filter(entry => !matchesCartItem(entry, itemId, weight, flavor, note)); 
			saveCart(); 
		} 
	}
	const remove = event.target.closest('[data-remove]');
	if (remove) { 
		const itemId = Number(remove.dataset.remove);
		const weight = remove.dataset.weight || 'Standard';
		const flavor = remove.dataset.flavor || '';
		const note = remove.dataset.note || '';
		cart = cart.filter(item => !matchesCartItem(item, itemId, weight, flavor, note)); 
		saveCart(); 
	}
});

if ($('#menuBtn')) {
	$('#menuBtn').onclick = () => $('#navLinks').classList.toggle('open');
}
$('#navLinks')?.querySelectorAll('a').forEach(link => {
	link.addEventListener('click', () => {
		$('#navLinks')?.classList.remove('open');
	});
});
document.addEventListener('click', event => {
	const navLinks = $('#navLinks');
	const menuBtn = $('#menuBtn');
	if (navLinks && navLinks.classList.contains('open')) {
		if (!navLinks.contains(event.target) && (!menuBtn || !menuBtn.contains(event.target))) {
			navLinks.classList.remove('open');
		}
	}
});
$('#searchBtn').onclick = () => {
	$('#siteSearch').classList.toggle('open');
	if ($('#siteSearch').classList.contains('open')) {
		goPage('cakes');
		$('#searchInput').focus();
	}
};
$('#searchClose').onclick = () => {
	searchTerm = '';
	$('#searchInput').value = '';
	$('#siteSearch').classList.remove('open');
	renderAllProducts();
};
$('#searchInput').oninput = event => {
	searchTerm = event.target.value.trim().toLowerCase();
	if (!$('#cakes-page').classList.contains('active')) goPage('cakes');
	renderAllProducts();
};
$('#modalClose').onclick = closeModal;
$('#productModal').onclick = event => { if (event.target.id === 'productModal') closeModal(); };
$('#modalAdd').onclick = () => { 
	// Get selected unit/size from dropdown
	const selectedWeight = $('#weightSelect') ? ($('#weightSelect').value || 'Standard') : 'Standard';
	const selectedFlavor = $('#flavorChoice').hidden ? '' : ($('#cakeFlavor').value || 'Not sure yet');
	const specialNote = $('#specialNote').value.trim();
	const existing = cart.find(item => item.id === activeProduct.id && item.weight === selectedWeight && (item.flavor || '') === selectedFlavor && (item.note || '') === specialNote);
	if (existing) {
		existing.qty++;
	} else {
		cart.push({ ...activeProduct, qty: 1, weight: selectedWeight, flavor: selectedFlavor, note: specialNote });
	}
	saveCart();
	closeModal();
	showToast(`${activeProduct.name} added to your selection`);
};
if ($('#quoteForm')) {
	const quoteForm = $('#quoteForm');
	const fileInput = quoteForm.querySelector('#inspiration');
	const uploadSpan = quoteForm.querySelector('.upload span');
	const defaultUploadText = uploadSpan ? uploadSpan.textContent : 'Upload a reference photo or sketch';

	if (fileInput && uploadSpan) {
		fileInput.addEventListener('change', () => {
			if (fileInput.files && fileInput.files[0]) {
				uploadSpan.textContent = fileInput.files[0].name;
			} else {
				uploadSpan.textContent = defaultUploadText;
			}
		});
	}

	quoteForm.onsubmit = async event => {
		event.preventDefault();
		const form = event.target;
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalBtnText = submitBtn ? submitBtn.textContent : 'Send my cake inquiry →';
		const successMsg = $('#successMessage');

		// Client validation
		const name = (form.querySelector('#name')?.value || '').trim();
		const phone = (form.querySelector('#phone')?.value || '').trim();
		const email = (form.querySelector('#email')?.value || '').trim();
		const cakeType = (form.querySelector('#cakeType')?.value || '').trim();
		const eventDate = (form.querySelector('#eventDate')?.value || '').trim();
		const servings = (form.querySelector('#servings')?.value || '').trim();

		if (!name || name.length < 2) {
			showToast('Please enter your full name (at least 2 characters).');
			form.querySelector('#name')?.focus();
			return;
		}
		if (!phone || !/^[0-9+\s\-().]{7,20}$/.test(phone)) {
			showToast('Please enter a valid phone number (at least 7 digits).');
			form.querySelector('#phone')?.focus();
			return;
		}
		if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			showToast('Please enter a valid email address.');
			form.querySelector('#email')?.focus();
			return;
		}
		if (!cakeType) {
			showToast('Please select a cake type.');
			form.querySelector('#cakeType')?.focus();
			return;
		}
		if (!eventDate) {
			showToast('Please select your event date.');
			form.querySelector('#eventDate')?.focus();
			return;
		}

		// Prevent past dates and dates under 48 hours notice in Asia/Colombo
		const minDate = getMinDeliveryDateString();
		const parts = minDate.split('-');
		const minFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
		if (eventDate < minDate) {
			const errEl = document.getElementById('eventDateErr');
			if (errEl) {
				errEl.textContent = `Inquiries require at least 48 hours advance notice (earliest available: ${minFormatted}).`;
				errEl.style.display = 'block';
			}
			showToast(`Inquiries require at least 48 hours advance notice (earliest available: ${minFormatted}).`);
			form.querySelector('#eventDate')?.focus();
			return;
		} else {
			const errEl = document.getElementById('eventDateErr');
			if (errEl) {
				errEl.textContent = '';
				errEl.style.display = 'none';
			}
		}

		if (servings && Number(servings) < 4) {
			showToast('Number of servings must be at least 4.');
			form.querySelector('#servings')?.focus();
			return;
		}

		// File validation if selected
		if (fileInput && fileInput.files && fileInput.files[0]) {
			const file = fileInput.files[0];
			if (file.size > 5 * 1024 * 1024) {
				showToast('Inspiration image must be under 5 MB.');
				return;
			}
			const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
			if (!allowedTypes.includes(file.type)) {
				showToast('Please upload a JPEG, PNG, or WebP image.');
				return;
			}
		}

		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting inquiry...';
		}

		try {
			const formData = new FormData(form);
			const response = await fetch('api/inquiries.php', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			});

			const data = await response.json();

			if (response.ok && data.success) {
				if (successMsg) {
					successMsg.classList.add('show');
					successMsg.textContent = data.message || 'Thank you! Your cake inquiry has been received. KukiCakes will contact you shortly.';
				}
				if (submitBtn) {
					submitBtn.textContent = 'Inquiry received ✓';
					// Re-enable after short delay in case user wants to submit another
					setTimeout(() => {
						submitBtn.disabled = false;
						submitBtn.textContent = originalBtnText;
					}, 4000);
				}
				form.reset();
				if (uploadSpan) {
					uploadSpan.textContent = defaultUploadText;
				}
				showToast(data.message || 'Your cake inquiry is on its way!');
			} else {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = originalBtnText;
				}
				showToast(data.message || 'Failed to submit inquiry. Please try again.');
			}
		} catch (err) {
			console.error('[KúkiCakes] Inquiry submission error:', err);
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.textContent = originalBtnText;
			}
			showToast('Network error submitting inquiry. Please check your connection.');
		}
	};
}
$('#checkoutBtn').onclick = () => {
	if (cart.length) goPage('checkout');
	else showToast('Add a cake before checking out');
};
if ($('#checkoutForm')) $('#checkoutForm').onsubmit = async event => {
	event.preventDefault();
	if (!cart.length) {
		showToast('Add a cake before placing an order');
		goPage('cart');
		return;
	}

	const form = $('#checkoutForm');
	const submitBtn = form.querySelector('.checkout-submit') || form.querySelector('button[type="submit"]');
	const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Place order / Pay now <span>→</span>';

	const fullName            = ($('#checkoutName')?.value || '').trim();
	const phoneNo             = ($('#checkoutPhone')?.value || '').trim();
	const email               = ($('#checkoutEmail')?.value || '').trim();
	const deliveryAddress     = ($('#deliveryAddress')?.value || '').trim();
	const deliveryDate        = ($('#deliveryDate')?.value || '').trim();
	const deliveryTimeSlot    = ($('#deliveryTime')?.value || '').trim();
	const specialInstructions = ($('#deliveryNote')?.value || '').trim();
	const paymentMethodEl     = document.querySelector('input[name="payment"]:checked');
	const paymentMethod       = paymentMethodEl ? paymentMethodEl.value : 'card';

	if (!fullName || !phoneNo || !email || !deliveryAddress || !deliveryDate || !deliveryTimeSlot) {
		showToast('Please fill in all required delivery details.');
		return;
	}

	const minDate = getMinDeliveryDateString();
	if (deliveryDate < minDate) {
		const parts = minDate.split('-');
		const minFormatted = `${parts[2]}/${parts[1]}/${parts[0]}`;
		showToast(`Orders require at least 48 hours notice (from ${minFormatted}).`);
		const input = document.getElementById('deliveryDate');
		if (input) {
			input.focus();
			input.style.borderColor = 'var(--berry)';
		}
		const errEl = document.getElementById('deliveryDateErr');
		if (errEl) {
			errEl.textContent = `Celebration cakes require 48 hours notice. Earliest available date: ${minFormatted}.`;
			errEl.style.display = 'block';
		}
		return;
	}

	if (submitBtn) {
		submitBtn.disabled = true;
		submitBtn.innerHTML = 'Processing order...';
	}

	try {
		// Sync local cart to server prior to order creation
		if (typeof window.syncCartWithServer === 'function') {
			try { await window.syncCartWithServer(cart); } catch (_) {}
		}

		const response = await fetch('api/create-order.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json'
			},
			body: JSON.stringify({
				full_name: fullName,
				phone_no: phoneNo,
				email: email,
				delivery_address: deliveryAddress,
				delivery_date: deliveryDate,
				delivery_time_slot: deliveryTimeSlot,
				special_instructions: specialInstructions,
				payment_method: paymentMethod
			})
		});

		const data = await response.json();

		if (!response.ok || !data.success) {
			const errorMsg = data.message || (data.errors ? Object.values(data.errors).join(', ') : 'Failed to place order.');
			showToast(errorMsg);
			if (response.status === 401) {
				goPage('auth-guard');
			}
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalBtnHtml;
			}
			return;
		}

		const order = data.order;

		// Cash on delivery or Bank transfer: complete order and proceed directly to confirmation
		if (paymentMethod === 'cash' || paymentMethod === 'transfer') {
			localStorage.setItem('kukiLastOrder', JSON.stringify({
				number: order.order_number,
				total: order.total,
				subtotal: order.subtotal,
				delivery: order.delivery_fee,
				method: paymentMethod
			}));

			// Clear cart only after successful order placement
			cart = [];
			saveCart();
			if (typeof window.clearServerCart === 'function') {
				window.clearServerCart();
			}

			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalBtnHtml;
			}
			goPage('confirmation');
			return;
		}

		// Credit / Debit card: Launch official PayHere Sandbox payment interface
		if (paymentMethod === 'card') {
			const payhereParams = data.payhere;
			if (!payhereParams || typeof window.payhere === 'undefined') {
				showToast('PayHere payment gateway is currently unavailable. Please try again.');
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalBtnHtml;
				}
				return;
			}

			// Configure PayHere JavaScript SDK callbacks
			window.payhere.onCompleted = async function onCompleted(orderId) {
				showToast('Payment completed successfully!');

				try {
					await fetch('api/confirm-card-payment.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json'
						},
						body: JSON.stringify({
							order_id: order.order_id,
							order_number: order.order_number || orderId
						})
					});
				} catch (err) {
					console.warn('Could not notify server of card payment completion:', err);
				}

				localStorage.setItem('kukiLastOrder', JSON.stringify({
					number: order.order_number,
					total: order.total,
					subtotal: order.subtotal,
					delivery: order.delivery_fee,
					method: 'card',
					paid: true
				}));

				// Clear cart only after successful payment state is established
				cart = [];
				saveCart();
				if (typeof window.clearServerCart === 'function') {
					window.clearServerCart();
				}

				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalBtnHtml;
				}
				goPage('confirmation');
			};

			window.payhere.onDismissed = function onDismissed() {
				// Keep user on checkout. Do not delete order, do not clear cart, do not falsely show success.
				showToast('Payment window closed. You can retry your payment anytime.');
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalBtnHtml;
				}
			};

			window.payhere.onError = function onError(error) {
				// Keep user on checkout. Show friendly error and do not mark as paid.
				showToast('Payment error: ' + (error || 'Transaction could not be completed.'));
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalBtnHtml;
				}
			};

			// Open official PayHere modal
			window.payhere.startPayment(payhereParams);
		}

	} catch (err) {
		showToast('Network error while placing order. Please try again.');
		if (submitBtn) {
			submitBtn.disabled = false;
			submitBtn.innerHTML = originalBtnHtml;
		}
	}
};
if ($('#newsletter')) $('#newsletter').onsubmit = event => { event.preventDefault(); showToast('You are on the sweet list!'); };
document.querySelectorAll('.filter[data-filter]').forEach(button => button.onclick = () => { document.querySelectorAll('.filter[data-filter]').forEach(item => item.classList.remove('active')); button.classList.add('active'); renderAllProducts(); });
if ($('#priceSort')) $('#priceSort').onchange = renderAllProducts;
document.querySelectorAll('.chips').forEach(group => group.onclick = event => { if (event.target.classList.contains('chip')) { group.querySelectorAll('.chip').forEach(chip => chip.classList.remove('active')); event.target.classList.add('active'); } });
document.addEventListener('keydown', event => {
	if (event.key === 'Escape') closeModal();
	if (event.key === 'Enter' || event.key === ' ') {
		const targetProduct = event.target.closest('.gallery-item[data-product]');
		if (targetProduct) {
			event.preventDefault();
			openProduct(Number(targetProduct.dataset.product));
		}
	}
});

loadProducts();
saveCart();
initDeliveryDateInput();
initInquiryDateInput();
const _knownPages = ['cakes', 'gallery', 'cart', 'checkout', 'confirmation', 'admin', 'signup', 'login', 'profile', 'my-orders', 'auth-guard', 'reset-password', 'forgot-password', 'account'];
const initialHash = location.hash.slice(1);
const initialPage = initialHash.split('?')[0].split('&')[0];
if (_knownPages.includes(initialPage)) goPage(initialHash || initialPage);

window.addEventListener('hashchange', () => {
	const raw = location.hash.slice(1);
	const target = raw.split('?')[0].split('&')[0];
	if (_knownPages.includes(target)) {
		goPage(raw || target);
	}
});
