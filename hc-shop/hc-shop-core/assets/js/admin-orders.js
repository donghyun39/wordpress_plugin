(function($){
  $(document).on('click', '#hc-order-status-save', function(e){
    e.preventDefault();
    const id = parseInt($('#hc-order-status').data('id'),10)||0;
    const status = $('#hc-order-status').val();
    const msg = $('#hc-order-status-msg');
    msg.text('저장 중...');
    $.post(HCOrders.ajaxurl, {
      action: 'hc_order_update_status',
      _ajax_nonce: HCOrders.nonce,
      id, status
    }, function(res){
      if(res?.success){ msg.text('변경 완료'); }
      else { msg.text(res?.data?.message || '실패'); }
    }).fail(function(){ msg.text('요청 실패'); });
  });
})(jQuery);
