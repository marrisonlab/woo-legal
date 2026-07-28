# Woo Legal Returns - EU Directive

Versione corrente: **1.4.0**

Plugin WordPress/WooCommerce per adeguare il negozio alla **Direttiva UE sui Diritti dei Consumatori (2011/83/UE come modificata dalla Direttiva 2023/2673)** e al D.Lgs. 209/2025 (art. 54-bis Codice del Consumo).

## Funzionalita

| Funzionalita | Descrizione |
|---|---|
| **Modulo di recesso UE** | Dichiarazione standardizzata compilabile dall'area cliente o da una pagina pubblica dedicata |
| **Doppia conferma digitale** | Validazione iniziale, riepilogo della dichiarazione e invio definitivo solo dopo conferma finale |
| **Ricevuta probatoria** | Salvataggio di data/ora UTC, IP, user agent, hash SHA-256 e stato invio ricevuta email |
| **Finestra 14 giorni configurabile** | Termine indicativo o bloccante, decorrenza da completamento/pagamento o creazione ordine e giorni di tolleranza |
| **Stati ordine idonei** | Gli stati WooCommerce ammessi alla richiesta sono configurabili da admin |
| **Tab "Resi & Recesso"** | Sezione dedicata in "Il mio account" con elenco richieste e form per nuove richieste |
| **Supporto clienti ospiti** | Link con chiave ordine da thank-you page/email e lookup pubblico tramite numero ordine + email |
| **Caricamento prodotti dinamico** | I prodotti recedibili dell'ordine selezionato vengono caricati via AJAX |
| **Regole prodotto/categoria** | Stato recesso ereditabile dalle categorie e sovrascrivibile sul singolo prodotto o variante |
| **Eccezioni normative** | Gestione di contenuti digitali, servizi iniziati prima del termine, beni personalizzati, deperibili, sigillati, media, periodici e altri casi previsti |
| **Consensi checkout** | Checkbox e registrazione consensi per contenuti digitali e servizi con esecuzione immediata |
| **Avvisi frontend** | Avviso checkout, avviso thank-you page, pulsanti recesso nelle email e notice prodotto |
| **Notifiche email** | Ricevuta cliente, aggiornamenti stato e nuova richiesta agli indirizzi admin configurati |
| **Dashboard admin** | Lista con filtri, ricerca, paginazione, bulk update, dettaglio richiesta e storico note |
| **Export CSV** | Esportazione richieste filtrata per stato/date con dati tecnici opzionali |
| **Colonna ordini WooCommerce** | Stato recesso visibile nella lista ordini classica e HPOS |
| **Privacy WordPress** | Testo suggerito privacy policy, exporter ed eraser per i dati delle richieste |
| **Setup wizard** | Configurazione pagina informativa, dati venditore, menu e avviso checkout |
| **Aggiornamenti GitHub** | Updater integrato basato sulle release GitHub del repository |
| **HPOS compatibile** | Dichiarazione compatibilita WooCommerce High-Performance Order Storage |

## Requisiti

- PHP >= 8.0
- WordPress >= 6.0
- WooCommerce >= 7.0

## Installazione

1. Clona o copia la cartella `woo-legal` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Completa il wizard da **WooCommerce > Configurazione Woo Legal Returns**.
4. Gestisci richieste, export e impostazioni da **WooCommerce > Resi UE**.
5. Per lo sviluppo, esegui `composer install` e poi `composer phpcs` per i controlli coding standard.

## Struttura file

