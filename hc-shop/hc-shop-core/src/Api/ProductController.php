<?php
namespace HC\Shop\Api;

use WP_REST_Request; use WP_REST_Response;
use HC\Shop\Data\ProductRepo;

final class ProductController
{
    public function register_routes(): void
    {
        register_rest_route('hc/v1', '/products', [
            'methods'=>'GET','permission_callback'=>'__return_true','callback'=>[$this,'list'],
            'args'=>['per_page'=>['default'=>12],'page'=>['default'=>1],'q'=>['default'=>'']]
        ]);
        register_rest_route('hc/v1', '/products/(?P<id>\\d+)', [
            'methods'=>'GET','permission_callback'=>'__return_true','callback'=>[$this,'get']
        ]);
    }

    public function list(WP_REST_Request $req): WP_REST_Response
    {
        $pp=max(1,(int)$req->get_param('per_page')); $pg=max(1,(int)$req->get_param('page')); $q=(string)$req->get_param('q');
        $repo=new ProductRepo(); $res=$repo->list($pp,$pg,$q);
        $items=array_map([$this,'shape'],$res['items']);
        return new WP_REST_Response(['items'=>$items,'total'=>$res['total']]);
    }

    public function get(WP_REST_Request $req): WP_REST_Response
    {
        $repo=new ProductRepo(); $row=$repo->get((int)$req['id']);
        if(!$row || $row['status']!=='publish') return new WP_REST_Response(['error'=>['code'=>'not_found']],404);
        return new WP_REST_Response($this->shape($row));
    }

    private function mediaUrl($id){ $u = wp_get_attachment_url((int)$id); return $u ?: ''; }

    private function shape(array $r): array
    {
        return [
            'id'    => (int)$r['id'],
            'title' => (string)$r['title'],
            'sku'   => (string)$r['sku'],
            'price' => (int)$r['price'],
            'stock' => (int)$r['stock'],
            'status'=> (string)$r['status'],
            'image' => $this->mediaUrl($r['image_id'] ?? 0),
            'gallery' => array_values(array_filter(array_map([$this,'mediaUrl'], $r['gallery_ids'] ?? []))),
            'categories' => $r['categories'] ?? [],
            'colors'     => $r['colors'] ?? [],
            'options'    => $r['options'] ?? [],
            'description_html' => apply_filters('the_content', $r['description'] ?? ''),
        ];
    }
}
