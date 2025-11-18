<?php
namespace HC\Shop\Shortcodes;

use HC\Shop\Data\OrderRepo;

final class OrderComplete
{
    public function register(): void
    {
        add_shortcode('hc_order_complete', [$this,'render']);
    }

    public function render(): string
    {
        if (!function_exists('hc_shop_is_page') || !hc_shop_is_page()) return '';
        $id = (int)($_GET['order'] ?? 0);
        if ($id <= 0) return '<p>주문 번호가 없습니다.</p>';

        $repo = new OrderRepo();
        $o = $repo->get($id);
        if (!$o) return '<p>주문을 찾을 수 없습니다.</p>';

        // ========= Toss 성공 콜백 처리 (paymentKey, orderId, amount)
        $provider   = sanitize_text_field($_GET['provider'] ?? '');
        $paymentKey = sanitize_text_field($_GET['paymentKey'] ?? '');
        $oid_from_q = sanitize_text_field($_GET['orderId'] ?? ''); // Toss에 넘긴 orderId(문자열)
        $amount_q   = (int)($_GET['amount'] ?? 0);

        if ($provider === 'toss' && $paymentKey && $oid_from_q && ($o['status'] ?? '') === 'pending') {
            $expectedOrderId = (string)($o['order_number'] ?? $id);   // 우리가 Toss에 준 값과 맞추기
            $grand           = (int)($o['totals']['grand'] ?? 0);

            if ($expectedOrderId === $oid_from_q && $grand === $amount_q) {
                $s = get_option('hc_shop_settings', []);
                $secret = (string)($s['pg_toss_secret_key'] ?? '');
                if ($secret) {
                    $resp = wp_remote_post('https://api.tosspayments.com/v1/payments/confirm', [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode($secret . ':'),
                            'Content-Type'  => 'application/json',
                        ],
                        'body'    => wp_json_encode([
                            'paymentKey' => $paymentKey,
                            'orderId'    => $expectedOrderId,
                            'amount'     => $grand,
                        ]),
                        'timeout' => 30,
                    ]);

                    if (!is_wp_error($resp) && (int)wp_remote_retrieve_response_code($resp) === 200) {
                        $body = json_decode(wp_remote_retrieve_body($resp), true);
                        // 실제 저장 로직에 맞게 업데이트
                        if (method_exists($repo, 'markPaid')) {
                            $repo->markPaid($id, [
                                'provider' => 'toss',
                                'txn_id'   => $paymentKey,
                                'raw'      => $body,
                            ]);
                            $o = $repo->get($id); // 갱신
                        } else {
                            // 임시 화면 갱신(저장 미구현 시)
                            $o['status'] = 'paid';
                        }
                    } else {
                        $err = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                        echo '<div class="notice notice-error"><p>토스 결제 승인 실패: '. esc_html($err) .'</p></div>';
                    }
                }
            }
        }
        // ========= /토스 승인 처리

        $fakeEndpoint = esc_url(rest_url('hc/v1/payments/fake/complete'));
        $payments_on  = (int)(get_option('hc_shop_settings', [])['payments_enabled'] ?? 0);
        $pg_provider  = (string)(get_option('hc_shop_settings', [])['pg_provider'] ?? 'none');

        ob_start(); ?>
        <div class="hc-shop">
          <h2>주문완료</h2>
          <p><strong>주문번호:</strong> <?php echo esc_html($o['order_number'] ?? ('#'.$id)); ?></p>
          <p><strong>상태:</strong> <span id="hc-status"><?php echo esc_html($o['status']); ?></span></p>

          <h3>주문 내역</h3>
          <table class="widefat striped">
            <thead><tr><th>상품</th><th>수량</th><th>단가</th><th>금액</th></tr></thead>
            <tbody>
            <?php foreach (($o['items'] ?? []) as $it): ?>
              <tr>
                <td><?php echo esc_html($it['title']); ?></td>
                <td><?php echo (int)$it['qty']; ?></td>
                <td><?php echo number_format((int)$it['unit_price']); ?>원</td>
                <td><?php echo number_format((int)$it['line_total']); ?>원</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <p style="margin-top:12px">
            <strong>합계:</strong>
            <?php echo number_format((int)($o['totals']['grand'] ?? 0)); ?>원
            <small style="color:#666">(상품 <?php echo number_format((int)($o['totals']['subtotal'] ?? 0)); ?> + 배송 <?php echo number_format((int)($o['totals']['shipping'] ?? 0)); ?>)</small>
          </p>

          <?php if (($o['status'] ?? '') !== 'paid' && !($payments_on && $pg_provider === 'toss')): ?>
            <hr>
            <p><em>테스트 전용</em> — 가짜 결제 완료:</p>
            <button class="hc-btn hc-solid" id="hc-fakepay"
                    data-ep="<?php echo $fakeEndpoint; ?>" data-id="<?php echo (int)$id; ?>">결제 완료 처리</button>
            <span id="hc-fakepay-msg" style="margin-left:8px;color:#444"></span>
          <?php endif; ?>
        </div>
        <script>
        (function(){
          const btn=document.getElementById('hc-fakepay');
          if(!btn) return;
          const ep=btn.getAttribute('data-ep'); const id=parseInt(btn.getAttribute('data-id'),10)||0;
          const msg=document.getElementById('hc-fakepay-msg');
          const st=document.getElementById('hc-status');
          btn.addEventListener('click', async ()=>{
            btn.disabled=true; msg.textContent='처리 중...';
            try{
              const res=await fetch(ep,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:id})});
              const data=await res.json();
              if(res.ok){ st.textContent='paid'; msg.textContent='완료되었습니다.'; btn.remove(); }
              else{ msg.textContent=data?.error?.message||'실패'; btn.disabled=false; }
            }catch(e){ msg.textContent='네트워크 오류'; btn.disabled=false; }
          });
        })();
        </script>
        <?php
        return (string)ob_get_clean();
    }
}
