# Woo Legal Returns - EU Directive

Versione corrente: **1.5.6**

Il flusso cliente mostra solo le informazioni e i consensi pertinenti all'acquisto. Per gli ordini di soli beni fisici non vengono mostrate caselle o diciture sui consensi per contenuti digitali e servizi. Le email distinguono la decorrenza in base ai prodotti dell'ordine; il modulo di recesso rimanda all'informativa per le condizioni complete.

Plugin WordPress/WooCommerce per adeguare il negozio alla **Direttiva UE sui Diritti dei Consumatori (2011/83/UE come modificata dalla Direttiva 2023/2673)** e al D.Lgs. 209/2025 (art. 54-bis Codice del Consumo).

## Funzionalita

| Funzionalita | Descrizione |
|---|---|
| **Modulo di recesso UE** | Dichiarazione standardizzata compilabile dall'area cliente o da una pagina pubblica dedicata |
| **Doppia conferma digitale** | Validazione iniziale, riepilogo della dichiarazione e invio definitivo solo dopo conferma finale |
| **Ricevuta probatoria** | Salvataggio di data/ora UTC, IP, user agent, hash SHA-256 e stato invio ricevuta email |
| **Finestra 14 giorni configurabile** | Blocco predefinito oltre la scadenza calcolata, anche per ordini precedenti; ricezione effettiva o decorrenza configurata, tolleranza e calendario |
| **Stati ordine idonei** | Gli stati WooCommerce ammessi alla richiesta sono configurabili da admin |
| **Tab "Resi & Recesso"** | Sezione dedicata in "Il mio account" con elenco richieste e form per nuove richieste |
| **Supporto clienti ospiti** | Link con chiave ordine da thank-you page/email e lookup pubblico tramite numero ordine + email |
| **Caricamento prodotti dinamico** | I prodotti recedibili dell'ordine selezionato vengono caricati via AJAX |
| **Regole prodotto/categoria** | Stato recesso ereditabile dalle categorie e sovrascrivibile sul singolo prodotto o variante |
| **Eccezioni normative** | Gestione di contenuti digitali, servizi iniziati prima del termine, beni personalizzati, deperibili, sigillati, media, periodici e altri casi previsti |
| **Consensi checkout** | Checkbox e prova del consenso nel checkout classico e nei Blocks (WooCommerce 9.9+) |
| **Avvisi frontend** | Avviso checkout, avviso thank-you page, pulsanti recesso nelle email e notice prodotto |
| **Notifiche email** | Ricevuta cliente, aggiornamenti stato e nuova richiesta agli indirizzi admin configurati |
| **Dashboard admin** | Lista con filtri, ricerca, paginazione, bulk update, dettaglio richiesta e storico note |
| **Export CSV** | Esportazione richieste filtrata per stato/date con dati tecnici opzionali |
| **Colonna ordini WooCommerce** | Stato recesso visibile nella lista ordini classica e HPOS |
| **Privacy WordPress** | Export/eraser delle richieste, incluse quelle nel cestino; consensi integrati negli strumenti privacy WooCommerce |
| **Setup wizard** | Scelta tra pagina informativa generata e shortcode per pagine proprie, dati venditore, menu e avviso checkout |
| **Aggiornamenti GitHub** | Updater integrato basato sulle release GitHub del repository |
| **HPOS compatibile** | Dichiarazione compatibilita WooCommerce High-Performance Order Storage |

## Requisiti

- PHP >= 8.0
- WordPress >= 6.0
- WooCommerce >= 7.0
- Per i consensi nel checkout a blocchi: WooCommerce >= 9.9. Nelle versioni precedenti usare il checkout classico; il server rifiuta gli acquisti digitali privi del consenso richiesto.
- Database MySQL/MariaDB con supporto a GET_LOCK(); il plugin rifiuta scritture concorrenti se non può acquisire il lock.

## Installazione

1. Copia la sottocartella `woo-legal/` del repository in `wp-content/plugins/`, oppure installa lo ZIP generato in `dist/`. La radice del repository contiene gli strumenti di sviluppo e non va installata come plugin.
2. Attiva il plugin da **Plugin > Plugin installati**.
3. Completa il wizard da **WooCommerce > Configurazione Woo Legal Returns**.
4. Gestisci richieste, export e impostazioni da **WooCommerce > Resi UE**.
5. Per lo sviluppo, esegui `composer install` e poi `composer phpcs` per i controlli coding standard.

