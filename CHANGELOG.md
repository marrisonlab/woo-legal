# Changelog

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
