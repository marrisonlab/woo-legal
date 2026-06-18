# Woo Legal Returns – EU Directive

Plugin WordPress/WooCommerce per adeguare il negozio alla **Direttiva UE sui Diritti dei Consumatori (2011/83/UE)** e al D.Lgs. 21/2014 (recepimento italiano).

## Funzionalità

| Funzionalità | Descrizione |
|---|---|
| **Modulo di recesso UE** | Modulo standardizzato (Allegato I della Direttiva) compilabile online dall'area cliente |
| **Finestra 14 giorni** | Blocco automatico delle richieste oltre il periodo legale, calcolato dalla data di completamento/pagamento |
| **Tab "Resi & Recesso"** | Sezione dedicata nell'area "Il mio account" con elenco richieste e form per nuove richieste |
| **Caricamento prodotti dinamico** | I prodotti fisici dell'ordine selezionato vengono caricati via AJAX per la selezione degli item da rendere |
| **Avviso thank-you page** | Notifica del diritto di recesso mostrata subito dopo l'acquisto (solo prodotti fisici) |
| **Notifiche email** | Email al cliente (conferma ricezione + aggiornamenti stato) e all'admin (nuova richiesta) |
| **Dashboard admin** | Lista resi con filtri per stato, ricerca, paginazione e pagina di dettaglio |
| **Gestione stati** | `Richiesto → Approvato → Rimborsato` (o Rifiutato/Annullato) con storico modifiche e note admin |
| **HPOS compatibile** | Dichiarazione compatibilità WooCommerce High-Performance Order Storage |

## Requisiti

- PHP ≥ 8.0
- WordPress ≥ 6.0
- WooCommerce ≥ 7.0

## Installazione

1. Clona/copia la cartella `woo-legal` nella directory `wp-content/plugins/`
2. Attiva il plugin da **Plugin → Plugin installati**
3. Va su **WooCommerce → Resi UE** per gestire le richieste
4. Il tab "Resi & Recesso" compare automaticamente nel "Il mio account"

## Struttura file

```
woo-legal/
├── woo-legal-returns.php               # Entry point plugin
├── includes/
│   ├── class-wlr-post-type.php         # CPT + CRUD richieste di reso
│   ├── class-wlr-customer-account.php  # Tab My Account + AJAX
│   ├── class-wlr-emails.php            # Notifiche email
│   └── class-wlr-admin.php             # Dashboard admin
├── templates/
│   ├── myaccount/
│   │   ├── returns.php                 # Elenco resi cliente
│   │   ├── return-request.php          # Form recesso UE
│   │   └── withdrawal-notice.php       # Avviso thank-you page
│   ├── emails/
│   │   ├── customer-return-received.php
│   │   ├── customer-status-update.php
│   │   └── admin-new-request.php
│   └── admin/
│       ├── list.php                    # Lista admin
│       └── detail.php                  # Dettaglio + azioni
└── assets/
    ├── css/wlr-frontend.css
    ├── css/wlr-admin.css
    ├── js/wlr-frontend.js
    └── js/wlr-admin.js
```

## Hook disponibili

| Hook | Tipo | Descrizione |
|---|---|---|
| `wlr_return_created` | action | Lanciato dopo la creazione di una richiesta `(return_id, order_id, customer_id)` |
| `wlr_return_status_changed` | action | Lanciato dopo il cambio di stato `(return_id, new_status, old_status)` |

## Override template

I template possono essere sovrascritti dal tema creando la cartella:
```
wp-content/themes/[tema]/woo-legal-returns/
```
e copiando i file da `templates/` mantenendo la stessa struttura.

## Conformità normativa

- **Art. 52 D.Lgs. 206/2005** (Codice del Consumo) – diritto di recesso 14 giorni
- **Allegato I Direttiva 2011/83/UE** – modulo di recesso tipo
- **Art. 56** – rimborso entro 14 giorni dalla ricezione della comunicazione di recesso
