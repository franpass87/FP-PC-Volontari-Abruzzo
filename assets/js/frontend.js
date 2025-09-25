(function(){
  function qs(s,ctx){return (ctx||document).querySelector(s);}
  function ce(t){return document.createElement(t);}
  function storageSafe(){
    var fallback = {
      get: function(){ return null; },
      set: function(){}
    };
    var storage;
    try {
      storage = window.localStorage;
      if(!storage){ return fallback; }
      var testKey = '__pcv_test__';
      storage.setItem(testKey, '1');
      storage.removeItem(testKey);
      return {
        get: function(key){
          try {
            return storage.getItem(key);
          } catch (err) {
            return null;
          }
        },
        set: function(key, value){
          try {
            storage.setItem(key, value);
          } catch (err) {
            // no-op on failure
          }
        }
      };
    } catch (err) {
      return fallback;
    }
  }
  function getLabel(key, fallback){
    if (typeof PCV_DATA !== 'undefined' && PCV_DATA && PCV_DATA.labels && typeof PCV_DATA.labels[key] === 'string'){ 
      var value = PCV_DATA.labels[key];
      if (value){
        return value;
      }
    }
    return fallback;
  }
  function fillProvince(sel){
    var selectProvinceText = getLabel('selectProvince', 'Seleziona provincia');
    sel.innerHTML = '<option value="">' + selectProvinceText + '</option>';
    for (var code in PCV_DATA.province){
      var opt = ce('option');
      opt.value = code;
      opt.textContent = PCV_DATA.province[code] + ' ('+code+')';
      sel.appendChild(opt);
    }
  }
  function fillComuni(sel, prov, preselect){
    var selectComuneText = getLabel('selectComune', 'Seleziona comune');
    sel.innerHTML = '<option value="">' + selectComuneText + '</option>';
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
    var storage = storageSafe();
    var storedProv = storage.get('pcv_provincia') || '';
    var storedComune = storage.get('pcv_comune') || '';
    if (selComune) {
      selComune.addEventListener('change', function(){
        var comuneValue = selComune.value || '';
        storage.set('pcv_comune', comuneValue);
        storedComune = comuneValue;
      });
    }

    if (selProv) {
      fillProvince(selProv);

      selProv.addEventListener('change', function(){
        var newProv = selProv.value || '';
        var previousProv = storedProv;
        var shouldResetComune = (newProv !== previousProv) || !newProv;

        fillComuni(selComune, newProv, shouldResetComune ? null : storedComune);

        storage.set('pcv_provincia', newProv);
        storedProv = newProv;

        if (shouldResetComune) {
          storedComune = '';
          storage.set('pcv_comune', '');
        }

        if (selComune) {
          if (shouldResetComune) {
            selComune.value = '';
          } else if (storedComune) {
            selComune.value = storedComune;
          }
          selComune.dispatchEvent(new Event('change'));
        }
      });

      // Se abbiamo già locale, pre-seleziona
      if(storedProv){
        selProv.value = storedProv;
        selProv.dispatchEvent(new Event('change'));
      }
    }
    if(selComune && storedComune && storedProv){
      selComune.value = storedComune;
    }

    // Modal per comune (memorizza localStorage)
    var modal = qs('#pcvComuneModal');
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
          storage.set('pcv_provincia', prov);
          storage.set('pcv_comune', comune);
          storedProv = prov;
          storedComune = comune;
          selProv.value = prov;
          selProv.dispatchEvent(new Event('change'));
          selComune.value = comune;
          if(modal) modal.classList.add('pcv-hidden');
        } else {
          alert(getLabel('modalAlert', 'Seleziona provincia e comune.'));
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
        var selectComuneText = getLabel('selectComune', 'Seleziona comune');
        popComune.innerHTML = '<option value="">' + selectComuneText + '</option>';
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
