(function () {
  function qs(s, r = document) { return r.querySelector(s); }

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.HCShop || !HCShop.pg || HCShop.pg.provider !== 'toss') return;
    const clientKey = HCShop.pg.toss && HCShop.pg.toss.client_key;
    if (!clientKey) return;

    // 고객 식별 키(임의 생성/보존)
    const customerKey = (function () {
      const k = localStorage.getItem('hc_customer_key');
      if (k) return k;
      const nk = 'hc_' + Math.random().toString(36).slice(2);
      localStorage.setItem('hc_customer_key', nk);
      return nk;
    })();

    let paymentWidget = null;

    // 주문 생성 후 신호를 받으면 위젯 구성 및 결제 요청
    window.addEventListener('hc:orderCreated', function (ev) {
      const root = qs('#toss-payment-root');
      if (!root) return;

      const { orderDbId, orderNo, amount, orderName } = ev.detail || {};
      if (!orderNo || !amount) {
        alert('결제 정보가 올바르지 않습니다.'); return;
      }
      const successBase = root.getAttribute('data-success') || '/';
      const successUrl  = successBase + '?order=' + encodeURIComponent(orderDbId) + '&provider=toss';
      const failUrl     = location.pathname + '?pay_failed=1';

      // 위젯 초기화 & 렌더 (최초 1회)
      if (!paymentWidget) {
        paymentWidget = PaymentWidget(clientKey, customerKey);
        paymentWidget.renderPaymentMethods('#toss-payment-methods', { value: amount });
        paymentWidget.renderAgreement('#toss-agreement');
      }

      const btn = qs('#toss-pay');
      if (!btn) return;
      btn.onclick = async function () {
        try {
          await paymentWidget.requestPayment({
            orderId:  String(orderNo),     // Toss에서 식별하는 주문번호(문자열)
            orderName: String(orderName),
            amount:   Number(amount),
            successUrl, failUrl
          });
        } catch (err) {
          alert((err && err.message) || '결제 요청 실패');
        }
      };
    });
  });
})();
