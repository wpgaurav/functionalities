<?php
/**
 * Admin PWA UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_PWA_UI {

	/**
	 * Render PWA shortcuts repeater field.
	 *
	 * @return void
	 */
	public static function field_pwa_shortcuts(): void {
		$o         = self::get_pwa_options();
		$shortcuts = ! empty( $o['shortcuts'] ) ? $o['shortcuts'] : array();
		echo '<div id="func-pwa-shortcuts">';
		$i = 0;
		foreach ( $shortcuts as $sc ) {
			self::render_shortcut_row( $i, $sc );
			++$i;
		}
		echo '</div>';
		echo '<button type="button" class="button" id="func-pwa-add-shortcut">' . \esc_html__( '+ Add Shortcut', 'functionalities' ) . '</button>';
		echo '<p class="description">' . \esc_html__( 'Maximum 4 shortcuts. Each needs a name and URL.', 'functionalities' ) . '</p>';
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			$("#func-pwa-add-shortcut").on("click",function(){
				if(idx>=4) return;
				var html = ' . \wp_json_encode( self::get_shortcut_row_html( '__INDEX__' ) ) . ';
				html = html.replace(/__INDEX__/g, idx);
				$("#func-pwa-shortcuts").append(html);
				idx++;
			});
			$(document).on("click",".func-pwa-remove-shortcut",function(){
				$(this).closest(".func-pwa-shortcut-row").remove();
			});
		});
		</script>';
	}

	/**
	 * Render a single shortcut row.
	 *
	 * @param int   $index Row index.
	 * @param array $data  Shortcut data.
	 * @return void
	 */
	private static function render_shortcut_row( int $index, array $data ): void {
		$name   = \esc_attr( $data['name'] ?? '' );
		$url    = \esc_attr( $data['url'] ?? '' );
		$desc   = \esc_attr( $data['description'] ?? '' );
		$icon   = \esc_attr( $data['icon'] ?? '' );
		$prefix = 'functionalities_pwa[shortcuts][' . $index . ']';
		echo '<div class="func-pwa-shortcut-row" style="padding:10px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;background:#fafafa">';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[name]" value="' . \esc_attr( $name ) . '" placeholder="' . \esc_attr__( 'Name', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[url]" value="' . \esc_attr( $url ) . '" placeholder="' . \esc_attr__( 'URL (e.g. /blog/)', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[description]" value="' . \esc_attr( $desc ) . '" placeholder="' . \esc_attr__( 'Description', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[icon]" value="' . \esc_attr( $icon ) . '" placeholder="' . \esc_attr__( 'Icon URL (96x96 PNG)', 'functionalities' ) . '" class="regular-text">';
		echo '</div>';
		echo '<button type="button" class="button button-link-delete func-pwa-remove-shortcut" style="margin-top:6px">' . \esc_html__( 'Remove', 'functionalities' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Get shortcut row HTML template for JS.
	 *
	 * @param string $index Placeholder index.
	 * @return string HTML template.
	 */
	private static function get_shortcut_row_html( string $index ): string {
		ob_start();
		self::render_shortcut_row( (int) $index, array() );
		$html = ob_get_clean();
		return str_replace( (string) (int) $index, $index, $html );
	}

	/**
	 * Render PWA screenshots repeater field.
	 *
	 * @return void
	 */
	public static function field_pwa_screenshots(): void {
		$o           = self::get_pwa_options();
		$screenshots = ! empty( $o['screenshots'] ) ? $o['screenshots'] : array();
		echo '<div id="func-pwa-screenshots">';
		$i = 0;
		foreach ( $screenshots as $ss ) {
			self::render_screenshot_row( $i, $ss );
			++$i;
		}
		echo '</div>';
		echo '<button type="button" class="button" id="func-pwa-add-screenshot">' . \esc_html__( '+ Add Screenshot', 'functionalities' ) . '</button>';
		echo '<script>
		jQuery(function($){
			var idx = ' . \absint( $i ) . ';
			$("#func-pwa-add-screenshot").on("click",function(){
				var html = ' . \wp_json_encode( self::get_screenshot_row_html( '__INDEX__' ) ) . ';
				html = html.replace(/__INDEX__/g, idx);
				$("#func-pwa-screenshots").append(html);
				idx++;
			});
			$(document).on("click",".func-pwa-remove-screenshot",function(){
				$(this).closest(".func-pwa-screenshot-row").remove();
			});
		});
		</script>';
	}

	/**
	 * Render a single screenshot row.
	 *
	 * @param int   $index Row index.
	 * @param array $data  Screenshot data.
	 * @return void
	 */
	private static function render_screenshot_row( int $index, array $data ): void {
		$src    = \esc_attr( $data['src'] ?? '' );
		$sizes  = \esc_attr( $data['sizes'] ?? '' );
		$label  = \esc_attr( $data['label'] ?? '' );
		$form   = $data['form_factor'] ?? 'wide';
		$prefix = 'functionalities_pwa[screenshots][' . $index . ']';
		echo '<div class="func-pwa-screenshot-row" style="padding:10px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px;background:#fafafa">';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
		echo '<input type="url" name="' . \esc_attr( $prefix ) . '[src]" value="' . \esc_attr( $src ) . '" placeholder="' . \esc_attr__( 'Image URL', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[sizes]" value="' . \esc_attr( $sizes ) . '" placeholder="' . \esc_attr__( '1280x720', 'functionalities' ) . '" class="regular-text">';
		echo '<input type="text" name="' . \esc_attr( $prefix ) . '[label]" value="' . \esc_attr( $label ) . '" placeholder="' . \esc_attr__( 'Label', 'functionalities' ) . '" class="regular-text">';
		echo '<select name="' . \esc_attr( $prefix ) . '[form_factor]">';
		echo '<option value="wide"' . \selected( $form, 'wide', false ) . '>' . \esc_html__( 'Wide (desktop)', 'functionalities' ) . '</option>';
		echo '<option value="narrow"' . \selected( $form, 'narrow', false ) . '>' . \esc_html__( 'Narrow (mobile)', 'functionalities' ) . '</option>';
		echo '</select>';
		echo '</div>';
		echo '<button type="button" class="button button-link-delete func-pwa-remove-screenshot" style="margin-top:6px">' . \esc_html__( 'Remove', 'functionalities' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Get screenshot row HTML template for JS.
	 *
	 * @param string $index Placeholder index.
	 * @return string HTML template.
	 */
	private static function get_screenshot_row_html( string $index ): string {
		ob_start();
		self::render_screenshot_row( (int) $index, array() );
		$html = ob_get_clean();
		return str_replace( (string) (int) $index, $index, $html );
	}
}
