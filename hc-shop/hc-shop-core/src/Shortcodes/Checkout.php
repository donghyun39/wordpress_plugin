<?php
namespace HC\Shop\Shortcodes;

final class Checkout
{
    public function register(): void
    {
        add_shortcode('hc_checkout', [$this, 'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return '';

        $s = get_option('hc_shop_settings', []);
        $payments_enabled = isset($s['payments_enabled']) ? (int)$s['payments_enabled'] : 0;
        $pg_provider      = (string)($s['pg_provider'] ?? 'none');
        $toss_client_key  = (string)($s['pg_toss_client_key'] ?? '');

        // 결제 아예 OFF면 안내
        if (!$payments_enabled) {
            return '<div class="hc-shop"><p>현재 온라인 결제를 지원하지 않습니다. <a href="' .
                esc_url(get_permalink(get_page_by_path('inquiry'))) .
                '">문의하기</a>를 이용해주세요.</p></div>';
        }

        $endpoint = esc_url(rest_url('hc/v1/checkout/create-order'));
        $complete = get_permalink(get_page_by_path('order-complete'));

        ob_start(); ?>
        <div class="hc-shop">
            <h2>Checkout</h2>
            <form id="hc-checkout" class="hc-form"
                  data-endpoint="<?php echo $endpoint; ?>"
                  data-complete="<?php echo esc_url($complete); ?>">
                <fieldset>
                    <legend>구매자 정보</legend>
                    <label>이름 <input type="text" name="billing[name]" required></label>
                    <label>휴대폰 <input type="text" name="billing[phone]" required></label>
                    <label>이메일 <input type="email" name="billing[email]" required></label>
                </fieldset>
                <fieldset>
                    <legend>배송지</legend>
                    <label>주소1 <input type="text" name="shipping[address1]" required></label>
                    <label>주소2 <input type="text" name="shipping[address2]"></label>
                    <label>우편번호 <input type="text" name="shipping[postcode]" required></label>
                </fieldset>
                <button class="hc-btn hc-solid" type="submit">주문하기</button>
            </form>

            <div id="hc-checkout-msg" style="margin-top:10px;"></div>

            <?php if ($pg_provider === 'toss' && $toss_client_key): ?>
                <!-- 토스 위젯 자리 -->
                <div id="toss-payment-root" style="display:none;margin-top:16px"
                     data-success="<?php echo esc_url($complete); ?>">
                    <div id="toss-payment-methods"></div>
                    <div id="toss-agreement" style="margin-top:12px;"></div>
                    <button id="toss-pay" class="hc-btn hc-solid" style="margin-top:16px;">결제하기</button>
                </div>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            function serialize(form) {
                const data = { billing: {}, shipping: {} };
                new FormData(form).forEach((v, k) => {
                    if (k.startsWith('billing['))   data.billing[k.slice(8, -1)] = v;
                    else if (k.startsWith('shipping[')) data.shipping[k.slice(9, -1)] = v;
                });
                return data;
            }
            const f = document.getElementById('hc-checkout');
            if (!f) return;

            f.addEventListener('submit', async (e) => {
                e.preventDefault();
                const ep   = f.getAttribute('data-endpoint');
                const done = f.getAttribute('data-complete');
                const msg  = document.getElementById('hc-checkout-msg');

                msg.textContent = '주문 생성 중...';
                try {
                    const res  = await fetch(ep, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(serialize(f))
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        msg.textContent = (data && data.error && data.error.message) || '주문 생성 실패';
                        return;
                    }

                    // 서버가 반환하는 값 예상: { order_id, order_number, totals:{grand}, title }
                    const orderDbId   = data.order_id;
                    const orderNo     = data.order_number || String(orderDbId); // 위젯 orderId로 사용할 문자열
                    const amount      = (data.totals && parseInt(data.totals.grand||0,10)) || 0;
                    const orderName   = data.title || '주문';

                    // 토스 사용 가능하면 위젯으로
                    if (window.HCShop && HCShop.pg && HCShop.pg.provider === 'toss' && HCShop.pg.toss && HCShop.pg.toss.client_key) {
                        document.getElementById('toss-payment-root').style.display = '';
                        msg.textContent = '결제창을 준비하고 있어요...';
                        // checkout-toss.js가 이 이벤트를 받아 결제 진행
                        window.dispatchEvent(new CustomEvent('hc:orderCreated', {
                            detail: { orderDbId, orderNo, amount, orderName }
                        }));
                    } else {
                        // 폴백: 주문완료로 이동
                        const url = new URL(done, location.origin);
                        url.searchParams.set('order', orderDbId);
                        location.href = url.toString();
                    }
                } catch (err) {
                    msg.textContent = '네트워크 오류';
                }
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }
}
