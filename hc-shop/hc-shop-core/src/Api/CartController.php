<?php
namespace HC\Shop\Api;

use WP_REST_Request;
use WP_REST_Response;
use HC\Shop\Data\ProductRepo;

final class CartController
{
    private const COOKIE = 'hc_sid';
    private const TTL = 2 * DAY_IN_SECONDS;

    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/cart', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'getCart']
        ]);

        register_rest_route('hc/v1', '/cart/items', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'addItem']
        ]);

        register_rest_route('hc/v1', '/cart/items/(?P<key>[a-f0-9]{8,40})', [
            'methods' => 'DELETE',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'removeItem']
        ]);

        register_rest_route('hc/v1', '/cart/items/(?P<key>[a-f0-9]{8,40})', [
            'methods' => 'PATCH',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'updateQty']
        ]);
    }

    public function getCart(WP_REST_Request $req): WP_REST_Response
    {
        $cart = $this->load();
        return new WP_REST_Response($this->totals($cart));
    }

    public function addItem(WP_REST_Request $req): WP_REST_Response
    {
        $p = $req->get_json_params() ?: $req->get_body_params();

        $pid = (int)($p['product_id'] ?? 0);
        $sku = sanitize_text_field($p['sku'] ?? '');
        $qty = max(1, (int)($p['qty'] ?? 1));

        // 새 옵션(변형/추가옵션)
        // variant: 정수 index | null
        // addons:  정수 index 배열
        $variantIndex = isset($p['variant']) ? (int)$p['variant'] : null;
        $addonIdx = $this->toIntArray($p['addons'] ?? []);

        if ($pid <= 0 && $sku === '') {
            return $this->err('invalid_request', 'product_id 또는 sku가 필요합니다.', 400);
        }

        // 상품 조회
        $repo = new ProductRepo();
        $row = $pid > 0 ? $repo->get($pid) : ($repo->getBySku($sku) ?? null);
        if (!$row || ($row['status'] ?? '') !== 'publish') {
            return $this->err('not_found', '상품을 찾을 수 없거나 판매 불가 상태입니다.', 404);
        }

        $pid = (int)$row['id'];
        $basePrice = (int)($row['price'] ?? 0);
        $baseSku   = (string)($row['sku'] ?? '');

        // 옵션 구조 정규화 (구버전 호환)
        $options = is_array($row['options'] ?? null) ? $row['options'] : [];
        if (!isset($options['variant']) && !isset($options['addons'])) {
            // 예전 구조: 단일 배열을 add-ons로 간주
            $options = ['variant' => [], 'addons' => ($row['options'] ?? [])];
        }
        $variants = is_array($options['variant'] ?? null) ? $options['variant'] : [];
        $addons   = is_array($options['addons'] ?? null) ? $options['addons'] : [];

        // 변형 선택 처리
        $variant = null;
        $variantName = '';
        $variantSkuSuffix = '';
        $variantDelta = 0;
        $variantStock = null; // null이면 상품 전역 stock 사용

        if ($variants && $variantIndex !== null) {
            if (!isset($variants[$variantIndex])) {
                return $this->err('invalid_option', '잘못된 변형 선택입니다.', 400);
            }
            $variant = $variants[$variantIndex];
            $variantName = sanitize_text_field($variant['name'] ?? '');
            $variantDelta = (int)($variant['price_delta'] ?? 0);
            $variantSkuSuffix = (string)($variant['sku_suffix'] ?? '');
            $variantStock = isset($variant['stock']) ? max(0, (int)$variant['stock']) : null;
        } elseif ($variants && $variantIndex === null) {
            return $this->err('missing_option', '필수 변형을 선택해주세요.', 400);
        }

        // 추가옵션 처리
        $addonIdx = array_values(array_unique(array_filter($addonIdx, fn($i) => isset($addons[$i]))));
        $addonNames = [];
        $addonDelta = 0;
        foreach ($addonIdx as $i) {
            $a = $addons[$i];
            $addonNames[] = sanitize_text_field($a['name'] ?? '');
            $addonDelta += (int)($a['price_delta'] ?? 0);
        }

        // 단가/표시/재고 계산
        $unit = max(0, $basePrice + $variantDelta + $addonDelta);
        $resolvedSku = $baseSku . $variantSkuSuffix;

        // 재고 확인: 변형 stock 우선, 없으면 상품 stock
        $globalStock = max(0, (int)($row['stock'] ?? 0));
        $stockCap = $variantStock !== null ? $variantStock : $globalStock;

        // 현재 카트에 같은 조합이 있는지 확인
        $cart = $this->load();
        $lineKey = $this->buildKey($pid, $variantIndex, $addonIdx);

        $currQty = (int)($cart['items'][$lineKey]['qty'] ?? 0);
        if ($stockCap > 0 && ($currQty + $qty) > $stockCap) {
            return $this->err('out_of_stock', '선택하신 옵션의 재고가 부족합니다.', 409);
        }

        // 라인 설명
        $labelParts = [];
        if ($variantName !== '') $labelParts[] = $variantName;
        if ($addonNames) $labelParts[] = implode(' + ', $addonNames);
        $optionLabel = $labelParts ? implode(' / ', $labelParts) : '-';

        // 아이템 합치기/추가
        if (!isset($cart['items'][$lineKey])) {
            $cart['items'][$lineKey] = [
                'key'         => $lineKey,
                'product_id'  => $pid,
                'sku'         => $resolvedSku,
                'name'        => (string)$row['title'],
                'unit_price'  => $unit,
                'qty'         => 0,
                'variant'     => $variantIndex,
                'addons'      => $addonIdx,
                'option_label'=> $optionLabel,
                'image_id'    => (int)($row['image_id'] ?? 0),
            ];
        }
        $cart['items'][$lineKey]['qty'] += $qty;

        $this->save($cart);
        return new WP_REST_Response($this->totals($cart), 201);
    }

    public function updateQty(WP_REST_Request $req): WP_REST_Response
    {
        $key = sanitize_text_field($req['key']);
        $p = $req->get_json_params() ?: $req->get_body_params();
        $qty = max(0, (int)($p['qty'] ?? 0));

        $cart = $this->load();
        if (!isset($cart['items'][$key])) return $this->getCart($req);

        // 재고 캡 확인
        $it = $cart['items'][$key];
        $repo = new ProductRepo();
        $row = $repo->get((int)$it['product_id']);
        if ($row) {
            $options = is_array($row['options'] ?? null) ? $row['options'] : [];
            if (!isset($options['variant']) && !isset($options['addons'])) {
                $options = ['variant' => [], 'addons' => ($row['options'] ?? [])];
            }
            $variants = $options['variant'] ?? [];
            $vIdx = $it['variant'] ?? null;
            $variantStock = ($vIdx !== null && isset($variants[$vIdx]) && isset($variants[$vIdx]['stock']))
                ? max(0, (int)$variants[$vIdx]['stock']) : null;
            $globalStock = max(0, (int)($row['stock'] ?? 0));
            $cap = $variantStock !== null ? $variantStock : $globalStock;
            if ($cap > 0 && $qty > $cap) $qty = $cap;
        }

        if ($qty === 0) unset($cart['items'][$key]);
        else $cart['items'][$key]['qty'] = $qty;

        $this->save($cart);
        return new WP_REST_Response($this->totals($cart));
    }

    public function removeItem(WP_REST_Request $req): WP_REST_Response
    {
        $key = sanitize_text_field($req['key']);
        $cart = $this->load();
        unset($cart['items'][$key]);
        $this->save($cart);
        return new WP_REST_Response($this->totals($cart));
    }

    // ===== 내부 =====
    private function sid(): string
    {
        if (!isset($_COOKIE[self::COOKIE]) || !preg_match('/^[A-Za-z0-9]{12,}$/', $_COOKIE[self::COOKIE])) {
            $sid = bin2hex(random_bytes(8));
            setcookie(self::COOKIE, $sid, time()+self::TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE[self::COOKIE] = $sid;
        }
        return $_COOKIE[self::COOKIE];
    }

    private function load(): array
    {
        $sid = $this->sid();
        $cart = get_transient('hc_cart_' . $sid);
        if (!is_array($cart)) $cart = ['items'=>[]];
        return $cart;
    }

    private function save(array $cart): void
    {
        set_transient('hc_cart_' . $this->sid(), $cart, self::TTL);
    }

    private function totals(array $cart): array
    {
        $subtotal = 0.0; $items = array_values($cart['items']);
        foreach ($items as &$it) {
            $it['unit_price'] = (int)$it['unit_price'];
            $it['line_total'] = $it['unit_price'] * (int)$it['qty'];
            $subtotal += $it['line_total'];
        }
        unset($it);

        $s = get_option('hc_shop_settings', []);
        $flat = (int)($s['shipping_flat_fee'] ?? 3000);
        $freeOver = (int)($s['shipping_free_over'] ?? 30000);
        $shipping = ($freeOver > 0 && $subtotal >= $freeOver) ? 0 : $flat;
        $grand = $subtotal + $shipping;

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => (int)$subtotal,
                'shipping' => (int)$shipping,
                'grand_total' => (int)$grand,
                'currency' => ($s['currency'] ?? 'KRW')
            ]
        ];
    }

    private function buildKey(int $pid, ?int $variantIndex, array $addonIdx): string
    {
        sort($addonIdx);
        $seed = $pid . '|' . ($variantIndex === null ? '-' : $variantIndex) . '|' . implode(',', $addonIdx);
        return substr(md5($seed), 0, 12);
    }

    private function toIntArray($v): array {
        if (is_string($v)) { $try = json_decode($v, true); if (is_array($try)) $v = $try; }
        if (!is_array($v)) return [];
        return array_map(fn($x)=> (int)$x, $v);
    }

    private function err(string $code, string $msg, int $status): WP_REST_Response
    {
        return new WP_REST_Response(['error'=>['code'=>$code,'message'=>$msg]], $status);
    }
}
