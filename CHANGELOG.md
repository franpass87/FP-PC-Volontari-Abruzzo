# Changelog

Tutte le modifiche rilevanti del plugin **PC Volontari Abruzzo**.

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
