<?php
/**
 * Admin Snippets UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Snippets_UI {

	/**
	 * Render snippets repeater field for a given location.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location  Location key: header, body_open, or footer.
	 * @param string $hook_name WordPress hook name for description.
	 * @return void
	 */
	public static function field_snippets_repeater( string $location, string $hook_name ): void {
		$o     = self::get_snippets_options();
		$items = ! empty( $o[ $location ] ) && \is_array( $o[ $location ] ) ? $o[ $location ] : array();
		$cid   = 'func-snippets-' . $location;

		echo '<div id="' . \esc_attr( $cid ) . '">';
		$i = 0;
		foreach ( $items as $item ) {
			self::render_snippet_row( $location, $i, $item );
			++$i;
		}
		echo '</div>';

		echo '<button type="button" class="button func-snippets-add" data-location="' . \esc_attr( $location ) . '" data-container="' . \esc_attr( $cid ) . '">';
		echo \esc_html__( '+ Add Snippet', 'functionalities' ) . '</button>';
		echo '<p class="description">' . \sprintf(
			/* translators: %s: WordPress hook name */
			\esc_html__( 'Output in %s. Each snippet can be individually toggled.', 'functionalities' ),
			'<code>' . \esc_html( $hook_name ) . '</code>'
		) . '</p>';
		if ( ! \current_user_can( 'unfiltered_html' ) ) {
			echo '<p class="description">' . \esc_html__( 'Your account can save ordinary HTML. Executable scripts, styles, and embedded frames require the unfiltered_html capability.', 'functionalities' ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline JS for repeater template, encoded via wp_json_encode.
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			var loc = ' . \wp_json_encode( $location ) . ';
			var cid = ' . \wp_json_encode( $cid ) . ';
			var tpl = ' . \wp_json_encode( self::get_snippet_row_html( $location, '__INDEX__' ) ) . ';
			var $c = $("#"+cid);

			$(".func-snippets-add[data-location=\""+loc+"\"]").on("click",function(){
				var html = tpl.replace(/__INDEX__/g, idx);
				$c.append(html);
				var $row = $c.children().last();
				$row.addClass("is-open is-enabled");
				var ta = $row.find("textarea")[0];
				if(ta && typeof window.funcFsWrapTextarea==="function"){
					window.funcFsWrapTextarea(ta);
				}
				if(ta) ta.focus();
				idx++;
			});

			$c.on("click",".func-snippet-remove",function(){
				var $row = $(this).closest(".func-snippet-row");
				$row.slideUp(150, function(){ $row.remove(); });
			});

			$c.on("click",".func-snippet-expand",function(){
				$(this).closest(".func-snippet-row").toggleClass("is-open");
			});

			$c.on("change",".func-snippet-toggle input",function(){
				$(this).closest(".func-snippet-row").toggleClass("is-enabled", this.checked);
			});
		});
		</script>';
	}

	/**
	 * Render a single snippet repeater row.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location Location key.
	 * @param int    $index    Row index.
	 * @param array  $data     Snippet data (label, code, enabled).
	 * @return void
	 */
	private static function render_snippet_row( string $location, int $index, array $data ): void {
		$label    = $data['label'] ?? '';
		$code     = $data['code'] ?? '';
		$enabled  = ! empty( $data['enabled'] );
		$prefix   = 'functionalities_snippets[' . $location . '][' . $index . ']';
		$has_code = '' !== $code;

		echo '<div class="func-snippet-row' . ( $enabled ? ' is-enabled' : '' ) . '">';

		// Header bar — always visible.
		echo '<div class="func-snippet-row-header">';
		echo '<label class="func-snippet-toggle"><input type="checkbox" name="' . \esc_attr( $prefix ) . '[enabled]" value="1" ' . \checked( $enabled, true, false ) . '></label>';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[label]" value="' . \esc_attr( $label ) . '" placeholder="' . \esc_attr__( 'Label this snippet…', 'functionalities' ) . '" class="func-snippet-label">';
		if ( $has_code ) {
			echo '<span class="func-snippet-badge">' . \esc_html( self::snippet_type_badge( $code ) ) . '</span>';
		}
		echo '<button type="button" class="func-snippet-expand" title="' . \esc_attr__( 'Expand / Collapse', 'functionalities' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<button type="button" class="func-snippet-remove" title="' . \esc_attr__( 'Remove snippet', 'functionalities' ) . '"><span class="dashicons dashicons-trash"></span></button>';
		echo '</div>';

		// Body — collapsible.
		echo '<div class="func-snippet-body">';
		echo '<textarea name="' . \esc_attr( $prefix ) . '[code]" rows="6" cols="60" class="large-text code" placeholder="' . \esc_attr__( 'Paste your <script>, <style>, <meta>, or <link> tag here…', 'functionalities' ) . '">' . \esc_textarea( $code ) . '</textarea>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Detect snippet type from code for badge display.
	 *
	 * @since 1.4.0
	 *
	 * @param string $code Snippet code.
	 * @return string Badge label.
	 */
	private static function snippet_type_badge( string $code ): string {
		$code_lower = strtolower( ltrim( $code ) );
		if ( 0 === strpos( $code_lower, '<style' ) ) {
			return 'CSS';
		}
		if ( 0 === strpos( $code_lower, '<script' ) ) {
			return 'JS';
		}
		if ( 0 === strpos( $code_lower, '<link' ) ) {
			return 'Link';
		}
		if ( 0 === strpos( $code_lower, '<meta' ) ) {
			return 'Meta';
		}
		if ( 0 === strpos( $code_lower, '<noscript' ) ) {
			return 'NoScript';
		}
		return 'HTML';
	}

	/**
	 * Get snippet row HTML template for JavaScript.
	 *
	 * @since 1.4.0
	 *
	 * @param string $location Location key.
	 * @param string $index    Placeholder index string.
	 * @return string HTML template with placeholder index.
	 */
	private static function get_snippet_row_html( string $location, string $index ): string {
		$sentinel = 89714;
		ob_start();
		self::render_snippet_row( $location, $sentinel, array( 'enabled' => true ) );
		$html = ob_get_clean();
		return str_replace( (string) $sentinel, $index, $html );
	}
}
