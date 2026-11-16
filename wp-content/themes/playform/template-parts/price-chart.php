<?php
/**
 * Prishistorik som SVG. $args['history'] = [ [ 'day' => 'Y-m-d', 'price' => float ], … ]
 */
defined( 'ABSPATH' ) || exit;
$h = $args['history'] ?? [];
if ( count( $h ) < 2 ) {
	return;
}
$prices = array_column( $h, 'price' );
$min    = min( $prices );
$max    = max( $prices );
$now    = end( $prices );
$pad    = max( 1, ( $max - $min ) * 0.15 );
$lo     = $min - $pad;
$hi     = $max + $pad;
$w      = 560;
$top    = 16;
$bottom = 168;
$left   = 56;
$n      = count( $h );
$x      = static fn( int $i ) => $left + ( $w - $left ) * ( $n > 1 ? $i / ( $n - 1 ) : 0 );
$y      = static fn( float $p ) => $bottom - ( $bottom - $top ) * ( ( $p - $lo ) / max( 1, $hi - $lo ) );
// Trappekurve: prisen holder, indtil den ændrer sig.
$pts = [];
foreach ( $h as $i => $row ) {
	if ( $i > 0 ) {
		$pts[] = round( $x( $i ), 1 ) . ',' . round( $y( $h[ $i - 1 ]['price'] ), 1 );
	}
	$pts[] = round( $x( $i ), 1 ) . ',' . round( $y( $row['price'] ), 1 );
}
$min_i = array_search( $min, $prices, true );
$max_i = array_search( $max, $prices, true );
$first = (float) $prices[0];
// Status for prisen nu: laveste (grøn), højeste (rød) eller midt imellem.
$state = 'mid';
if ( $min < $max ) {
	if ( $now <= $min ) {
		$state = 'low';
	} elseif ( $now >= $max ) {
		$state = 'high';
	}
}
$diff_pct = $first > 0 ? (int) round( ( $now - $first ) / $first * 100 ) : 0;
$label = sprintf( 'Prisudvikling fra %s til %s. Laveste pris %s, højeste %s.', wp_date( 'j. F', strtotime( $h[0]['day'] ) ), 'i dag', playform_kr( $min ), playform_kr( $max ) );
// Område under kurven.
$area = round( $x( 0 ), 1 ) . ',' . $bottom . ' ' . implode( ' ', $pts ) . ' ' . round( $x( $n - 1 ), 1 ) . ',' . $bottom;
?>
<div class="chart chart--<?php echo esc_attr( $state ); ?>">
	<?php if ( 'low' === $state ) : ?>
		<p class="chart__badge chart__badge--low">Laveste pris, vi har målt<?php echo $diff_pct < 0 ? esc_html( ' · −' . abs( $diff_pct ) . ' % siden ' . wp_date( 'j. M', strtotime( $h[0]['day'] ) ) ) : ''; ?></p>
	<?php elseif ( 'high' === $state ) : ?>
		<p class="chart__badge chart__badge--high">Højeste pris, vi har målt – den har været <?php echo esc_html( playform_kr( $max - $min ) ); ?> billigere</p>
	<?php elseif ( $min < $max ) : ?>
		<p class="chart__badge chart__badge--mid"><?php echo esc_html( playform_kr( $now - $min ) ); ?> over den laveste pris, vi har målt</p>
	<?php else : ?>
		<p class="chart__badge chart__badge--mid">Prisen har ligget stabilt</p>
	<?php endif; ?>
	<svg viewBox="0 0 <?php echo (int) $w; ?> 200" width="100%" role="img" aria-label="<?php echo esc_attr( $label ); ?>">
		<?php foreach ( [ $max, ( $max + $min ) / 2, $min ] as $gv ) : ?>
			<line x1="<?php echo (int) $left; ?>" x2="<?php echo (int) $w; ?>" y1="<?php echo esc_attr( round( $y( $gv ), 1 ) ); ?>" y2="<?php echo esc_attr( round( $y( $gv ), 1 ) ); ?>" class="chart__grid"></line>
			<text x="0" y="<?php echo esc_attr( round( $y( $gv ), 1 ) + 4 ); ?>" class="chart__label"><?php echo esc_html( number_format( $gv, 0, ',', '.' ) ); ?></text>
		<?php endforeach; ?>
		<polygon points="<?php echo esc_attr( $area ); ?>" class="chart__area"></polygon>
		<?php
		// Vandrette stykker i sort; prisfald grønt, prisstigninger rødt.
		foreach ( $h as $i => $row ) {
			if ( 0 === $i ) {
				continue;
			}
			$x0 = round( $x( $i - 1 ), 1 );
			$x1 = round( $x( $i ), 1 );
			$y0 = round( $y( $h[ $i - 1 ]['price'] ), 1 );
			$y1 = round( $y( $row['price'] ), 1 );
			echo '<line x1="' . esc_attr( $x0 ) . '" y1="' . esc_attr( $y0 ) . '" x2="' . esc_attr( $x1 ) . '" y2="' . esc_attr( $y0 ) . '" class="chart__seg"></line>';
			if ( $row['price'] != $h[ $i - 1 ]['price'] ) { // phpcs:ignore
				echo '<line x1="' . esc_attr( $x1 ) . '" y1="' . esc_attr( $y0 ) . '" x2="' . esc_attr( $x1 ) . '" y2="' . esc_attr( $y1 ) . '" class="chart__seg ' . ( $row['price'] < $h[ $i - 1 ]['price'] ? 'chart__seg--down' : 'chart__seg--up' ) . '"></line>';
			}
		}
		?>
		<?php if ( $min < $max ) : ?>
			<circle cx="<?php echo esc_attr( round( $x( (int) $max_i ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $max ), 1 ) ); ?>" r="4" class="chart__dot chart__dot--high"></circle>
			<circle cx="<?php echo esc_attr( round( $x( (int) $min_i ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $min ), 1 ) ); ?>" r="4" class="chart__dot chart__dot--low"></circle>
		<?php endif; ?>
		<circle cx="<?php echo esc_attr( round( $x( $n - 1 ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $now ), 1 ) ); ?>" r="6" class="chart__now"></circle>
		<text x="<?php echo (int) $left; ?>" y="196" class="chart__label"><?php echo esc_html( wp_date( 'j. M', strtotime( $h[0]['day'] ) ) ); ?></text>
		<text x="<?php echo (int) ( $w - 30 ); ?>" y="196" class="chart__label">I dag</text>
	</svg>
	<div class="chart__stats">
		<div class="chart__stat chart__stat--now"><span class="muted">Nu</span><b><?php echo esc_html( playform_kr( $now ) ); ?></b></div>
		<div class="chart__stat chart__stat--low"><span class="muted">Laveste</span><b><?php echo esc_html( playform_kr( $min ) ); ?></b></div>
		<div class="chart__stat chart__stat--high"><span class="muted">Højeste</span><b><?php echo esc_html( playform_kr( $max ) ); ?></b></div>
	</div>
</div>
