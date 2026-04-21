<?php
/**
 * Plugin Name: Mavo Barcelona – Calculateur de visites
 * Plugin URI:  https://www.mamanvoyage.com/
 * Description: Calculateur interactif du coût des visites à Barcelone. Utilisez le shortcode [barcelona_calc] sur la page de votre choix.
 * Version:     1.0.0
 * Author:      Maman Voyage
 * License:     GPL-2.0-or-later
 * Text Domain: mavo-barcelona
 */

defined( 'ABSPATH' ) || exit;

/* -----------------------------------------------------------------------
 * 1. Enqueue assets — only on pages that contain the shortcode
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

	// Pass price data and texts to JS
	wp_localize_script(
		'mavo-barcelona-calc',
		'bcData',
		mavo_barcelona_load_data()
	);
}

/* -----------------------------------------------------------------------
 * 2. Load price data from JSON file
 * --------------------------------------------------------------------- */
function mavo_barcelona_load_data() {
	$json_file = plugin_dir_path( __FILE__ ) . 'data/prices.json';
	if ( ! file_exists( $json_file ) ) {
		return [];
	}
	$json    = file_get_contents( $json_file );
	$decoded = json_decode( $json, true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return [];
	}
	return $decoded;
}

/* -----------------------------------------------------------------------
 * 3. Shortcode [barcelona_calc]
 * --------------------------------------------------------------------- */
add_shortcode( 'barcelona_calc', 'mavo_barcelona_shortcode' );
function mavo_barcelona_shortcode() {
	$data = mavo_barcelona_load_data();
	if ( empty( $data ) ) {
		return '<p>Erreur : données de prix introuvables.</p>';
	}

	$texts  = $data['texts']  ?? [];
	$ages   = $data['ages']   ?? [];
	$venues = $data['venues'] ?? [];

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
			<table class="bc-ages-table" aria-label="Saisie des âges">
				<thead>
					<tr>
						<th scope="col">#</th>
						<th scope="col"><?php echo esc_html( $texts['age_col_label'] ?? 'Âge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Default: persons 1-2 = '30-99', persons 3-6 = '–'
					$defaults = [ '30-99', '30-99', '–', '–', '–', '–' ];
					for ( $i = 1; $i <= 6; $i++ ) :
						$default_age = $defaults[ $i - 1 ];
						?>
						<tr>
							<td class="bc-member-num"><?php echo $i; ?></td>
							<td>
								<select class="bc-age-select" data-member="<?php echo $i; ?>" aria-label="Âge du membre <?php echo $i; ?>">
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
			<table class="bc-visits-table" aria-label="Visites et coûts">
				<thead>
					<tr>
						<th scope="col" class="bc-col-venue">Visite</th>
						<th scope="col" class="bc-col-type"><?php echo esc_html( $texts['visits_type_col'] ?? 'Type de visite' ); ?></th>
						<th scope="col" class="bc-col-price"><?php echo esc_html( $texts['visits_price_col'] ?? 'Prix total' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $venues as $idx => $venue ) :
						$vid          = esc_attr( $venue['id'] );
						$visit_types  = $venue['visitTypes'] ?? [];
						$tooltip_text = $venue['tooltip']    ?? '';
						$footnote     = $venue['footnote']   ?? '';
						// First venue defaults to 'visite simple', all others to '–'
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
								<?php if ( $footnote ) : ?>
									<span class="bc-footnote-marker"><?php echo esc_html( $footnote ); ?></span>
								<?php endif; ?>
							</td>
							<td class="bc-visit-type-cell">
								<select class="bc-visit-select" data-venue="<?php echo $vid; ?>" aria-label="Type de visite – <?php echo esc_attr( $venue['name'] ); ?>">
									<?php foreach ( $visit_types as $vtype ) : ?>
										<option value="<?php echo esc_attr( $vtype ); ?>"
											<?php selected( $vtype, $default_type ); ?>>
											<?php echo esc_html( $vtype ); ?>
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
										aria-label="Informations tarifaires"
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
