(function(){
  function toArray(value){
    if (Array.isArray(value)) {
      return value.slice();
    }
    return [];
  }

  document.addEventListener('DOMContentLoaded', function(){
    if (typeof window.PCV_ADMIN_DATA === 'undefined') {
      return;
    }

    var data = window.PCV_ADMIN_DATA || {};
    var provSelect = document.getElementById('pcv-admin-provincia');
    var comuneSelect = document.getElementById('pcv-admin-comune');

    if (!provSelect || !comuneSelect) {
      return;
    }

    var allComuni = toArray(data.allComuni);
    var placeholderText = (data.labels && data.labels.placeholderComune) ? data.labels.placeholderComune : 'Tutti i comuni';
    var selectedComune = data.selectedComune || comuneSelect.value || '';

    function buildOptions(list, selectedValue){
      comuneSelect.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = placeholderText;
      comuneSelect.appendChild(placeholder);

      list.forEach(function(name){
        if (typeof name !== 'string' || name === '') {
          return;
        }
        var option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        if (selectedValue && name === selectedValue) {
          option.selected = true;
        }
        comuneSelect.appendChild(option);
      });

      if (selectedValue && list.indexOf(selectedValue) !== -1) {
        comuneSelect.value = selectedValue;
      } else {
        comuneSelect.value = '';
      }
    }

    function listForProvince(prov){
      if (prov && data.comuni && data.comuni[prov]) {
        return toArray(data.comuni[prov]);
      }
      return toArray(allComuni);
    }

    function refreshComuni(prov, preferredComune){
      var list = listForProvince(prov);
      var keepComune = preferredComune || comuneSelect.value || '';
      if (keepComune && list.indexOf(keepComune) === -1) {
        keepComune = '';
      }
      if (!keepComune && selectedComune && list.indexOf(selectedComune) !== -1) {
        keepComune = selectedComune;
      }
      buildOptions(list, keepComune);
      selectedComune = comuneSelect.value || '';
    }

    var initialProvince = provSelect.value || data.selectedProvincia || '';
    if (initialProvince && provSelect.value !== initialProvince) {
      provSelect.value = initialProvince;
    }

    refreshComuni(initialProvince, selectedComune);

    provSelect.addEventListener('change', function(){
      var prov = provSelect.value || '';
      selectedComune = '';
      refreshComuni(prov, '');
    });

    comuneSelect.addEventListener('change', function(){
      selectedComune = comuneSelect.value || '';
    });
  });
})();
