<?php
namespace HC\Shop\Shortcodes;

final class CartInquiry
{
    public function register(): void
    {
        add_shortcode('hc_cart_inquiry', [$this,'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return '';
        ob_start(); ?>
        <div class="hc-shop">
          <h2>장바구니 + 문의하기</h2>

          <div id="hc-ci-cart">
            <table class="hc-cart-table" style="display:none">
              <thead><tr><th>상품</th><th>수량</th><th>단가</th><th>소계</th></tr></thead>
              <tbody></tbody>
            </table>
            <div class="hc-cart-empty" style="display:none">장바구니가 비어있습니다.</div>
          </div>

          <hr style="margin:16px 0">

          <form id="hc-inquiry-form" class="hc-form">
            <label>이름 <input type="text" name="name" required></label>
            <label>휴대폰 <input type="text" name="phone" required></label>
            <label>이메일 <input type="email" name="email" required></label>
            <label>문의 내용 <textarea name="message" rows="6" required></textarea></label>
            <button class="hc-btn hc-solid" type="submit">문의 보내기</button>
          </form>
          <div id="hc-inquiry-msg" style="margin-top:10px;"></div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
