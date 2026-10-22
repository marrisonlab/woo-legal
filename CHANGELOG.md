# Changelog

## 1.5.3 - 2026-10-22

- Modulo tipo di recesso racchiuso in un box distinto, con spiegazione della sua funzione alternativa alla procedura online e senza duplicare la richiesta già inviata.
- Email e numeri di telefono cliccabili tramite `mailto:` e `tel:` in tutti i recapiti dell'informativa e del modulo tipo; mantenuta la formattazione leggibile dei numeri.

## 1.5.2 - 2026-10-22

- Aggiunta nel wizard la scelta dello shortcode `[wlr_precontractual_policy]` per inserire l'informativa completa in pagine WordPress o Elementor, mantenendo la creazione della pagina e l'associazione a pagine esistenti.
- Shortcode basato sullo stesso testo della pagina generata e sui dati venditore salvati, senza creazione o modifica automatica di pagine; associazione facoltativa di una pagina pubblicata per menu e link informativi.
- Sostituiti gli heading dell'informativa e del modulo tipo con paragrafi in grassetto; mantenuti elenchi, corsivo e collegamento alla procedura di recesso, senza stile pulsante nell'informativa.
- Conservati i contenuti delle pagine già create; aggiornamento disponibile tramite l'opzione esplicita del wizard. Nessuna voce menu vuota quando la pagina non è ancora associata.

## 1.5.1 - 2026-10-07

- Reso obbligatorio il blocco oltre termine per nuove installazioni e aggiornamenti, rimuovendo la modalità che consentiva invii scaduti.
- Applicato il termine anche agli ordini senza flag di attendibilità, usando la ricezione registrata o la decorrenza configurata. Bloccati anche gli ordini senza alcuna data; una ricezione non valida usa la decorrenza alternativa.
- Conservati tolleranza, festività, proroga al giorno lavorativo e controllo server nella conferma finale.
- Aggiunto il rifiuto automatico delle richieste pendenti inviate dopo la scadenza salvata, con nota e notifica cliente: controllo immediato alla creazione e recupero tramite WP-Cron in blocchi di 100. Conservate le richieste inviate nei termini e gli stati già gestiti.
- Motivo del recesso ancora facoltativo, visualizzato come “Non indicato (facoltativo)” quando assente, anche nelle email storiche senza modificare le dichiarazioni originali.
- Rifiuto con nota al cliente obbligatoria indicata nel modulo; errori AJAX specifici per nota mancante, operazione occupata, dati non validi e salvataggio fallito.
- Verificato lo stato effettivamente salvato prima di storico e notifica, mantenendo compatibile l'API booleana di aggiornamento stato.
- Aggiunta l'azione Elimina nell'elenco e nel dettaglio e la cancellazione definitiva dal cestino, con verifica dell'esito, protezione dell'ordine WooCommerce e annullamento delle ricevute pianificate. Gestita esplicitamente la disattivazione del cestino WordPress.
- Aggiunte regressioni locali per scadenze, conferma finale, clienti/ospiti e aggiornamento stato/AJAX.
