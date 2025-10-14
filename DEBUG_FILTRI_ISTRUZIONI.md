# 🔍 Istruzioni per Debug dei Filtri

## 📋 Problema
I filtri continuano a non funzionare nonostante le correzioni apportate.

## 🛠️ Debug Aggiunto

Ho aggiunto del logging di debug per identificare esattamente dove si trova il problema:

### 1. **Debug nel file `includes/admin/class-admin-assets.php`**
- Verifica quale hook viene passato alla funzione `enqueue()`
- Controlla se il JavaScript viene caricato
- Verifica se i dati vengono localizzati correttamente

### 2. **Debug nel file `assets/js/admin.js`**
- Verifica se i dati JavaScript sono presenti
- Controlla se gli elementi DOM vengono trovati
- Testa gli eventi dei filtri

## 🧪 Come Eseguire il Debug

### Passo 1: Abilita il Log di WordPress
1. Apri il file `wp-config.php` nella root di WordPress
2. Aggiungi questa riga (se non c'è già):
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Salva il file

### Passo 2: Controlla i Log
1. Vai alla pagina admin "Volontari Abruzzo"
2. Controlla il file di log WordPress in `/wp-content/debug.log`
3. Cerca i messaggi che iniziano con "PCV"

### Passo 3: Controlla la Console del Browser
1. Apri la console del browser (F12)
2. Vai alla pagina admin "Volontari Abruzzo"
3. Controlla i messaggi di debug che iniziano con "=== DEBUG FILTRI"

## 📊 Cosa Cercare nei Log

### Log WordPress (`/wp-content/debug.log`)
```
PCV Admin Assets Hook: [hook_name]
PCV Menu Slug: pcv-volontari
PCV Hook contains menu slug: YES/NO
PCV Assets loading for hook: [hook_name]
PCV Admin JS enqueued: [url]
PCV Admin Data localized: [json_data]
PCV AJAX Data localized: [json_data]
```

### Console Browser
```
=== DEBUG FILTRI PC VOLONTARI ABRUZZO ===
DOM caricato: complete
PCV_ADMIN_DATA presente: true/false
PCV_AJAX_DATA presente: true/false
Form pcv-filter-form trovato: true/false
Provincia select trovato: true/false
Comune select trovato: true/false
Categoria select trovato: true/false
Search input trovato: true/false
jQuery caricato: true/false
Script admin.js caricato: true/false
```

## 🚨 Possibili Problemi e Soluzioni

### Problema 1: Hook non corrisponde
**Sintomo:** `PCV Hook contains menu slug: NO`
**Soluzione:** Il problema è nel controllo dell'hook. Potrebbe essere necessario modificare la logica.

### Problema 2: JavaScript non caricato
**Sintomo:** `PCV Admin JS enqueued: [url]` non appare nei log
**Soluzione:** Il file JavaScript non viene caricato. Verifica il percorso del file.

### Problema 3: Dati non localizzati
**Sintomo:** `PCV_ADMIN_DATA presente: false` nella console
**Soluzione:** I dati JavaScript non vengono passati correttamente.

### Problema 4: Elementi DOM non trovati
**Sintomo:** `Form pcv-filter-form trovato: false`
**Soluzione:** Gli ID degli elementi non corrispondono o la pagina non è quella corretta.

## 📝 Prossimi Passi

1. **Esegui il debug** seguendo le istruzioni sopra
2. **Copia i log** e inviameli per analisi
3. **Identifica il problema specifico** basandoti sui sintomi
4. **Applica la soluzione** appropriata

## 🔧 File di Debug Temporaneo

Ho creato anche un file `debug-filtri.js` che puoi includere temporaneamente nella pagina per testare gli eventi dei filtri.

**Per usarlo:**
1. Aggiungi questo script alla pagina admin (temporaneamente)
2. Controlla la console per i messaggi di debug
3. Rimuovi il file quando hai finito il debug

## ⚠️ Importante

- I messaggi di debug possono essere rimossi in produzione
- Il file `debug-filtri.js` è temporaneo e va rimosso dopo il debug
- I log di WordPress vanno disabilitati in produzione per motivi di sicurezza
