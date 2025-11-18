<?php
namespace HC\Shop\Shortcodes;

use HC\Shop\Data\ProductRepo;

final class Shop
{
    public function register(): void
    {
        add_shortcode('hc_shop', [$this, 'render']);
    }

    public function render($atts = []): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page())
            return '';

        $pid = (int)($_GET['product_id'] ?? 0);
        return $pid > 0 ? $this->renderDetail($pid) : $this->renderGrid();
    }

    // GRID
    private function renderGrid(): string
    {
        $repo = new ProductRepo();
        $res = $repo->list(24, 1, '');
        $items = array_filter($res['items'] ?? [], fn($r) => ($r['status'] ?? '') === 'publish');

        $shop_url = get_permalink(get_page_by_path('shop'));
        $endpoint = esc_url(rest_url('hc/v1/cart/items'));

        ob_start(); ?>
        <div class="hc-shop">
            <div class="hc-shop-grid" data-endpoint="<?php echo $endpoint; ?>">
                <?php foreach ($items as $r):
                    $img = $r['image_id'] ? (wp_get_attachment_image_url((int)$r['image_id'], 'medium') ?: '') : '';
                ?>
                    <div class="hc-card" data-id="<?php echo (int)$r['id']; ?>" data-sku="<?php echo esc_attr($r['sku'] ?? ''); ?>">
                        <a class="hc-thumb" href="<?php echo esc_url(add_query_arg('product_id', (int)$r['id'], $shop_url)); ?>">
                            <?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($r['title']); ?>"><?php endif; ?>
                        </a>
                        <div class="hc-meta">
                            <div class="hc-title"><?php echo esc_html($r['title']); ?></div>
                            <div class="hc-price"><?php echo number_format((int)$r['price']); ?>원</div>
                        </div>
                        <div class="hc-actions">
                            <a class="hc-btn" href="<?php echo esc_url(add_query_arg('product_id', (int)$r['id'], $shop_url)); ?>">자세히</a>
                            <button class="hc-btn hc-add">담기</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    // DETAIL
    private function renderDetail(int $id): string
    {
        $repo = new ProductRepo();
        $row = $repo->get($id);
        if (!$row || ($row['status'] ?? '') !== 'publish') return '<p>상품을 찾을 수 없습니다.</p>';

        $endpoint = esc_url(rest_url('hc/v1/cart/items'));

        // 옵션 구조 정규화
        $options = is_array($row['options'] ?? null) ? $row['options'] : [];
        if (!isset($options['variant']) && !isset($options['addons'])) {
            $options = ['variant' => [], 'addons' => ($row['options'] ?? [])];
        }
        $variants = is_array($options['variant'] ?? null) ? $options['variant'] : [];
        $addons   = is_array($options['addons'] ?? null) ? $options['addons'] : [];

        $cover = $row['image_id'] ? (wp_get_attachment_image_url((int)$row['image_id'], 'large') ?: '') : '';
        $gallery = array_map(fn($gid)=> wp_get_attachment_image_url((int)$gid, 'thumbnail') ?: '', (array)($row['gallery_ids'] ?? []));
        $gallery = array_values(array_filter($gallery));

        $back = get_permalink(get_page_by_path('shop'));

        $s = get_option('hc_shop_settings', []);
        $payments_enabled = (int)($s['payments_enabled'] ?? 1);
        $sku = (string)($row['sku'] ?? '');

        ob_start(); ?>
        <div class="hc-shop">
            <div class="hc-product-detail" id="hc-product-detail" data-endpoint="<?php echo $endpoint; ?>"
                 data-id="<?php echo (int)$row['id']; ?>" data-base="<?php echo (int)$row['price']; ?>">
                <p><a class="hc-btn" href="<?php echo esc_url($back); ?>">&larr; 돌아가기</a></p>
                <div class="hc-detail-wrap">
                    <div class="hc-detail-left">
                        <?php if ($cover): ?><div class="hc-cover"><img src="<?php echo esc_url($cover); ?>" alt=""></div><?php endif; ?>
                        <?php if ($gallery): ?>
                            <div class="hc-gallery">
                                <?php foreach ($gallery as $u): ?><img src="<?php echo esc_url($u); ?>" alt=""><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($row['description'])): ?>
                            <div class="hc-desc"><?php echo wp_kses_post($row['description']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="hc-detail-right">
                        <h2><?php echo esc_html($row['title']); ?></h2>
                        <div class="hc-price-box">
                            <span>가격</span>
                            <strong id="hc-price"><?php echo number_format((int)$row['price']); ?></strong><span> 원</span>
                        </div>

                        <?php if ($variants): ?>
                        <div class="hc-option-box">
                            <div class="hc-opt-title">변형 선택</div>
                            <div class="hc-opt-list" id="hc-variant">
                                <?php foreach ($variants as $idx => $v):
                                    $name = esc_html($v['name'] ?? '');
                                    $d = (int)($v['price_delta'] ?? 0);
                                    $stock = isset($v['stock']) ? (int)$v['stock'] : null;
                                    $disabled = ($stock !== null && $stock <= 0) ? 'disabled' : '';
                                ?>
                                  <label class="hc-opt-item">
                                    <input type="radio" name="hc_variant" value="<?php echo (int)$idx; ?>" <?php echo $disabled; ?>>
                                    <span class="name"><?php echo $name; ?></span>
                                    <?php if ($d !== 0): ?>
                                      <span class="delta"><?php echo ($d>0?'+':'').number_format($d); ?>원</span>
                                    <?php endif; ?>
                                    <?php if ($disabled): ?><span class="soldout">품절</span><?php endif; ?>
                                  </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($addons): ?>
                        <div class="hc-option-box">
                            <div class="hc-opt-title">추가 옵션</div>
                            <div class="hc-opt-list" id="hc-addons">
                                <?php foreach ($addons as $idx => $a):
                                    $name = esc_html($a['name'] ?? '');
                                    $d = (int)($a['price_delta'] ?? 0);
                                ?>
                                  <label class="hc-opt-item">
                                    <input type="checkbox" class="hc_addon" value="<?php echo (int)$idx; ?>">
                                    <span class="name"><?php echo $name; ?></span>
                                    <?php if ($d !== 0): ?>
                                      <span class="delta"><?php echo ($d>0?'+':'').number_format($d); ?>원</span>
                                    <?php endif; ?>
                                  </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="hc-buy">
                            <label>수량</label>
                            <input type="number" id="hc-qty" min="1" value="1">

                            <button id="hc-add-to-cart" data-product="<?php echo (int)$row['id']; ?>"
                                    data-sku="<?php echo esc_attr($sku); ?>">장바구니 담기</button>

                            <?php if ($payments_enabled): ?>
                                <button id="hc-buy-now" data-product="<?php echo (int)$row['id']; ?>"
                                        data-sku="<?php echo esc_attr($sku); ?>">구매하기</button>
                                <button id="hc-contact" style="display:none">문의하기</button>
                            <?php else: ?>
                                <button id="hc-buy-now" style="display:none">구매하기</button>
                                <button id="hc-contact" data-product="<?php echo (int)$row['id']; ?>"
                                        data-sku="<?php echo esc_attr($sku); ?>">문의하기</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
