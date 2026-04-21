<?php
/**
 * Plugin Name: Mavo Barcelona – Calculateur de visites
 * Plugin URI:  https://www.mamanvoyage.com/
 * Description: Calculateur interactif du coût des visites à Barcelone. Utilisez le shortcode [barcelona_calc] sur la page de votre choix.
 * Version:     1.1.0
 * Author:      Maman Voyage
 * License:     GPL-2.0-or-later
 * Text Domain: mavo-barcelona
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * 1. Data loading helpers
 * --------------------------------------------------------------------- */

/** Load prices.json (language-agnostic: IDs, price matrices, URLs). */
function mavo_barcelona_load_prices() {
	$file = plugin_dir_path( __FILE__ ) . 'data/prices.json';
	if ( ! file_exists( $file ) ) return [];
	$data = json_decode( file_get_contents( $file ), true );
	return ( json_last_error() === JSON_ERROR_NONE ) ? $data : [];
}

/**
 * Detect the active 2-letter language code.
 * Uses Polylang if available; falls back to the WordPress locale.
 */
function mavo_barcelona_lang_code() {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();
		if ( $lang ) return $lang;
	}
	// Fallback: take first two characters of WP locale (e.g. 'fr_FR' → 'fr')
	return substr( get_locale(), 0, 2 ) ?: 'fr';
}

/**
 * Load the language file for the active (or given) language code.
 * Falls back to fr.json if the requested file is missing.
 */
