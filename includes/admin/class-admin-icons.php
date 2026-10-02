<?php
/**
 * Local product mark and backend icon helpers.
 *
 * @package Functionalities\Admin
 */
namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Render trusted, bundled assets rather than loading a remote icon service. */
class Admin_Icons {
	const MODULES = array(
		'content-tools'        => 'copy',
		'link-health'          => 'link',
		'site-activity'        => 'activity',
		'task-manager'         => 'list-check',
		'misc'                 => 'gauge',
		'snippets'             => 'code',
		'link-management'      => 'link',
		'redirect-manager'     => 'arrows-shuffle',
		'block-cleanup'        => 'braces',
		'schema'               => 'hierarchy',
		'content-regression'   => 'shield-check',
		'assumption-detection' => 'eye',
		'login-security'       => 'lock',
		'meta'                 => 'copyright',
		'components'           => 'components',
		'fonts'                => 'typography',
		'editor-links'         => 'search',
		'svg-icons'            => 'icons',
		'pwa'                  => 'device-mobile',
	);
	/** Return a decorative module glyph with an allowlisted CSS class. */
	public static function module( string $slug ): string {
		$name = self::MODULES[ $slug ] ?? 'settings';
		return '<span class="functionalities-icon functionalities-icon--' . \esc_attr( $name ) . '" aria-hidden="true"></span>';
	}
	/** Return the canonical product mark; nearby text supplies its accessible name. */
	public static function brand( int $size = 48 ): string {
		$size = max( 20, min( 96, $size ) );
		return '<img class="functionalities-brand-mark" src="' . \esc_url( FUNCTIONALITIES_URL . 'assets/brand/functionalities.svg' ) . '" alt="" width="' . \esc_attr( $size ) . '" height="' . \esc_attr( $size ) . '" decoding="async">';
	}
}
