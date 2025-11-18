(function () {
  const root = document.querySelector('#hc-cart');
  if (!root) return;

  const ep = root.getAttribute('data-endpoint') || (window.HCShop?.rest?.cart || '');
  const table = root.querySelector('.hc-cart-table');
  const tbody = table.querySelector('tbody');
  const empty = root.querySelector('.hc-cart-empty');
  const summary = root.querySelector('.hc-cart-summary');

  function fmt(n) { return Number(n || 0).toLocaleString(); }
  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  async function load() {
    const res = await fetch(ep);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { console.error(data); return; }
    render(data);
  }

  function render(data) {
    const items = data.items || [];
    if (!items.length) {
      table.style.display = 'none';
      summary.style.display = 'none';
      empty.style.display = 'block';
      return;
    }
    empty.style.display = 'none';
    table.style.display = 'table';
    summary.style.display = 'block';

    tbody.innerHTML = items.map(rowHtml).join('');

    const t = data.totals || {};
    root.querySelector('#hc-subtotal').textContent = fmt(t.subtotal);
    root.querySelector('#hc-shipping').textContent = fmt(t.shipping);
    root.querySelector('#hc-grand').textContent = fmt(t.grand_total);
  }

  function rowHtml(it) {
    const opt = it.option_label ? esc(it.option_label) : '-';
    return `
      <tr data-key="${esc(it.key)}">
        <td class="td-product"><div class="p-title">${esc(it.name)}</div></td>
        <td class="td-option">${opt}</td>
        <td class="td-price">${fmt(it.unit_price)}원</td>
        <td class="td-qty">
          <button class="qty-dec" aria-label="감소">-</button>
          <input type="number" class="qty" min="1" value="${Number(it.qty) || 1}">
          <button class="qty-inc" aria-label="증가">+</button>
        </td>
        <td class="td-line">${fmt(it.line_total)}원</td>
        <td class="td-remove"><button class="remove" aria-label="삭제">×</button></td>
      </tr>`;
  }

  async function setQty(key, qty) {
    const url = ep.replace(/\/cart$/, '') + '/cart/items/' + key;
    const res = await fetch(url, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ qty }) });
    const data = await res.json().catch(() => ({}));
    if (res.ok) render(data); else alert(data?.error?.message || '수량 변경 실패');
  }

  async function removeRow(key) {
    const url = ep.replace(/\/cart$/, '') + '/cart/items/' + key;
    const res = await fetch(url, { method: 'DELETE' });
    const data = await res.json().catch(() => ({}));
    if (res.ok) render(data); else alert(data?.error?.message || '삭제 실패');
  }

  // 수량 및 삭제 핸들러
  root.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-key]');
    if (!tr) return;
    const key = tr.getAttribute('data-key');

    if (e.target.classList.contains('qty-inc')) {
      const n = Number(tr.querySelector('input.qty')?.value || 1) + 1;
      setQty(key, n);
    } else if (e.target.classList.contains('qty-dec')) {
      const n = Math.max(1, Number(tr.querySelector('input.qty')?.value || 1) - 1);
      setQty(key, n);
    } else if (e.target.classList.contains('remove')) {
      removeRow(key);
    }
  });

  root.addEventListener('change', function (e) {
    if (!e.target.matches('input.qty')) return;
    const tr = e.target.closest('tr[data-key]');
    const key = tr.getAttribute('data-key');
    const n = Math.max(1, Number(e.target.value || 1));
    setQty(key, n);
  });

  // 비우기
  const clearBtn = root.querySelector('.hc-btn.hc-clear');
  if (clearBtn) clearBtn.addEventListener('click', async function () {
    const res = await fetch(ep);
    const data = await res.json().catch(() => ({}));
    const items = data.items || [];
    for (const it of items) { await removeRow(it.key); }
  });

  load();
})();
