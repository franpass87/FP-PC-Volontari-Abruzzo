# Changelog

Tutte le modifiche rilevanti del plugin **PC Volontari Abruzzo**.

## [1.2.0] - 2025-10-14
### Novità
- **Sistema Completo di Gestione Categorie**: Aggiunta pagina dedicata per creare, modificare, eliminare e gestire categorie volontari
- **Filtro Categoria**: Nuovo filtro dropdown nella lista volontari per filtrare per categoria con auto-submit
- **Widget Dashboard Statistiche**: Widget dashboard con statistiche in tempo reale, grafici e distribuzione volontari per categoria
- **Select Categoria nei Form**: Trasformati i campi categoria da input testuale a menu a tendina sia in modifica singola che bulk
- **Statistiche Avanzate**: Conteggio volontari per categoria, provincia più attiva, nuovi iscritti ultimi 7 giorni
- **Rinomina Categoria**: Possibilità di rinominare categorie aggiornando automaticamente tutti i volontari associati

### Miglioramenti
- Export CSV ora rispetta il filtro categoria selezionato
- Le categorie vengono caricate dinamicamente nei form di modifica
- Interfaccia utente migliorata con grafici visuali nelle statistiche
- Sistema di categorie predefinite con possibilità di personalizzazione completa
- Identificazione automatica di categorie in uso non presenti nell'elenco predefinito

### File Aggiunti
- `includes/class-category-manager.php` - Classe centrale gestione categorie
- `includes/admin/class-categories-page.php` - Interfaccia admin categorie
- `includes/admin/class-dashboard-widget.php` - Widget dashboard statistiche
- `GESTIONE_CATEGORIE.md` - Documentazione completa funzionalità categorie

### File Modificati
- `includes/class-plugin.php` - Integrazione categorie e widget dashboard
- `includes/admin/class-admin-menu.php` - Aggiunto menu Categorie
- `includes/data/class-repository.php` - Supporto filtro categoria
- `includes/admin/class-list-table.php` - Filtro dropdown categoria
- `includes/admin/class-admin-assets.php` - Dati categorie per JavaScript
- `assets/js/admin.js` - Select categoria dinamiche e auto-submit

## [1.1.0] - 2025-09-30
### Novità
- Aggiunta la possibilità di inviare notifiche email configurabili ai referenti quando viene registrato un nuovo volontario.
- Introdotto un hook (`pcv_volunteer_registered`) per integrare workflow personalizzati dopo il salvataggio dei dati.
### Miglioramenti
- Sanitizzazione avanzata dell'User Agent memorizzato con ogni iscrizione.

## [1.0.2] - 2025-09-26
### Correzioni
- Risolto un errore fatale nell'interfaccia di amministrazione sostituendo riferimenti errati al dominio di traduzione nella tabella dei volontari.

## [1.0.1] - 2025-09-25
### Modifiche
- Aggiornata la documentazione principale con panoramica completa delle funzionalità e delle procedure operative.
- Aggiunti riferimenti di contatto ufficiali e informazioni sull'autore.
- Introdotto questo changelog per tracciare l'evoluzione del plugin.

## [1.0.0] - 2025-09-15
### Novità
- Prima release pubblica del plugin.
- Form di iscrizione frontend con selezione guidata di Provincia e Comune.
- Validazione client-side, protezione reCAPTCHA opzionale e memorizzazione delle preferenze.
- Gestionale backend con filtri, ricerca e esportazione CSV dei volontari.
- Tabella dedicata nel database WordPress per la conservazione dei dati.
