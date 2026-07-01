# Changelog

## [1.3.1] - 2026-06-23

### Correzioni
- **Fix BOM UTF-8 (causa "headers already sent")**: rimosso il Byte Order Mark UTF-8 da `class-wlr-customer-account.php` e `class-wlr-setup-wizard.php`. Il BOM veniva emesso prima dei tag PHP causando errori "Cannot modify header information - headers already sent", che rompevano redirect del wizard (pagina bianca dopo lo step precontrattuale), risposte AJAX e header WooCommerce
- **Fix pagina bianca nel wizard**: risolto anche un potenziale fatal error dovuto all'accesso non protetto a `WC()->countries` durante la creazione automatica della pagina informativa, quando WooCommerce non è ancora completamente inizializzato in `admin_init`
- **Gestione errori wizard**: aggiunto `try-catch` con logging in `handle_post()`; eventuali errori durante il salvataggio mostrano ora un messaggio chiaro con back-link invece di una pagina bianca silenziosa

### Modifiche file
- `includes/class-wlr-customer-account.php`: rimosso BOM UTF-8
- `includes/class-wlr-setup-wizard.php`: rimosso BOM UTF-8, accesso null-safe a `WC()->countries` in `get_default_page_content()`, `try-catch` in `handle_post()`

---

## [1.3] - 2026-06-19

### Correzioni
- **Fix conflitto CSS frontend**: `WLR_Setup_Wizard` ora viene caricato solo nell'admin, risolvendo loop di caricamento e distorsione icone su checkout e My Account
- **Fix errori AJAX frontend**: aggiunto `ob_end_clean()` e `try-catch` agli handler AJAX per prevenire corruzione JSON da output PHP stray (notice/warning)
- **Fix errori AJAX admin**: applicata stessa fix a `handle_update_status` per eliminare "Errore di connessione" nel cambio stato dashboard
- **Checkout notice sicuro**: iniezione via hook `woocommerce_checkout_before_customer_details` invece di `wp_footer` per evitare conflitti AJAX
- **Link "Maggiori informazioni"**: ora punta alla pagina informativa creata dal wizard con `target="_blank"`

### Aggiunte
- **Pagina guida admin**: nuova pagina WooCommerce → Guida Resi con tab stile WordPress
  - Tab 1: Wizard di Configurazione (con link diretto al wizard)
  - Tab 2: Esclusioni Prodotti (come escludere prodotti dal recesso)
  - Tab 3: Gestione Rimborsi (flusso completo: ricezione → approvazione → rimborso WooCommerce → chiusura)
  - Tab 4: Stati Richieste (tabella riepilogo stati e effetti su ordine WooCommerce)

### Modifiche file
- `woo-legal-returns.php`: versione 1.3, caricamento `WLR_Setup_Wizard` solo in admin
- `includes/class-wlr-customer-account.php`: `ob_end_clean()` + `try-catch` in `handle_get_order_items()` e `handle_submit()`, checkout notice via hook WooCommerce
- `includes/class-wlr-admin.php`: aggiunta pagina guida, `ob_end_clean()` + `try-catch` in `handle_update_status()`
- `templates/admin/guide.php`: nuovo file template per pagina guida con tab

---

## [1.2] - 2026-06-18

### Aggiunte
- **Aggiornamenti automatici da GitHub**: il plugin ora si aggiorna automaticamente tramite le release GitHub (repo marrisonlab/woo-legal)
- **Validazione server-side della conferma recesso**: la checkbox di conferma è ora verificata lato server nell'handler AJAX
- **Invio conferma recesso al server**: il frontend JS invia correttamente il campo `confirm_withdrawal` al backend

### Correzioni
- **Localizzazione completa**: tutti i testi in email cliente e admin dashboard sono ora in italiano (motivi di reso tradotti invece di chiavi grezze)
- **Email aggiornamento stato per "approvato"**: il cliente riceve ora l'email di aggiornamento quando la richiesta è approvata, non solo quando è rimborsata
- **Rimozione double-fire email**: eliminato hook ridondante `save_post_wlr_return` che causava invio doppio delle email
- **Testo link recesso in email WooCommerce**: "Apri richiesta di reso →" → "Recedere dal contratto qui" (conforme art. 54-bis)
- **Fix parse error PHP**: corretto errore di sintassi in `class-wlr-customer-account.php` causato da escape apostrofo
- **Chiavi i18n mancanti**: aggiunte `selectOrder` e `submitBtn` per localizzazione completa del frontend JS

### Aggiornamenti normativi
- **Direttiva UE 2023/2673**: aggiornati tutti i riferimenti alla nuova direttiva che modifica il diritto di recesso digitale
- **D.Lgs. 209/2025 (art. 54-bis)**: recepimento italiano della funzione digitale di recesso con conferma immediata

### Modifiche file
- `woo-legal-returns.php`: versione 1.2, autore Marrisonlab, aggiunto updater GitHub
- `includes/class-wlr-github-updater.php`: nuovo file per aggiornamenti automatici
- `includes/class-wlr-emails.php`: aggiunto `reason_label` a `get_email_data()`, rimosso hook ridondante, fix testo link
- `includes/class-wlr-customer-account.php`: validazione `confirm_withdrawal`, fix parse error, aggiunte chiavi i18n
- `assets/js/wlr-frontend.js`: invio `confirm_withdrawal`, uso chiavi i18n per testo bottone
- `templates/emails/customer-return-received.php`: uso `reason_label` in entrambe le tabelle
- `templates/emails/admin-new-request.php`: uso `reason_label`
- `templates/admin/list.php`: traduzione motivo in label italiana
- `README.md`: aggiornati riferimenti normativi e struttura file

---

## [1.0] - 2026-06-XX

### Release iniziale
- Modulo di recesso UE standardizzato (Allegato I Direttiva 2011/83/UE)
- Tab "Resi & Recesso" nell'area My Account
- Gestione stati richiesta (Richiesto → Approvato → Rimborsato)
- Notifiche email cliente e admin
- Dashboard admin con lista e dettaglio resi
- Compatibilità HPOS WooCommerce
