<?php
/**
 * Content filter tests for the HTML API ports.
 *
 * @package FunctionalitiesTests
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/features/class-link-management.php';
require_once dirname( __DIR__ ) . '/includes/features/class-block-cleanup.php';
require_once dirname( __DIR__ ) . '/includes/features/class-schema.php';

/**
 * Cover the three content filters that used to run DOMDocument.
 *
 * These need WordPress's HTML API. Point FUNCTIONALITIES_WP_DIR at a WordPress
 * root to run them; without it they skip.
 */
final class ContentFilterTest extends TestCase {

	/**
	 * Markup that the old DOMDocument passes corrupted.
	 *
	 * @var string
	 */
	private const FRAMEWORK_MARKUP = '<div v-cloak class="wp-block-group listing" :data-id="item.id" @click.prevent="open(item)"><p class="wp-block-paragraph">{{ item.title }}</p><a href="https://external.example/page">Read</a></div>';

	/**
	 * Skip unless the HTML API is loadable.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$this->markTestSkipped( 'Set FUNCTIONALITIES_WP_DIR to a WordPress root to run the HTML API tests.' );
		}

		$GLOBALS['functionalities_test_options'] = array();

		foreach ( array(
			\Functionalities\Features\Link_Management::class => array( 'options', 'cached_exceptions' ),
			\Functionalities\Features\Block_Cleanup::class   => array( 'options' ),
			\Functionalities\Features\Schema::class          => array( 'options' ),
		) as $class => $properties ) {
			foreach ( $properties as $name ) {
				$property = new ReflectionProperty( $class, $name );
				if ( PHP_VERSION_ID < 80100 ) {
					$property->setAccessible( true );
				}
				$property->setValue( null, null );
			}
		}
	}

	/**
	 * Call a protected static method.
	 *
	 * @param string $class  Class name.
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call( string $class, string $method, array $args ) {
		$reflection = new ReflectionMethod( $class, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		return $reflection->invokeArgs( null, $args );
	}

	/**
	 * Link Management adds rel attributes without touching framework markup.
	 *
	 * Releases 1.4.3 and 1.4.4 added a guard that skipped this content entirely
	 * because DOMDocument reserialized it. The HTML API edits in place, so the
	 * content is now processed and left intact.
	 *
	 * @return void
	 */
	public function test_link_management_keeps_framework_attributes(): void {
		$GLOBALS['functionalities_test_options']['functionalities_link_management'] = array(
			'enabled'               => true,
			'nofollow_external'     => true,
			'open_external_new_tab' => true,
		);

		$result = \Functionalities\Features\Link_Management::process_content( self::FRAMEWORK_MARKUP );

		$this->assertStringContainsString( 'v-cloak', $result );
		$this->assertStringContainsString( ':data-id="item.id"', $result );
		$this->assertStringContainsString( '@click.prevent="open(item)"', $result );
		$this->assertStringContainsString( '{{ item.title }}', $result );
		$this->assertStringContainsString( 'nofollow', $result );
		$this->assertStringContainsString( 'noopener', $result );
		$this->assertStringContainsString( 'target="_blank"', $result );
	}

	/**
	 * An internal link is left alone when only external rules are on.
	 *
	 * @return void
	 */
	public function test_link_management_leaves_internal_links_untouched(): void {
		$GLOBALS['functionalities_test_options']['functionalities_link_management'] = array(
			'enabled'           => true,
			'nofollow_external' => true,
		);

		$result = \Functionalities\Features\Link_Management::process_content( '<a href="/about/">About</a>' );

		$this->assertSame( '<a href="/about/">About</a>', $result );
	}

	/**
	 * An existing rel value is extended rather than replaced.
	 *
	 * @return void
	 */
	public function test_link_management_preserves_existing_rel_tokens(): void {
		$GLOBALS['functionalities_test_options']['functionalities_link_management'] = array(
			'enabled'           => true,
			'nofollow_external' => true,
		);

		$result = \Functionalities\Features\Link_Management::process_content(
			'<a href="https://external.example/x" rel="sponsored">x</a>'
		);

		$this->assertStringContainsString( 'sponsored', $result );
		$this->assertStringContainsString( 'nofollow', $result );
	}

	/**
	 * Block Cleanup removes only the configured class token.
	 *
	 * @return void
	 */
	public function test_block_cleanup_removes_only_the_target_class(): void {
		$GLOBALS['functionalities_test_options']['functionalities_block_cleanup'] = array(
			'enabled'                      => true,
			'remove_group_block_class'     => true,
			'remove_paragraph_block_class' => true,
			'custom_classes_to_remove'     => '',
		);

		$result = $this->call(
			\Functionalities\Features\Block_Cleanup::class,
			'filter_content_cleanup',
			array( self::FRAMEWORK_MARKUP )
		);

		$this->assertStringNotContainsString( 'wp-block-group', $result );
		$this->assertStringNotContainsString( 'wp-block-paragraph', $result );
		$this->assertStringContainsString( 'listing', $result );
		$this->assertStringContainsString( 'v-cloak', $result );
		$this->assertStringContainsString( '{{ item.title }}', $result );
	}

	/**
	 * Schema marks up an article, its headline, dates, and author.
	 *
	 * @return void
	 */
	public function test_schema_adds_microdata_in_place(): void {
		$GLOBALS['functionalities_test_options']['functionalities_schema'] = array(
			'enabled'          => true,
			'enable_article'   => true,
			'article_itemtype' => 'BlogPosting',
			'add_headline'     => true,
			'add_dates'        => true,
			'add_author'       => true,
		);

		$html = '<article v-scope><h2>Title</h2><time class="entry-date published">x</time>'
			. '<time class="updated">y</time><span class="author vcard">Gaurav</span></article>';

		$result = $this->call( \Functionalities\Features\Schema::class, 'filter_article', array( $html ) );

		$this->assertStringContainsString( 'itemtype="https://schema.org/BlogPosting"', $result );
		$this->assertStringContainsString( 'itemprop="headline"', $result );
		$this->assertStringContainsString( 'itemprop="datePublished"', $result );
		$this->assertStringContainsString( 'itemprop="dateModified"', $result );
		$this->assertStringContainsString( 'itemprop="author"', $result );
		$this->assertStringContainsString( 'v-scope', $result );
	}
}
