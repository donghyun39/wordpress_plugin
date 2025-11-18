<?php
// 상점 관련 페이지 감지 (설치 시 생성한 페이지들)
function hc_shop_is_page(): bool {
    if (!function_exists('is_page')) return false;
    return is_page(['shop','cart','checkout','order-complete','account','inquiry','cart-inquiry']);
}
