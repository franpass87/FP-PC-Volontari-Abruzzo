# 🛡️ Protezione Dati e Impostazioni - Versione 1.2.0

## ❌ Problema Identificato

Durante l'aggiornamento o il salvataggio delle impostazioni, alcuni dati venivano persi perché:

1. **Campi mancanti nel POST**: Se un campo del form non veniva inviato (errore JS, form incompleto, ecc.), il valore veniva sovrascritto con una stringa vuota
2. **Nessun backup**: Non c'era un sistema di backup automatico delle impostazioni prima degli aggiornamenti
3. **Nessun tracciamento versione**: Il plugin non tracciava la sua versione per gestire upgrade in modo sicuro

---

## ✅ Soluzioni Implementate

### 1. **Protezione Salvataggio Impostazioni**
📁 File: `includes/admin/class-settings-page.php`

**Modifica applicata:**
- ✅ I campi vengono aggiornati **SOLO se presenti** nel POST
- ✅ Se un campo non è inviato, il valore esistente viene **preservato**
- ✅ I checkbox vengono gestiti correttamente (assenza = non spuntato)

**Prima:**
```php
// ❌ Pericoloso: sovrascrive anche se vuoto
$raw_value = isset( $_POST[ $option_key ] ) ? wp_unslash( $_POST[ $option_key ] ) : '';
update_option( $option_key, $clean_value );
```

**Dopo:**
```php
// ✅ Sicuro: aggiorna solo se presente
if ( ! isset( $_POST[ $option_key ] ) ) {
    continue; // Preserva valore esistente
}
$raw_value = wp_unslash( $_POST[ $option_key ] );
update_option( $option_key, $clean_value );
```

---

### 2. **Sistema Backup Automatico**
📁 File: `includes/class-upgrade-manager.php` *(nuovo)*

**Funzionalità:**
- ✅ **Backup automatico** prima di ogni upgrade del plugin
- ✅ Salva **tutte le impostazioni critiche** con timestamp
- ✅ Possibilità di **ripristino** in caso di problemi
- ✅ Visualizzazione info backup nella pagina impostazioni

**Impostazioni protette nel backup:**
- Site Key e Secret Key reCAPTCHA
- Privacy notice
- Notifiche email (abilitazione, destinatari, oggetto)
- Categoria predefinita
- **Categorie personalizzate** (pcv_categories)
- Tutte le etichette personalizzate
- Placeholder provincia/comune

**Metodi disponibili:**
```php
PCV_Upgrade_Manager::maybe_upgrade();      // Chiamato automaticamente
PCV_Upgrade_Manager::backup_settings();    // Crea backup
PCV_Upgrade_Manager::restore_from_backup(); // Ripristina backup
PCV_Upgrade_Manager::get_backup_info();    // Info sul backup
```

---

### 3. **Tracciamento Versione Plugin**
📁 File: `includes/class-upgrade-manager.php`

**Funzionalità:**
- ✅ Traccia la versione corrente del plugin in `pcv_plugin_version`
- ✅ Rileva quando c'è un upgrade
- ✅ Esegue backup automatico prima dell'upgrade
- ✅ Pronto per migrazioni dati future

**Come funziona:**
1. Plugin si aggiorna (es. da 1.1.0 a 1.2.0)
2. `maybe_upgrade()` rileva la nuova versione
3. **Backup automatico** delle impostazioni
4. Esegue eventuali migrazioni necessarie
5. Aggiorna versione salvata a 1.2.0

---

### 4. **Visualizzazione Info Backup**
📁 File: `includes/admin/class-settings-page.php`

**Cosa mostra:**
- ✅ Avviso nella pagina Impostazioni se c'è un backup disponibile
- ✅ Data e ora del backup
- ✅ Numero di impostazioni salvate

**Esempio visualizzato:**
```
ℹ️ Backup automatico disponibile: 23 impostazioni salvate il 14/10/2025 15:30
```

---

### 5. **Aggiornamento Disinstallazione**
📁 File: `includes/class-installer.php`

**Modifica:**
- ✅ Aggiunta `pcv_categories` alla lista opzioni da eliminare
- ✅ Assicura pulizia completa durante disinstallazione

---

## 🔧 Integrazione nel Plugin

### File Modificati:

1. **`includes/class-autoloader.php`**
   - Aggiunta `PCV_Upgrade_Manager` alla mappa classi

2. **`includes/class-plugin.php`**
   - Hook `admin_init` per chiamare `PCV_Upgrade_Manager::maybe_upgrade()`
   - Esegue backup ad ogni caricamento admin se c'è nuovo upgrade

