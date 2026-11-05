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
$label = sprintf( 'Prisudvikling fra %s til %s. Laveste pris %s, højeste %s.', wp_date( 'j. F', strtotime( $h[0]['day'] ) ), 'i dag', playform_kr( $min ), playform_kr( $max ) );
?>
<div class="chart">
	<svg viewBox="0 0 <?php echo (int) $w; ?> 200" width="100%" role="img" aria-label="<?php echo esc_attr( $label ); ?>">
		<?php foreach ( [ $max, ( $max + $min ) / 2, $min ] as $gv ) : ?>
			<line x1="<?php echo (int) $left; ?>" x2="<?php echo (int) $w; ?>" y1="<?php echo esc_attr( round( $y( $gv ), 1 ) ); ?>" y2="<?php echo esc_attr( round( $y( $gv ), 1 ) ); ?>" class="chart__grid"></line>
			<text x="0" y="<?php echo esc_attr( round( $y( $gv ), 1 ) + 4 ); ?>" class="chart__label"><?php echo esc_html( number_format( $gv, 0, ',', '.' ) ); ?></text>
		<?php endforeach; ?>
		<polyline points="<?php echo esc_attr( implode( ' ', $pts ) ); ?>" class="chart__line"></polyline>
		<circle cx="<?php echo esc_attr( round( $x( $n - 1 ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $now ), 1 ) ); ?>" r="5" class="chart__now"></circle>
		<?php if ( $min < $max ) : ?><circle cx="<?php echo esc_attr( round( $x( (int) $min_i ), 1 ) ); ?>" cy="<?php echo esc_attr( round( $y( $min ), 1 ) ); ?>" r="4" class="chart__min"></circle><?php endif; ?>
		<text x="<?php echo (int) $left; ?>" y="196" class="chart__label"><?php echo esc_html( wp_date( 'j. M', strtotime( $h[0]['day'] ) ) ); ?></text>
		<text x="<?php echo (int) ( $w - 30 ); ?>" y="196" class="chart__label">I dag</text>
	</svg>
	<div class="chart__stats">
		<div><span class="muted">Nu</span><b><?php echo esc_html( playform_kr( $now ) ); ?></b></div>
		<div><span class="muted">Laveste</span><b><?php echo esc_html( playform_kr( $min ) ); ?></b></div>
		<div><span class="muted">Højeste</span><b><?php echo esc_html( playform_kr( $max ) ); ?></b></div>
	</div>
</div>
