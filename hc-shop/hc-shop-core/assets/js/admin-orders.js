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

  // 배송 정보 저장
  $(document).on('click', '#hc-ship-save', function(e){
    e.preventDefault();
    const id = parseInt($(this).data('id'),10)||0;
    const msg = $('#hc-ship-msg');
    msg.text('저장 중...');
    $.post(HCOrders.ajaxurl, {
      action: 'hc_order_save_shipping',
      _ajax_nonce: HCOrders.nonce,
      id,
      carrier: $('#hc-ship-carrier').val(),
      tracking: $('#hc-ship-tracking').val(),
      shipped_at: $('#hc-ship-date').val(),
      status: 'shipped'
    }, function(res){
      msg.text(res?.success ? '저장 완료' : (res?.data?.message || '실패'));
    }).fail(function(){ msg.text('요청 실패'); });
  });

  // 메모 저장
  $(document).on('click', '#hc-note-save', function(e){
    e.preventDefault();
    const id = parseInt($(this).data('id'),10)||0;
    const msg = $('#hc-note-msg');
    msg.text('저장 중...');
    $.post(HCOrders.ajaxurl, {
      action: 'hc_order_save_notes',
      _ajax_nonce: HCOrders.nonce,
      id,
      internal: $('#hc-note-internal').val(),
      customer: $('#hc-note-customer').val(),
    }, function(res){
      msg.text(res?.success ? '저장 완료' : (res?.data?.message || '실패'));
    }).fail(function(){ msg.text('요청 실패'); });
  });

  // 환불 처리
  $(document).on('click', '#hc-refund-btn', function(e){
    e.preventDefault();
    const id = parseInt($(this).data('id'),10)||0;
    const amt = parseInt($('#hc-refund-amount').val(),10)||0;
    const msg = $('#hc-refund-msg');
    msg.text('처리 중...');
    $.post(HCOrders.ajaxurl, {
      action: 'hc_order_refund',
      _ajax_nonce: HCOrders.nonce,
      id,
      amount: amt
    }, function(res){
      if(res?.success){
        msg.text('환불 완료');
        $('#hc-order-status').val(res.data?.status || 'refunded');
        $('#hc-order-status-msg').text('환불로 상태 변경됨');
      } else {
        msg.text(res?.data?.message || '실패');
      }
    }).fail(function(){ msg.text('요청 실패'); });
  });

  // 일괄 상태 변경
  $(document).on('submit', '#hc-order-bulk-form', function(e){
    e.preventDefault();
    const ids = $('.hc-bulk-item:checked').map(function(){ return parseInt($(this).val(),10)||0; }).get();
    const status = $('#hc-bulk-status').val();
    const msg = $('#hc-bulk-msg');
    msg.text('처리 중...');
    $.post(HCOrders.ajaxurl, {
      action: 'hc_order_bulk_status',
      _ajax_nonce: HCOrders.nonce,
      ids,
      status
    }, function(res){
      msg.text(res?.success ? `변경 완료 (${res.data?.updated||0}건)` : (res?.data?.message || '실패'));
      if(res?.success){ location.reload(); }
    }).fail(function(){ msg.text('요청 실패'); });
  });

  // 전체 선택
  $(document).on('change', '#hc-bulk-all', function(){
    $('.hc-bulk-item').prop('checked', $(this).is(':checked'));
  });
})(jQuery);