## Struttura file

```text
woo-legal/                            # Repository
|-- woo-legal/                        # Solo file installabili del plugin
|   |-- woo-legal-returns.php          # Entry point
|   |-- includes/                     # Classi PHP
|   |-- templates/                    # Template admin, account ed email
|   `-- assets/                       # CSS e JavaScript
|-- tests/                            # Test locali
|-- tools/package.ps1                 # Generazione ZIP dalla sola sottocartella
|-- dist/                             # Pacchetti installabili
|-- composer.json                     # Tooling sviluppo
|-- README.md
`-- CHANGELOG.md
```

## Shortcode disponibili

| Shortcode | Uso |
|---|---|
| `[wlr_precontractual_policy]` | Informativa completa sul diritto di recesso con dati venditore aggiornati, link alla procedura e modulo tipo, senza titoli H1–H6 |
| `[wlr_return_form]` | Mostra il form pubblico di recesso, con supporto ospiti tramite numero ordine + email |
| `[wlr_withdrawal_link]` | Mostra la procedura account per utenti registrati e la pagina pubblica per gli ospiti |
| `[wlr_checkout_notice]` | Mostra l'avviso checkout configurato nel wizard/impostazioni |
| `[wlr_withdrawal_notice id="123"]` | Mostra l'avviso di non recedibilita o consenso per un prodotto specifico |
| `[wlr_model_withdrawal_form]` | Modulo tipo stampabile con dati del venditore; nessuna rimozione automatica dei contenuti esistenti |

Nel primo passaggio del wizard puoi creare una nuova pagina oppure scegliere **Genera lo shortcode per una mia pagina**. Copia `[wlr_precontractual_policy]` nel blocco Shortcode di WordPress o nel widget Shortcode di Elementor: il testo riprende i dati venditore salvati nel wizard, senza creare o sovrascrivere pagine. Puoi associare una pagina pubblicata subito o tornare dopo averla realizzata; la pagina associata viene proposta per i menu e usata per i link dell'informativa. Se modifichi l'associazione, verifica anche il link nel testo personalizzato dell'avviso checkout.

La pagina generata e lo shortcode condividono il testo, con paragrafi, grassetto, elenchi e corsivo. Anche il modulo tipo usa il grassetto al posto degli heading. Le pagine generate nelle versioni precedenti conservano il contenuto salvato: per applicare il nuovo formato, selezionale nel wizard e richiedi esplicitamente l'aggiornamento del contenuto, oppure inserisci lo shortcode nella tua pagina.

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
| `wlr_deadline_mode` | Opzione legacy mantenuta a `strict`; il blocco è sempre attivo e non può essere disabilitato |
| `wlr_deadline_basis` | Decorrenza per i beni senza ricezione registrata: `completed_or_paid` oppure `created`; il blocco si applica anche a questa stima |
| `wlr_deadline_grace_days` | Giorni di tolleranza aggiunti al termine indicativo |
| `wlr_digital_consent_text` | Testo consenso contenuti digitali |
| `wlr_service_consent_text` | Testo consenso servizi |
| `wlr_auto_update_order_status` | Se `yes`, consente `refunded` soltanto con rimborso effettivo dell'intero importo; approvazione e rimborsi parziali conservano lo stato globale |

## Override template

I template possono essere sovrascritti dal tema creando la cartella:

```text
wp-content/themes/[tema]/woo-legal-returns/
```

e copiando i file da `woo-legal/templates/` del repository mantenendo la stessa struttura.

## Conformita normativa

- **Direttiva UE 2011/83/UE come modificata dalla Direttiva 2023/2673**: funzione digitale di recesso (Art. 54-bis)
- **D.Lgs. 209/2025 (art. 54-bis Codice del Consumo)**: conferma immediata, doppia conferma e testo completo dichiarazione
- **Finestra 14 giorni**: diritto di recesso senza motivazione dalla ricezione dei beni
- **Rimborso entro 14 giorni**: dalla comunicazione del recesso; per i beni può essere trattenuto fino alla ricezione o alla prova della spedizione, se precedente, salvo ritiro offerto dal venditore.

