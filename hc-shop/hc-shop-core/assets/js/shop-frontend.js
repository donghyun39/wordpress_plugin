(function () {
  function qs(s, r = document) { return r.querySelector(s); }
  function qsa(s, r = document) { return Array.from(r.querySelectorAll(s)); }

  async function api(url, method = 'GET', body) {
    const res = await fetch(url, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined
    });
    let data = {};
    try { data = await res.json(); } catch(_) {}
    if (!res.ok) throw (data?.error || { message: '요청 실패' });
    return data;
  }

  function confirmGoCart() {
    return window.confirm('장바구니에 담았습니다.\n장바구니로 이동하시겠습니까?');
  }

  // ===== 상세 페이지 옵션 & 가격 =====
  function syncDetailPrice() {
    const root = qs('#hc-product-detail');
    if (!root) return;
    const base = Number(root.getAttribute('data-base') || 0);

    let delta = 0;

    // variant delta
    const v = qs('input[name="hc_variant"]:checked');
    if (v) {
      const label = v.closest('label');
      const dEl = label && label.querySelector('.delta');
      if (dEl && dEl.textContent) {
        // "+1,000원" / "-500원"
        const raw = dEl.textContent.replace(/[^\-\d]/g, '');
        delta += Number(raw || 0);
      }
    }

    // addons delta
    qsa('#hc-addons input[type="checkbox"]:checked').forEach(chk => {
      const label = chk.closest('label');
      const dEl = label && label.querySelector('.delta');
      if (dEl && dEl.textContent) {
        const raw = dEl.textContent.replace(/[^\-\d]/g, '');
        delta += Number(raw || 0);
      }
    });

    const priceEl = qs('#hc-price');
    if (priceEl) priceEl.textContent = (base + delta).toLocaleString();
  }

  function collectSelections() {
    const root = qs('#hc-product-detail');
    if (!root) return null;
    const pid = root.getAttribute('data-id');

    // variant (single)
    let variant = null;
    const v = qs('input[name="hc_variant"]:checked');
    if (v) variant = Number(v.value);

    // addons (multi)
    const addons = qsa('#hc-addons input[type="checkbox"]:checked').map(x => Number(x.value));

    return { pid, variant, addons };
  }

  async function addToCartFromDetail(goCheckoutOrInquiry) {
    const root = qs('#hc-product-detail');
    if (!root) return;

    const { pid, variant, addons } = collectSelections() || {};
    const qty = Number(qs('#hc-qty')?.value || 1);
    const sku = qs('#hc-add-to-cart')?.getAttribute('data-sku') || '';

    try {
      const payload = { product_id: Number(pid), qty, sku };
      if (variant !== null && !isNaN(variant)) payload.variant = variant;
      if (addons && addons.length) payload.addons = addons;
      await api(HCShop.rest.add_item, 'POST', payload);

      if (goCheckoutOrInquiry === 'checkout') {
        location.href = HCShop.urls.checkout;
      } else if (goCheckoutOrInquiry === 'inquiry') {
        if (HCShop.inquiry_flow === 'combined') location.href = HCShop.urls.cartInquiry;
        else location.href = HCShop.urls.cart;
      } else {
        if (confirmGoCart()) location.href = HCShop.urls.cart;
      }
    } catch (e) {
      alert(e?.message || '추가 실패');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // 가격 동기화
    document.addEventListener('change', function (e) {
      const t = e.target;
      if (!t) return;
      if (t.name === 'hc_variant' || t.classList.contains('hc_addon')) {
        syncDetailPrice();
      }
    });
    syncDetailPrice();

    // 상세 - 장바구니
    const addBtn = qs('#hc-add-to-cart');
    if (addBtn) addBtn.addEventListener('click', () => addToCartFromDetail('add'));

    // 상세 - 구매하기(결제ON)
    const buyBtn = qs('#hc-buy-now');
    if (buyBtn) buyBtn.addEventListener('click', () => addToCartFromDetail('checkout'));

    // 상세 - 문의하기(결제OFF)
    const askBtn = qs('#hc-contact');
    if (askBtn) askBtn.addEventListener('click', () => addToCartFromDetail('inquiry'));

    // GRID 카드 “담기” (옵션이 있는 상품은 상세로 유도하는 것이 안전)
    document.addEventListener('click', async function (e) {
      const btn = e.target.closest('.hc-add');
      if (!btn) return;
      const card = btn.closest('.hc-card');
      const pid = card?.dataset.id;
      const sku = card?.dataset.sku || '';
      try {
        await api(HCShop.rest.add_item, 'POST', { product_id: Number(pid), qty: 1, sku });
        if (confirmGoCart()) location.href = HCShop.urls.cart;
      } catch (err) {
        alert(err?.message || '추가 실패');
      }
    });
  });
})();
