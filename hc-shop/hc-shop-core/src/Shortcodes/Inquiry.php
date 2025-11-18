<?php
namespace HC\Shop\Shortcodes;

final class Inquiry
{
    public function register(): void
    {
        add_shortcode('hc_inquiry', [$this,'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return '';
        ob_start(); ?>
        <div class="hc-shop">
          <h2>문의하기</h2>
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