## Aggiornamento alla versione 1.5.4

- Lo shortcode dell'informativa restituisce solo un frammento HTML con paragrafi, elenchi, grassetto, corsivo e link. Non esegue il rendering dei blocchi WordPress e non aggiunge i loro marcatori, classi o stili.
- Il solo contenitore aggiuntivo è il box del modulo tipo, con bordo, fondo e spaziatura interna. Font, dimensioni del testo, margini e impaginazione restano gestiti dal tema o dal widget della pagina.

## Aggiornamento alla versione 1.5.3

- Modulo tipo separato dal resto dell'informativa tramite un box, con istruzioni che lo identificano come alternativa alla procedura online: non occorre inviarlo se la richiesta è già stata presentata dall'area Resi e Recesso.
- Email e telefoni cliccabili nell'informativa e nel modulo tipo. I numeri conservano spazi e prefisso nel testo, con destinazione `tel:` normalizzata.
- Gli shortcode mostrano subito il nuovo formato dopo l'aggiornamento del plugin; per i recapiti nel testo di una pagina generata in precedenza occorre rigenerarne esplicitamente il contenuto dal wizard.

## Aggiornamento alla versione 1.5.2

- Scelta nel wizard tra creazione della pagina e shortcode `[wlr_precontractual_policy]`, con associazione facoltativa alla pagina creata dall'utente.
- Informativa e modulo tipo senza heading H1–H6; dati venditore aggiornati a ogni visualizzazione dello shortcode.
- Pagine e layout esistenti conservati; aggiornamento dei testi statici solo su richiesta esplicita nel wizard.

## Aggiornamento alla versione 1.5.1

- Il blocco oltre termine è sempre attivo, anche se la vecchia configurazione prevedeva la verifica manuale. Non è più disponibile un'opzione per consentire gli invii dopo la scadenza.
- Ordini scaduti esclusi dai moduli account/ospiti e rifiutati lato server sia nel riepilogo sia nella conferma finale. L'assenza dei flag di informativa/calendario verificati non permette di superare il blocco. Senza alcuna data disponibile la richiesta richiede assistenza del venditore.
- Come protezione aggiuntiva, una richiesta comunque registrata oltre termine passa automaticamente da **Richiesto** a **Rifiutato**, con nota nello storico e notifica al cliente. Il controllo avviene alla creazione, prima delle email ordinarie; WP-Cron recupera anche le richieste già presenti, ogni ora in blocchi di 100, con prosecuzioni a un minuto. La disattivazione rimuove questi eventi.
- Il rifiuto automatico confronta invio UTC e scadenza conservati nella richiesta, senza riscrivere dichiarazione o hash. Non rifiuta le richieste inviate in tempo, quelle già approvate/rimborsate/annullate o quelle prive di date valide da verificare manualmente. WP-Cron richiede traffico sul sito o un cron esterno.
- Per i beni si privilegia la data effettiva di ricezione; in sua assenza si usa la decorrenza configurata. Questa resta una stima: registrare la ricezione e verificare eventuali termini prolungati per informativa mancante prima di applicare un rifiuto. La data di completamento/pagamento non dimostra la consegna.
- Il motivo del **recesso** resta facoltativo. Quando assente, dettaglio, elenchi, riepilogo ed email mostrano **Non indicato (facoltativo)**. Le dichiarazioni storiche e i relativi hash non vengono riscritti.
- Per passare a **Rifiutato** inserire una nota con il motivo del rifiuto: il modulo lo indica e la risposta AJAX distingue nota mancante, ordine/richiesta non validi, operazione occupata e salvataggio non riuscito. Lo stato salvato viene verificato prima di registrare lo storico e inviare la notifica.
- **Elimina** è disponibile nell'elenco e nel dettaglio: sposta la richiesta nel cestino di **Resi UE**, da cui si può ripristinarla o usare **Elimina definitivamente**. La cancellazione definitiva rimuove anche i metadati della richiesta; l'ordine WooCommerce resta presente. Entrambe le azioni richiedono permessi amministrativi, nonce e conferma, verificano l'esito e annullano i tentativi di invio ricevuta. Se WordPress ha il cestino disattivato viene proposta esplicitamente la cancellazione definitiva.
- Aggiornare gli eventuali override del tema per visualizzare il motivo facoltativo e l'indicazione della nota obbligatoria.

