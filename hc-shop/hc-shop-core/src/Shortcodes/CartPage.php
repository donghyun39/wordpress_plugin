<?php
namespace HC\Shop\Shortcodes;

final class CartPage
{
    public function register(): void
    {
        add_shortcode('hc_cart', [$this, 'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page())
            return '';
        $ep = esc_url(rest_url('hc/v1/cart'));
        $shop = get_permalink(get_page_by_path('shop')) ?: home_url('/');
        $checkout = get_permalink(get_page_by_path('checkout')) ?: home_url('/');
        ob_start(); ?>
        <div class="hc-shop">
            <h2>Cart</h2>
            <div id="hc-cart" data-endpoint="<?php echo $ep; ?>">
                <div class="hc-cart-empty" style="display:none">장바구니가 비어있습니다.</div>
                <table class="hc-cart-table" style="display:none">
                    <thead>
                        <tr>
                            <th>상품</th>
                            <th>옵션</th>
                            <th>단가</th>
                            <th>수량</th>
                            <th>소계</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <?php
                $cfg = get_option('hc_shop_settings', []);
                $payments_enabled = (int) ($cfg['payments_enabled'] ?? 1);
                $inquiry_flow = (string) ($cfg['inquiry_flow'] ?? 'cart_then_inquiry');
                $inquiry_url = $inquiry_flow === 'combined'
                    ? (get_permalink(get_page_by_path('cart-inquiry')) ?: home_url('/cart-inquiry'))
                    : (get_permalink(get_page_by_path('inquiry')) ?: home_url('/inquiry'));
                ?>
                <div class="hc-cart-summary" style="display:none">
                    <div><span>상품 합계</span><strong id="hc-subtotal">0</strong>원</div>
                    <div><span>배송비</span><strong id="hc-shipping">0</strong>원</div>
                    <div class="hc-grand"><span>총 결제금액</span><strong id="hc-grand">0</strong>원</div>
                    <div class="hc-actions">
                        <a class="hc-btn" href="<?php echo esc_url($shop); ?>">계속 쇼핑</a>

                        <?php if ($payments_enabled): ?>
                            <a class="hc-btn hc-solid" href="<?php echo esc_url($checkout); ?>">결제하기</a>
                        <?php else: ?>
                            <a class="hc-btn hc-solid" href="<?php echo esc_url($inquiry_url); ?>">문의하기</a>
                        <?php endif; ?>

                        <button class="hc-btn hc-clear">비우기</button>
                    </div>
                </div>

            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
