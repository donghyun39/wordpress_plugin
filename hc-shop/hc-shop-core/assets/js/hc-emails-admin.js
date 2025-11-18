(function($){
  $(function(){
    // 탭 전환
    $('#hc-email-tabs .nav-tab').on('click', function(e){
      e.preventDefault();
      const id = $(this).attr('href');
      $('#hc-email-tabs .nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      $('.hc-tab-panel').hide();
      $(id).show();
      history.replaceState(null,'', id); // URL hash
    });
    // hash 복원
    if (location.hash && $('#hc-email-tabs a[href="'+location.hash+'"]').length) {
      $('#hc-email-tabs a[href="'+location.hash+'"]').trigger('click');
    }

    // 미디어: 로고 선택
    if (typeof wp !== 'undefined' && wp.media) {
      $('#hc-email-logo-select').on('click', function(e){
        e.preventDefault();
        const frame = wp.media({ title:'로고 선택', multiple:false });
        frame.on('select', function(){
          const att = frame.state().get('selection').first();
          $('#hc-email-logo-id').val(att.id);
          $('#hc-email-logo-preview').html('<img style="max-width:200px;height:auto" src="'+att.get('url')+'">');
          $('#hc-email-logo-remove').show();
        });
        frame.open();
      });
      $('#hc-email-logo-remove').on('click', function(){
        $('#hc-email-logo-id').val('0');
        $('#hc-email-logo-preview').empty();
        $(this).hide();
      });
    }

    // 미리보기
    $('#hc-email-preview-btn').on('click', function(){
      const orderId = parseInt($('#hc-email-order-id').val(),10)||0;
      const tpl = $('#hc-email-template').val();
      const msg = $('#hc-email-msg');
      if (!orderId) { alert('주문 ID를 입력하세요.'); return; }

      msg.text('렌더링 중…');
      $.post(HCEmails.ajax, { action:'hc_emails_preview', nonce:HCEmails.nonce, order_id:orderId, template:tpl }, function(res){
        if (res?.success) {
          const html = res.data.html || '';
          $('#hc-email-preview').show();
          const ifr = $('#hc-email-preview iframe')[0];
          const doc = ifr.contentDocument || ifr.contentWindow.document;
          doc.open(); doc.write(html); doc.close();
          msg.text('미리보기 생성');
        } else {
          msg.text(res?.data?.message || '실패');
        }
      });
    });

    // 테스트 발송
    $('#hc-email-test-btn').on('click', function(){
      const orderId = parseInt($('#hc-email-order-id').val(),10)||0;
      const tpl = $('#hc-email-template').val();
      const to  = $('#hc-email-test-to').val();
      const msg = $('#hc-email-msg');
      if (!orderId || !to) { alert('주문 ID와 이메일을 입력하세요.'); return; }

      msg.text('발송 중…');
      $.post(HCEmails.ajax, { action:'hc_emails_test', nonce:HCEmails.nonce, order_id:orderId, template:tpl, to:to }, function(res){
        msg.text(res?.success ? '발송 성공' : (res?.data?.message || '실패'));
      });
    });
  });
})(jQuery);
