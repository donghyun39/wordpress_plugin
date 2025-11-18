(function(){
  function qs(s,r=document){return r.querySelector(s);}
  function qsa(s,r=document){return Array.from(r.querySelectorAll(s));}
  async function api(url, method='GET', body){
    const res = await fetch(url,{
      method, headers: body?{'Content-Type':'application/json'}:{},
      body: body?JSON.stringify(body):undefined
    });
    const data = await res.json().catch(()=>({}));
    if(!res.ok) throw data?.error || {message:'요청 실패'};
    return data;
  }
  function fmt(n){return Number(n||0).toLocaleString('ko-KR');}

  async function loadCartInto(root){
    if(!root) return;
    try{
      const data = await api(HCShop.rest.cart, 'GET');
      const items = data?.items||[];
      const tbl = root.querySelector('.hc-cart-table');
      const empty = root.querySelector('.hc-cart-empty');
      const tbody = tbl.querySelector('tbody');
      if(!items.length){ empty.style.display='block'; tbl.style.display='none'; return; }
      empty.style.display='none'; tbl.style.display='';
      tbody.innerHTML='';
      items.forEach(it=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${it.name||''}</td>
          <td>${it.qty||1}</td>
          <td>${fmt(it.unit_price)}원</td>
          <td>${fmt((it.unit_price||0)*(it.qty||1))}원</td>
        `;
        tbody.appendChild(tr);
      });
    }catch(e){ /* ignore */ }
  }

  document.addEventListener('DOMContentLoaded', function(){
    // 통합 페이지라면 카트 렌더
    loadCartInto(qs('#hc-ci-cart'));

    const form = qs('#hc-inquiry-form');
    const msg  = qs('#hc-inquiry-msg');
    if(!form) return;

    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const f = new FormData(form);
      const payload = {
        name: f.get('name')||'',
        phone: f.get('phone')||'',
        email: f.get('email')||'',
        message: f.get('message')||'',
        source: 'cart'
      };
      msg.textContent='전송 중...';
      try{
        await api(HCShop.rest.inquiry, 'POST', payload);
        msg.textContent='문의가 접수되었습니다. 빠르게 연락드리겠습니다.';
        form.reset();
      }catch(err){
        msg.textContent = err?.message || '전송 실패';
      }
    });
  });
})();
