<?php
/**
 * DOM Parser safety trait.
 *
 * Shared guard for classes that run DOMDocument::loadHTML() on the_content.
 * DOMDocument re-parses HTML and corrupts non-standard attributes used by
 * Vue.js, Alpine.js, and similar JS frameworks.
 *
 * @package Functionalities\Traits
 * @since   1.4.3
 */

namespace Functionalities\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Has_Dom_Parser.
 */
trait Has_Dom_Parser {

	/**
	 * Check whether content contains JS-framework directives that
	 * DOMDocument::loadHTML() would corrupt or strip.
	 *
	 * Covers Vue 2/3 directives (v-if, v-show, v-cloak, v-for, v-bind, v-on,
	 * v-model, v-html, v-text), shorthand bindings (:class, :style),
	 * shorthand events (@click, @submit.prevent), and mustache interpolation
	 * ({{ expr }}).
	 *
	 * @since 1.4.3
	 *
	 * @param string $content HTML content to inspect.
	 * @return bool True if JS-framework directives are detected.
	 */
	protected static function content_has_js_framework_directives( string $content ) : bool {
		return (bool) preg_match(
			'/\bv-(?:cloak|if|show|for|bind|on|model|html|text)\b|:[a-z]+="|@[a-z.]+="|{{.+?}}/s',
			$content
		);
	}
}
