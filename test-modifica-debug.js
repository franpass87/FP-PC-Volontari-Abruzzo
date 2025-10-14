// Script di debug per il pulsante modifica
// Apri la console del browser (F12) e incolla questo script

console.log('=== DEBUG PULSANTE MODIFICA ===');

// 1. Verifica se PCV_AJAX_DATA è definito
console.log('PCV_AJAX_DATA definito?', typeof window.PCV_AJAX_DATA !== 'undefined');
if (typeof window.PCV_AJAX_DATA !== 'undefined') {
  console.log('PCV_AJAX_DATA:', window.PCV_AJAX_DATA);
}

// 2. Verifica se jQuery è disponibile
console.log('jQuery disponibile?', typeof jQuery !== 'undefined');
if (typeof jQuery !== 'undefined') {
  console.log('jQuery version:', jQuery.fn.jquery);
}

// 3. Cerca i pulsanti modifica
var editButtons = document.querySelectorAll('.pcv-edit-volunteer');
console.log('Pulsanti modifica trovati:', editButtons.length);
if (editButtons.length > 0) {
  console.log('Primo pulsante:', editButtons[0]);
  console.log('Data-id del primo pulsante:', editButtons[0].getAttribute('data-id'));
}

// 4. Verifica se il modal esiste
var modal = document.getElementById('pcv-edit-modal');
console.log('Modal esiste?', modal !== null);
if (modal) {
  console.log('Modal:', modal);
  console.log('Display del modal:', window.getComputedStyle(modal).display);
}

// 5. Aggiungi un listener di test
if (editButtons.length > 0) {
  console.log('Aggiungo listener di test al primo pulsante...');
  editButtons[0].addEventListener('click', function(e) {
    console.log('CLICK INTERCETTATO DAL LISTENER DI TEST!');
    console.log('Event:', e);
  });
  console.log('Listener aggiunto. Prova a cliccare sul pulsante modifica ora.');
}

console.log('=== FINE DEBUG ===');

