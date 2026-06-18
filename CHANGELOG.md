# Changelog

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
