# Traduzioni del plugin

Il plugin carica automaticamente i file di traduzione dal percorso `languages/` utilizzando il dominio `pc-volontari-abruzzo`.

Per generare i file `.po`/`.mo` aggiornati puoi utilizzare [WP-CLI](https://developer.wordpress.org/cli/commands/i18n/make-pot/):

```bash
wp i18n make-pot . languages/pc-volontari-abruzzo.pot
```

Successivamente crea i file locali (es. `pc-volontari-abruzzo-it_IT.po`) con un editor di traduzioni come [Poedit](https://poedit.net/).