3. **`includes/admin/class-settings-page.php`**
   - Logica salvataggio protetta
   - Visualizzazione info backup

4. **`includes/class-installer.php`**
   - Aggiunta `pcv_categories` a lista disinstallazione

### File Creati:

1. **`includes/class-upgrade-manager.php`**
   - Classe completa gestione upgrade e backup

---

## 🚀 Come Funziona in Pratica

### Scenario 1: Aggiornamento Plugin
```
1. Utente aggiorna plugin da 1.1.0 a 1.2.0
2. Prima visita admin → maybe_upgrade() viene chiamato
3. Rileva versione diversa
4. ✅ BACKUP AUTOMATICO di tutte le impostazioni
5. Aggiorna versione a 1.2.0
6. Impostazioni preservate ✓
```

### Scenario 2: Salvataggio Impostazioni con Campo Mancante
```
1. Form impostazioni viene inviato
2. Campo "pcv_label_nome" NON presente nel POST (errore JS)
3. ✅ Campo viene SALTATO (continue)
4. Valore esistente PRESERVATO
5. Altri campi salvati normalmente ✓
```

### Scenario 3: Ripristino da Backup (se necessario)
```php
// In futuro si potrà aggiungere un pulsante per:
if ( PCV_Upgrade_Manager::restore_from_backup() ) {
    echo "Impostazioni ripristinate dal backup!";
}
```

---

## 📊 Opzioni WordPress Gestite

### Opzioni con Backup Automatico:
- `pcv_plugin_version` - Versione corrente plugin
- `pcv_settings_backup` - Backup completo impostazioni
- `pcv_recaptcha_site` - reCAPTCHA Site Key
- `pcv_recaptcha_secret` - reCAPTCHA Secret Key
- `pcv_privacy_notice` - Informativa privacy
- `pcv_notify_enabled` - Notifiche abilitate
- `pcv_notify_recipients` - Email destinatari
- `pcv_notify_subject` - Oggetto email
- `pcv_default_category` - Categoria predefinita
- `pcv_categories` - **Categorie personalizzate** ✨
- `pcv_label_*` - Tutte le etichette personalizzate

---

## 🛡️ Protezioni Attive

✅ **Anti-sovrascrittura**: Campi mancanti non sovrascrivono valori esistenti  
✅ **Backup automatico**: Prima di ogni upgrade  
✅ **Tracciamento versione**: Rileva aggiornamenti  
✅ **Preservazione categorie**: Categorie personalizzate protette  
✅ **Info visibili**: Utente vede quando c'è un backup  
✅ **Disinstallazione completa**: Pulizia corretta di tutte le opzioni  

---

## 📝 Note per Sviluppatori

### Aggiungere Nuove Opzioni al Backup:

Modificare `includes/class-upgrade-manager.php`:
```php
private static function backup_settings() {
    $settings_to_backup = [
        // ... opzioni esistenti ...
        'pcv_nuova_opzione', // Aggiungi qui
    ];
    // ...
}
```

### Aggiungere Migrazione Versione Specifica:

In `includes/class-upgrade-manager.php`:
```php
public static function maybe_upgrade() {
    // ... codice esistente ...
    
    // Esempio migrazione per versione specifica
    if ( version_compare( $current_version, '1.3.0', '<' ) 
         && version_compare( $plugin_version, '1.3.0', '>=' ) ) {
        self::migrate_to_1_3_0();
    }
    
    // ...
}
```

---

## ✅ Test Consigliati

1. **Test Aggiornamento:**
   - Imposta impostazioni personalizzate
   - Aggiorna plugin
   - Verifica che tutto sia preservato

2. **Test Salvataggio Parziale:**
   - Modifica solo alcune impostazioni
   - Salva
   - Verifica che le altre siano intatte

3. **Test Backup:**
   - Verifica presenza info backup in Impostazioni
   - Controlla opzione `pcv_settings_backup` nel database

4. **Test Disinstallazione:**
   - Disinstalla plugin
   - Verifica che tutte le opzioni siano eliminate

---

## 🔒 Sicurezza

- ✅ Tutti i dati sanitizzati prima del salvataggio
- ✅ Nonce verificati su tutte le azioni
- ✅ Permessi utente controllati
- ✅ Escape output HTML
- ✅ Prepared statements database

---

**Versione Documento:** 1.0  
**Data Implementazione:** 14 Ottobre 2025  
**Plugin Versione:** 1.2.0

