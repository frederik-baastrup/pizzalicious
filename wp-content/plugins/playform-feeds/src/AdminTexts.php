<?php
namespace Playform\Feeds;

/**
 * Godkendelse af AI-tekster: en boks på produktsiden i admin og en oversigt over udkast,
 * der venter (Produkter › Tekster til godkendelse).
 */
final class AdminTexts {

	public static function register(): void {
		add_action( 'add_meta_boxes_product', [ self::class, 'meta_box' ] );
		add_action( 'save_post_product', [ self::class, 'save' ], 20, 2 );
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_post_playform_text_action', [ self::class, 'quick_action' ] );
		add_filter( 'manage_edit-product_columns', [ self::class, 'column' ] );
		add_action( 'manage_product_posts_custom_column', [ self::class, 'column_value' ], 10, 2 );
	}

	public static function meta_box(): void {
		add_meta_box( 'playform-ai-text', 'Produkttekst (AI)', [ self::class, 'render_box' ], 'product', 'normal', 'high' );
	}

	public static function render_box( \WP_Post $post ): void {
		$status = (string) get_post_meta( $post->ID, AiWriter::META_STATUS, true );
		$draft  = (array) get_post_meta( $post->ID, AiWriter::META_DRAFT, true );
		$labels = [ 'pending' => 'Afventer godkendelse', 'approved' => 'Godkendt og vist på siden', 'rejected' => 'Afvist' ];
		wp_nonce_field( 'playform_ai_text', 'playform_ai_nonce' );
		echo '<p><strong>Status:</strong> ' . esc_html( $labels[ $status ] ?? 'Ingen tekst endnu' ) . '</p>';
		if ( ! Ai::enabled() ) {
			echo '<p>Tilføj en API-nøgle under Værktøjer › Playform Feeds for at få skrevet tekster.</p>';
		}
		if ( $draft ) {
			echo '<p><label for="pf_ai_html"><strong>Tekst</strong> (du kan rette den, før du godkender)</label></p>';
			echo '<textarea id="pf_ai_html" name="pf_ai_html" rows="10" style="width:100%">' . esc_textarea( $draft['html'] ?? '' ) . '</textarea>';
			echo '<div style="display:flex;gap:16px"><p style="flex:1"><label for="pf_ai_pros"><strong>Fordele</strong> (én pr. linje)</label><br><textarea id="pf_ai_pros" name="pf_ai_pros" rows="3" style="width:100%">' . esc_textarea( implode( "\n", $draft['pros'] ?? [] ) ) . '</textarea></p>';
			echo '<p style="flex:1"><label for="pf_ai_cons"><strong>Ulemper</strong> (én pr. linje)</label><br><textarea id="pf_ai_cons" name="pf_ai_cons" rows="3" style="width:100%">' . esc_textarea( implode( "\n", $draft['cons'] ?? [] ) ) . '</textarea></p></div>';
			if ( ! empty( $draft['sources'] ) ) {
				echo '<details><summary>Fakta, teksten bygger på</summary><pre style="white-space:pre-wrap">' . esc_html( wp_json_encode( $draft['sources'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ) . '</pre></details>';
			}
		}
		echo '<p>';
		if ( $draft ) {
			echo '<button class="button button-primary" name="pf_ai_do" value="approve">Godkend og vis på siden</button> ';
			echo '<button class="button" name="pf_ai_do" value="reject">Afvis</button> ';
		}
		if ( Ai::enabled() ) {
			echo '<button class="button" name="pf_ai_do" value="generate">' . ( $draft ? 'Skriv en ny tekst' : 'Skriv tekst med AI' ) . '</button>';
		}
		echo '</p>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( empty( $_POST['playform_ai_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['playform_ai_nonce'] ), 'playform_ai_text' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$do = isset( $_POST['pf_ai_do'] ) ? sanitize_key( $_POST['pf_ai_do'] ) : '';
		remove_action( 'save_post_product', [ self::class, 'save' ], 20 );
		$lines = static fn( string $k ) => array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', (string) wp_unslash( $_POST[ $k ] ?? '' ) ) ) );
		try {
			if ( $do === 'approve' ) {
				AiWriter::approve( $post_id, wp_kses_post( wp_unslash( $_POST['pf_ai_html'] ?? '' ) ), $lines( 'pf_ai_pros' ), $lines( 'pf_ai_cons' ) );
			} elseif ( $do === 'reject' ) {
				AiWriter::reject( $post_id );
			} elseif ( $do === 'generate' ) {
				AiWriter::generate( $post_id );
			}
		} catch ( \Throwable $e ) {
			set_transient( 'playform_ai_error_' . get_current_user_id(), $e->getMessage(), 60 );
		}
	}

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=product', 'Tekster til godkendelse', 'Tekster til godkendelse', 'edit_products', 'playform-texts', [ self::class, 'page' ] );
	}

	public static function page(): void {
		$ids = get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 50, 'meta_key' => AiWriter::META_STATUS, 'meta_value' => 'pending' ] );
		echo '<div class="wrap"><h1>Tekster til godkendelse</h1>';
		if ( ! $ids ) {
			echo '<p>Der er ingen tekster, der venter.</p></div>';
			return;
		}
		echo '<p>' . count( $ids ) . ' tekster venter. Tekster, der består faktatjekket, udgives automatisk – disse faldt igennem. Godkend direkte her, ret dem, eller afvis.</p>';
		foreach ( $ids as $id ) {
			$draft = (array) get_post_meta( $id, AiWriter::META_DRAFT, true );
			$url   = wp_nonce_url( admin_url( 'admin-post.php?action=playform_text_action&id=' . $id ), 'playform_text_' . $id );
			echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin:12px 0;max-width:900px">';
			echo '<h2 style="margin:0 0 8px"><a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h2>';
			if ( ! empty( $draft['problems'] ) ) {
				echo '<p style="color:#b42318"><strong>Faktatjek:</strong> ' . esc_html( implode( ' ', $draft['problems'] ) ) . '</p>';
			}
			echo wp_kses_post( $draft['html'] ?? '' );
			if ( ! empty( $draft['pros'] ) || ! empty( $draft['cons'] ) ) {
				echo '<p><strong>Fordele:</strong> ' . esc_html( implode( ' · ', $draft['pros'] ?? [] ) ) . '<br><strong>Ulemper:</strong> ' . esc_html( implode( ' · ', $draft['cons'] ?? [] ) ) . '</p>';
			}
			echo '<p><a class="button button-primary" href="' . esc_url( $url . '&do=approve' ) . '">Godkend</a> <a class="button" href="' . esc_url( get_edit_post_link( $id ) ) . '">Ret</a> <a class="button" href="' . esc_url( $url . '&do=reject' ) . '">Afvis</a></p></div>';
		}
		echo '</div>';
	}

	public static function quick_action(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'playform_text_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Ingen adgang' );
		}
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		if ( $do === 'approve' ) {
			AiWriter::approve( $id );
		} elseif ( $do === 'reject' ) {
			AiWriter::reject( $id );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=playform-texts' ) );
		exit;
	}

	public static function column( array $cols ): array {
		$cols['pf_text'] = 'Tekst';
		return $cols;
	}

	public static function column_value( string $col, int $post_id ): void {
		if ( $col !== 'pf_text' ) {
			return;
		}
		$s = (string) get_post_meta( $post_id, AiWriter::META_STATUS, true );
		echo esc_html( [ 'pending' => 'Afventer', 'approved' => 'Godkendt', 'rejected' => 'Afvist' ][ $s ] ?? '–' );
	}
}
