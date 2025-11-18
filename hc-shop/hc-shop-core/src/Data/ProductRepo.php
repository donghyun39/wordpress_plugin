<?php
namespace HC\Shop\Data;

class ProductRepo
{
    private string $table;
    public function __construct(){ global $wpdb; $this->table = $wpdb->prefix.'hc_products'; }

    // ── helpers ─────────────────────────────────────────────
    private function enc($v){ return $v === null ? null : wp_json_encode($v, JSON_UNESCAPED_UNICODE); }
    private function dec($s){ if (!$s) return null; $j = json_decode($s, true); return is_array($j) ? $j : null; }

    // CRUD
    public function create(array $p): int {
        global $wpdb;
        $wpdb->insert($this->table, [
            'title'       => sanitize_text_field($p['title'] ?? ''),
            'sku'         => sanitize_text_field($p['sku'] ?? ''),
            'price'       => (int)($p['price'] ?? 0),
            'stock'       => (int)($p['stock'] ?? 0),
            'image_id'    => (int)($p['image_id'] ?? 0),
            'description' => wp_kses_post($p['description'] ?? ''),
            'gallery_ids' => $this->enc(array_map('intval', $p['gallery_ids'] ?? [])),
            'categories'  => $this->enc(array_values(array_filter(array_map('sanitize_text_field', $p['categories'] ?? [])))),
            'colors'      => $this->enc(array_values(array_filter(array_map('sanitize_text_field', $p['colors'] ?? [])))),
            'options'     => $this->enc($this->sanitizeOptions($p['options'] ?? [])),
            'status'      => in_array($p['status'] ?? 'publish', ['publish','draft'], true) ? $p['status'] : 'publish',
        ]);
        return (int)$wpdb->insert_id;
    }

    public function update(int $id, array $p): bool {
        global $wpdb;
        return (bool)$wpdb->update($this->table, [
            'title'       => sanitize_text_field($p['title'] ?? ''),
            'sku'         => sanitize_text_field($p['sku'] ?? ''),
            'price'       => (int)($p['price'] ?? 0),
            'stock'       => (int)($p['stock'] ?? 0),
            'image_id'    => (int)($p['image_id'] ?? 0),
            'description' => wp_kses_post($p['description'] ?? ''),
            'gallery_ids' => $this->enc(array_map('intval', $p['gallery_ids'] ?? [])),
            'categories'  => $this->enc(array_values(array_filter(array_map('sanitize_text_field', $p['categories'] ?? [])))),
            'colors'      => $this->enc(array_values(array_filter(array_map('sanitize_text_field', $p['colors'] ?? [])))),
            'options'     => $this->enc($this->sanitizeOptions($p['options'] ?? [])),
            'status'      => in_array($p['status'] ?? 'publish', ['publish','draft'], true) ? $p['status'] : 'publish',
        ], ['id'=>$id]);
    }

    public function delete(int $id): bool { global $wpdb; return (bool)$wpdb->delete($this->table, ['id'=>$id]); }

    public function get(int $id): ?array {
        global $wpdb; $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id), ARRAY_A);
        return $row ? $this->inflate($row) : null;
    }
public function getBySku(string $sku): ?array
{
    global $wpdb;
    $t = $wpdb->prefix . 'hc_products';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE sku=%s", $sku), ARRAY_A);
    if (!$row) return null;
    foreach (['gallery_ids','categories','colors','options'] as $k) {
        if (isset($row[$k])) {
            $j = json_decode($row[$k], true);
            if (is_array($j)) $row[$k] = $j;
        }
    }
    return $row;
}

    public function list(int $per_page=20, int $page=1, string $q=''): array {
        global $wpdb;
        $offset = max(0, ($page-1)*$per_page);
        $where = "WHERE 1=1"; $params = [];
        if ($q !== '') { $where.=" AND (title LIKE %s OR sku LIKE %s)"; $like='%'.$wpdb->esc_like($q).'%'; $params[]=$like; $params[]=$like; }
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page,$offset])
        ), ARRAY_A);
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} {$where}", ...$params));
        return ['items'=>array_map([$this,'inflate'],$items), 'total'=>$total];
    }

    // ── internals ───────────────────────────────────────────
    private function inflate(array $r): array {
        $r['gallery_ids'] = $this->dec($r['gallery_ids']) ?: [];
        $r['categories']  = $this->dec($r['categories']) ?: [];
        $r['colors']      = $this->dec($r['colors']) ?: [];
        $r['options']     = $this->dec($r['options']) ?: [];
        return $r;
    }

    private function sanitizeOptions(array $opts): array {
        $out = [];
        foreach ($opts as $o) {
            $out[] = [
                'name'        => sanitize_text_field($o['name'] ?? ''),
                'price_delta' => (int)($o['price_delta'] ?? 0),
                'sku_suffix'  => sanitize_text_field($o['sku_suffix'] ?? ''),
                'stock_delta' => (int)($o['stock_delta'] ?? 0),
            ];
        }
        return $out;
    }
}
