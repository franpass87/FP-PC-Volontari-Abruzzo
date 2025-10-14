# Fix Errore Fatale all'Attivazione

## Problema
Il plugin generava un errore fatale durante l'attivazione senza messaggi di errore chiari nei log.

## Causa
Il problema era causato da:
1. L'inizializzazione completa del plugin veniva eseguita immediatamente, anche durante la fase di attivazione
2. Questo poteva causare conflitti con hook di WordPress non ancora completamente disponibili
3. Il plugin tentava di aggiungere un'azione all'hook `plugins_loaded` mentre era già in esecuzione

## Modifiche Apportate

### 1. File principale: `pc-volontari-abruzzo.php`

#### Definizione Costante
- Aggiunta costante `PCV_PLUGIN_FILE` per il percorso del file principale

#### Spostamento Inizializzazione
- L'inizializzazione del plugin (`new PCV_Plugin()`) è stata spostata dall'esecuzione immediata all'hook `plugins_loaded`
- Creata funzione `pcv_init_plugin()` per gestire l'inizializzazione

#### Controlli Versione
- Aggiunta verifica versione PHP minima (7.0+)
- Aggiunta verifica versione WordPress minima (5.0+)
- Se i requisiti non sono soddisfatti, il plugin viene disattivato con messaggio esplicativo

### 2. Classe Plugin: `includes/class-plugin.php`

#### Fix Hook Database
- Cambiato hook per `PCV_Database::maybe_upgrade_schema` da `plugins_loaded` a `init`
- Questo evita il conflitto con l'inizializzazione del plugin in `plugins_loaded`

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