```text
woo-legal/
|-- woo-legal-returns.php               # Entry point plugin
|-- includes/
|   |-- class-wlr-post-type.php         # CPT, validazione, deadline e persistenza richieste
|   |-- class-wlr-customer-account.php  # My Account, shortcode pubblici, AJAX e flusso ospiti
|   |-- class-wlr-emails.php            # Notifiche email e link recesso nelle email WooCommerce
|   |-- class-wlr-admin.php             # Dashboard, impostazioni, export CSV e bulk action
|   |-- class-wlr-setup-wizard.php      # Wizard configurazione iniziale e pagina informativa
|   |-- class-wlr-product-settings.php  # Regole prodotto/categoria e avvisi recesso
|   |-- class-wlr-checkout-consent.php  # Consensi checkout per digitale/servizi
|   |-- class-wlr-privacy.php           # Privacy policy, exporter ed eraser WordPress
|   |-- class-wlr-annex-form.php        # Compatibilita shortcode legacy
|   `-- class-wlr-github-updater.php    # Aggiornamenti automatici da GitHub
|-- templates/
|   |-- myaccount/
|   |   |-- returns.php                 # Elenco resi cliente
|   |   |-- return-request.php          # Form recesso UE
|   |   `-- withdrawal-notice.php       # Avviso thank-you page
|   |-- emails/
|   |   |-- customer-return-received.php
|   |   |-- customer-status-update.php
|   |   `-- admin-new-request.php
|   `-- admin/
|       |-- list.php                    # Lista admin
|       |-- detail.php                  # Dettaglio + azioni
|       `-- guide.php                   # Pagina guida admin
|-- assets/
|   |-- css/wlr-frontend.css
|   |-- css/wlr-admin.css
|   |-- js/wlr-frontend.js
|   `-- js/wlr-admin.js
|-- composer.json                       # Tooling sviluppo
|-- composer.lock
|-- phpcs.xml.dist
`-- CHANGELOG.md
```

## Shortcode disponibili

| Shortcode | Uso |
|---|---|
| `[wlr_return_form]` | Mostra il form pubblico di recesso, con supporto ospiti tramite numero ordine + email |
| `[wlr_withdrawal_link]` | Mostra un link alla procedura di recesso dell'account |
| `[wlr_checkout_notice]` | Mostra l'avviso checkout configurato nel wizard/impostazioni |
| `[wlr_withdrawal_notice id="123"]` | Mostra l'avviso di non recedibilita o consenso per un prodotto specifico |
| `[wlr_model_withdrawal_form]` | Shortcode legacy mantenuto per compatibilita; il contenuto viene rimosso dalle informative generate |

## Hook disponibili

| Hook | Tipo | Descrizione |
|---|---|---|
| `wlr_return_created` | action | Lanciato dopo la creazione di una richiesta `(return_id, order_id, customer_id)` |
| `wlr_return_status_changed` | action | Lanciato dopo il cambio di stato `(return_id, new_status, old_status)` |
| `wlr_after_return_form` | action | Punto di estensione dopo il form di recesso |
| `wlr_allow_guest_email_order_lookup` | filter | Abilita/disabilita il lookup ospite tramite numero ordine + email |
| `wlr_withdrawal_link_email_ids` | filter | Personalizza le email WooCommerce in cui mostrare il link di recesso |
| `wlr_digital_consent_text` | filter | Personalizza il testo consenso per contenuti digitali |
| `wlr_service_consent_text` | filter | Personalizza il testo consenso per servizi |

## Opzioni principali

| Opzione | Descrizione |
|---|---|
| `wlr_withdrawal_page_id` | Pagina pubblica che contiene `[wlr_return_form]` |
| `wlr_notify_emails` | Destinatari admin delle nuove richieste, separati da virgole |
| `wlr_eligible_order_statuses` | Stati ordine WooCommerce ammessi alla richiesta |
| `wlr_deadline_mode` | `advisory` per verifica manuale o `strict` per blocco oltre termine |
| `wlr_deadline_basis` | Base decorrenza: `completed_or_paid` oppure `created` |
| `wlr_deadline_grace_days` | Giorni di tolleranza aggiunti al termine indicativo |
| `wlr_digital_consent_text` | Testo consenso contenuti digitali |
| `wlr_service_consent_text` | Testo consenso servizi |
| `wlr_auto_update_order_status` | Se impostato a `yes`, sincronizza alcuni stati recesso con lo stato ordine WooCommerce |

## Override template

I template possono essere sovrascritti dal tema creando la cartella:

```text
wp-content/themes/[tema]/woo-legal-returns/
```

e copiando i file da `templates/` mantenendo la stessa struttura.

## Conformita normativa

- **Direttiva UE 2011/83/UE come modificata dalla Direttiva 2023/2673**: funzione digitale di recesso (Art. 54-bis)
- **D.Lgs. 209/2025 (art. 54-bis Codice del Consumo)**: conferma immediata, doppia conferma e testo completo dichiarazione
- **Finestra 14 giorni**: diritto di recesso senza motivazione dalla ricezione dei beni
- **Rimborso entro 14 giorni**: dalla ricezione della merce restituita
