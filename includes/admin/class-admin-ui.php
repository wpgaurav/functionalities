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
	 * Guidance collected while a module's settings render.
	 *
	 * @var array|null
	 */
	private static $guidance = null;

	/** Render settings and guidance separately, keeping every form intact. */
	public static function render_settings_layout( callable $render ): void {
		$previous       = self::$guidance;
		self::$guidance = array();
		$buffer_level   = ob_get_level();
		ob_start();
		try {
			$render();
			$content  = ob_get_clean();
			$guidance = implode( '', self::$guidance );
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			self::$guidance = $previous;
		}
		echo '<div class="functionalities-settings-layout' . ( '' !== $guidance ? ' functionalities-settings-layout--with-guide' : '' ) . '"><div class="functionalities-settings-content">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captured, pre-escaped admin render output.
		echo $content;
		echo '</div>';
		if ( '' !== $guidance ) {
			echo '<aside class="functionalities-settings-sidebar" aria-label="' . \esc_attr( \__( 'Module guide', 'functionalities' ) ) . '"><h2>' . \esc_html( \__( 'Module guide', 'functionalities' ) ) . '</h2>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped documentation from render_docs_section.
			echo $guidance;
			echo '</aside>';
		}
		echo '</div>';
	}

	/** Render consistent module navigation, including optional project ancestry. */
	public static function render_navigation( string $title, string $slug, string $child = '' ): void {
		echo '<nav class="functionalities-navigation" aria-label="' . \esc_attr__( 'Breadcrumb', 'functionalities' ) . '"><ol>';
		echo '<li><a class="button functionalities-back" href="' . \esc_url( \admin_url( 'admin.php?page=functionalities' ) ) . '"><span class="functionalities-icon functionalities-icon--arrow-left" aria-hidden="true"></span>' . \esc_html__( 'Back to modules', 'functionalities' ) . '</a></li>';
		echo '<li><span class="separator" aria-hidden="true">/</span>';
		if ( '' !== $child ) {
			echo '<a href="' . \esc_url( \admin_url( 'admin.php?page=functionalities&module=' . rawurlencode( $slug ) ) ) . '">' . \esc_html( $title ) . '</a></li><li><span class="separator" aria-hidden="true">/</span><span aria-current="page">' . \esc_html( $child ) . '</span>';
		} else {
			echo '<span aria-current="page">' . \esc_html( $title ) . '</span>';
		}
		echo '</li></ol></nav>';
	}

	/** Render the shared product header without changing a module's form controls. */
	public static function render_header( string $title, string $description = '', string $slug = '', string $child = '' ): void {
		if ( '' !== $slug ) {
			self::render_navigation( $title, $slug, $child );
		}
		echo '<div class="functionalities-header">';
		if ( '' === $slug ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted local asset markup from Admin_Icons.
			echo Admin_Icons::brand( 64 );
		}
		echo '<div class="functionalities-header__copy"><h1>';
		if ( '' !== $slug ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Allowlisted local icon markup.
			echo Admin_Icons::module( $slug ) . ' ';
		}
		echo \esc_html( $title ) . ' <span class="functionalities-version">v' . \esc_html( FUNCTIONALITIES_VERSION ) . '</span></h1>';
		if ( '' !== $description ) {
			echo '<p>' . \esc_html( $description ) . '</p>';
		}
		echo '</div></div>';
	}


	/**
	 * Render a documentation section with details/summary accordion.
	 *
	 * @param string $title   Section title.
	 * @param string $content HTML content for the section.
	 * @param string $type    Type: 'info', 'usage', 'developer'. Default 'info'.
	 * @param bool   $open    Whether to show open by default. Default false.
	 * @return void
	 */
	public static function render_docs_section( string $title, string $content, string $type = 'info', bool $open = false ): void {
		$open_attr = $open ? ' open' : '';
		$class     = 'functionalities-docs-accordion functionalities-docs-' . esc_attr( $type );

		$html = '<details class="' . esc_attr( $class ) . '"' . $open_attr . '><summary>' . esc_html( $title ) . '</summary><div class="functionalities-docs-content">' . $content . '</div></details>';
		if ( null !== self::$guidance ) {
			self::$guidance[] = $html;
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is pre-escaped HTML from render functions.
			echo $html;
		}
	}

	/**
	 * Render "What This Module Does" section.
	 *
	 * @param array $items List of feature descriptions.
	 * @return void
	 */
	public static function render_features_docs( array $items ): void {
		$content = '<ul>';
		foreach ( $items as $item ) {
			$content .= '<li>' . esc_html( $item ) . '</li>';
		}
		$content .= '</ul>';

		self::render_docs_section(
			\__( 'What This Module Does', 'functionalities' ),
			$content,
			'info',
			true
		);
	}

	/**
	 * Render "How to Use" section.
	 *
	 * @param string $description Usage description.
	 * @return void
	 */
	public static function render_usage_docs( string $description ): void {
		self::render_docs_section(
			\__( 'How to Use', 'functionalities' ),
			'<p>' . esc_html( $description ) . '</p>',
			'usage',
			true
		);
	}

	/**
	 * Render "For Developers" section with filters/actions.
	 *
	 * @param array $hooks Array of hooks with 'name' and 'description' keys.
	 * @return void
	 */
	public static function render_developer_docs( array $hooks ): void {
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
	public static function render_caution_docs( string $message ): void {
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
	public static function render_module_docs( array $config ): void {
		if ( null === self::$guidance ) {
			echo '<div class="functionalities-module-docs">';
		}

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

		if ( null === self::$guidance ) {
			echo '</div>';
		}
	}
}
