(function(){
  function qs(s,ctx){return (ctx||document).querySelector(s);}
  function ce(t){return document.createElement(t);}
  function fillProvince(sel){
    sel.innerHTML = '<option value="">Seleziona provincia</option>';
    for (var code in PCV_DATA.province){
      var opt = ce('option');
      opt.value = code;
      opt.textContent = PCV_DATA.province[code] + ' ('+code+')';
      sel.appendChild(opt);
    }
  }
  function fillComuni(sel, prov, preselect){
    sel.innerHTML = '<option value="">Seleziona comune</option>';
    var list = PCV_DATA.comuni[prov] || [];
    list.forEach(function(c){
      var o = ce('option'); o.value = c; o.textContent = c;
      sel.appendChild(o);
    });
    if(preselect){
      sel.value = preselect;
    }
  }
  document.addEventListener('DOMContentLoaded', function(){
    var selProv = qs('#pcv_provincia');
    var selComune = qs('#pcv_comune');
    if (selProv) {
      fillProvince(selProv);

      selProv.addEventListener('change', function(){
        fillComuni(selComune, selProv.value, null);
        if (selComune) {
          selComune.dispatchEvent(new Event('change'));
        }
      });

      // Se abbiamo già locale, pre-seleziona
      if(localStorage.getItem('pcv_provincia')){
        selProv.value = localStorage.getItem('pcv_provincia');
        selProv.dispatchEvent(new Event('change'));
      }
    }
    if(selComune && localStorage.getItem('pcv_comune') && localStorage.getItem('pcv_provincia')){
      selComune.value = localStorage.getItem('pcv_comune');
    }

    // Modal per comune (memorizza localStorage)
    var modal = qs('#pcvComuneModal');
    var storedComune = localStorage.getItem('pcv_comune')||'';
    var storedProv   = localStorage.getItem('pcv_provincia')||'';
    if(!storedComune){
      if(modal) modal.classList.remove('pcv-hidden');
    }

    var confirmBtn = qs('#pcvComuneConfirm');
    if(confirmBtn){
      confirmBtn.addEventListener('click', function(){
        var inpComune = qs('#pcvComuneInput');
        var inpProv = qs('#pcvProvinciaInput');
        var comune = inpComune && inpComune.value ? inpComune.value.trim() : '';
        var prov = inpProv && inpProv.value ? inpProv.value : '';
        if(prov && comune){
          localStorage.setItem('pcv_provincia', prov);
          localStorage.setItem('pcv_comune', comune);
          selProv.value = prov;
          selProv.dispatchEvent(new Event('change'));
          selComune.value = comune;
          if(modal) modal.classList.add('pcv-hidden');
        } else {
          alert('Seleziona provincia e comune.');
        }
      });
    }
    var skipBtn = qs('#pcvComuneSkip');
    if(skipBtn){
      skipBtn.addEventListener('click', function(){ if(modal) modal.classList.add('pcv-hidden'); });
    }

    // Selezione guidata nel popup (provincia -> comuni)
    var popProv = qs('#pcvProvinciaInput');
    var popComune = qs('#pcvComuneInput');
    if(popProv && popComune){
      // riempi province
      for (var code in PCV_DATA.province){
        var o = ce('option'); o.value = code; o.textContent = PCV_DATA.province[code] + ' ('+code+')'; popProv.appendChild(o);
      }
      popProv.addEventListener('change', function(){
        var prov = popProv.value;
        popComune.innerHTML = '<option value="">Seleziona comune</option>';
        (PCV_DATA.comuni[prov]||[]).forEach(function(c){
          var o = ce('option'); o.value = c; o.textContent = c; popComune.appendChild(o);
        });
      });
      if(storedProv){
        popProv.value = storedProv; popProv.dispatchEvent(new Event('change'));
        if(storedComune){ popComune.value = storedComune; }
      }
    }

    // reCAPTCHA v2
    if(PCV_DATA.recaptcha_site){
      var s = document.createElement('script');
      s.src = 'https://www.google.com/recaptcha/api.js';
      s.async = true; s.defer = true;
      document.head.appendChild(s);
    }
  });
})();
