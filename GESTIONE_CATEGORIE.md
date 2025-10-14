# Gestione Categorie - Plugin PC Volontari Abruzzo

## 📋 Panoramica

Il sistema di gestione categorie permette di organizzare e classificare i volontari in diverse categorie personalizzabili. Questa funzionalità è stata completamente integrata nel plugin con le seguenti caratteristiche:

## ✨ Funzionalità Implementate

### 1. **Pagina Gestione Categorie** 
*Menu: Volontari Abruzzo → Categorie*

Una pagina completa per gestire le categorie con funzionalità CRUD:

- ✅ **Aggiungi nuove categorie** - Crea categorie personalizzate
- ✅ **Rinomina categorie** - Modifica il nome e aggiorna automaticamente tutti i volontari associati
- ✅ **Elimina categorie** - Rimuovi categorie non più utilizzate
- ✅ **Ripristina predefinite** - Torna alle categorie di default
- ✅ **Statistiche integrate** - Visualizza il numero di volontari per ogni categoria
- ✅ **Categorie in uso** - Identifica categorie usate ma non nell'elenco predefinito

#### Categorie Predefinite:
- Volontari
- Staff
- Organizzatori
- Protezione Civile

### 2. **Filtro Categoria nella Lista Volontari**
*Menu: Volontari Abruzzo*

Un nuovo filtro dropdown è stato aggiunto alla toolbar:

- ✅ Filtra volontari per categoria
- ✅ Auto-submit al cambio selezione
- ✅ Integrato con filtri esistenti (Provincia, Comune, Ricerca)
- ✅ Export CSV rispetta il filtro categoria

### 3. **Select Categoria nei Form di Modifica**

I campi categoria sono ora menu a tendina invece di input testuali:

- ✅ **Modifica singola** - Select con tutte le categorie disponibili
- ✅ **Modifica multipla (bulk)** - Aggiorna categoria su più volontari contemporaneamente
- ✅ Caricamento dinamico delle categorie dal database
- ✅ Mostra categorie predefinite + categorie in uso

### 4. **Widget Dashboard Statistiche**
*Dashboard WordPress*

Widget completo con statistiche in tempo reale:

- 📊 **Totale volontari** registrati
- 📅 **Nuovi volontari** ultimi 7 giorni  
- 🏆 **Provincia più attiva** con numero volontari
- 📈 **Distribuzione per categoria** con:
  - Numero volontari per categoria
  - Percentuale sul totale
  - Grafico a barre visuale
- 🔗 Link rapidi a Gestisci Volontari e Gestisci Categorie

## 🔧 Struttura Tecnica

### Nuovi File Creati:

1. **`includes/class-category-manager.php`**
   - Classe centrale per la gestione categorie
   - Metodi: get_categories(), save_categories(), add_category(), delete_category(), rename_category()
   - Gestione statistiche e conteggi

2. **`includes/admin/class-categories-page.php`**
   - Interfaccia admin per gestione categorie
   - Form per aggiunta, modifica ed eliminazione
   - Visualizzazione statistiche

3. **`includes/admin/class-dashboard-widget.php`**
   - Widget dashboard WordPress
   - Statistiche visuali con grafici
   - Design responsive e moderno

### File Modificati:

1. **`includes/admin/class-admin-menu.php`**
   - Aggiunto menu "Categorie"

2. **`includes/class-plugin.php`**
   - Integrato categories_page
   - Registrato dashboard widget
   - Aggiunto filtro categoria all'export CSV

3. **`includes/data/class-repository.php`**
   - Aggiunto supporto filtro 'categoria' in get_volunteers()
   - Aggiunto supporto filtro 'categoria' in count_volunteers()

4. **`includes/admin/class-list-table.php`**
   - Aggiunto dropdown filtro categoria in extra_tablenav()
   - Integrato con sistema filtri esistente

5. **`includes/admin/class-admin-assets.php`**
   - Passate categorie ai dati JavaScript via PCV_AJAX_DATA

6. **`assets/js/admin.js`**
   - Trasformato input categoria in select nei modal
   - Aggiunta funzione populateCategorieSelect()
   - Auto-submit filtro categoria
   - Popolamento dinamico select bulk edit

## 🎯 Come Usare

### Gestire le Categorie:

1. Vai su **Volontari Abruzzo → Categorie**
2. Aggiungi nuove categorie nel box "Aggiungi Nuova Categoria"
3. Rinomina o elimina categorie dalla tabella
4. Visualizza statistiche di utilizzo in tempo reale

### Filtrare per Categoria:

1. Vai su **Volontari Abruzzo**
2. Usa il dropdown "Filtra per Categoria" nella toolbar
3. Combina con altri filtri (Provincia, Comune, Ricerca)
4. Esporta risultati filtrati in CSV

### Modificare Categoria Volontari:

**Singolo volontario:**
1. Clicca "Modifica" su un volontario
2. Seleziona la categoria dal menu a tendina
3. Salva le modifiche

**Multipli volontari:**
1. Seleziona più volontari con checkbox
2. Scegli "Modifica campi selezionati" dal menu azioni
3. Clicca "Applica"
4. Seleziona la categoria dal menu
5. Clicca "Aggiorna"

### Visualizzare Statistiche:

1. Vai alla **Dashboard** di WordPress
2. Visualizza il widget "Statistiche Volontari Abruzzo"
3. Consulta grafici e percentuali per categoria
4. Usa i link rapidi per accedere alle pagine di gestione

## 🔒 Permessi

Tutte le funzionalità rispettano i permessi esistenti:

- **Visualizzare categorie/statistiche**: `pcv_view_volunteers`
- **Gestire categorie**: `pcv_manage_settings`
- **Modificare volontari**: Permessi di modifica esistenti

## 📝 Note Tecniche

- Le categorie sono salvate nell'opzione WordPress `pcv_categories`
- Il campo categoria nel database supporta stringhe fino a 150 caratteri
- La rinomina categoria aggiorna automaticamente tutti i volontari associati
- Il sistema supporta categorie custom inserite manualmente dagli utenti
- Le categorie vengono ordinate alfabeticamente nelle select

## 🚀 Compatibilità

- ✅ WordPress 5.0+
- ✅ PHP 7.0+
- ✅ Compatibile con tutte le funzionalità esistenti del plugin
- ✅ Responsive e accessibile
- ✅ Nessun conflitto con plugin esistenti

## 📊 Database

Nessuna modifica allo schema database richiesta. Il campo `categoria` esiste già nella tabella `pcv_volontari`.

## 🎨 UI/UX

- Design coerente con WordPress admin
- Modal responsive per modifica
- Auto-submit intelligente per filtri
- Grafici visuali nel dashboard widget
- Feedback utente con notifiche
- Conferme per azioni distruttive

