<?php
/**
 * Admin UI helper functions.
 *
 * Provides reusable UI components for the admin interface.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI helper class.
 */
class Admin_UI {

	/**
	 * Render a documentation section with details/summary accordion.
	 *
	 * @param string $title   Section title.
	 * @param string $content HTML content for the section.
	 * @param string $type    Type: 'info', 'usage', 'developer'. Default 'info'.
	 * @param bool   $open    Whether to show open by default. Default false.
	 * @return void
	 */
	public static function render_docs_section( string $title, string $content, string $type = 'info', bool $open = false ) : void {
		$open_attr = $open ? ' open' : '';
		$class = 'functionalities-docs-accordion functionalities-docs-' . esc_attr( $type );

		echo '<details class="' . $class . '"' . $open_attr . '>';
		echo '<summary>' . esc_html( $title ) . '</summary>';
		echo '<div class="functionalities-docs-content">' . $content . '</div>';
		echo '</details>';
	}

	/**
	 * Render "What This Module Does" section.
	 *
	 * @param array $items List of feature descriptions.
	 * @return void
	 */
	public static function render_features_docs( array $items ) : void {
		$content = '<ul>';
		foreach ( $items as $item ) {
			$content .= '<li>' . esc_html( $item ) . '</li>';
		}
		$content .= '</ul>';

		self::render_docs_section(
			\__( 'What This Module Does', 'functionalities' ),
			$content,
			'info'
		);
	}

	/**
	 * Render "How to Use" section.
	 *
	 * @param string $description Usage description.
	 * @return void
	 */
	public static function render_usage_docs( string $description ) : void {
		self::render_docs_section(
			\__( 'How to Use', 'functionalities' ),
			'<p>' . esc_html( $description ) . '</p>',
			'usage'
		);
	}

	/**
	 * Render "For Developers" section with filters/actions.
	 *
	 * @param array $hooks Array of hooks with 'name' and 'description' keys.
	 * @return void
	 */
	public static function render_developer_docs( array $hooks ) : void {
		$content = '<dl class="functionalities-hooks-list">';
		foreach ( $hooks as $hook ) {
			$content .= '<dt><code>' . esc_html( $hook['name'] ) . '</code></dt>';
			$content .= '<dd>' . esc_html( $hook['description'] ) . '</dd>';
		}
		$content .= '</dl>';

		self::render_docs_section(
			\__( 'For Developers', 'functionalities' ),
			$content,
			'developer'
		);
	}

	/**
	 * Render a caution/warning section.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	public static function render_caution_docs( string $message ) : void {
		self::render_docs_section(
			\__( 'Caution', 'functionalities' ),
			'<p>' . esc_html( $message ) . '</p>',
			'caution'
		);
	}

	/**
	 * Render all documentation sections for a module.
	 *
	 * @param array $config Configuration array with 'features', 'usage', 'caution', 'hooks' keys.
	 * @return void
	 */
	public static function render_module_docs( array $config ) : void {
		echo '<div class="functionalities-module-docs">';

		if ( ! empty( $config['features'] ) ) {
			self::render_features_docs( $config['features'] );
		}

		if ( ! empty( $config['usage'] ) ) {
			self::render_usage_docs( $config['usage'] );
		}

		if ( ! empty( $config['caution'] ) ) {
			self::render_caution_docs( $config['caution'] );
		}

		if ( ! empty( $config['hooks'] ) ) {
			self::render_developer_docs( $config['hooks'] );
		}

		echo '</div>';
	}
}
