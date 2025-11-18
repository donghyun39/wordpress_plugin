(function(){
  function onlyDigits(s){ return (s||'').replace(/[^\d]/g,''); }
  function fmtComma(s){
    s = onlyDigits(s);
    if(!s) return '';
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  function applyMoneyFormat(el){
    el.value = fmtComma(el.value);
  }
  function stripMoneyBeforeSubmit(form){
    form.querySelectorAll('.hc-money').forEach(function(el){
      el.value = onlyDigits(el.value);
    });
  }

  function togglePG(){
    var prov = document.getElementById('hc-pg-provider');
    if(!prov) return;
    var v = prov.value;
    var tossRows   = document.querySelectorAll('.pg-toss-row');
    var inicisRows = document.querySelectorAll('.pg-inicis-row');
    // show/hide + disable
    function setRows(rows, show){
      rows.forEach(function(tr){
        tr.style.display = show ? '' : 'none';
        tr.querySelectorAll('input,select,textarea,button').forEach(function(i){
          i.disabled = !show;
        });
      });
    }
    setRows(tossRows,   v === 'toss');
    setRows(inicisRows, v === 'inicis');
  }

  document.addEventListener('DOMContentLoaded', function(){
    // money fields
    document.querySelectorAll('.hc-money').forEach(function(el){
      applyMoneyFormat(el);
      el.addEventListener('input', function(){ applyMoneyFormat(el); });
      el.addEventListener('blur',  function(){ applyMoneyFormat(el); });
    });

    // PG toggle
    togglePG();
    var prov = document.getElementById('hc-pg-provider');
    if (prov) prov.addEventListener('change', togglePG);

    // submit: strip commas
    var form = document.getElementById('hc-settings-form');
    if(form){
      form.addEventListener('submit', function(){
        stripMoneyBeforeSubmit(form);
      });
    }
  });
})();