## Gestione delle richieste

- I resi parziali lasciano disponibili le quantità residue. Richieste aperte/approvate e rimborsi WooCommerce impegnano le quantità; dopo il rimborso effettivo aggiornare il relativo reso a **Rimborsata**.
- Le nuove righe ordine conservano regola di recesso, tipo di contratto e SKU. Gli ordini precedenti usano il catalogo corrente quando presente: non è possibile ricostruire retroattivamente le regole acquistate. Un prodotto eliminato non impedisce automaticamente il recesso.
- Nel dettaglio ordine, **Recesso: fatti verificati**, registrare ricezione effettiva, informativa completa fornita prima del contratto, festività applicabili e verifica del calendario. Questi elementi distinguono una scadenza verificata da una stima; in modalità strict entrambe vengono applicate. Sabati, domeniche e festività indicate rinviano la scadenza al successivo giorno lavorativo, fino alle 23:59:59 nel fuso del sito.
- Per escludere il digitale occorrono consenso, esecuzione iniziata e conferma fornita su supporto durevole. Per i servizi occorrono consenso con riconoscimento della perdita del diritto e piena esecuzione; per sigilli/beni mescolati occorre il fatto verificato. I soli flag virtuale/scaricabile non sono esclusioni.
- La dichiarazione confermata conserva nome, email, ordine, articoli, motivo facoltativo, note, destinatario e timestamp UTC. La ricevuta usa questo snapshot. SHA-256 rileva una modifica del contenuto se confrontato con il valore originale; non impedisce a un amministratore di modificare il database.
- Le ricevute fallite conservano un diagnostico, hanno fino a tre tentativi automatici tramite WP-Cron e possono essere reinviate dal dettaglio richiesta. Un invio accettato dal servizio email non prova la consegna alla casella.
- La conferma è associata alla sessione; lo stesso token restituisce lo stesso ID dopo una risposta persa. I tentativi sono serializzati per ordine. Il modulo richiede JavaScript e cookie di sessione per gli ospiti; i canali alternativi indicati nell'informativa restano disponibili.
- Le richieste cliente e gli ordini sono paginati; il CSV viene scritto per blocchi di 200 record. La cancellazione privacy usa un elenco stabile con scadenza di un giorno e segnala gli elementi non eliminabili. L'erasure dei consensi segue l'autorizzazione/conservazione degli ordini stabilita dalle impostazioni privacy WooCommerce.
- Gli override del tema vanno riallineati ai template 1.5.0 per nome obbligatorio, motivo facoltativo e ricevuta completa. Il wizard aggiorna una pagina esistente soltanto con la checkbox esplicitamente selezionata.

## Verifiche di sviluppo

```text
php tests/regression.php
php tests/precontractual.php
php tests/checkout-consents.php
php tests/updater-package.php
node --check woo-legal/assets/js/wlr-frontend.js
node --check woo-legal/assets/js/wlr-admin.js
```

Per generare il pacchetto installabile con i soli file del plugin:

```powershell
powershell -NoProfile -File tools/package.ps1
```

Le prove locali usano dipendenze simulate e metodi reali del plugin, compreso l'endpoint AJAX di aggiornamento stato in processi separati. Non sostituiscono le verifiche WordPress/WooCommerce, browser, HPOS, invio email e concorrenza su un database reale.

Il plugin supporta la gestione tecnica del recesso. La conformità del negozio dipende anche dai fatti, dai testi e dai processi del venditore. Riferimenti: [Direttiva 2011/83/UE](https://eur-lex.europa.eu/legal-content/IT/ALL/?uri=celex%3A32011L0083), [termini di calendario, Regolamento 1182/71](https://eur-lex.europa.eu/legal-content/IT/TXT/PDF/?uri=CELEX%3A31971R1182), [Additional Checkout Fields WooCommerce](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/additional-checkout-fields/).
