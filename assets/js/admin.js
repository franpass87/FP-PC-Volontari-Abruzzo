(function(){
  console.log('=== ADMIN.JS CARICATO ===');
  
  function toArray(value){
    if (Array.isArray(value)) {
      return value.slice();
    }
    return [];
  }

  function initAdmin() {
    console.log('=== INIT ADMIN TRIGGERED ===');
    console.log('DOM ready state:', document.readyState);
    
    // =====================================================
    // GESTIONE FILTRI PROVINCIA/COMUNE
    // =====================================================
    var data = window.PCV_ADMIN_DATA || {};
    var provSelect = document.getElementById('pcv-admin-provincia');
    var comuneSelect = document.getElementById('pcv-admin-comune');
    
    // Verifica se il form esiste
    var testForm = document.getElementById('pcv-filter-form');
    console.log('Form test during init:', testForm);
    
    // Debug: verifica se i dati sono caricati
    console.log('PCV_ADMIN_DATA:', data);
    console.log('PCV_AJAX_DATA:', window.PCV_AJAX_DATA);
    console.log('Provincia select:', provSelect);
    console.log('Comune select:', comuneSelect);
    
    // Verifica se i dati sono presenti
    if (!data || Object.keys(data).length === 0) {
      console.error('PCV_ADMIN_DATA is empty or not loaded');
    }
    if (!window.PCV_AJAX_DATA) {
      console.error('PCV_AJAX_DATA is not loaded');
    }

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

      function loadComuniAjax(prov, preferredComune) {
        if (!prov) {
          refreshComuni('', preferredComune);
          return;
        }
        
        jQuery.post(window.PCV_AJAX_DATA.ajax_url, {
          action: 'pcv_get_comuni',
          nonce: window.PCV_AJAX_DATA.nonce,
          provincia: prov
        }, function(response) {
          if (response.success && response.data.comuni) {
            var list = response.data.comuni;
            var keepComune = preferredComune || '';
            if (keepComune && list.indexOf(keepComune) === -1) {
              keepComune = '';
            }
            buildOptions(list, keepComune);
            selectedComune = comuneSelect.value || '';
          } else {
            // Fallback ai dati locali
            refreshComuni(prov, preferredComune);
          }
        }).fail(function() {
          // Fallback ai dati locali in caso di errore
          refreshComuni(prov, preferredComune);
        });
      }

      var initialProvince = provSelect.value || data.selectedProvincia || '';
      if (initialProvince && provSelect.value !== initialProvince) {
        provSelect.value = initialProvince;
      }

      // Carica comuni iniziali
      if (typeof window.PCV_AJAX_DATA !== 'undefined' && window.PCV_AJAX_DATA.ajax_url && initialProvince) {
        loadComuniAjax(initialProvince, selectedComune);
      } else {
        refreshComuni(initialProvince, selectedComune);
      }

      provSelect.addEventListener('change', function(){
        var prov = provSelect.value || '';
        selectedComune = '';
        
        // Se abbiamo dati AJAX disponibili, carica i comuni dinamicamente
        if (typeof window.PCV_AJAX_DATA !== 'undefined' && window.PCV_AJAX_DATA.ajax_url) {
          loadComuniAjax(prov, '');
        } else {
          refreshComuni(prov, '');
        }
        
        // Auto-submit del form quando cambia provincia
        setTimeout(function() {
          var form = document.getElementById('pcv-filter-form');
          if (!form) {
            // Fallback: cerca il form che contiene il select provincia
            form = provSelect.closest('form');
          }
          if (!form) {
            // Fallback: cerca qualsiasi form GET
            form = document.querySelector('form[method="get"]');
          }
          if (form) {
            var submitBtn = form.querySelector('input[type="submit"]');
            if (submitBtn) {
              submitBtn.value = 'Filtra...';
              submitBtn.disabled = true;
            }
            console.log('Submitting form after provincia change');
            form.submit();
          } else {
            console.error('Form not found - provSelect:', provSelect, 'closest form:', provSelect ? provSelect.closest('form') : 'N/A');
          }
        }, 100);
      });

      comuneSelect.addEventListener('change', function(){
        selectedComune = comuneSelect.value || '';
        
        // Auto-submit del form quando cambia comune
        setTimeout(function() {
          var form = document.getElementById('pcv-filter-form');
          if (!form) {
            // Fallback: cerca il form che contiene il select comune
            form = comuneSelect.closest('form');
          }
          if (!form) {
            // Fallback: cerca qualsiasi form GET
            form = document.querySelector('form[method="get"]');
          }
          if (form) {
            var submitBtn = form.querySelector('input[type="submit"]');
            if (submitBtn) {
              submitBtn.value = 'Filtra...';
              submitBtn.disabled = true;
            }
            console.log('Submitting form after comune change');
            form.submit();
          } else {
            console.error('Form not found - comuneSelect:', comuneSelect, 'closest form:', comuneSelect ? comuneSelect.closest('form') : 'N/A');
          }
        }, 100);
      });

      // Auto-submit quando cambia categoria
      var categoriaSelect = document.getElementById('pcv-admin-categoria');
      if (categoriaSelect) {
        console.log('Categoria select found:', categoriaSelect);
        categoriaSelect.addEventListener('change', function(){
          setTimeout(function() {
            var form = document.getElementById('pcv-filter-form');
            if (!form) {
              // Fallback: cerca il form che contiene il select categoria
              form = categoriaSelect.closest('form');
            }
            if (!form) {
              // Fallback: cerca qualsiasi form GET
              form = document.querySelector('form[method="get"]');
            }
            if (form) {
              var submitBtn = form.querySelector('input[type="submit"]');
              if (submitBtn) {
                submitBtn.value = 'Filtra...';
                submitBtn.disabled = true;
              }
              console.log('Submitting form after categoria change');
              form.submit();
            } else {
              console.error('Form not found - categoriaSelect:', categoriaSelect, 'closest form:', categoriaSelect ? categoriaSelect.closest('form') : 'N/A');
            }
          }, 100);
        });
      } else {
        console.log('Categoria select not found');
      }

    }
    
    // Gestione ricerca con debounce (indipendente dai filtri provincia/comune)
    var filterForm = document.getElementById('pcv-filter-form');
    console.log('Filter form found:', filterForm);
    
    // Se il form non viene trovato, proviamo a cercarlo in altri modi
    if (!filterForm) {
      console.log('Form pcv-filter-form not found, searching alternatives...');
      var allForms = document.querySelectorAll('form');
      console.log('Total forms found:', allForms.length);
      allForms.forEach(function(form, index) {
        console.log('Form ' + index + ':', form.id, form.method, form.action);
      });
      
      filterForm = document.querySelector('form[method="get"]');
      console.log('Alternative form found:', filterForm);
      
      if (!filterForm) {
        filterForm = document.querySelector('form');
        console.log('Any form found:', filterForm);
      }
    }
    
    if (filterForm) {
      var searchInput = filterForm.querySelector('input[name="s"]');
      console.log('Search input found:', searchInput);
      if (searchInput) {
        var searchTimeout;
        searchInput.addEventListener('input', function() {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(function() {
            // Aggiungi indicatore di caricamento
            var submitBtn = filterForm.querySelector('input[type="submit"]');
            if (submitBtn) {
              submitBtn.value = 'Ricerca...';
              submitBtn.disabled = true;
            }
            
            console.log('Submitting form after search input');
            // Auto-submit del form dopo 500ms di inattività
            filterForm.submit();
          }, 500);
        });
        
        // Aggiungi anche un listener per il tasto Enter
        searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            clearTimeout(searchTimeout);
            console.log('Submitting form after Enter key');
            filterForm.submit();
          }
        });
      } else {
        console.log('Search input not found');
      }
      
      // Gestione submit manuale del form di ricerca
      filterForm.addEventListener('submit', function(e) {
        console.log('Form submit event triggered');
        // Aggiungi indicatore di caricamento
        var submitBtn = filterForm.querySelector('input[type="submit"]');
        if (submitBtn) {
          submitBtn.value = 'Filtra...';
          submitBtn.disabled = true;
        }
        // Il form verrà comunque sottoposto normalmente
      });
    }

    // =====================================================
    // GESTIONE MODIFICA ED ELIMINAZIONE VOLONTARI
    // =====================================================
    if (typeof window.PCV_AJAX_DATA === 'undefined') {
      console.error('PCV_AJAX_DATA non è definito');
      return;
    }
    
    if (typeof jQuery === 'undefined') {
      console.error('jQuery non è disponibile');
      return;
    }
    
    var ajaxData = window.PCV_AJAX_DATA || {};
    var currentEditId = null;

    // Crea modal HTML se non esiste
    function ensureModals() {
      if (!document.getElementById('pcv-edit-modal')) {
        var editModal = document.createElement('div');
        editModal.id = 'pcv-edit-modal';
        editModal.className = 'pcv-admin-modal';
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
                <select name="categoria" id="pcv-modal-categoria"></select>
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
        bulkModal.className = 'pcv-admin-modal';
        bulkModal.innerHTML = `
          <div class="pcv-modal-content">
            <span class="pcv-modal-close">&times;</span>
            <h2>Modifica Multipla</h2>
            <form id="pcv-bulk-form">
              <div class="pcv-form-row">
                <label>Categoria</label>
                <select name="categoria" id="pcv-bulk-categoria">
                  <option value="">Non modificare</option>
                </select>
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
    console.log('Modal creati, PCV_AJAX_DATA disponibile');
    console.log('ajaxData:', ajaxData);

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

    // Funzione per popolare select categorie
    function populateCategorieSelect(selectEl, selectedValue, includeEmpty) {
      selectEl.innerHTML = '';
      
      if (includeEmpty) {
        var emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Seleziona categoria';
        selectEl.appendChild(emptyOption);
      }
      
      var categorie = ajaxData.categories || [];
      categorie.forEach(function(cat) {
        var option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        if (cat === selectedValue) {
          option.selected = true;
        }
        selectEl.appendChild(option);
      });
    }

    // Popola select categoria bulk edit
    var bulkCatSelect = document.getElementById('pcv-bulk-categoria');
    if (bulkCatSelect && ajaxData.categories) {
      var categorie = ajaxData.categories || [];
      categorie.forEach(function(cat) {
        var option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        bulkCatSelect.appendChild(option);
      });
    }

    // Event listener per cambio provincia nel modal (aggiunto una sola volta)
    var modalProvSelect = document.getElementById('pcv-modal-provincia');
    var modalComuneSelect = document.getElementById('pcv-modal-comune');
    if (modalProvSelect && modalComuneSelect) {
      modalProvSelect.addEventListener('change', function() {
        populateModalComuneSelect(modalProvSelect.value, modalComuneSelect, '');
      });
    }

    // Verifica presenza pulsanti modifica
    var editButtons = document.querySelectorAll('.pcv-edit-volunteer');
    console.log('Pulsanti modifica trovati:', editButtons.length);
    if (editButtons.length > 0) {
      console.log('Primo pulsante modifica:', editButtons[0]);
    }

    // Apri modal modifica singola
    console.log('Aggiungo listener per click su modifica');
    document.addEventListener('click', function(e) {
      // Verifica se il click è su un pulsante modifica o su un suo elemento padre
      var target = e.target;
      var editBtn = null;
      
      if (target.classList.contains('pcv-edit-volunteer')) {
        editBtn = target;
      } else if (target.closest && target.closest('.pcv-edit-volunteer')) {
        editBtn = target.closest('.pcv-edit-volunteer');
      }
      
      if (editBtn) {
        console.log('Click su modifica intercettato');
        e.preventDefault();
        var id = parseInt(editBtn.getAttribute('data-id'));
        console.log('ID volontario:', id);
        if (!id) {
          console.error('ID non valido');
          return;
        }
        
        currentEditId = id;
        
        // Carica dati volontario
        console.log('Invio richiesta AJAX per ottenere dati volontario');
        jQuery.post(ajaxData.ajax_url, {
          action: 'pcv_get_volunteer',
          nonce: ajaxData.nonce,
          id: id
        }, function(response) {
          console.log('Risposta AJAX ricevuta:', response);
          if (response.success && response.data.volunteer) {
            var v = response.data.volunteer;
            var form = document.getElementById('pcv-edit-form');
            form.querySelector('[name="nome"]').value = v.nome || '';
            form.querySelector('[name="cognome"]').value = v.cognome || '';
            form.querySelector('[name="email"]').value = v.email || '';
            form.querySelector('[name="telefono"]').value = v.telefono || '';
            form.querySelector('[name="categoria"]').value = v.categoria || '';
            form.querySelector('[name="privacy"]').checked = parseInt(v.privacy) === 1;
            form.querySelector('[name="partecipa"]').checked = parseInt(v.partecipa) === 1;
            form.querySelector('[name="dorme"]').checked = parseInt(v.dorme) === 1;
            form.querySelector('[name="mangia"]').checked = parseInt(v.mangia) === 1;
            
            var provSelect = document.getElementById('pcv-modal-provincia');
            var comuneSelect = document.getElementById('pcv-modal-comune');
            var catSelect = document.getElementById('pcv-modal-categoria');
            
            populateModalProvinceSelect(provSelect, v.provincia);
            populateModalComuneSelect(v.provincia, comuneSelect, v.comune);
            populateCategorieSelect(catSelect, v.categoria, true);
            
            document.getElementById('pcv-edit-modal').style.display = 'block';
          } else {
            console.error('Errore nella risposta:', response);
            alert('Errore nel caricamento dei dati: ' + (response.data && response.data.message ? response.data.message : 'Errore sconosciuto'));
          }
        }).fail(function(xhr, status, error) {
          console.error('Errore AJAX:', status, error);
          alert('Errore di connessione: ' + error);
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
            closeModals();
            alert(response.data.message);
            location.reload();
          } else {
            alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
          }
        });
      }
    });

    // Funzione per chiudere i modal
    function closeModals() {
      document.getElementById('pcv-edit-modal').style.display = 'none';
      document.getElementById('pcv-bulk-modal').style.display = 'none';
      currentEditId = null;
      
      // Reset del form bulk
      var bulkForm = document.getElementById('pcv-bulk-form');
      if (bulkForm) {
        bulkForm.reset();
      }
      
      // Reset del titolo del modal bulk
      var bulkModal = document.getElementById('pcv-bulk-modal');
      var title = bulkModal.querySelector('h2');
      if (title) {
        title.textContent = 'Modifica Multipla';
      }
    }
    
    // Chiudi modal
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('pcv-modal-close') || e.target.classList.contains('pcv-modal-cancel')) {
        closeModals();
      }
      
      // Chiudi modal cliccando fuori dal contenuto
      if (e.target.classList.contains('pcv-admin-modal')) {
        closeModals();
      }
    });
    
    // Chiudi modal con tasto ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModals();
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
          closeModals();
          alert(response.data.message);
          location.reload();
        } else {
          alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
        }
      });
    });

    // Gestione bulk edit - intercetta il click sul pulsante Applica
    document.addEventListener('click', function(e) {
      // Controlla se è il pulsante "Applica" o "Do Action"
      if (e.target.type === 'submit' && 
          (e.target.value === 'Applica' || e.target.value === 'Do Action' || 
           e.target.classList.contains('button') && e.target.closest('#posts-filter'))) {
        
        var action1 = document.querySelector('select[name="action"]');
        var action2 = document.querySelector('select[name="action2"]');
        var action = (action1 && action1.value) || (action2 && action2.value);
        
        if (action === 'bulk_edit') {
          e.preventDefault();
          e.stopPropagation();
          
          var checkboxes = document.querySelectorAll('input[name="id[]"]:checked');
          if (checkboxes.length === 0) {
            // Mostra un messaggio più user-friendly
            var message = document.createElement('div');
            message.className = 'notice notice-error';
            message.style.cssText = 'margin: 15px 0; padding: 12px; background: #fef2f2; border-left: 4px solid #ef4444; color: #7f1d1d;';
            message.innerHTML = '<p><strong>Errore:</strong> Seleziona almeno un volontario per effettuare la modifica bulk.</p>';
            
            // Rimuovi eventuali messaggi precedenti
            var existingNotice = document.querySelector('.pcv-bulk-notice');
            if (existingNotice) {
              existingNotice.remove();
            }
            
            message.className += ' pcv-bulk-notice';
            
            // Inserisci il messaggio dopo la toolbar
            var toolbar = document.querySelector('.tablenav.top');
            if (toolbar) {
              toolbar.insertAdjacentElement('afterend', message);
            } else {
              // Fallback: inserisci all'inizio della tabella
              var table = document.querySelector('.wp-list-table');
              if (table) {
                table.insertAdjacentElement('beforebegin', message);
              }
            }
            
            // Rimuovi il messaggio dopo 5 secondi
            setTimeout(function() {
              if (message && message.parentNode) {
                message.remove();
              }
            }, 5000);
            
            return;
          }
          
          // Mostra il numero di elementi selezionati nel modal
          var modal = document.getElementById('pcv-bulk-modal');
          var title = modal.querySelector('h2');
          if (title) {
            title.textContent = 'Modifica Multipla (' + checkboxes.length + ' volontari selezionati)';
          }
          
          modal.style.display = 'block';
        }
      }
    });

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
          closeModals();
          alert(response.data.message);
          location.reload();
        } else {
          alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
        }
      });
    });
  }

  // Esegui init quando il DOM è pronto
  if (document.readyState === 'loading') {
    console.log('DOM ancora in caricamento, attendo DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', function() {
      // Aspetta un po' per assicurarsi che tutto sia caricato
      setTimeout(initAdmin, 100);
    });
  } else {
    console.log('DOM già caricato, eseguo init subito');
    // Aspetta un po' anche se il DOM è già caricato
    setTimeout(initAdmin, 100);
  }
})();
