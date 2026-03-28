<?php
/**
 * CSS Sanitizer trait.
 *
 * Shared CSS sanitization logic used by Components and Fonts modules.
 *
 * @package Functionalities\Traits
 * @since 1.5.0
 */

namespace Functionalities\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait CSS_Sanitizer.
 */
trait CSS_Sanitizer {

	/**
	 * Sanitize CSS string to prevent injection attacks.
	 *
	 * Removes HTML tags, script vectors, and legacy IE expression/behavior patterns.
	 *
	 * @param string $css Raw CSS string.
	 * @return string Sanitized CSS.
	 */
	protected static function sanitize_css( string $css ) : string {
		// Remove any HTML tags.
		$css = wp_strip_all_tags( $css );

		// Remove style closing tags that could break out of style context.
		$css = preg_replace( '/<\/style\s*>/i', '', $css );

		// Remove JavaScript expressions (legacy IE).
		$css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css );

		// Remove JavaScript URLs.
		$css = preg_replace( '/javascript\s*:/i', '', $css );

		// Remove behavior property (legacy IE).
		$css = preg_replace( '/behavior\s*:\s*url\s*\([^)]*\)/i', '', $css );

		return $css;
	}
}
