<?php
/**
 * Template: Pagina guida per l'amministratore.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap wlr-guide-wrap">
	<h1><?php esc_html_e( 'Guida – Gestione Resi UE', 'woo-legal-returns' ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a href="#wlr-tab-wizard" class="nav-tab nav-tab-active"><?php esc_html_e( 'Wizard Configurazione', 'woo-legal-returns' ); ?></a>
		<a href="#wlr-tab-exclusions" class="nav-tab"><?php esc_html_e( 'Esclusioni Prodotti', 'woo-legal-returns' ); ?></a>
		<a href="#wlr-tab-refunds" class="nav-tab"><?php esc_html_e( 'Gestione Rimborsi', 'woo-legal-returns' ); ?></a>
		<a href="#wlr-tab-statuses" class="nav-tab"><?php esc_html_e( 'Stati Richieste', 'woo-legal-returns' ); ?></a>
	</h2>

	<div class="wlr-guide-content" style="max-width: 900px; margin-top: 20px;">

		<!-- Tab 1: Wizard di configurazione -->
		<div id="wlr-tab-wizard" class="wlr-tab-content" style="display: block;">
			<h2><?php esc_html_e( 'Wizard di Configurazione Iniziale', 'woo-legal-returns' ); ?></h2>

			<p><?php esc_html_e( 'Il wizard di configurazione guidata è accessibile da WooCommerce → Resi UE → Configurazione. Può essere eseguito la prima volta dopo l\'installazione e rieseguito in qualsiasi momento per modificare le impostazioni.', 'woo-legal-returns' ); ?></p>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wlr-setup-wizard' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Avvia Wizard', 'woo-legal-returns' ); ?>
				</a>
			</p>

			<h3><?php esc_html_e( 'Passaggi del wizard', 'woo-legal-returns' ); ?></h3>

			<h4><?php esc_html_e( '1. Informativa Precontrattuale', 'woo-legal-returns' ); ?></h4>
			<p><?php esc_html_e( 'Crea o associa una pagina del sito contenente le informazioni obbligatorie sul diritto di recesso (artt. 49–51 D.Lgs. 206/2005, Direttiva 2023/2673).', 'woo-legal-returns' ); ?></p>
			<ul style="line-height: 1.6;">
				<li><?php esc_html_e( 'Crea una nuova pagina', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Il contenuto viene precompilato automaticamente con il testo standard aggiornato secondo il D.Lgs. 209/2025.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Usa una pagina esistente', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Seleziona una pagina già presente nel sito (utile se hai già una pagina informativa).', 'woo-legal-returns' ); ?></li>
			</ul>

			<h4><?php esc_html_e( '2. Menu di Navigazione', 'woo-legal-returns' ); ?></h4>
			<p><?php esc_html_e( 'Aggiunge automaticamente il link alla pagina informativa nei menu selezionati. Puoi saltare questo passaggio e aggiungere il link manualmente da Aspetto → Menu.', 'woo-legal-returns' ); ?></p>

			<h4><?php esc_html_e( '3. Avviso al Checkout', 'woo-legal-returns' ); ?></h4>
			<p><?php esc_html_e( 'Configura un avviso informativo che appare nella pagina di checkout, appena sopra il tasto "Conferma ordine". Raccomandato per la conformità all\'art. 49 D.Lgs. 206/2005.', 'woo-legal-returns' ); ?></p>
			<ul style="line-height: 1.6;">
				<li><?php esc_html_e( 'Mostra avviso', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Attiva o disattiva la visualizzazione dell\'avviso.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Testo avviso', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Personalizza il testo (supporta HTML di base). Il link "Maggiori informazioni" viene aggiornato automaticamente se cambi la pagina informativa nel wizard.', 'woo-legal-returns' ); ?></li>
			</ul>

			<p style="background: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; border-radius: 3px;">
				<?php esc_html_e( 'Nota: Puoi rieseguire il wizard in qualsiasi momento per modificare le impostazioni. Le modifiche sovrascrivono le configurazioni precedenti.', 'woo-legal-returns' ); ?>
			</p>
		</div>

		<!-- Tab 2: Esclusione prodotti -->
		<div id="wlr-tab-exclusions" class="wlr-tab-content" style="display: none;">
			<h2><?php esc_html_e( 'Escludere un prodotto dal diritto di recesso', 'woo-legal-returns' ); ?></h2>

			<p><?php esc_html_e( 'Per impostazione predefinita, tutti i prodotti fisici possono essere restituiti dal cliente entro 14 giorni dalla ricezione. Tuttavia, la normativa UE (art. 59 D.Lgs. 206/2005) prevede delle eccezioni.', 'woo-legal-returns' ); ?></p>

			<h3><?php esc_html_e( 'Come escludere un prodotto', 'woo-legal-returns' ); ?></h3>
			<ol style="line-height: 1.6;">
				<li><?php esc_html_e( 'Vai nel menu Prodotti e apri il prodotto da modificare.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Scorri fino alla sezione Dati prodotto e seleziona la scheda Diritto di recesso.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Spunta la casella Escludi dal diritto di recesso.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Seleziona la Motivazione esclusione appropriata tra quelle disponibili.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Salva le modifiche.', 'woo-legal-returns' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Casistiche di esclusione previste dalla legge', 'woo-legal-returns' ); ?></h3>
			<ul style="line-height: 1.6;">
				<li><?php esc_html_e( 'Bene confezionato su misura', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Prodotti personalizzati o realizzati su specifiche del cliente.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Bene deperibile', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Alimenti, bevande, prodotti cosmetici aperti o con scadenza breve.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Bene sigillato aperto', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Prodotti igienici o sanitari sigillati che sono stati aperti dopo la consegna.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Contenuto audio/video o software informatico sigillato aperto', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'CD, DVD, software con sigillo di garanzia aperto.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Giornali, periodici e riviste', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'Pubblicazioni non sigillate.', 'woo-legal-returns' ); ?></li>
				<li><?php esc_html_e( 'Servizi di fornitura di contenuto digitale non su supporto materiale', 'woo-legal-returns' ); ?> – <?php esc_html_e( 'E-book, download digitali, corsi online (il cliente rinuncia al recesso dopo aver accettato la fornitura).', 'woo-legal-returns' ); ?></li>
			</ul>

			<p style="background: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; border-radius: 3px;">
				<?php esc_html_e( 'Nota importante: I prodotti virtuali e scaricabili sono automaticamente esclusi dal plugin e non compaiono nel form di reso.', 'woo-legal-returns' ); ?>
			</p>
		</div>

		<!-- Tab 3: Gestione rimborsi -->
		<div id="wlr-tab-refunds" class="wlr-tab-content" style="display: none;">
			<h2><?php esc_html_e( 'Gestione dei rimborsi', 'woo-legal-returns' ); ?></h2>

			<p><?php esc_html_e( 'Il plugin Woo Legal Returns gestisce le richieste di recesso e il flusso di comunicazione con il cliente, ma non emette i rimborsi. I rimborsi devono essere effettuati tramite le funzionalità native di WooCommerce.', 'woo-legal-returns' ); ?></p>

			<h3><?php esc_html_e( 'Flusso di lavoro consigliato', 'woo-legal-returns' ); ?></h3>
			<ol style="line-height: 1.6;">
				<li>
					<?php esc_html_e( 'Ricezione della richiesta', 'woo-legal-returns' ); ?>
					<br><?php esc_html_e( 'Il cliente invia la richiesta tramite il form nella pagina "Il mio account". Tu ricevi una notifica email e vedi la richiesta nella lista WooCommerce → Resi UE.', 'woo-legal-returns' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Valutazione della richiesta', 'woo-legal-returns' ); ?>
					<br><?php esc_html_e( 'Apri il dettaglio della richiesta per verificare i prodotti, il motivo e le note del cliente. Controlla che i prodotti siano effettivamente restituibili (non esclusi, non danneggiati, ecc.).', 'woo-legal-returns' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Approvazione (accettata)', 'woo-legal-returns' ); ?>
					<br><?php esc_html_e( 'Se decidi di accettare la richiesta, cambia lo stato in Accettata. Questo:', 'woo-legal-returns' ); ?>
					<ul style="margin-top: 8px; margin-bottom: 8px;">
						<li><?php esc_html_e( 'Invia automaticamente una email al cliente confermando l\'accettazione.', 'woo-legal-returns' ); ?></li>
						<li><?php esc_html_e( 'Sposta l\'ordine WooCommerce in stato In sospeso (on-hold) per bloccare ulteriori elaborazioni.', 'woo-legal-returns' ); ?></li>
					</ul>
				</li>
				<li>
					<?php esc_html_e( 'Emissione del rimborso tramite WooCommerce', 'woo-legal-returns' ); ?>
					<br><?php esc_html_e( 'Vai nell\'ordine WooCommerce (WooCommerce → Ordini) e usa il pulsante Rimborsa di WooCommerce per emettere il rimborso effettivo. Questo è obbligatorio perché il plugin non ha accesso ai gateway di pagamento.', 'woo-legal-returns' ); ?>
				</li>
				<li>
					<?php esc_html_e( 'Chiusura della richiesta (rimborsata)', 'woo-legal-returns' ); ?>
					<br><?php esc_html_e( 'Dopo aver emesso il rimborso in WooCommerce, torna nella lista Resi UE, apri la richiesta e cambia lo stato in Rimborsata. Questo:', 'woo-legal-returns' ); ?>
					<ul style="margin-top: 8px; margin-bottom: 8px;">
						<li><?php esc_html_e( 'Invia una email al cliente confermando che il rimborso è stato effettuato.', 'woo-legal-returns' ); ?></li>
						<li><?php esc_html_e( 'Sposta l\'ordine WooCommerce in stato Rimborsato (refunded).', 'woo-legal-returns' ); ?></li>
					</ul>
				</li>
			</ol>

			<p style="background: #f0f6fc; padding: 12px; border-left: 4px solid #2271b1; border-radius: 3px;">
				<?php esc_html_e( 'Importante: Lo stato "Rimborsata" di Woo Legal Returns non è collegato allo stato dell\'ordine WooCommerce. Devi impostarlo manualmente dopo aver effettuato il rimborso in WooCommerce. Questo garantisce flessibilità e compatibilità con tutti i gateway di pagamento.', 'woo-legal-returns' ); ?>
			</p>

			<h3><?php esc_html_e( 'Rifiuto della richiesta', 'woo-legal-returns' ); ?></h3>
			<p><?php esc_html_e( 'Se decidi di rifiutare la richiesta (es. prodotto danneggiato, fuori tempo, ecc.), cambia lo stato in Rifiutata. Il cliente riceverà una email di notifica con il motivo del rifiuto. L\'ordine WooCommerce non viene modificato.', 'woo-legal-returns' ); ?></p>
		</div>

		<!-- Tab 4: Riepilogo stati -->
		<div id="wlr-tab-statuses" class="wlr-tab-content" style="display: none;">
			<h2><?php esc_html_e( 'Riepilogo stati delle richieste', 'woo-legal-returns' ); ?></h2>
			<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
				<thead>
					<tr>
						<th style="padding: 10px;"><?php esc_html_e( 'Stato', 'woo-legal-returns' ); ?></th>
						<th style="padding: 10px;"><?php esc_html_e( 'Descrizione', 'woo-legal-returns' ); ?></th>
						<th style="padding: 10px;"><?php esc_html_e( 'Effetto su ordine WooCommerce', 'woo-legal-returns' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td style="padding: 10px;"><?php esc_html_e( 'In attesa', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Richiesta appena ricevuta, in attesa di valutazione.', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Nessuno', 'woo-legal-returns' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 10px;"><?php esc_html_e( 'Accettata', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Richiesta approvata, in attesa di restituzione merce.', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Ordine → In sospeso (on-hold)', 'woo-legal-returns' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 10px;"><?php esc_html_e( 'Rimborsata', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Rimborso emesso, richiesta chiusa.', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Ordine → Rimborsato (refunded)', 'woo-legal-returns' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 10px;"><?php esc_html_e( 'Rifiutata', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Richiesta rifiutata dal venditore.', 'woo-legal-returns' ); ?></td>
						<td style="padding: 10px;"><?php esc_html_e( 'Nessuno', 'woo-legal-returns' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

	</div>

	<script>
	jQuery( function( $ ) {
		$( '.nav-tab' ).on( 'click', function( e ) {
			e.preventDefault();
			var target = $( this ).attr( 'href' ).substring( 1 );
			$( '.nav-tab' ).removeClass( 'nav-tab-active' );
			$( this ).addClass( 'nav-tab-active' );
			$( '.wlr-tab-content' ).hide();
			$( '#' + target ).show();
		} );
	} );
	</script>
</div>
