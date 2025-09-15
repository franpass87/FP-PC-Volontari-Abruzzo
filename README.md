# PC Volontari Abruzzo

## Descrizione

Plugin WordPress per la raccolta iscrizioni volontari della Protezione Civile Abruzzo. Il plugin fornisce un sistema completo per la gestione delle registrazioni con form via shortcode, popup di selezione comune, lista completa dei Comuni e Province dell'Abruzzo, protezione reCAPTCHA v2 e gestionale backend.

## Funzionalità

### Frontend
- **Form di registrazione** tramite shortcode `[pc_volontari_form]`
- **Popup interattivo** per la selezione di Provincia e Comune
- **Lista completa** di tutti i Comuni e Province dell'Abruzzo
- **Validazione client-side** con JavaScript
- **Protezione reCAPTCHA v2** (opzionale)
- **Memorizzazione locale** delle preferenze utente (localStorage)
- **Design responsive** e moderno

### Backend
- **Gestionale completo** con tabella dati
- **Sistema di filtri** per Comune, Provincia e ricerca libera
- **Export CSV** dei dati raccolti
- **Configurazione reCAPTCHA** tramite interfaccia admin
- **Paginazione** e ordinamento dei risultati

## Installazione

1. Carica la cartella del plugin nella directory `/wp-content/plugins/`
2. Attiva il plugin dal menu "Plugin" di WordPress
3. Vai su "Volontari Abruzzo" nel menu amministrativo per configurare le impostazioni

## Configurazione

### reCAPTCHA (Opzionale)
1. Vai su [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Crea un nuovo sito con reCAPTCHA v2
3. Copia Site Key e Secret Key
4. Vai su "Volontari Abruzzo" > "Impostazioni" nell'admin WordPress
5. Inserisci le chiavi e salva

## Utilizzo

### Inserimento Form
Inserisci lo shortcode `[pc_volontari_form]` in qualsiasi pagina o post per visualizzare il form di registrazione.

### Gestione Dati
- Accedi a "Volontari Abruzzo" nel menu admin per vedere tutte le registrazioni
- Usa i filtri per cercare registrazioni specifiche
- Esporta i dati in formato CSV quando necessario

## Dati Raccolti

Il form raccoglie i seguenti dati obbligatori:
- Nome e Cognome
- Provincia e Comune di provenienza (validati contro lista ufficiale Abruzzo)
- Email e Telefono
- Consenso privacy (obbligatorio)
- Intenzione di partecipazione all'evento (opzionale)

## Privacy e GDPR

Il plugin è conforme al Regolamento UE 2016/679 (GDPR):
- Raccolta del consenso esplicito per il trattamento dati
- Informativa privacy integrata nel form
- Memorizzazione sicura dei dati nel database WordPress
- Tracciamento IP e User Agent per finalità di sicurezza

## Requisiti Tecnici

- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- MySQL 5.6 o superiore

## Struttura Database

Il plugin crea la tabella `wp_pcv_volontari` con i seguenti campi:
- `id` - Identificativo univoco
- `created_at` - Data registrazione
- `nome`, `cognome` - Dati anagrafici
- `comune`, `provincia` - Località
- `email`, `telefono` - Contatti
- `privacy`, `partecipa` - Consensi
- `ip`, `user_agent` - Dati tecnici

## Personalizzazione Province e Comuni

L'elenco delle Province e dei Comuni è definito nel file `data/comuni_abruzzo.json`.
Modifica questo file (mantenendo la struttura JSON con chiavi `province` e `comuni`)
per aggiornare rapidamente l'elenco utilizzato dal plugin.

## Licenza

Questo plugin è rilasciato sotto licenza GPLv2 or later.

## Autore

**Francesco Passeri**

## Versione

1.0

## Supporto

Per supporto tecnico o segnalazione bug, contattare l'autore del plugin.