function mavo_barcelona_load_lang( $code = null ) {
	if ( ! $code ) $code = mavo_barcelona_lang_code();
	$base = plugin_dir_path( __FILE__ ) . 'data/lang/';
	foreach ( [ $code, 'fr' ] as $try ) {
		$file = $base . $try . '.json';
		if ( file_exists( $file ) ) {
			$data = json_decode( file_get_contents( $file ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) return $data;
		}
	}
	return [];
}

/**
 * Build the minimal data payload sent to JavaScript.
 * Only canonical venue IDs, price matrices, family-ticket prices,
 * and the one string the JS needs to render (family_ticket_note).
 */
function mavo_barcelona_js_data() {
	$prices = mavo_barcelona_load_prices();
	$lang   = mavo_barcelona_load_lang();
	if ( empty( $prices ) || empty( $lang ) ) return [];

	$venues_js = [];
	foreach ( $prices['venues'] ?? [] as $venue ) {
		$entry = [
			'id'     => $venue['id'],
			'prices' => $venue['prices'] ?? [],
		];
		if ( ! empty( $venue['familyTicket'] ) ) {
			$entry['familyTicket'] = $venue['familyTicket'];
		}
		$venues_js[] = $entry;
	}

	return [
		'texts'  => [
			'family_ticket_note' => $lang['texts']['family_ticket_note'] ?? 'billet famille appliqué',
		],
		'venues' => $venues_js,
	];
}

/* -----------------------------------------------------------------------
 * 2. Enqueue assets — only on pages that contain the shortcode
 * --------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'mavo_barcelona_enqueue' );
function mavo_barcelona_enqueue() {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'barcelona_calc' ) ) {
		return;
	}

	$plugin_url  = plugin_dir_url( __FILE__ );
	$plugin_path = plugin_dir_path( __FILE__ );

	wp_enqueue_style(
		'mavo-barcelona-calc',
		$plugin_url . 'assets/css/barcelona-calc.css',
		[],
		filemtime( $plugin_path . 'assets/css/barcelona-calc.css' )
	);

	wp_enqueue_script(
		'mavo-barcelona-calc',
		$plugin_url . 'assets/js/barcelona-calc.js',
		[],
		filemtime( $plugin_path . 'assets/js/barcelona-calc.js' ),
		true  // load in footer
	);

	// Pass minimal price data + current-language string to JS
	wp_localize_script( 'mavo-barcelona-calc', 'bcData', mavo_barcelona_js_data() );
}

/* -----------------------------------------------------------------------
 * 3. Shortcode [barcelona_calc]
 * --------------------------------------------------------------------- */
add_shortcode( 'barcelona_calc', 'mavo_barcelona_shortcode' );
function mavo_barcelona_shortcode() {
	$prices = mavo_barcelona_load_prices();
	$lang   = mavo_barcelona_load_lang();

	if ( empty( $prices ) || empty( $lang ) ) {
		return '<p>Erreur : données introuvables.</p>';
	}

	$texts    = $lang['texts']            ?? [];
	$vt_labels = $lang['visitTypeLabels'] ?? [];
	$ages     = $prices['ages']           ?? [];
	$venues   = $prices['venues']         ?? [];

	// Build tooltip lookup indexed by venue ID from the language file
	$tooltips = [];
	foreach ( $lang['venues'] ?? [] as $lv ) {
		if ( ! empty( $lv['id'] ) ) {
			$tooltips[ $lv['id'] ] = $lv['tooltip'] ?? '';
		}
	}

	ob_start();
	?>
	<div class="bc-wrap" id="bc-calculator">

		<!-- ── Header ─────────────────────────────────────────────── -->
		<div class="bc-header">
			<h2 class="bc-title"><?php echo esc_html( $texts['title'] ?? '' ); ?></h2>
			<p class="bc-intro"><?php echo esc_html( $texts['intro_1'] ?? '' ); ?></p>
			<p class="bc-intro"><?php echo esc_html( $texts['intro_2'] ?? '' ); ?></p>
		</div>

		<!-- ── Ages section ───────────────────────────────────────── -->
		<section class="bc-ages-section">
			<h3 class="bc-section-title"><?php echo esc_html( $texts['ages_section_label'] ?? 'Âges' ); ?></h3>
			<p class="bc-age-note"><?php echo esc_html( $texts['age_note'] ?? '' ); ?></p>
			<table class="bc-ages-table" aria-label="<?php echo esc_attr( $texts['aria_ages_table'] ?? 'Saisie des âges' ); ?>">
				<thead>
					<tr>
						<th scope="col">#</th>
						<th scope="col"><?php echo esc_html( $texts['age_col_label'] ?? 'Âge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Default: persons 1–2 = '30-99', persons 3–6 = '–'
					$defaults = [ '30-99', '30-99', '–', '–', '–', '–' ];
					for ( $i = 1; $i <= 6; $i++ ) :
						$default_age = $defaults[ $i - 1 ];
						?>
						<tr>
							<td class="bc-member-num"><?php echo $i; ?></td>
							<td>
								<select class="bc-age-select" data-member="<?php echo $i; ?>"
									aria-label="<?php echo esc_attr( ( $texts['aria_age_member'] ?? 'Âge du membre' ) . ' ' . $i ); ?>">
									<?php foreach ( $ages as $age ) : ?>
										<option value="<?php echo esc_attr( $age ); ?>"
											<?php selected( (string) $age, $default_age ); ?>>
											<?php echo esc_html( $age ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endfor; ?>
				</tbody>
			</table>
		</section>

		<!-- ── Disclaimers ────────────────────────────────────────── -->
		<div class="bc-disclaimers">
			<p><?php echo esc_html( $texts['disclaimer_online']    ?? '' ); ?></p>
			<p><?php echo esc_html( $texts['disclaimer_discounts'] ?? '' ); ?></p>
			<p><?php echo esc_html( $texts['disclaimer_update']    ?? '' ); ?></p>
		</div>

		<!-- ── Visits table ───────────────────────────────────────── -->
		<section class="bc-visits-section">
			<table class="bc-visits-table" aria-label="<?php echo esc_attr( $texts['aria_visits_table'] ?? 'Visites et coûts' ); ?>">
				<thead>
					<tr>
						<th scope="col" class="bc-col-venue"><?php echo esc_html( $texts['visits_col_label'] ?? 'Visite' ); ?></th>
						<th scope="col" class="bc-col-type"><?php echo esc_html( $texts['visits_type_col'] ?? 'Type de visite' ); ?></th>
						<th scope="col" class="bc-col-price"><?php echo esc_html( $texts['visits_price_col'] ?? 'Prix total' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $venues as $idx => $venue ) :
						$vid          = esc_attr( $venue['id'] );
						$visit_types  = $venue['visitTypes'] ?? [];
						$tooltip_text = $tooltips[ $venue['id'] ] ?? '';
						// First venue defaults to its first visit type; all others default to '–'
						$default_type = ( $idx === 0 ) ? ( $visit_types[0] ?? '–' ) : '–';
						?>
						<tr class="bc-venue-row" data-venue="<?php echo $vid; ?>">
							<td class="bc-venue-name">
								<?php
								$venue_url = ! empty( $venue['url'] ) ? esc_url( $venue['url'] ) : '';
								if ( $venue_url ) : ?>
									<a href="<?php echo $venue_url; ?>" target="_blank" rel="noopener noreferrer" class="bc-venue-link"><?php echo esc_html( $venue['name'] ); ?></a>
								<?php else :
									echo esc_html( $venue['name'] );
								endif; ?>
								<?php if ( ! empty( $venue['footnote'] ) ) : ?>
									<span class="bc-footnote-marker"><?php echo esc_html( $venue['footnote'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bc-visit-type-cell">
								<select class="bc-visit-select" data-venue="<?php echo $vid; ?>"
									aria-label="<?php echo esc_attr( ( $texts['aria_visit_type'] ?? 'Type de visite' ) . ' – ' . $venue['name'] ); ?>">
									<?php foreach ( $visit_types as $vt_id ) :
										$vt_label = $vt_labels[ $vt_id ] ?? $vt_id;
										?>
										<option value="<?php echo esc_attr( $vt_id ); ?>"
											<?php selected( $vt_id, $default_type ); ?>>
											<?php echo esc_html( $vt_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td class="bc-price-cell">
								<span class="bc-price-output" data-venue="<?php echo $vid; ?>">–</span>
								<?php if ( $tooltip_text ) : ?>
									<button
										class="bc-tooltip-btn"
										type="button"
										aria-label="<?php echo esc_attr( $texts['aria_price_info'] ?? 'Informations tarifaires' ); ?>"
										data-tooltip="<?php echo esc_attr( $tooltip_text ); ?>">
										<span aria-hidden="true">ℹ</span>
									</button>
									<div class="bc-tooltip-box" role="tooltip">
										<?php echo nl2br( esc_html( $tooltip_text ) ); ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr class="bc-total-row">
						<td colspan="2" class="bc-total-label">
							<?php echo esc_html( $texts['total_label'] ?? 'Total' ); ?>
						</td>
						<td class="bc-total-price-cell">
							<span class="bc-total-output">–</span>
						</td>
					</tr>
				</tfoot>
			</table>
		</section>

		<!-- ── Footnotes ──────────────────────────────────────────── -->
		<div class="bc-footnotes">
			<p><?php echo esc_html( $texts['footnote_star'] ?? '' ); ?></p>
			<p>
				<?php
				$fn2     = $texts['footnote_star2']     ?? '';
				$fn2_url = ! empty( $texts['footnote_star2_url'] ) ? esc_url( $texts['footnote_star2_url'] ) : '';
				if ( $fn2_url ) :
					echo '<a href="' . $fn2_url . '" target="_blank" rel="noopener noreferrer" class="bc-footnote-link">' . esc_html( $fn2 ) . '</a>';
				else :
					echo esc_html( $fn2 );
				endif;
				?>
			</p>
		</div>

	</div><!-- .bc-wrap -->
	<?php
	return ob_get_clean();
}
