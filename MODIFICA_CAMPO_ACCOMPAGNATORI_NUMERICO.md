# Aggiunta Campo Numerico Accompagnatori

## 📋 Riepilogo Modifiche

È stato aggiunto un nuovo campo **num_accompagnatori** (INT) per il conteggio numerico degli accompagnatori, mantenendo il campo **accompagnatori** (TEXT) per i dettagli. Questo permette il calcolo automatico del totale delle persone (volontario principale + accompagnatori) e la raccolta di informazioni dettagliate.

## 🔧 Modifiche Implementate

### 1. **Database Schema** ✅
- **File**: `includes/data/class-database.php`
- **Modifica**: Aggiunto nuovo campo `num_accompagnatori INT UNSIGNED NOT NULL DEFAULT 0`
- **Mantenuto**: Campo `accompagnatori TEXT NULL` per i dettagli
- **Migrazione**: Aggiunto metodo `maybe_migrate_num_accompagnatori_field()` per migrare automaticamente i dati esistenti
- **Logica migrazione**: 
  - Valori numerici esistenti nel campo accompagnatori vengono copiati in num_accompagnatori
  - Valori di testo rimangono nel campo accompagnatori
  - Limite massimo di 20 accompagnatori

### 2. **Form Frontend** ✅
- **File**: `includes/frontend/class-shortcode.php`
- **Modifica**: Aggiunto campo numerico + mantenuta textarea
- **Caratteristiche**:
  - **Campo numerico**: `type="number"` con `min="0"` e `max="20"` per il conteggio
  - **Campo dettagli**: `textarea` per nomi, età, relazioni degli accompagnatori
  - Valore predefinito: 0 per il numero
  - Placeholder esplicativi per entrambi i campi
  - Testi di aiuto specifici per ogni campo

### 3. **Form Handler** ✅
- **File**: `includes/frontend/class-form-handler.php`
- **Modifica**: Gestione di entrambi i campi
- **Logica**:
  - **Campo numerico**: Conversione con `absint()` per garantire numero positivo
  - **Campo dettagli**: Sanitizzazione con `sanitize_text()`
  - Limite massimo di 20 accompagnatori per il numero
  - Valore predefinito 0 se campo numerico vuoto

### 4. **Validazione** ✅
- **File**: `includes/services/class-validator.php`
- **Modifica**: Aggiunta validazione per campo numero accompagnatori
- **Controlli**:
  - Valore compreso tra 0 e 20 per il campo numerico
  - Messaggio di errore specifico se fuori range
  - Campo dettagli opzionale (nessuna validazione specifica)

### 5. **Interfaccia Admin** ✅
- **File**: `includes/admin/class-list-table.php`
- **Modifica**: Due colonne separate per numero e dettagli accompagnatori
- **Caratteristiche**:
  - **Colonna "N° Accompagnatori"**: Mostra il numero con tooltip del totale persone
  - **Colonna "Dettagli Accompagnatori"**: Mostra i dettagli con truncamento
  - "Nessun accompagnatore" se numero = 0
  - "Nessun dettaglio" se dettagli vuoti

- **File**: `includes/admin/class-ajax-handler.php`
- **Modifica**: Gestione di entrambi i campi nell'aggiornamento
- **Logica**: Stessa sanitizzazione del form frontend per entrambi i campi

- **File**: `assets/js/admin.js`
- **Modifica**: 
  - Due campi separati nel modal di modifica (numero + textarea)
  - Funzioni `formatNumAccompagnatoriCell()` e `formatAccompagnatoriCell()` separate
  - Tooltip con totale persone per il campo numerico

### 6. **Repository** ✅
- **File**: `includes/data/class-repository.php`
- **Modifica**: Formati aggiornati per entrambi i campi
- **Aggiornamento**: 
  - `accompagnatori` formato `%s` (testo)
  - `num_accompagnatori` formato `%d` (numero)
  - Formati di inserimento e aggiornamento corretti

### 7. **Import/Export** ✅
- **File**: `includes/import-export/class-importer.php`
- **Modifica**: 
  - Aggiunta definizione per entrambi i campi
  - Sanitizzazione appropriata per ogni campo
  - Limite massimo di 20 accompagnatori per il numero

- **File**: `includes/import-export/parsers/class-csv-parser.php`
- **Modifica**: Aggiunto mapping per entrambi i campi

- **File**: `includes/import-export/class-exporter.php`
- **Modifica**: Due colonne separate nell'export CSV

## 🎯 Funzionalità

### **Calcolo Totale Persone**
- **Formula**: Volontario principale (1) + Numero accompagnatori
- **Visualizzazione**: Tooltip nella tabella admin mostra "Totale persone: X"
- **Esempio**: 3 accompagnatori = 4 persone totali

### **Due Campi Separati**
- **Campo Numerico**: Per conteggio rapido e calcoli
- **Campo Dettagli**: Per informazioni specifiche (nomi, età, relazioni)
- **Flessibilità**: Possibilità di avere solo il numero o solo i dettagli

### **Validazione e Limiti**
- **Range**: 0-20 accompagnatori per il campo numerico
- **Predefinito**: 0 accompagnatori
- **Validazione**: Controllo lato client e server per il numero
- **Dettagli**: Campo opzionale senza limiti di lunghezza

### **Migrazione Dati**
- **Automatica**: Al prossimo accesso admin
- **Intelligente**: Preserva sia dati numerici che di testo
- **Sicura**: Nessuna perdita di informazioni esistenti

## 📊 Compatibilità

### **Retrocompatibilità** ✅
- Record esistenti migrati automaticamente
- Import CSV continua a funzionare
- Export mantiene formato numerico

### **Database** ✅
- Schema aggiornato automaticamente
- Migrazione dati trasparente
- Indici e performance ottimizzati

### **API** ✅
- Tutte le funzioni esistenti mantengono compatibilità
- Nuovo formato numerico trasparente
- Validazione migliorata

## 🧪 Test da Eseguire

### **Form Frontend**
1. Inserire volontario con 0 accompagnatori
2. Inserire volontario con 5 accompagnatori
3. Testare limite massimo (20)
4. Verificare validazione valori negativi

### **Admin**
1. Verificare visualizzazione nella tabella
2. Testare modifica campo accompagnatori
3. Verificare tooltip con totale persone
4. Testare ricerca per numero accompagnatori

### **Import/Export**
1. Importare CSV con campo accompagnatori numerico
2. Esportare dati e verificare formato
3. Testare migrazione dati esistenti

### **Database**
1. Verificare migrazione automatica
2. Controllare schema aggiornato
3. Testare performance con nuovi dati

## 📈 Benefici

1. **Calcolo Automatico**: Totale persone calcolato automaticamente
2. **Flessibilità Massima**: Possibilità di inserire solo numero, solo dettagli, o entrambi
3. **Validazione Migliorata**: Controlli numerici più robusti
4. **UX Migliorata**: Input numerico per conteggio + textarea per dettagli
5. **Performance**: Campo numerico più efficiente per query e analisi
6. **Analisi Dati**: Possibilità di statistiche sui numeri
7. **Informazioni Complete**: Mantenimento di tutti i dettagli esistenti

## 🔄 Prossimi Passi

1. **Test Completo**: Eseguire tutti i test elencati
2. **Backup**: Fare backup prima della migrazione
3. **Monitoraggio**: Verificare migrazione dati
4. **Documentazione**: Aggiornare manuale utente se necessario

---

**Data Modifica**: $(date)  
**Versione**: 1.3.3  
**Stato**: ✅ Completato
