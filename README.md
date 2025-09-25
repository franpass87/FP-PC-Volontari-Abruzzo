# PC Volontari Abruzzo

Plugin WordPress per la raccolta delle iscrizioni dei volontari della Protezione Civile Abruzzo. Offre un flusso completo dalla compilazione del form pubblico alla consultazione e all'esportazione dei dati dall'area amministrativa.

## Sommario
- [Caratteristiche principali](#caratteristiche-principali)
- [Architettura del plugin](#architettura-del-plugin)
- [Installazione](#installazione)
- [Aggiornamento del plugin](#aggiornamento-del-plugin)
- [Disinstallazione](#disinstallazione)
- [Configurazione](#configurazione)
- [Utilizzo](#utilizzo)
- [Dati raccolti](#dati-raccolti)
- [Privacy e GDPR](#privacy-e-gdpr)
- [Requisiti tecnici](#requisiti-tecnici)
- [Supporto](#supporto)
- [Autore](#autore)
- [Changelog](#changelog)

## Caratteristiche principali

### Frontend
- Form di registrazione tramite shortcode `[pc_volontari_form]`.
- Popup interattivo per la scelta di provincia e comune.
- Ricerca istantanea all'interno della lista di Comuni e Province dell'Abruzzo.
- Validazione client-side con JavaScript e messaggi di errore contestuali.
- Protezione facoltativa con reCAPTCHA v2.
- Memorizzazione locale delle preferenze (localStorage) per facilitare compilazioni successive.
- Layout responsive compatibile con i principali temi WordPress.

### Backend
- Gestionale dedicato in "Volontari Abruzzo" con tabella, ricerca, filtri e ordinamenti.
- Esportazione CSV dei dati raccolti.
- Configurazione reCAPTCHA dall'interfaccia admin.
- Personalizzazione delle etichette principali del form.
- Notifiche email configurabili per avvisare i referenti ad ogni nuova iscrizione.
- Gestione completa degli stati della tabella (paginazione, ordinamento, ricerca libera).

## Architettura del plugin

- `pc-volontari-abruzzo.php`: file principale, registra hook, shortcode, admin menu e gestisce il database.
- `assets/css/frontend.css`: stili per form e popup frontend.
- `assets/js/frontend.js`: logica interattiva per il form pubblico (inclusa l'inizializzazione di reCAPTCHA).
- `assets/js/admin.js`: supporto JavaScript per le schermate amministrative.
- `data/comuni_abruzzo.json`: elenco di Province e Comuni dell'Abruzzo (province e comuni associati).

Il plugin crea la tabella `wp_pcv_volontari` (prefisso variabile in base all'installazione) con i campi:
- `id`, `created_at`
- `nome`, `cognome`
- `comune`, `provincia`
- `email`, `telefono`
- `privacy`, `partecipa`
- `dorme`, `mangia`
- `ip`, `user_agent`

## Installazione

1. Copia la cartella `pc-volontari-abruzzo` in `wp-content/plugins/`.
2. Accedi a WordPress e attiva "PC Volontari Abruzzo" dal menu **Plugin**.
3. Apri il menu **Volontari Abruzzo** per completare la configurazione.

## Aggiornamento del plugin

1. Effettua un backup del database WordPress.
2. Sostituisci i file del plugin con la nuova versione.
3. Verifica la voce **Volontari Abruzzo → Impostazioni** per confermare le configurazioni.
4. Controlla il [changelog](#changelog) per le novità della versione installata.

## Disinstallazione

La disinstallazione del plugin rimuove la tabella dei volontari e le opzioni di reCAPTCHA, cancellando tutti i dati memorizzati.

## Configurazione

### reCAPTCHA v2 (opzionale)
1. Vai su [Google reCAPTCHA](https://www.google.com/recaptcha/admin).
2. Crea un nuovo sito selezionando reCAPTCHA v2.
3. Copia Site Key e Secret Key.
4. In WordPress vai su **Volontari Abruzzo → Impostazioni**.
5. Inserisci le chiavi e salva.

### Notifiche email
- Attiva l'opzione **Notifiche email** per ricevere un avviso ad ogni nuova iscrizione.
- Inserisci uno o più destinatari separati da invio, virgola o punto e virgola (se vuoto verrà usata l'email amministratore di WordPress).
- Personalizza l'oggetto dell'email per riconoscere rapidamente le comunicazioni in arrivo.

### Personalizzazione del form
- Modifica le etichette e i testi direttamente dall'interfaccia admin.
- Per aggiornare l'elenco dei Comuni modifica `data/comuni_abruzzo.json` mantenendo la struttura JSON originale.

## Utilizzo

### Inserimento del form
Inserisci lo shortcode `[pc_volontari_form]` in qualunque pagina o articolo per mostrare il form di registrazione.

### Gestione dei dati
- Accedi a **Volontari Abruzzo** nel menu admin per visualizzare tutte le registrazioni.
- Usa i filtri per limitare la visualizzazione per provincia, comune o parole chiave.
- Esporta l'elenco completo in formato CSV quando necessario.

## Dati raccolti

Il form richiede i seguenti dati obbligatori:
- Nome e Cognome
- Provincia e Comune (validati rispetto alla lista ufficiale Abruzzo)
- Email e Telefono
- Consenso privacy

Campi opzionali:
- Partecipazione all'evento
- Preferenze su pernottamento e pasti

## Privacy e GDPR

Il plugin è progettato per supportare gli adempimenti del Regolamento UE 2016/679 (GDPR):
- Raccolta del consenso esplicito per il trattamento dei dati.
- Informativa privacy integrata nel form.
- Memorizzazione sicura dei dati nel database WordPress.
- Salvataggio di IP e User Agent per finalità di sicurezza.

## Requisiti tecnici

- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- MySQL 5.6 o superiore

## Supporto

Per assistenza tecnica o segnalazione bug contattare l'autore.

- Sito web: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)

## Autore

**Francesco Passeri**

## Changelog

Consulta il file [CHANGELOG.md](CHANGELOG.md) per lo storico completo delle versioni.

## Versione

Attuale: **1.1.0**

## Build e distribuzione

Questo repository include workflow GitHub Actions per creare automaticamente file ZIP del plugin.

- **Push su `main`**: genera build di sviluppo.
- **Tag git**: produce release ufficiali (es. `git tag v1.1.0 && git push origin v1.1.0`).
- **Pull Request**: esegue build di verifica.

### Build manuale
- Vai su Actions → "Build WordPress Plugin ZIP" → "Run workflow".
- (Facoltativo) specifica una versione personalizzata.

### Download
- **Artifacts**: disponibili nella pagina Actions del repository.
- **Releases**: ZIP allegati alle release GitHub per le versioni taggate.

Per ulteriori dettagli consulta [.github/README.md](.github/README.md).
