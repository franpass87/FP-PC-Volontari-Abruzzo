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

    if (provSelect && comuneSelect) {
      var allComuni = toArray(data.allComuni);
      var fallbackPlaceholder = (data.fallbacks && data.fallbacks.placeholderComune) ? data.fallbacks.placeholderComune : 'Tutti i comuni';
      var placeholderText = (data.labels && data.labels.placeholderComune) ? data.labels.placeholderComune : fallbackPlaceholder;
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
    }

    // =====================================================
    // GESTIONE MODIFICA ED ELIMINAZIONE VOLONTARI
    // =====================================================

    if (typeof window.PCV_AJAX_DATA === 'undefined') {
      return;
    }

    var ajaxData = window.PCV_AJAX_DATA || {};
    var currentEditId = null;

    // Crea modal HTML se non esiste
    function ensureModals() {
      if (!document.getElementById('pcv-edit-modal')) {
        var editModal = document.createElement('div');
        editModal.id = 'pcv-edit-modal';
        editModal.className = 'pcv-modal';
        editModal.innerHTML = `
          <div class="pcv-modal-content">
            <span class="pcv-modal-close">&times;</span>
            <h2>Modifica Volontario</h2>
            <form id="pcv-edit-form">
              <div class="pcv-form-row">
                <label>Nome</label>
                <input type="text" name="nome" required>
              </div>
              <div class="pcv-form-row">
                <label>Cognome</label>
                <input type="text" name="cognome" required>
              </div>
              <div class="pcv-form-row">
                <label>Email</label>
                <input type="email" name="email" required>
              </div>
              <div class="pcv-form-row">
                <label>Telefono</label>
                <input type="text" name="telefono" required>
              </div>
              <div class="pcv-form-row">
                <label>Provincia</label>
                <select name="provincia" id="pcv-modal-provincia" required></select>
              </div>
              <div class="pcv-form-row">
                <label>Comune</label>
                <select name="comune" id="pcv-modal-comune" required></select>
              </div>
              <div class="pcv-form-row">
                <label>Categoria</label>
                <input type="text" name="categoria">
              </div>
              <div class="pcv-form-row">
                <label><input type="checkbox" name="privacy" value="1"> Privacy</label>
              </div>
              <div class="pcv-form-row">
                <label><input type="checkbox" name="partecipa" value="1"> Partecipa</label>
              </div>
              <div class="pcv-form-row">
                <label><input type="checkbox" name="dorme" value="1"> Pernotta</label>
              </div>
              <div class="pcv-form-row">
                <label><input type="checkbox" name="mangia" value="1"> Pasti</label>
              </div>
              <div class="pcv-form-actions">
                <button type="submit" class="button button-primary">Salva</button>
                <button type="button" class="button pcv-modal-cancel">Annulla</button>
              </div>
            </form>
          </div>
        `;
        document.body.appendChild(editModal);
      }

      if (!document.getElementById('pcv-bulk-modal')) {
        var bulkModal = document.createElement('div');
        bulkModal.id = 'pcv-bulk-modal';
        bulkModal.className = 'pcv-modal';
        bulkModal.innerHTML = `
          <div class="pcv-modal-content">
            <span class="pcv-modal-close">&times;</span>
            <h2>Modifica Multipla</h2>
            <form id="pcv-bulk-form">
              <div class="pcv-form-row">
                <label>Categoria</label>
                <input type="text" name="categoria" placeholder="Lascia vuoto per non modificare">
              </div>
              <div class="pcv-form-row">
                <label>Privacy</label>
                <select name="privacy">
                  <option value="">Non modificare</option>
                  <option value="1">Sì</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="pcv-form-row">
                <label>Partecipa</label>
                <select name="partecipa">
                  <option value="">Non modificare</option>
                  <option value="1">Sì</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="pcv-form-row">
                <label>Pernotta</label>
                <select name="dorme">
                  <option value="">Non modificare</option>
                  <option value="1">Sì</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="pcv-form-row">
                <label>Pasti</label>
                <select name="mangia">
                  <option value="">Non modificare</option>
                  <option value="1">Sì</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="pcv-form-actions">
                <button type="submit" class="button button-primary">Aggiorna</button>
                <button type="button" class="button pcv-modal-cancel">Annulla</button>
              </div>
            </form>
          </div>
        `;
        document.body.appendChild(bulkModal);
      }
    }

    ensureModals();

    // Funzione per popolare select province nel modal
    function populateModalProvinceSelect(selectEl, selectedValue) {
      selectEl.innerHTML = '<option value="">Seleziona provincia</option>';
      if (ajaxData.province) {
        Object.keys(ajaxData.province).forEach(function(code) {
          var option = document.createElement('option');
          option.value = code;
          option.textContent = ajaxData.province[code] + ' (' + code + ')';
          if (code === selectedValue) {
            option.selected = true;
          }
          selectEl.appendChild(option);
        });
      }
    }

    // Funzione per popolare select comuni nel modal
    function populateModalComuneSelect(provincia, selectEl, selectedValue) {
      selectEl.innerHTML = '<option value="">Seleziona comune</option>';
      var comuniList = [];
      if (provincia && ajaxData.comuni && ajaxData.comuni[provincia]) {
        comuniList = ajaxData.comuni[provincia];
      } else if (ajaxData.allComuni) {
        comuniList = ajaxData.allComuni;
      }
      comuniList.forEach(function(comune) {
        var option = document.createElement('option');
        option.value = comune;
        option.textContent = comune;
        if (comune === selectedValue) {
          option.selected = true;
        }
        selectEl.appendChild(option);
      });
    }

    // Apri modal modifica singola
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('pcv-edit-volunteer')) {
        e.preventDefault();
        var id = parseInt(e.target.getAttribute('data-id'));
        if (!id) return;
        
        currentEditId = id;
        
        // Carica dati volontario
        jQuery.post(ajaxData.ajax_url, {
          action: 'pcv_get_volunteer',
          nonce: ajaxData.nonce,
          id: id
        }, function(response) {
          if (response.success && response.data.volunteer) {
            var v = response.data.volunteer;
            var form = document.getElementById('pcv-edit-form');
            form.querySelector('[name="nome"]').value = v.nome || '';
            form.querySelector('[name="cognome"]').value = v.cognome || '';
            form.querySelector('[name="email"]').value = v.email || '';
            form.querySelector('[name="telefono"]').value = v.telefono || '';
            form.querySelector('[name="categoria"]').value = v.categoria || '';
            form.querySelector('[name="privacy"]').checked = v.privacy == 1;
            form.querySelector('[name="partecipa"]').checked = v.partecipa == 1;
            form.querySelector('[name="dorme"]').checked = v.dorme == 1;
            form.querySelector('[name="mangia"]').checked = v.mangia == 1;
            
            var provSelect = document.getElementById('pcv-modal-provincia');
            var comuneSelect = document.getElementById('pcv-modal-comune');
            
            populateModalProvinceSelect(provSelect, v.provincia);
            populateModalComuneSelect(v.provincia, comuneSelect, v.comune);
            
            provSelect.addEventListener('change', function() {
              populateModalComuneSelect(provSelect.value, comuneSelect, '');
            });
            
            document.getElementById('pcv-edit-modal').style.display = 'block';
          } else {
            alert('Errore nel caricamento dei dati');
          }
        });
      }
    });

    // Elimina singolo
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('pcv-delete-volunteer')) {
        e.preventDefault();
        var id = parseInt(e.target.getAttribute('data-id'));
        if (!id || !confirm('Sei sicuro di voler eliminare questo volontario?')) return;
        
        jQuery.post(ajaxData.ajax_url, {
          action: 'pcv_delete_volunteer',
          nonce: ajaxData.nonce,
          id: id
        }, function(response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
          }
        });
      }
    });

    // Chiudi modal
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('pcv-modal-close') || e.target.classList.contains('pcv-modal-cancel')) {
        document.getElementById('pcv-edit-modal').style.display = 'none';
        document.getElementById('pcv-bulk-modal').style.display = 'none';
        currentEditId = null;
      }
    });

    // Salva modifica singola
    document.getElementById('pcv-edit-form').addEventListener('submit', function(e) {
      e.preventDefault();
      if (!currentEditId) return;
      
      var formData = new FormData(e.target);
      var data = {
        action: 'pcv_update_volunteer',
        nonce: ajaxData.nonce,
        id: currentEditId,
        nome: formData.get('nome'),
        cognome: formData.get('cognome'),
        email: formData.get('email'),
        telefono: formData.get('telefono'),
        provincia: formData.get('provincia'),
        comune: formData.get('comune'),
        categoria: formData.get('categoria'),
        privacy: formData.get('privacy') ? 1 : 0,
        partecipa: formData.get('partecipa') ? 1 : 0,
        dorme: formData.get('dorme') ? 1 : 0,
        mangia: formData.get('mangia') ? 1 : 0
      };
      
      jQuery.post(ajaxData.ajax_url, data, function(response) {
        if (response.success) {
          alert(response.data.message);
          location.reload();
        } else {
          alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
        }
      });
    });

    // Gestione bulk edit
    var bulkActionSelect = document.querySelector('select[name="action"]');
    if (bulkActionSelect) {
      var form = bulkActionSelect.closest('form');
      form.addEventListener('submit', function(e) {
        var action = bulkActionSelect.value;
        if (action === 'bulk_edit') {
          e.preventDefault();
          var checkboxes = document.querySelectorAll('input[name="id[]"]:checked');
          if (checkboxes.length === 0) {
            alert('Seleziona almeno un volontario');
            return;
          }
          document.getElementById('pcv-bulk-modal').style.display = 'block';
        }
      });
    }

    // Salva modifica bulk
    document.getElementById('pcv-bulk-form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      var checkboxes = document.querySelectorAll('input[name="id[]"]:checked');
      var ids = Array.from(checkboxes).map(function(cb) { return parseInt(cb.value); });
      
      if (ids.length === 0) {
        alert('Seleziona almeno un volontario');
        return;
      }
      
      var formData = new FormData(e.target);
      var data = {
        action: 'pcv_bulk_update',
        nonce: ajaxData.nonce,
        ids: ids
      };
      
      if (formData.get('categoria')) data.categoria = formData.get('categoria');
      if (formData.get('privacy') !== '') data.privacy = parseInt(formData.get('privacy'));
      if (formData.get('partecipa') !== '') data.partecipa = parseInt(formData.get('partecipa'));
      if (formData.get('dorme') !== '') data.dorme = parseInt(formData.get('dorme'));
      if (formData.get('mangia') !== '') data.mangia = parseInt(formData.get('mangia'));
      
      jQuery.post(ajaxData.ajax_url, data, function(response) {
        if (response.success) {
          alert(response.data.message);
          location.reload();
        } else {
          alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
        }
      });
    });
  });
})();
