# Fix Errore Fatale all'Attivazione e White Screen

## Problema
1. **Errore fatale all'attivazione** senza messaggi di errore nei log
2. **White Screen of Death (WSOD)** dopo le prime modifiche

## Causa
Il problema era causato da:
1. L'inizializzazione completa del plugin veniva eseguita immediatamente, anche durante la fase di attivazione
2. Questo causava conflitti con hook di WordPress non ancora completamente disponibili  
3. **PRINCIPALE**: Le classi admin (PCV_List_Table) venivano caricate anche nel frontend, richiedendo `WP_List_Table` che è disponibile solo in area admin
4. Il caricamento di `WP_List_Table` falliva nel frontend causando il white screen

## Modifiche Apportate

### 1. File principale: `pc-volontari-abruzzo.php`

#### Definizione Costanti
- Aggiunta costante `PCV_PLUGIN_FILE` per il percorso del file principale
- Aggiunta costante `PCV_PLUGIN_DIR` per la directory del plugin

#### Controlli Versione
- Aggiunta verifica versione PHP minima (7.0+)
- Aggiunta verifica versione WordPress minima (5.0+)
- Se i requisiti non sono soddisfatti, il plugin viene disattivato con messaggio esplicativo

#### Inizializzazione Sicura
- L'inizializzazione usa `wp_installing()` per verificare che WordPress sia pronto
- Il plugin non si inizializza durante l'installazione di WordPress

### 2. Classe Plugin: `includes/class-plugin.php`

#### **FIX CRITICO: Caricamento Condizionale Admin** ⭐
- Le classi admin vengono caricate **SOLO** quando `is_admin()` è `true`
- Questo previene il caricamento di `WP_List_Table` nel frontend
- Risolve il white screen causato dalla richiesta di classi admin non disponibili

#### Hook Admin Condizionali
- Gli hook admin (`admin_menu`, `admin_enqueue_scripts`, `admin_init`) vengono registrati solo in area admin
- Riduce il carico nel frontend

#### Fix Hook Database
- Cambiato hook per `PCV_Database::maybe_upgrade_schema` da `plugins_loaded` a `init`
- Garantisce che il database sia aggiornato quando necessario

### 3. Installer: `includes/class-installer.php`

#### Caricamento Textdomain
- Aggiunto caricamento del textdomain durante l'attivazione
- Questo assicura che le traduzioni siano disponibili per i messaggi di errore

## Come Testare

1. **Disattiva il plugin** se attualmente attivo
2. **Riattiva il plugin** dal pannello dei plugin di WordPress
3. Il plugin dovrebbe attivarsi senza errori
4. Verifica che:
   - La tabella del database sia stata creata
   - Il ruolo personalizzato "Gestore Volontari" sia presente
   - Il menu del plugin appaia nel backend

## Verifica Requisiti

Il plugin ora verifica automaticamente:
- **PHP**: Versione 7.0 o superiore
- **WordPress**: Versione 5.0 o superiore

Se i requisiti non sono soddisfatti, l'attivazione fallirà con un messaggio chiaro.

## Debug Aggiuntivo

Se il problema persiste, verifica:

1. **Permessi File**: Assicurati che i file del plugin siano leggibili dal server web
2. **File JSON**: Verifica che `data/comuni_abruzzo.json` esista e sia leggibile
3. **Log PHP**: Controlla il log degli errori PHP del server
   - In wp-config.php: `define('WP_DEBUG', true);` e `define('WP_DEBUG_LOG', true);`
   - Il log sarà in `wp-content/debug.log`
4. **Memoria PHP**: Assicurati che il limite di memoria sia adeguato (almeno 64MB)

## Rollback

Se necessario, è disponibile il backup del file originale: `pc-volontari-abruzzo.php.backup`

Per ripristinare:
```bash
cp pc-volontari-abruzzo.php.backup pc-volontari-abruzzo.php
```

