# Changelog

## [1.4.0] - 2026-07-28

### Aggiunte
- **Versione plugin aggiornata a 1.4.0**: header WordPress e costante `WLR_VERSION` allineati alla nuova release.
- **Pagina pubblica per il recesso**: nuova creazione automatica della pagina "Diritto di recesso" e shortcode `[wlr_return_form]` per consentire l'invio anche fuori dall'area account.
- **Link e shortcode dedicati**: aggiunto `[wlr_withdrawal_link]` per inserire il link alla procedura digitale nelle informative e mantenuto `[wlr_checkout_notice]` per l'avviso checkout.
- **Flusso a doppia conferma**: la richiesta viene prima validata, poi mostrata in riepilogo e registrata solo dopo conferma finale del cliente.
- **Ricevuta probatoria**: ogni richiesta salva data/ora UTC, IP, user agent, hash SHA-256 della dichiarazione e stato di invio della ricevuta email.
- **Consensi checkout per contenuti digitali e servizi**: nuovi campi checkout con testi configurabili e salvataggio dei consensi sull'ordine.
- **Regole recesso per prodotti e categorie**: nuovo stato recesso ereditabile dalle categorie, con eccezioni per contenuti digitali, servizi, prodotti su misura, deperibili, sigillati, media, beni mescolati, periodici e altre esclusioni normative.
- **Avvisi prodotto**: visualizzazione automatica e shortcode `[wlr_withdrawal_notice]` per indicare prodotti esclusi o soggetti a consenso espresso.
- **Impostazioni admin**: pagina "Impostazioni Resi" per pagina pubblica, email notifiche, stati ordine idonei, modalita del termine 14 giorni, giorni di tolleranza e testi dei consensi.
- **Export CSV**: nuova pagina "Export Resi" con filtri per stato, intervallo date e inclusione opzionale dei dati tecnici.
- **Azioni massive admin**: aggiornamento stato di piu richieste dalla lista, con nota cliente opzionale.
- **Colonna recesso sugli ordini WooCommerce**: stato della richiesta visibile sia nella lista ordini classica sia in quella HPOS.
- **Integrazione privacy WordPress**: testo suggerito per la privacy policy, exporter ed eraser per i dati personali delle richieste.
- **Tooling sviluppo**: aggiunti `composer.json`, `composer.lock`, `phpcs.xml.dist` e `.gitignore` per PHPCS, WPCS e compatibilita PHP/WP.

### Miglioramenti
- **Supporto ospiti piu completo**: link dalla thank-you page e dalle email con chiave ordine, piu lookup pubblico tramite numero ordine + email quando previsto.
- **Validazione server-side centralizzata**: accesso ordine, stati idonei, finestra 14 giorni, duplicati e prodotti recedibili vengono controllati in `WLR_Post_Type::validate_return_request()`.
- **Termine 14 giorni configurabile**: modalita indicativa o bloccante, decorrenza da completamento/pagamento o creazione ordine e tolleranza opzionale.
- **Email aggiornate**: ricevuta cliente con hash, dati di invio e avviso termini; destinatari admin multipli; supporto email WooCommerce plain text.
- **Wizard migliorato**: dati venditore precompilati e modificabili, aggiornamento della pagina informativa generata e logging errori tramite logger WooCommerce.
- **Dashboard admin arricchita**: dettaglio con dati tecnici, hash ricevuta, termine indicativo, consensi checkout e gestione ospiti.
- **Compatibilita coding standard**: progressiva conversione a sintassi array estesa, sanitizzazione/nonce piu espliciti e soppressioni PHPCS documentate.

### Correzioni
- **Duplicati piu precisi**: blocco solo delle richieste aperte o rimborsate, lasciando fuori quelle rifiutate/annullate.
- **Prodotti non recedibili**: esclusione coerente anche per varianti e prodotti che ereditano lo stato dalla categoria.
- **Prompt duplicati**: evitata la doppia visualizzazione del pulsante di recesso su thank-you page e dettaglio ordine.
- **Updater GitHub**: normalizzate strutture array e fallback changelog per maggiore compatibilita con gli standard del progetto.

### Modifiche file principali
- `woo-legal-returns.php`: versione 1.4.0, caricamento consensi checkout, privacy, compatibilita shortcode legacy e creazione pagina pubblica in attivazione.
- `includes/class-wlr-customer-account.php`: shortcode pubblici, flusso ospiti, doppia conferma AJAX, URL recesso centralizzati e pagina automatica.
- `includes/class-wlr-post-type.php`: validazione centralizzata, hash ricevuta, deadline configurabile, dati tecnici e controlli prodotti.
- `includes/class-wlr-product-settings.php`: stati recesso per prodotto/categoria, ereditarieta, avvisi prodotto e compatibilita meta legacy.
- `includes/class-wlr-checkout-consent.php`: nuovo gestore dei consensi checkout.
- `includes/class-wlr-privacy.php`: nuovo exporter/eraser dati personali e testo privacy policy.
- `includes/class-wlr-annex-form.php`: compatibilita con lo shortcode legacy del modulo tipo.
- `includes/class-wlr-admin.php`: impostazioni, export CSV, bulk action, colonna ordini e dettaglio probatorio.
- `includes/class-wlr-emails.php`: ricevuta cliente, destinatari admin configurabili, link recesso estesi e opzionale aggiornamento stato ordine WooCommerce.
- `templates/` e `assets/`: aggiornati form, liste, email e stili per riepilogo conferma, consensi e prodotti non recedibili.
- `composer.json`, `composer.lock`, `phpcs.xml.dist`, `.gitignore`: aggiunto tooling di sviluppo.

---

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
